import "server-only";

import { ApiError } from "@/lib/api/errors";
import { requestOperationalApi, requestPublicApi } from "@/lib/api/server-request";
import type { JsonValue } from "@/lib/api/types";
import {
  buildInventoryLabels,
  catalogProductName,
  catalogVariantEntries,
  type InventoryLabelLookup,
  type InventoryVariantEntry,
  type ResolvedInventoryLabels,
} from "@/lib/catalog/inventory-label";
import { INVENTORY_QUANTITY_DELTA_FIELD, INVENTORY_REASON_FIELD } from "@/lib/catalog/inventory-mutations";
import { inventoryListRequestQuery, type InventoryListQuery } from "@/lib/catalog/inventory-query";
import {
  InventoryResourceError,
  parseInventoryPage,
  parseInventoryRecord,
  type InventoryPage,
  type InventoryRecord,
} from "@/lib/catalog/inventory-resource";

/**
 * Laravel-backed inventory reads and the audited adjustment. Every call goes
 * through the shared transport; authenticated calls stay `no-store`, and no
 * response is reconstructed. Label lookup additionally reads the public catalog,
 * because `inventory.view` alone cannot read the operational product resource.
 */
export type InventoryApiFailure = "session" | "forbidden" | "not-found" | "invalid" | "unavailable" | "throttled";

export type InventoryReadOutcome<T> =
  | { readonly ok: true; readonly value: T }
  | { readonly ok: false; readonly failure: InventoryApiFailure; readonly retryAfterSeconds: number | null };

const STATUS_FAILURES: Readonly<Record<number, InventoryApiFailure>> = {
  401: "session",
  403: "forbidden",
  404: "not-found",
  409: "invalid",
  422: "invalid",
  429: "throttled",
};

const INVENTORY_PATH = "/inventory";
const CATALOG_PRODUCTS_PATH = "/products";
const LABEL_LOOKUP_CONCURRENCY = 4;

export async function fetchInventoryPage(query: InventoryListQuery): Promise<InventoryPage> {
  const response = await requestOperationalApi<unknown>({ path: INVENTORY_PATH, query: inventoryListRequestQuery(query) });
  return parseInventoryPage(response);
}

export async function fetchInventoryRecord(inventoryId: string): Promise<InventoryRecord> {
  const response = await requestOperationalApi<unknown>({ path: inventoryRecordPath(inventoryId) });
  return parseInventoryRecord(response.data);
}

/**
 * INV-003. The response body is deliberately ignored: the caller refreshes the
 * authoritative INV-002 snapshot instead of trusting the mutation's shape.
 */
export async function submitInventoryAdjustment(
  inventoryId: string,
  quantityDelta: number,
  reason: string,
  idempotencyKey: string,
): Promise<void> {
  const body: Record<string, JsonValue> = {
    [INVENTORY_QUANTITY_DELTA_FIELD]: quantityDelta,
    [INVENTORY_REASON_FIELD]: reason,
  };
  await requestOperationalApi<unknown>({
    path: `${inventoryRecordPath(inventoryId)}/adjust`,
    method: "POST",
    body,
    idempotencyKey,
  });
}

/** Resolves the display-only labels for the distinct products a page refers to. */
export async function resolveInventoryLabels(productIds: readonly string[]): Promise<ResolvedInventoryLabels> {
  const lookups = await mapWithConcurrency(productIds, LABEL_LOOKUP_CONCURRENCY, async (productId): Promise<InventoryLabelLookup> => ({
    productId,
    productName: await readCatalogProductName(productId),
    variants: await readCatalogVariantEntries(productId),
  }));
  return buildInventoryLabels(lookups);
}

/** Runs a read and converts a thrown failure into a render-safe outcome. */
export async function attemptInventoryRead<T>(operation: () => Promise<T>): Promise<InventoryReadOutcome<T>> {
  try {
    return { ok: true, value: await operation() };
  } catch (error) {
    return { ok: false, failure: describeInventoryApiFailure(error), retryAfterSeconds: retryAfterSeconds(error) };
  }
}

function inventoryRecordPath(inventoryId: string): string {
  return `${INVENTORY_PATH}/${encodeURIComponent(inventoryId)}`;
}

/**
 * A response the parsers could not read is `invalid` (unreadable data), not
 * `unavailable` (a service outage) — the two render different states and only one
 * of them is worth retrying.
 */
function describeInventoryApiFailure(error: unknown): InventoryApiFailure {
  if (error instanceof InventoryResourceError) {
    return "invalid";
  }

  return error instanceof ApiError ? STATUS_FAILURES[error.status] ?? "unavailable" : "unavailable";
}

function retryAfterSeconds(error: unknown): number | null {
  return error instanceof ApiError ? error.retryAfterSeconds ?? null : null;
}

/** Best-effort label read: an unreachable or missing product leaves the label unresolved. */
async function readCatalogProductName(productId: string): Promise<string | null> {
  try {
    const response = await requestPublicApi<unknown>({ path: `${CATALOG_PRODUCTS_PATH}/${encodeURIComponent(productId)}` });
    return catalogProductName(response.data);
  } catch {
    return null;
  }
}

async function readCatalogVariantEntries(productId: string): Promise<readonly InventoryVariantEntry[] | null> {
  try {
    const response = await requestPublicApi<unknown>({
      path: `${CATALOG_PRODUCTS_PATH}/${encodeURIComponent(productId)}/variants`,
    });
    return catalogVariantEntries(response.data);
  } catch {
    return null;
  }
}

/** Bounded parallel map, so a full inventory page never opens an unbounded fan-out. */
async function mapWithConcurrency<T, R>(items: readonly T[], limit: number, run: (item: T) => Promise<R>): Promise<readonly R[]> {
  const results: R[] = [];
  let cursor = 0;
  const worker = async (): Promise<void> => {
    while (cursor < items.length) {
      const index = cursor;
      cursor += 1;
      results[index] = await run(items[index]);
    }
  };
  await Promise.all(Array.from({ length: Math.min(limit, items.length) }, worker));
  return results;
}
