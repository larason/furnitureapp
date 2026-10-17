"use client";

import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Dialog from "@mui/material/Dialog";
import DialogActions from "@mui/material/DialogActions";
import DialogContent from "@mui/material/DialogContent";
import DialogTitle from "@mui/material/DialogTitle";
import MenuItem from "@mui/material/MenuItem";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import { useEffect, useRef, useState } from "react";
import { newIdempotencyKey } from "@/lib/api/idempotency";
import {
  INVENTORY_ADJUSTMENT_UNCERTAIN_MESSAGE,
  INVENTORY_REASON_LABELS,
  INVENTORY_REASON_VALUES,
  INVENTORY_SNAPSHOT_UNAVAILABLE_MESSAGE,
  inventoryAdjustmentError,
  inventoryAdjustmentSeverity,
  inventoryAdjustmentSignature,
  parseQuantityDelta,
  previewInventoryQuantity,
  type InventoryAdjustmentActions,
  type InventoryAdjustmentIntent,
  type InventoryAdjustmentOutcome,
} from "@/lib/catalog/inventory-mutations";
import {
  formatInventoryQuantity,
  formatInventoryTimestamp,
  inventoryReasonHint,
  inventorySubjectLabel,
  inventoryVariantLabel,
  INVENTORY_QUANTITY_UNAVAILABLE,
  INVENTORY_SKU_UNAVAILABLE,
  INVENTORY_TIMESTAMP_UNAVAILABLE,
  type InventoryRow,
} from "@/lib/catalog/inventory-presentation";
import type { InventoryRecord } from "@/lib/catalog/inventory-resource";

const DELTA_DEFAULT_HINT = "Enter a whole number; a leading minus sign reduces stock.";
const REASON_FIELD_ID = "inventory-adjustment-reason";
const DELTA_FIELD_ID = "inventory-adjustment-quantity-delta";
const DELTA_ERROR_ID = `${DELTA_FIELD_ID}-error`;
const DESCRIPTION_ID = "inventory-adjustment-description";
const TITLE_ID = "inventory-adjustment-title";

interface InventoryAdjustmentDialogProps {
  readonly subject: InventoryRow;
  readonly canManage: boolean;
  readonly actions: InventoryAdjustmentActions;
  /** The row's in-progress intent, owned by the table so close and reopen keep it. */
  readonly intent: InventoryAdjustmentIntent | null;
  readonly onIntentChange: (intent: InventoryAdjustmentIntent | null) => void;
  readonly onClose: () => void;
}

/** The draft a rejected submit referred to, so its message never outlives that draft. */
interface RejectedDraft {
  readonly message: string;
  readonly deltaDraft: string;
  readonly reason: string;
}

/**
 * Inventory detail and the `INV-003` adjustment.
 *
 * The dialog always re-reads the record through `INV-002` before showing a value,
 * so the quantities on screen are the ones Laravel currently holds rather than
 * the row the list happened to render. The form pre-flights the backend's
 * business rules so an operator understands the change before submitting, and
 * Laravel re-validates every one of them inside the transaction.
 *
 * One idempotency key belongs to one logical intent: it is reused only while the
 * payload is identical, and cleared after a confirmed success so a genuinely new
 * adjustment receives a fresh key. The table owns that intent, so closing and
 * reopening the same record during an uncertain retry keeps the original key.
 * Nothing here retries automatically, and the dialog cannot be dismissed while a
 * submission is in flight, so an in-flight outcome is never silently discarded.
 */
