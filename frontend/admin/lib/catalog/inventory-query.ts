import { ADMIN_ROUTE_PATHS } from "@/lib/navigation/admin-route-paths";

/**
 * The approved `GET /api/v1/inventory` query vocabulary. Laravel accepts exactly
 * `product`, `variant`, `warehouse_location`, `page` and `per_page`, and rejects
 * unknown parameters, so no other filter is invented or emitted.
 */
export const INVENTORY_LIST_DEFAULT_PER_PAGE = 20;
const INVENTORY_LIST_MAX_PER_PAGE = 100;
export const INVENTORY_PRODUCT_FILTER_MAX_LENGTH = 100;
export const INVENTORY_VARIANT_FILTER_MAX_LENGTH = 100;
export const INVENTORY_WAREHOUSE_FILTER_MAX_LENGTH = 255;
const VARIANT_IDENTIFIER_PATTERN = /^var_[0-9a-z]+$/;

export interface InventoryListQuery {
  readonly product: string | null;
  readonly variant: string | null;
  readonly warehouseLocation: string | null;
  readonly page: number;
  readonly perPage: number;
}

/** A raw, unvalidated `searchParams` entry as Next.js delivers it. */
type RawQueryParam = string | string[] | undefined;

type QueryParamValue = string | number;

type QueryParamRecord = Readonly<Record<string, QueryParamValue>>;

export type InventoryListSearchParams = Record<string, RawQueryParam>;

export function parseInventoryListQuery(params: InventoryListSearchParams): InventoryListQuery {
  return {
    product: readText(params.product, INVENTORY_PRODUCT_FILTER_MAX_LENGTH),
    variant: readVariant(params.variant),
    warehouseLocation: readText(params.warehouse_location, INVENTORY_WAREHOUSE_FILTER_MAX_LENGTH),
    page: readInteger(params.page, 1, Number.MAX_SAFE_INTEGER, 1),
    perPage: readInteger(params.per_page, 1, INVENTORY_LIST_MAX_PER_PAGE, INVENTORY_LIST_DEFAULT_PER_PAGE),
  };
}

/** Builds the exact API query for the operational inventory request. */
export function inventoryListRequestQuery(query: InventoryListQuery): QueryParamRecord {
  return compact({
    product: query.product,
    variant: query.variant,
    warehouse_location: query.warehouseLocation,
    page: query.page,
    per_page: query.perPage,
  });
}

/** Builds a shareable admin URL, omitting default-valued parameters. */
export function inventoryListHref(query: InventoryListQuery): string {
  const params = new URLSearchParams();
  const entries = compact({
    product: query.product,
    variant: query.variant,
    warehouse_location: query.warehouseLocation,
    page: query.page > 1 ? query.page : null,
    per_page: query.perPage === INVENTORY_LIST_DEFAULT_PER_PAGE ? null : query.perPage,
  });

  for (const [key, value] of Object.entries(entries)) params.set(key, String(value));
  const search = params.toString();
  return search.length > 0 ? `${ADMIN_ROUTE_PATHS.inventory}?${search}` : ADMIN_ROUTE_PATHS.inventory;
}

export function countInventoryFilters(query: InventoryListQuery): number {
  return [query.product, query.variant, query.warehouseLocation].filter((value) => value !== null).length;
}

function compact(source: Readonly<Record<string, QueryParamValue | null>>): QueryParamRecord {
  return Object.fromEntries(Object.entries(source).filter(([, value]) => value !== null)) as Record<string, QueryParamValue>;
}

function readFirst(value: RawQueryParam): string | null {
  if (typeof value === "string") return value;
  return Array.isArray(value) && typeof value[0] === "string" ? value[0] : null;
}

function readText(value: RawQueryParam, maxLength: number): string | null {
  const text = readFirst(value)?.trim() ?? "";
  return text.length > 0 && text.length <= maxLength ? text : null;
}

/** Laravel accepts only `var_[0-9a-z]+`; an invalid value is dropped, never sent to be rejected. */
function readVariant(value: RawQueryParam): string | null {
  const variant = readText(value, INVENTORY_VARIANT_FILTER_MAX_LENGTH);
  return variant !== null && VARIANT_IDENTIFIER_PATTERN.test(variant) ? variant : null;
}

function readInteger(value: RawQueryParam, minimum: number, maximum: number, fallback: number): number {
  const candidate = Number(readFirst(value));
  return Number.isInteger(candidate) && candidate >= minimum && candidate <= maximum ? candidate : fallback;
}
