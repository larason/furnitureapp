import "server-only";

import { ApiError } from "@/lib/api/errors";
import type { ApiErrorItem, JsonValue } from "@/lib/api/types";
import { requestOperationalApi, requestPublicApi } from "@/lib/api/server-request";
import { CategoryResourceError, parseCategoryDetail, parseCategoryPage, type CategoryDetail, type CategoryPage } from "@/lib/catalog/category-resource";
import { categoryListRequestQuery, type CategoryListQuery } from "@/lib/catalog/category-query";

/**
 * Laravel-backed category reads and writes. The collection (and its detail)
 * read through the public catalog endpoints because no operational category
 * listing exists; the writes use the `products.manage`-gated endpoints. Every
 * call goes through the shared transport and no response is reconstructed.
 */
export type CategoryApiFailure = "session" | "forbidden" | "not-found" | "invalid" | "unavailable" | "throttled";

export type CategoryReadOutcome<T> =
  | { readonly ok: true; readonly value: T }
  | { readonly ok: false; readonly failure: CategoryApiFailure; readonly retryAfterSeconds: number | null };

const STATUS_FAILURES: Readonly<Record<number, CategoryApiFailure>> = {
  401: "session",
  403: "forbidden",
  404: "not-found",
  409: "invalid",
  422: "invalid",
  429: "throttled",
};

export async function fetchCategoryPage(query: CategoryListQuery): Promise<CategoryPage> {
  const response = await requestPublicApi<unknown>({ path: "/categories", query: categoryListRequestQuery(query) });
  return parseCategoryPage(response);
}

export async function fetchCategory(identifier: string): Promise<CategoryDetail> {
  const response = await requestPublicApi<unknown>({ path: `/categories/${encodeURIComponent(identifier)}` });
  return parseCategoryDetail(response.data);
}

export async function submitCategoryCreate(payload: Readonly<Record<string, JsonValue>>): Promise<CategoryDetail> {
  const response = await requestOperationalApi<unknown>({ path: "/categories", method: "POST", body: payload });
  return parseCategoryDetail(response.data);
}

export async function submitCategoryUpdate(identifier: string, payload: Readonly<Record<string, JsonValue>>): Promise<CategoryDetail> {
  const response = await requestOperationalApi<unknown>({ path: `/categories/${encodeURIComponent(identifier)}`, method: "PATCH", body: payload });
  return parseCategoryDetail(response.data);
}

/** Runs a read and converts a thrown failure into a render-safe outcome. */
export async function attemptCategoryRead<T>(operation: () => Promise<T>): Promise<CategoryReadOutcome<T>> {
  try {
    return { ok: true, value: await operation() };
  } catch (error) {
    return { ok: false, failure: describeCategoryApiFailure(error), retryAfterSeconds: retryAfterSeconds(error) };
  }
}

export function categoryWriteErrors(error: unknown): { readonly status: number | null; readonly errors: readonly ApiErrorItem[]; readonly retryAfterSeconds: number | null } {
  return error instanceof ApiError
    ? { status: error.status, errors: error.errors, retryAfterSeconds: error.retryAfterSeconds ?? null }
    : { status: null, errors: [], retryAfterSeconds: null };
}

/**
 * Classifies a failed category read. A response the parsers could not read is
 * `invalid` (unreadable data), not `unavailable` (a service outage) — the two
 * render different states and only one of them is worth retrying.
 */
function describeCategoryApiFailure(error: unknown): CategoryApiFailure {
  if (error instanceof CategoryResourceError) {
    return "invalid";
  }

  return error instanceof ApiError ? STATUS_FAILURES[error.status] ?? "unavailable" : "unavailable";
}

function retryAfterSeconds(error: unknown): number | null {
  return error instanceof ApiError ? error.retryAfterSeconds ?? null : null;
}
