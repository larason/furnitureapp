"use server";

import { revalidatePath } from "next/cache";
import { ApiError, ApiTransportError } from "@/lib/api/errors";
import { AdminPermissionName } from "@/lib/auth/permission-names";
import { resolveAdminRouteAccess, type AdminRouteAccess } from "@/lib/auth/route-guard";
import { requireOperationalIdentity } from "@/lib/auth/server-operational-identity";
import { ADMIN_REQUEST_PATHS, ADMIN_ROUTE_PATHS } from "@/lib/navigation/admin-route-paths";
import { submitRequestUpdate } from "@/lib/requests/request-api";
import {
  MAX_STAFF_NOTES,
  REQUEST_UPDATE_NOTES_KEY,
  REQUEST_UPDATE_SESSION_MESSAGE,
  REQUEST_UPDATE_STATUS_KEY,
  REQUEST_UPDATE_UNAVAILABLE_MESSAGE,
  REQUEST_UPDATE_UNIDENTIFIED_MESSAGE,
  REQUEST_UPDATE_UNAUTHORIZED_MESSAGE,
  isRequestStatusValue,
  requestUpdateFailureOutcome,
  requestUpdateRejection,
  requestUpdateSuccess,
  type RequestUpdateFailure,
  type RequestUpdateFields,
  type RequestUpdateOutcome,
} from "@/lib/requests/request-mutations";

const REQUEST_IDENTIFIER_PATTERN = /^req_[0-9a-z]+$/;
const MAX_UPDATE_FIELDS = 2;

/**
 * REQ-006. Server Functions are directly POST-reachable, so this repeats the
 * exact Laravel-backed `requests.manage` requirement before touching a resource,
 * re-validates the payload shape instead of trusting the caller, and never sends
 * a no-op or an unknown field.
 */
export async function updateRequestAction(input: unknown): Promise<RequestUpdateOutcome> {
  const access = resolveAdminRouteAccess(await requireOperationalIdentity(), { permission: AdminPermissionName.REQUESTS_MANAGE });
  if (access.kind !== "granted") return denialOutcome(access.kind);

  const prepared = prepareUpdate(input);
  if (!prepared.ok) return prepared.outcome;

  try {
    await submitRequestUpdate(prepared.requestId, prepared.fields);
  } catch (error) {
    return requestUpdateFailureOutcome(updateFailure(error));
  }

  revalidatePath(ADMIN_ROUTE_PATHS.requests);
  revalidatePath(ADMIN_REQUEST_PATHS.detail(prepared.requestId));
  return requestUpdateSuccess();
}

type PreparedUpdate =
  | { readonly ok: true; readonly requestId: string; readonly fields: RequestUpdateFields }
  | { readonly ok: false; readonly outcome: RequestUpdateOutcome };

function prepareUpdate(value: unknown): PreparedUpdate {
  const record = isRecord(value) ? value : {};
  const requestId = readRequestIdentifier(record.requestId);
  const fields = readUpdateFields(record.fields);

  if (requestId === null || fields === null) {
    return { ok: false, outcome: requestUpdateRejection(REQUEST_UPDATE_UNIDENTIFIED_MESSAGE) };
  }

  return { ok: true, requestId, fields };
}

/**
 * The state-free half of the REQ-006 rules: at most the two documented fields,
 * at least one of them, `request_status` inside the CLOSED enum, and notes inside
 * the 5000-character maximum. Laravel re-validates all of it.
 */
function readUpdateFields(value: unknown): RequestUpdateFields | null {
  if (!isRecord(value)) return null;
  const keys = Object.keys(value);
  if (keys.length === 0 || keys.length > MAX_UPDATE_FIELDS) return null;
  if (keys.some((key) => key !== REQUEST_UPDATE_STATUS_KEY && key !== REQUEST_UPDATE_NOTES_KEY)) return null;

  const fields: { requestStatus?: RequestUpdateFields["requestStatus"]; staffInternalNotes?: string | null } = {};

  if (REQUEST_UPDATE_STATUS_KEY in value) {
    if (!isRequestStatusValue(value[REQUEST_UPDATE_STATUS_KEY])) return null;
    fields.requestStatus = value[REQUEST_UPDATE_STATUS_KEY];
  }

  if (REQUEST_UPDATE_NOTES_KEY in value) {
    const notes = readNotes(value[REQUEST_UPDATE_NOTES_KEY]);
    if (notes === undefined) return null;
    fields.staffInternalNotes = notes;
  }

  return fields;
}

/** `null`/blank means clear; a non-blank string within the limit replaces the note. */
function readNotes(value: unknown): string | null | undefined {
  if (value === null) return null;
  if (typeof value !== "string") return undefined;
  const trimmed = value.trim();
  if (trimmed.length > MAX_STAFF_NOTES) return undefined;
  return trimmed.length > 0 ? trimmed : null;
}

function updateFailure(error: unknown): RequestUpdateFailure {
  if (error instanceof ApiTransportError) {
    return { status: null, errors: [], transportKind: error.kind, retryAfterSeconds: null };
  }

  return error instanceof ApiError
    ? { status: error.status, errors: error.errors, transportKind: null, retryAfterSeconds: error.retryAfterSeconds ?? null }
    : { status: null, errors: [], transportKind: null, retryAfterSeconds: null };
}

function denialOutcome(kind: AdminRouteAccess["kind"]): RequestUpdateOutcome {
  if (kind === "signed-out" || kind === "session-invalid") {
    return { status: "session", message: REQUEST_UPDATE_SESSION_MESSAGE, retryAfterSeconds: null, fieldErrors: [] };
  }

  return kind === "unavailable"
    ? { status: "unavailable", message: REQUEST_UPDATE_UNAVAILABLE_MESSAGE, retryAfterSeconds: null, fieldErrors: [] }
    : { status: "forbidden", message: REQUEST_UPDATE_UNAUTHORIZED_MESSAGE, retryAfterSeconds: null, fieldErrors: [] };
}

function readRequestIdentifier(value: unknown): string | null {
  return typeof value === "string" && REQUEST_IDENTIFIER_PATTERN.test(value) ? value : null;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}
