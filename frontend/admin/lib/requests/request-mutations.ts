import type { ApiErrorItem } from "@/lib/api/types";

/**
 * Made-to-order request vocabulary shared by the REQ-006 Server Action and the
 * client operational form: the CLOSED status set and its transitions, the field
 * allow-list and its wire names, advisory client-side validation, and the safe
 * outcome catalogue. It carries no server-only import so both sides consume one
 * definition, and Laravel remains authoritative for every rule it mirrors.
 */

export type RequestStatusValue = "SUBMITTED" | "IN_REVIEW" | "CLOSED";

/** The CLOSED Version 1 status set (`backend/laravel/app/Support/RequestStatus.php`). */
export const REQUEST_STATUS_VALUES = ["SUBMITTED", "IN_REVIEW", "CLOSED"] as const;

/** The two documented REQ-006 request fields, by their wire names. */
export const REQUEST_STATUS_FIELD = "request_status";
export const STAFF_INTERNAL_NOTES_FIELD = "staff_internal_notes";

/** The field keys the Server Action accepts, before they become wire fields. */
export const REQUEST_UPDATE_STATUS_KEY = "requestStatus";
export const REQUEST_UPDATE_NOTES_KEY = "staffInternalNotes";

export const MAX_STAFF_NOTES = 5000;

/** The select value meaning "keep the current status"; notes can still change. */
export const REQUEST_STATUS_NO_CHANGE = "";

export const REQUEST_STATUS_LABELS: Readonly<Record<RequestStatusValue, string>> = {
  SUBMITTED: "Submitted",
  IN_REVIEW: "In review",
  CLOSED: "Closed",
};

export type RequestStatusTone = "info" | "warning" | "neutral";

const REQUEST_STATUS_TONES: Readonly<Record<RequestStatusValue, RequestStatusTone>> = {
  SUBMITTED: "info",
  IN_REVIEW: "warning",
  CLOSED: "neutral",
};

export function isRequestStatusValue(value: unknown): value is RequestStatusValue {
  return typeof value === "string" && (REQUEST_STATUS_VALUES as readonly string[]).includes(value);
}

export function requestStatusLabel(value: string): string {
  return isRequestStatusValue(value) ? REQUEST_STATUS_LABELS[value] : "Unrecognised status";
}

export function requestStatusTone(value: string): RequestStatusTone {
  return isRequestStatusValue(value) ? REQUEST_STATUS_TONES[value] : "neutral";
}

/**
 * The transitions the Laravel request state machine allows from `current`.
 * `CLOSED` is terminal: there is no reopen in Version 1.
 */
const ALLOWED_TRANSITIONS: Readonly<Record<RequestStatusValue, readonly RequestStatusValue[]>> = {
  SUBMITTED: ["IN_REVIEW", "CLOSED"],
  IN_REVIEW: ["CLOSED"],
  CLOSED: [],
};

export function requestStatusTransitions(current: string): readonly RequestStatusValue[] {
  return isRequestStatusValue(current) ? ALLOWED_TRANSITIONS[current] : [];
}

/** A trimmed blank note is Laravel's "clear"; anything else replaces the value. */
export function normalizeStaffNotes(value: string): string | null {
  const trimmed = value.trim();
  return trimmed.length > 0 ? trimmed : null;
}

export interface RequestUpdateFields {
  readonly requestStatus?: RequestStatusValue;
  readonly staffInternalNotes?: string | null;
}

export interface RequestUpdateRequest {
  readonly requestId: string;
  readonly fields: RequestUpdateFields;
}

export interface RequestUpdateTarget {
  readonly id: string;
  readonly requestStatus: string;
  readonly staffInternalNotes: string | null;
}

/**
 * The changed-fields payload for one submission, or `null` when nothing changed.
 * Only `request_status` and/or a genuinely changed `staff_internal_notes` are
 * ever emitted, so a no-op update can never be sent.
 */
export function requestUpdateFields(
  record: Readonly<{ requestStatus: string; staffInternalNotes: string | null }>,
  selectedStatus: string,
  notesDraft: string,
): RequestUpdateFields | null {
  const fields: { requestStatus?: RequestStatusValue; staffInternalNotes?: string | null } = {};

  if (isRequestStatusValue(selectedStatus) && selectedStatus !== record.requestStatus) {
    fields.requestStatus = selectedStatus;
  }

  const desiredNotes = normalizeStaffNotes(notesDraft);
  if (desiredNotes !== record.staffInternalNotes) {
    fields.staffInternalNotes = desiredNotes;
  }

  return Object.keys(fields).length > 0 ? fields : null;
}

export const REQUEST_NOTES_TOO_LONG_MESSAGE = `Internal notes cannot exceed ${MAX_STAFF_NOTES} characters.`;

/** Advisory limit mirroring the frozen REQ-006 validation; Laravel re-checks it. */
export function requestNotesError(draft: string): string | null {
  return draft.trim().length > MAX_STAFF_NOTES ? REQUEST_NOTES_TOO_LONG_MESSAGE : null;
}

/** Advisory transition check; Laravel validates the transition authoritatively. */
export function requestStatusOptionError(current: string, selected: string): string | null {
  if (selected === REQUEST_STATUS_NO_CHANGE) return null;
  if (!isRequestStatusValue(selected)) return "Choose a valid request status.";
  return requestStatusTransitions(current).includes(selected)
    ? null
    : "That status transition is not allowed from the current status.";
}

