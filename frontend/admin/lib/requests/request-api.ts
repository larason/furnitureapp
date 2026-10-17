import "server-only";

import { ApiError } from "@/lib/api/errors";
import { requestOperationalApi } from "@/lib/api/server-request";
import type { JsonValue } from "@/lib/api/types";
import {
  REQUEST_STATUS_FIELD,
  STAFF_INTERNAL_NOTES_FIELD,
  type RequestUpdateFields,
} from "@/lib/requests/request-mutations";
import { requestListRequestQuery, type RequestListQuery } from "@/lib/requests/request-query";
import {
  RequestResourceError,
  parseOperationalRequest,
  parseRequestPage,
  type OperationalRequest,
  type RequestPage,
} from "@/lib/requests/request-resource";

/**
 * Laravel-backed request reads and the REQ-006 update. Every call goes through
 * the shared transport; authenticated calls stay `no-store`, and no response is
 * reconstructed.
 */
export type RequestApiFailure = "session" | "forbidden" | "not-found" | "invalid" | "unavailable" | "throttled";

export type RequestReadOutcome<T> =
  | { readonly ok: true; readonly value: T }
  | { readonly ok: false; readonly failure: RequestApiFailure; readonly retryAfterSeconds: number | null };

const REQUESTS_PATH = "/requests";

const STATUS_FAILURES: Readonly<Record<number, RequestApiFailure>> = {
  401: "session",
  403: "forbidden",
  404: "not-found",
  409: "invalid",
  422: "invalid",
  429: "throttled",
};

export async function fetchRequestPage(query: RequestListQuery): Promise<RequestPage> {
  const response = await requestOperationalApi<unknown>({ path: REQUESTS_PATH, query: requestListRequestQuery(query) });
  return parseRequestPage(response);
}

export async function fetchRequest(requestId: string): Promise<OperationalRequest> {
  const response = await requestOperationalApi<unknown>({ path: requestPath(requestId) });
  return parseOperationalRequest(response.data);
}

export async function submitRequestUpdate(requestId: string, fields: RequestUpdateFields): Promise<void> {
  await requestOperationalApi<unknown>({ path: requestPath(requestId), method: "PATCH", body: requestUpdateBody(fields) });
}

/** Serializes only the allow-listed, changed fields into their documented wire names. */
function requestUpdateBody(fields: RequestUpdateFields): Record<string, JsonValue> {
  const body: Record<string, JsonValue> = {};
  if (fields.requestStatus !== undefined) body[REQUEST_STATUS_FIELD] = fields.requestStatus;
  if (fields.staffInternalNotes !== undefined) body[STAFF_INTERNAL_NOTES_FIELD] = fields.staffInternalNotes;
  return body;
}

/** Runs a read and converts a thrown failure into a render-safe outcome. */
export async function attemptRequestRead<T>(operation: () => Promise<T>): Promise<RequestReadOutcome<T>> {
  try {
    return { ok: true, value: await operation() };
  } catch (error) {
    return { ok: false, failure: describeRequestApiFailure(error), retryAfterSeconds: retryAfterSeconds(error) };
  }
}

function requestPath(requestId: string): string {
  return `${REQUESTS_PATH}/${encodeURIComponent(requestId)}`;
}

/**
 * A response the parser could not read is `invalid` (unreadable data), not
 * `unavailable` (a service outage) — the two render different states.
 */
function describeRequestApiFailure(error: unknown): RequestApiFailure {
  if (error instanceof RequestResourceError) {
    return "invalid";
  }

  return error instanceof ApiError ? STATUS_FAILURES[error.status] ?? "unavailable" : "unavailable";
}

function retryAfterSeconds(error: unknown): number | null {
  return error instanceof ApiError ? error.retryAfterSeconds ?? null : null;
}
