import { ADMIN_ROUTE_PATHS } from "@/lib/navigation/admin-route-paths";
import { isRequestStatusValue } from "@/lib/requests/request-mutations";

/**
 * The approved `GET /api/v1/requests` query vocabulary (REQ-004). Laravel accepts
 * exactly `search`, `request_status`, `product_id`, `created_from`, `created_to`,
 * `page` and `per_page` and rejects any unknown parameter, so no other filter is
 * invented or emitted. The backend order is fixed (`created_at DESC, id ASC`) and
 * there is no `sort` parameter.
 */
export const REQUEST_LIST_DEFAULT_PER_PAGE = 20;
const REQUEST_LIST_MAX_PER_PAGE = 100;
export const REQUEST_SEARCH_MAX_LENGTH = 100;
export const REQUEST_PRODUCT_FILTER_MAX_LENGTH = 100;

export interface RequestListQuery {
  readonly search: string | null;
  readonly requestStatus: string | null;
  /** Exact opaque `prod_…` identifier; never a product-name search. */
  readonly productId: string | null;
  /** A `YYYY-MM-DD` UTC day; converted to explicit UTC bounds only for the API. */
  readonly createdFrom: string | null;
  readonly createdTo: string | null;
  readonly page: number;
  readonly perPage: number;
}

/** A raw, unvalidated `searchParams` entry as Next.js delivers it. */
type RawQueryParam = string | string[] | undefined;

type QueryParamValue = string | number;

type QueryParamRecord = Readonly<Record<string, QueryParamValue>>;

export type RequestListSearchParams = Record<string, RawQueryParam>;

const DATE_ONLY_PATTERN = /^\d{4}-\d{2}-\d{2}$/;

export function parseRequestListQuery(params: RequestListSearchParams): RequestListQuery {
  return {
    search: readText(params.search, REQUEST_SEARCH_MAX_LENGTH),
    requestStatus: readStatus(params.request_status),
    productId: readText(params.product_id, REQUEST_PRODUCT_FILTER_MAX_LENGTH),
    createdFrom: readDay(params.created_from),
    createdTo: readDay(params.created_to),
    page: readInteger(params.page, 1, Number.MAX_SAFE_INTEGER, 1),
    perPage: readInteger(params.per_page, 1, REQUEST_LIST_MAX_PER_PAGE, REQUEST_LIST_DEFAULT_PER_PAGE),
  };
}

/**
 * Builds the exact API query. The date-only filters become explicit inclusive UTC
 * bounds (`T00:00:00Z` / `T23:59:59Z`) so the boundary behaviour is unambiguous.
 */
export function requestListRequestQuery(query: RequestListQuery): QueryParamRecord {
  return compact({
    search: query.search,
    request_status: query.requestStatus,
    product_id: query.productId,
    created_from: query.createdFrom === null ? null : `${query.createdFrom}T00:00:00Z`,
    created_to: query.createdTo === null ? null : `${query.createdTo}T23:59:59Z`,
    page: query.page,
    per_page: query.perPage,
  });
}

/** Builds a shareable admin URL, omitting default-valued parameters. */
export function requestListHref(query: RequestListQuery): string {
  const params = new URLSearchParams();
  const entries = compact({
    search: query.search,
    request_status: query.requestStatus,
    product_id: query.productId,
    created_from: query.createdFrom,
    created_to: query.createdTo,
    page: query.page > 1 ? query.page : null,
    per_page: query.perPage === REQUEST_LIST_DEFAULT_PER_PAGE ? null : query.perPage,
  });

  for (const [key, value] of Object.entries(entries)) params.set(key, String(value));
  const search = params.toString();
  return search.length > 0 ? `${ADMIN_ROUTE_PATHS.requests}?${search}` : ADMIN_ROUTE_PATHS.requests;
}

export function countRequestFilters(query: RequestListQuery): number {
  return [query.search, query.requestStatus, query.productId, query.createdFrom, query.createdTo].filter(
    (value) => value !== null,
  ).length;
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

function readStatus(value: RawQueryParam): string | null {
  const status = readFirst(value)?.trim() ?? "";
  return isRequestStatusValue(status) ? status : null;
}

/** Accepts only a real `YYYY-MM-DD` day; anything else degrades to no bound. */
function readDay(value: RawQueryParam): string | null {
  const text = readFirst(value)?.trim() ?? "";
  return DATE_ONLY_PATTERN.test(text) && isRealUtcDay(text) ? text : null;
}

function isRealUtcDay(text: string): boolean {
  const [year, month, day] = text.split("-").map(Number);
  const date = new Date(Date.UTC(year, month - 1, day));
  return date.getUTCFullYear() === year && date.getUTCMonth() === month - 1 && date.getUTCDate() === day;
}

function readInteger(value: RawQueryParam, minimum: number, maximum: number, fallback: number): number {
  const candidate = Number(readFirst(value));
  return Number.isInteger(candidate) && candidate >= minimum && candidate <= maximum ? candidate : fallback;
}
