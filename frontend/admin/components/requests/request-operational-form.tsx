"use client";

import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import MenuItem from "@mui/material/MenuItem";
import Paper from "@mui/material/Paper";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import { useRef, useState } from "react";
import { SectionHeader } from "@/components/ui/section-header";
import {
  MAX_STAFF_NOTES,
  REQUEST_STATUS_LABELS,
  REQUEST_STATUS_NO_CHANGE,
  REQUEST_UPDATE_UNCERTAIN_MESSAGE,
  requestNotesError,
  requestStatusOptionError,
  requestStatusTransitions,
  requestUpdateFields,
  requestUpdateSeverity,
  type RequestUpdateActions,
  type RequestUpdateOutcome,
  type RequestUpdateTarget,
} from "@/lib/requests/request-mutations";
import { requestNotesDisplay, requestStatusDisplay } from "@/lib/requests/request-presentation";

const TITLE_ID = "request-operations-heading";
const STATUS_FIELD_ID = "request-status";
const NOTES_FIELD_ID = "request-internal-notes";
const NOTES_ERROR_ID = `${NOTES_FIELD_ID}-error`;
const NOTES_HINT = `Internal notes are not visible to the customer. Leave blank to clear the note. Maximum ${MAX_STAFF_NOTES} characters.`;

interface RequestOperationalFormProps {
  readonly record: RequestUpdateTarget;
  readonly canManage: boolean;
  readonly actions: RequestUpdateActions;
}

/**
 * The REQ-006 lifecycle controls for one request.
 *
 * The status select offers only the transitions Laravel's state machine allows
 * from the current status, plus an explicit "keep current status" option so the
 * internal note can be edited without forcing a transition. Only genuinely
 * changed fields are ever submitted, so a no-op update cannot be sent and a note
 * is replaced or cleared only when the operator changes it.
 *
 * A confirmed success resets the form to the values that were accepted; the
 * Server Action revalidates the list and detail from authoritative state, so no
 * mutation response body is treated as the source of truth.
 */
export function RequestOperationalForm({ record, canManage, actions }: Readonly<RequestOperationalFormProps>) {
  const [currentStatus, setCurrentStatus] = useState(record.requestStatus);
  const [currentNotes, setCurrentNotes] = useState(record.staffInternalNotes);
  const [statusDraft, setStatusDraft] = useState(REQUEST_STATUS_NO_CHANGE);
  const [notesDraft, setNotesDraft] = useState(record.staffInternalNotes ?? "");
  const [outcome, setOutcome] = useState<RequestUpdateOutcome | null>(null);
  const [pending, setPending] = useState(false);
  /** Claimed before any await, so a second submission cannot slip in before React re-renders. */
  const inFlight = useRef(false);

  const transitions = requestStatusTransitions(currentStatus);
  const fields = canManage
    ? requestUpdateFields({ requestStatus: currentStatus, staffInternalNotes: currentNotes }, statusDraft, notesDraft)
    : null;
  const notesError = requestNotesError(notesDraft);
  const statusError = requestStatusOptionError(currentStatus, statusDraft);
  const error = statusError ?? notesError;
  const blocked = pending || fields === null || error !== null;

  function submit(): void {
    if (inFlight.current || fields === null || !canManage) return;

    setOutcome(null);
    setPending(true);
    inFlight.current = true;

    void (async () => {
      const result = await settleUpdate(() => actions.update({ requestId: record.id, fields }));
      inFlight.current = false;
      setPending(false);
      setOutcome(result);

      if (result.status === "success") {
        const nextStatus = fields.requestStatus ?? currentStatus;
        const nextNotes = fields.staffInternalNotes === undefined ? currentNotes : fields.staffInternalNotes;
        setCurrentStatus(nextStatus);
        setCurrentNotes(nextNotes);
        setStatusDraft(REQUEST_STATUS_NO_CHANGE);
        setNotesDraft(nextNotes ?? "");
      }
    })();
  }

  return (
    <Paper component="section" variant="outlined" aria-labelledby={TITLE_ID} sx={{ p: 3, display: "flex", flexDirection: "column", gap: 3 }}>
      <SectionHeader title="Operations and internal notes" titleId={TITLE_ID} />
      <Typography color="text.secondary" variant="body2">
        Current status: {requestStatusDisplay(currentStatus).label}
      </Typography>
      <Box sx={{ display: "flex", flexDirection: "column", gap: 1 }}>
        <Typography component="h3" variant="h4">Internal notes</Typography>
        <Typography sx={{ whiteSpace: "pre-wrap", overflowWrap: "anywhere" }}>
          {requestNotesDisplay({ staffInternalNotes: currentNotes }, canManage)}
        </Typography>
      </Box>

      {canManage ? (
        <Box component="form" aria-label="Update request" onSubmit={(event) => event.preventDefault()} sx={{ display: "flex", flexDirection: "column", gap: 2 }}>
          {transitions.length === 0 ? (
            <Typography variant="body2" color="text.secondary">
              This request is closed. No further status transition is available, so only the internal notes can change.
            </Typography>
          ) : (
            <TextField
              select
              id={STATUS_FIELD_ID}
              name="request_status"
              label="New status"
              value={statusDraft}
              onChange={(event) => setStatusDraft(event.target.value)}
              disabled={pending}
              size="small"
              error={statusError !== null}
              slotProps={{ select: { displayEmpty: true } }}
              helperText="Only transitions the request state machine allows are offered. Laravel validates the transition authoritatively."
            >
              <MenuItem value={REQUEST_STATUS_NO_CHANGE}>Keep current status</MenuItem>
              {transitions.map((value) => (
                <MenuItem key={value} value={value}>{REQUEST_STATUS_LABELS[value]}</MenuItem>
              ))}
            </TextField>
          )}
          <TextField
            id={NOTES_FIELD_ID}
            name="staff_internal_notes"
            label="Internal notes"
            value={notesDraft}
            onChange={(event) => setNotesDraft(event.target.value)}
            disabled={pending}
            multiline
            minRows={4}
            error={notesError !== null}
            helperText={NOTES_HINT}
            slotProps={{ htmlInput: { maxLength: MAX_STAFF_NOTES, "aria-describedby": notesError === null ? undefined : NOTES_ERROR_ID } }}
          />
          {notesError === null ? null : (
            <Typography id={NOTES_ERROR_ID} role="alert" color="error" variant="body2">
              {notesError}
            </Typography>
          )}
          <Box>
            <Button type="submit" variant="contained" disabled={blocked} aria-busy={pending} onClick={submit}>
              {pending ? "Saving changes…" : "Save changes"}
            </Button>
          </Box>
        </Box>
      ) : (
        <Alert severity="info">
          This account has view-only request access. Changing the status or internal notes requires the requests.manage permission.
        </Alert>
      )}

      {outcome === null ? null : (
        <Alert severity={requestUpdateSeverity(outcome.status)} role={outcome.status === "success" ? "status" : "alert"}>
          {outcome.message}
        </Alert>
      )}
    </Paper>
  );
}

/**
 * A Server Action returns an outcome for every API error, so it only rejects on
 * an unexpected transport or serialization failure. That leaves the result
 * genuinely unknown, so it is reported as uncertain rather than as a failure.
 */
async function settleUpdate(operation: () => Promise<RequestUpdateOutcome>): Promise<RequestUpdateOutcome> {
  try {
    return await operation();
  } catch {
    return { status: "uncertain", message: REQUEST_UPDATE_UNCERTAIN_MESSAGE, retryAfterSeconds: null, fieldErrors: [] };
  }
}