export type RequestUpdateStatus =
  | "success"
  | "invalid"
  | "forbidden"
  | "session"
  | "not-found"
  | "conflict"
  | "rate-limited"
  | "unavailable"
  | "uncertain";

/** What a REQ-006 submission reports back. `uncertain` means the result is unknown. */
export interface RequestUpdateOutcome {
  readonly status: RequestUpdateStatus;
  /** Safe, operator-facing message. Never carries Laravel internals. */
  readonly message: string;
  readonly retryAfterSeconds: number | null;
  readonly fieldErrors: readonly ApiErrorItem[];
}

export const REQUEST_UPDATE_SUCCESS_MESSAGE = "Request updated. The list and detail have been refreshed.";
export const REQUEST_UPDATE_SESSION_MESSAGE = "Your session is no longer valid. Sign out and sign in again.";
export const REQUEST_UPDATE_UNAUTHORIZED_MESSAGE = "You are not authorized to update requests.";
export const REQUEST_UPDATE_UNAVAILABLE_MESSAGE = "The request service is unavailable. Try again shortly.";
export const REQUEST_UPDATE_NOT_FOUND_MESSAGE = "This request is no longer available. Return to the request list.";
export const REQUEST_UPDATE_CONFLICT_MESSAGE = "This request was changed by someone else. Reload to see the current status.";
export const REQUEST_UPDATE_UNCERTAIN_MESSAGE = "The connection failed before the result was known. Reload the request to check its current state before trying again.";
export const REQUEST_UPDATE_UNIDENTIFIED_MESSAGE = "This update could not be submitted because its contents were missing or invalid.";
const REQUEST_UPDATE_INVALID_MESSAGE = "Laravel rejected this update. Check the status and internal notes.";
export const REQUEST_VIEW_UNAUTHORIZED_MESSAGE = "You are not authorized to view requests.";
const REQUEST_TOO_MANY_MESSAGE = "Too many requests. Try again shortly.";

export function requestUpdateSuccess(): RequestUpdateOutcome {
  return { status: "success", message: REQUEST_UPDATE_SUCCESS_MESSAGE, retryAfterSeconds: null, fieldErrors: [] };
}

/** A request rejected before it reached Laravel, e.g. a malformed payload. */
export function requestUpdateRejection(message: string): RequestUpdateOutcome {
  return { status: "invalid", message, retryAfterSeconds: null, fieldErrors: [] };
}

/** A failed update as classified from the shared API error envelope. */
export interface RequestUpdateFailure {
  readonly status: number | null;
  readonly errors: readonly ApiErrorItem[];
  readonly transportKind: "network" | "timeout" | "aborted" | null;
  readonly retryAfterSeconds: number | null;
}

export function requestUpdateFailureOutcome(failure: RequestUpdateFailure): RequestUpdateOutcome {
  const { status } = failure;
  if (failure.transportKind !== null) return outcome("uncertain", REQUEST_UPDATE_UNCERTAIN_MESSAGE, failure);
  if (status === 401) return outcome("session", REQUEST_UPDATE_SESSION_MESSAGE, failure);
  if (status === 403) return outcome("forbidden", REQUEST_UPDATE_UNAUTHORIZED_MESSAGE, failure);
  if (status === 404) return outcome("not-found", REQUEST_UPDATE_NOT_FOUND_MESSAGE, failure);
  if (status === 409) return outcome("conflict", REQUEST_UPDATE_CONFLICT_MESSAGE, failure);
  if (status === 422) return outcome("invalid", invalidMessage(failure.errors), failure);
  if (status === 429) return outcome("rate-limited", rateLimitedMessage(failure.retryAfterSeconds), failure);
  return outcome("unavailable", REQUEST_UPDATE_UNAVAILABLE_MESSAGE, failure);
}

function outcome(status: RequestUpdateStatus, message: string, failure: RequestUpdateFailure): RequestUpdateOutcome {
  return {
    status,
    message,
    retryAfterSeconds: failure.status === 429 ? failure.retryAfterSeconds : null,
    fieldErrors: status === "invalid" ? failure.errors : [],
  };
}

/** Prefers Laravel's own field message over the generic fallback. */
function invalidMessage(errors: readonly ApiErrorItem[]): string {
  const chosen =
    errors.find((item) => item.field === REQUEST_STATUS_FIELD) ??
    errors.find((item) => item.field === STAFF_INTERNAL_NOTES_FIELD) ??
    errors[0];
  return chosen !== undefined && chosen.message.trim().length > 0 ? chosen.message : REQUEST_UPDATE_INVALID_MESSAGE;
}

function rateLimitedMessage(retryAfterSeconds: number | null): string {
  return retryAfterSeconds === null ? "Too many updates. Try again shortly." : `Too many updates. Try again in ${retryAfterSeconds} seconds.`;
}

export type RequestUpdateSeverity = "success" | "warning" | "error";

export function requestUpdateSeverity(status: RequestUpdateStatus): RequestUpdateSeverity {
  if (status === "success") return "success";
  return status === "uncertain" || status === "conflict" || status === "rate-limited" ? "warning" : "error";
}

/**
 * The one operation the operational form invokes. It is a Server Action supplied
 * by the page, so the client component never imports the server-only transport.
 */
export interface RequestUpdateActions {
  readonly update: (request: RequestUpdateRequest) => Promise<RequestUpdateOutcome>;
}
