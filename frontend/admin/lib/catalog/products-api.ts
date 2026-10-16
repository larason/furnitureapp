import "server-only";

import { ApiError } from "@/lib/api/errors";
import type { ApiErrorItem, JsonValue } from "@/lib/api/types";
import { requestOperationalApi, requestOperationalUpload, requestPublicApi } from "@/lib/api/server-request";
import { parseCategoryOptions, type CategoryOption } from "@/lib/catalog/category-option";
import { ProductResourceError, parseOperationalProduct, parseProductPage, type OperationalProduct, type OperationalProductPage } from "@/lib/catalog/product-resource";
import { productListRequestQuery, type ProductListQuery } from "@/lib/catalog/product-query";

/**
 * Laravel-backed product reads and writes. Every call goes through the shared
 * transport; authenticated calls stay `no-store`, and response parsing never
 * invents a product the API did not return.
 */
export type ProductApiFailure = "session" | "forbidden" | "not-found" | "invalid" | "unavailable" | "throttled";

export type ProductReadOutcome<T> =
  | { readonly ok: true; readonly value: T }
  | { readonly ok: false; readonly failure: ProductApiFailure; readonly retryAfterSeconds: number | null };

export interface CategoryOptionResult {
  readonly options: readonly CategoryOption[];
  /** True when the public category collection is paginated beyond the first page. */
  readonly truncated: boolean;
}

const STATUS_FAILURES: Readonly<Record<number, ProductApiFailure>> = {
  401: "session",
  403: "forbidden",
  404: "not-found",
  409: "invalid",
  422: "invalid",
  429: "throttled",
};

export async function fetchOperationalProductPage(query: ProductListQuery): Promise<OperationalProductPage> {
  const response = await requestOperationalApi<unknown>({ path: "/admin/products", query: productListRequestQuery(query) });
  return parseProductPage(response);
}

export async function fetchOperationalProduct(identifier: string): Promise<OperationalProduct> {
  const response = await requestOperationalApi<unknown>({ path: `/admin/products/${encodeURIComponent(identifier)}` });
  return parseOperationalProduct(response.data);
}

export async function fetchCategoryOptions(): Promise<CategoryOptionResult> {
  const response = await requestPublicApi<unknown>({ path: "/categories", query: { per_page: 100 } });
  return { options: parseCategoryOptions(response.data), truncated: hasMorePages(response.meta) };
}

export async function submitProductCreate(payload: Readonly<Record<string, JsonValue>>): Promise<OperationalProduct> {
  const response = await requestOperationalApi<unknown>({ path: "/products", method: "POST", body: payload });
  return parseOperationalProduct(response.data);
}

export async function submitProductUpdate(identifier: string, payload: Readonly<Record<string, JsonValue>>): Promise<OperationalProduct> {
  const response = await requestOperationalApi<unknown>({ path: `/products/${encodeURIComponent(identifier)}`, method: "PATCH", body: payload });
  return parseOperationalProduct(response.data);
}

/**
 * CAT-009 image upload. `form` must carry only the `image` file field — the
 * backend rejects any other field — and the response body is not parsed, because
 * the caller refreshes the product detail resource instead.
 */
export async function submitProductImage(identifier: string, form: FormData): Promise<void> {
  await requestOperationalUpload<unknown>({ path: `/products/${encodeURIComponent(identifier)}/images`, method: "POST", form });
}

/** Runs a read and converts a thrown failure into a render-safe outcome. */
export async function attemptProductRead<T>(operation: () => Promise<T>): Promise<ProductReadOutcome<T>> {
  try {
    return { ok: true, value: await operation() };
  } catch (error) {
    return { ok: false, failure: describeProductApiFailure(error), retryAfterSeconds: retryAfterSeconds(error) };
  }
}

/**
 * Classifies a failed product read. A response the parsers could not read is
 * `invalid` (unreadable data), not `unavailable` (a service outage) — the two
 * render different states and only one of them is worth retrying.
 */
function describeProductApiFailure(error: unknown): ProductApiFailure {
  if (error instanceof ProductResourceError) {
    return "invalid";
  }

  return error instanceof ApiError ? STATUS_FAILURES[error.status] ?? "unavailable" : "unavailable";
}

export function productWriteErrors(error: unknown): { readonly status: number | null; readonly errors: readonly ApiErrorItem[]; readonly retryAfterSeconds: number | null } {
  return error instanceof ApiError
    ? { status: error.status, errors: error.errors, retryAfterSeconds: error.retryAfterSeconds ?? null }
    : { status: null, errors: [], retryAfterSeconds: null };
}

function retryAfterSeconds(error: unknown): number | null {
  return error instanceof ApiError ? error.retryAfterSeconds ?? null : null;
}

function hasMorePages(meta: unknown): boolean {
  if (typeof meta !== "object" || meta === null) return false;
  const pagination = (meta as { pagination?: unknown }).pagination;
  if (typeof pagination !== "object" || pagination === null) return false;
  const lastPage = (pagination as { last_page?: unknown }).last_page;
  return typeof lastPage === "number" && lastPage > 1;
}