export function InventoryAdjustmentDialog({ subject, canManage, actions, intent, onIntentChange, onClose }: Readonly<InventoryAdjustmentDialogProps>) {
  const recordId = subject.record.id;
  const [snapshot, setSnapshot] = useState<InventoryRecord | null>(null);
  const [snapshotMessage, setSnapshotMessage] = useState<string | null>(null);
  /** The `reloadToken` whose read has settled, so a pending read is never reported as a failure. */
  const [settledReloadToken, setSettledReloadToken] = useState<number | null>(null);
  const [reason, setReason] = useState<string>(INVENTORY_REASON_VALUES[0]);
  const [deltaDraft, setDeltaDraft] = useState("");
  const [rejectedDraft, setRejectedDraft] = useState<RejectedDraft | null>(null);
  const [outcome, setOutcome] = useState<InventoryAdjustmentOutcome | null>(null);
  const [pending, setPending] = useState(false);
  const [reloadToken, setReloadToken] = useState(0);
  /** Claimed before any await, so a second submission cannot slip in before React re-renders. */
  const inFlight = useRef(false);
  /** Server Actions are re-serialized per render, so the effect reads them through a ref rather than depending on identity. */
  const actionsRef = useRef(actions);

  useEffect(() => {
    actionsRef.current = actions;
  }, [actions]);

  useEffect(() => {
    let active = true;
    void actionsRef.current.loadSnapshot(recordId).then(
      (result) => {
        if (!active) return;
        setSnapshot(result.ok ? result.record : null);
        setSnapshotMessage(result.ok ? null : result.message);
        setSettledReloadToken(reloadToken);
      },
      () => {
        if (!active) return;
        setSnapshot(null);
        setSnapshotMessage(INVENTORY_SNAPSHOT_UNAVAILABLE_MESSAGE);
        setSettledReloadToken(reloadToken);
      },
    );
    return () => {
      active = false;
    };
  }, [recordId, reloadToken]);

  /** A read is pending until the token it was started for settles; the dialog remounts per record. */
  const snapshotLoading = settledReloadToken !== reloadToken;

  const quantityDelta = parseQuantityDelta(deltaDraft);
  const advisoryError =
    snapshot === null || deltaDraft.trim().length === 0
      ? null
      : inventoryAdjustmentError({
          quantity: snapshot.quantity,
          reservedQuantity: snapshot.reservedQuantity,
          quantityDelta: quantityDelta ?? Number.NaN,
          reason,
        });
  // A rejected submit only describes the exact draft it rejected, so its message is
  // dropped as soon as the draft or reason changes and live guidance takes over.
  const rejectedStillApplies = rejectedDraft !== null && rejectedDraft.deltaDraft === deltaDraft && rejectedDraft.reason === reason;
  const visibleError = rejectedStillApplies ? rejectedDraft.message : advisoryError;
  const preview = snapshot === null || quantityDelta === null ? null : previewInventoryQuantity(snapshot.quantity, quantityDelta);
  const blocked = pending || snapshotLoading || snapshot === null || advisoryError !== null;

  function requestClose(): void {
    if (pending) return;
    onClose();
  }

  function submit(): void {
    if (inFlight.current || snapshot === null || !canManage) return;
    const delta = parseQuantityDelta(deltaDraft);
    const rejection = inventoryAdjustmentError({
      quantity: snapshot.quantity,
      reservedQuantity: snapshot.reservedQuantity,
      quantityDelta: delta ?? Number.NaN,
      reason,
    });
    if (rejection !== null) {
      setRejectedDraft({ message: rejection, deltaDraft, reason });
      return;
    }

    const quantity = delta ?? Number.NaN;
    const signature = `${snapshot.id}:${inventoryAdjustmentSignature({ quantityDelta: quantity, reason })}`;
    const idempotencyKey = intent !== null && intent.signature === signature ? intent.key : newIdempotencyKey();
    onIntentChange({ signature, key: idempotencyKey });
    setRejectedDraft(null);
    setOutcome(null);
    setPending(true);
    inFlight.current = true;

    void (async () => {
      const result = await settleAdjustment(() =>
        actionsRef.current.adjust({ inventoryId: snapshot.id, idempotencyKey, quantityDelta: quantity, reason }),
      );
      inFlight.current = false;
      setPending(false);
      setOutcome(result);
      if (result.status === "success") {
        onIntentChange(null);
        setDeltaDraft("");
        setReloadToken((token) => token + 1);
      }
    })();
  }

  return (
    <Dialog open onClose={requestClose} maxWidth="sm" fullWidth aria-labelledby={TITLE_ID} aria-describedby={DESCRIPTION_ID}>
      <DialogTitle id={TITLE_ID}>{canManage ? "Inspect and adjust stock" : "Inspect stock"}</DialogTitle>
      <DialogContent>
        <Box sx={{ display: "flex", flexDirection: "column", gap: 3, pt: 1 }}>
          <Box component="ul" sx={{ listStyle: "none", m: 0, p: 0, display: "flex", flexDirection: "column", gap: 0.5 }}>
            <SubjectLine label="Product" value={`${inventorySubjectLabel(subject.product)} (${subject.product.identifier})`} />
            <SubjectLine label="Variant" value={`${inventoryVariantLabel(subject.variant)} (${subject.variant.identifier})`} />
            <SubjectLine label="SKU" value={subject.variant.sku ?? INVENTORY_SKU_UNAVAILABLE} />
            <SubjectLine label="Warehouse location" value={subject.record.warehouseLocation} />
          </Box>
          <Typography id={DESCRIPTION_ID} color="text.secondary" variant="body2">
            Quantities below are re-read from Laravel when this panel opens. The adjustment rules are enforced by the backend inside one transaction; the guidance here only mirrors them.
          </Typography>
          <Box component="section" aria-label="Current inventory record" sx={{ display: "flex", flexDirection: "column", gap: 1 }}>
            {snapshotLoading ? (
              <Typography role="status" variant="body2" color="text.secondary">
                Reading the current inventory record…
              </Typography>
            ) : (
              <>
                {snapshotMessage === null ? null : <Alert severity="warning">{snapshotMessage}</Alert>}
                <SnapshotLine label="Current quantity" value={snapshot === null ? INVENTORY_QUANTITY_UNAVAILABLE : formatInventoryQuantity(snapshot.quantity)} />
                <SnapshotLine label="Reserved quantity" value={snapshot === null ? INVENTORY_QUANTITY_UNAVAILABLE : formatInventoryQuantity(snapshot.reservedQuantity)} />
                <SnapshotLine label="Available quantity" value={snapshot === null ? INVENTORY_QUANTITY_UNAVAILABLE : formatInventoryQuantity(snapshot.availableQuantity)} />
                <SnapshotLine label="Last updated" value={snapshot === null ? INVENTORY_TIMESTAMP_UNAVAILABLE : formatInventoryTimestamp(snapshot.updatedAt) ?? INVENTORY_TIMESTAMP_UNAVAILABLE} />
              </>
            )}
          </Box>
          {canManage ? (
            <AdjustmentForm
              reason={reason}
              deltaDraft={deltaDraft}
              error={visibleError}
              preview={preview}
              pending={pending}
              onChangeReason={setReason}
              onChangeDelta={setDeltaDraft}
            />
          ) : (
            <Alert severity="info">
              This account has view-only inventory access. Stock adjustments require the inventory.manage permission.
            </Alert>
          )}
          {outcome === null ? null : (
            <Alert severity={inventoryAdjustmentSeverity(outcome.status)} role={outcome.status === "success" ? "status" : "alert"}>
              {outcome.message}
            </Alert>
          )}
        </Box>
      </DialogContent>
      <DialogActions>
        <Button onClick={requestClose} disabled={pending}>Close</Button>
        {canManage ? (
          <Button onClick={submit} variant="contained" disabled={blocked} aria-busy={pending}>
            {pending ? "Applying adjustment…" : "Apply adjustment"}
          </Button>
        ) : null}
      </DialogActions>
    </Dialog>
  );
}

