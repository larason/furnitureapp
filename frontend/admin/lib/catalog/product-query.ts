import { ADMIN_PRODUCT_PATHS } from "@/lib/navigation/admin-route-paths";

/**
 * The approved `GET /api/v1/admin/products` query vocabulary. Only these
 * parameters exist in the frozen contract; no alias, sort or extra filter is
 * invented, and the field names match the Laravel request exactly.
 */
const PRODUCT_LIST_DEFAULT_PER_PAGE = 20;
const PRODUCT_LIST_MAX_PER_PAGE = 100;
export const PRODUCT_SEARCH_MAX_LENGTH = 100;
const PRODUCT_CATEGORY_MAX_LENGTH = 100;
export const PRODUCT_TYPE_VALUES = ["IN_STOCK", "MADE_TO_ORDER"] as const;
export const PRODUCT_AVAILABILITY_VALUES = ["available", "unavailable"] as const;

export type ProductTypeFilter = (typeof PRODUCT_TYPE_VALUES)[number];
export type ProductAvailabilityFilter = (typeof PRODUCT_AVAILABILITY_VALUES)[number];

export interface ProductListQuery {
  readonly search: string | null;
  readonly category: string | null;
  readonly productType: ProductTypeFilter | null;
  readonly isActive: boolean | null;
  readonly isPublished: boolean | null;
  readonly availability: ProductAvailabilityFilter | null;
  readonly page: number;
  readonly perPage: number;
}

export type ProductListSearchParams = Record<string, string | string[] | undefined>;

export function parseProductListQuery(params: ProductListSearchParams): ProductListQuery {
  return {
    search: readText(params.search, PRODUCT_SEARCH_MAX_LENGTH),
    category: readText(params.category, PRODUCT_CATEGORY_MAX_LENGTH),
    productType: readEnum(params.product_type, PRODUCT_TYPE_VALUES),
    isActive: readBoolean(params.is_active),
    isPublished: readBoolean(params.is_published),
    availability: readEnum(params.availability, PRODUCT_AVAILABILITY_VALUES),
    page: readInteger(params.page, 1, Number.MAX_SAFE_INTEGER, 1),
    perPage: readInteger(params.per_page, 1, PRODUCT_LIST_MAX_PER_PAGE, PRODUCT_LIST_DEFAULT_PER_PAGE),
  };
}

/** Builds the exact API query for the operational list request. */
export function productListRequestQuery(query: ProductListQuery): Readonly<Record<string, string | number | boolean>> {
  return compact({
    search: query.search,
    category: query.category,
    product_type: query.productType,
    is_active: query.isActive,
    is_published: query.isPublished,
    availability: query.availability,
    page: query.page,
    per_page: query.perPage,
  });
}

/** Builds a shareable admin URL, omitting default-valued parameters. */
export function productListHref(query: ProductListQuery): string {
  const params = new URLSearchParams();
  const entries = compact({
    search: query.search,
    category: query.category,
    product_type: query.productType,
    is_active: query.isActive,
    is_published: query.isPublished,
    availability: query.availability,
    page: query.page > 1 ? query.page : null,
    per_page: query.perPage === PRODUCT_LIST_DEFAULT_PER_PAGE ? null : query.perPage,
  });

  for (const [key, value] of Object.entries(entries)) params.set(key, String(value));
  const search = params.toString();
  return search.length > 0 ? `${ADMIN_PRODUCT_PATHS.list}?${search}` : ADMIN_PRODUCT_PATHS.list;
}

export function countProductFilters(query: ProductListQuery): number {
  return [query.search, query.category, query.productType, query.isActive, query.isPublished, query.availability].filter((value) => value !== null).length;
}

function compact(source: Readonly<Record<string, string | number | boolean | null>>): Readonly<Record<string, string | number | boolean>> {
  return Object.fromEntries(Object.entries(source).filter(([, value]) => value !== null)) as Record<string, string | number | boolean>;
}

function readFirst(value: string | string[] | undefined): string | null {
  if (typeof value === "string") return value;
  return Array.isArray(value) && typeof value[0] === "string" ? value[0] : null;
}

function readText(value: string | string[] | undefined, maxLength: number): string | null {
  const text = readFirst(value)?.trim() ?? "";
  return text.length > 0 && text.length <= maxLength ? text : null;
}

function readEnum<const T extends readonly string[]>(value: string | string[] | undefined, allowed: T): T[number] | null {
  const candidate = readFirst(value);
  return candidate !== null && (allowed as readonly string[]).includes(candidate) ? (candidate as T[number]) : null;
}

function readBoolean(value: string | string[] | undefined): boolean | null {
  const candidate = readFirst(value);
  if (candidate === "true") return true;
  return candidate === "false" ? false : null;
}

function readInteger(value: string | string[] | undefined, minimum: number, maximum: number, fallback: number): number {
  const candidate = Number(readFirst(value));
  return Number.isInteger(candidate) && candidate >= minimum && candidate <= maximum ? candidate : fallback;
}