interface AdjustmentFormProps {
  readonly reason: string;
  readonly deltaDraft: string;
  readonly error: string | null;
  readonly preview: number | null;
  readonly pending: boolean;
  readonly onChangeReason: (value: string) => void;
  readonly onChangeDelta: (value: string) => void;
}

function AdjustmentForm({ reason, deltaDraft, error, preview, pending, onChangeReason, onChangeDelta }: Readonly<AdjustmentFormProps>) {
  return (
    <Box component="form" aria-label="Stock adjustment" onSubmit={(event) => event.preventDefault()} sx={{ display: "flex", flexDirection: "column", gap: 2 }}>
      <TextField
        select
        id={REASON_FIELD_ID}
        name="reason"
        label="Adjustment reason"
        value={reason}
        onChange={(event) => onChangeReason(event.target.value)}
        disabled={pending}
        size="small"
      >
        {INVENTORY_REASON_VALUES.map((value) => (
          <MenuItem key={value} value={value}>{INVENTORY_REASON_LABELS[value]}</MenuItem>
        ))}
      </TextField>
      <TextField
        id={DELTA_FIELD_ID}
        name="quantity_delta"
        label="Quantity change"
        value={deltaDraft}
        onChange={(event) => onChangeDelta(event.target.value)}
        disabled={pending}
        size="small"
        error={error !== null}
        helperText={inventoryReasonHint(reason) ?? DELTA_DEFAULT_HINT}
        slotProps={{ htmlInput: { inputMode: "numeric", "aria-invalid": error !== null, "aria-describedby": error === null ? undefined : DELTA_ERROR_ID } }}
      />
      {error === null ? null : (
        <Typography id={DELTA_ERROR_ID} role="alert" color="error" variant="body2">
          {error}
        </Typography>
      )}
      <Typography color="text.secondary" variant="body2">
        {preview === null ? "Resulting quantity: enter a whole-number change to preview it." : `Resulting quantity: ${formatInventoryQuantity(preview)}`}
      </Typography>
    </Box>
  );
}

function SubjectLine({ label, value }: Readonly<{ label: string; value: string }>) {
  return (
    <Box component="li" sx={{ display: "flex", flexDirection: "column" }}>
      <Typography component="span" variant="body2" color="text.secondary">{label}</Typography>
      <Typography component="span" sx={{ overflowWrap: "anywhere" }}>{value}</Typography>
    </Box>
  );
}

function SnapshotLine({ label, value }: Readonly<{ label: string; value: string }>) {
  return (
    <Typography component="p" variant="body2" sx={{ display: "flex", gap: 1, flexWrap: "wrap", m: 0 }}>
      <Box component="span" sx={{ fontWeight: "var(--font-weight-medium)" }}>{label}</Box>
      <Box component="span">{value}</Box>
    </Typography>
  );
}

/**
 * A Server Action returns an outcome for every API error, so it only rejects on
 * an unexpected transport or serialization failure. That leaves the outcome
 * genuinely unknown, so it is reported as uncertain and the original key and
 * payload are kept for an identical retry.
 */
async function settleAdjustment(operation: () => Promise<InventoryAdjustmentOutcome>): Promise<InventoryAdjustmentOutcome> {
  try {
    return await operation();
  } catch {
    return { status: "uncertain", message: INVENTORY_ADJUSTMENT_UNCERTAIN_MESSAGE, retryAfterSeconds: null };
  }
}
