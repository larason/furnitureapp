import { ApiError } from "../api/client";

export type ProductCollectionQueryValue = string | readonly string[];

export type ProductCollectionQuery = Readonly<{
  search?: ProductCollectionQueryValue;
  category?: ProductCollectionQueryValue;
  product_type?: ProductCollectionQueryValue;
  availability?: ProductCollectionQueryValue;
  min_price?: ProductCollectionQueryValue;
  max_price?: ProductCollectionQueryValue;
  sort?: ProductCollectionQueryValue;
  sort_direction?: ProductCollectionQueryValue;
  page?: ProductCollectionQueryValue;
}>;

export type ProductCollectionSearchParams = Readonly<Record<string, string | readonly string[] | undefined>>;

export const PRODUCT_TYPES = ["IN_STOCK", "MADE_TO_ORDER"] as const;
export const AVAILABILITY_VALUES = ["available", "unavailable"] as const;
export const SORT_FIELDS = ["created_at", "price", "name"] as const;
export const SORT_DIRECTIONS = ["asc", "desc"] as const;

export const SEARCH_QUERY_MAX_LENGTH = 100;

const QUERY_KEYS = ["search", "category", "product_type", "availability", "min_price", "max_price", "sort", "sort_direction", "page"] as const;
const WHOLE_TZS = /^\d+$/;
const MAX_SAFE_MINOR_UNITS = String(Number.MAX_SAFE_INTEGER);

export function parseProductCollectionQuery(searchParams: ProductCollectionSearchParams): ProductCollectionQuery {
  const query: Record<string, ProductCollectionQueryValue> = {};
  for (const key of QUERY_KEYS) {
    const value = searchParams[key];
    if (value !== undefined) query[key] = value;
  }
  return query;
}

export function serializeProductCollectionQuery(query: ProductCollectionQuery): string {
  const params = new URLSearchParams();
  for (const key of QUERY_KEYS) {
    const value = query[key];
    if (value === undefined || (key === "page" && isPageOne(value)) || isDefaultSort(query, key)) continue;
    appendValue(params, key, value);
  }
  return params.toString();
}

export function productCollectionHref(pathname: "/products" | "/search", query: ProductCollectionQuery, page?: number): string {
  const nextQuery = { ...query, ...resolvePageUpdate(query.page, page) };
  const serialized = serializeProductCollectionQuery(nextQuery);
  return serialized ? `${pathname}?${serialized}` : pathname;
}

export function priceTzsToMinorUnits(value: string): string | null {
  if (!WHOLE_TZS.test(value)) return null;
  const minorUnits = `${value.replace(/^0+(?=\d)/, "")}00`.replace(/^0+(?=\d)/, "");
  return isSafeMinorUnitValue(minorUnits) ? minorUnits : null;
}

export function toFilterControlValues(query: ProductCollectionQuery) {
  const minPriceTzs = minorUnitsToWholeTzs(singleValue(query.min_price));
  const maxPriceTzs = minorUnitsToWholeTzs(singleValue(query.max_price));
  const sort = validValue(singleValue(query.sort), SORT_FIELDS);
  const sortDirection = validValue(singleValue(query.sort_direction), SORT_DIRECTIONS);

  return {
    category: singleValue(query.category) ?? "",
    productType: validValue(singleValue(query.product_type), PRODUCT_TYPES) ?? "",
    availability: validValue(singleValue(query.availability), AVAILABILITY_VALUES) ?? "",
    sort: sort === "created_at" && sortDirection === "desc" ? "newest" : sort ?? "",
    sortDirection: sort === "created_at" && sortDirection === "desc" ? "" : sortDirection ?? "",
    minPriceTzs: minPriceTzs ?? "",
    maxPriceTzs: maxPriceTzs ?? "",
  };
}

export function isMeaningfulSearch(query: ProductCollectionQuery): boolean {
  const search = singleValue(query.search);
  return search !== undefined ? search.trim().length > 0 : Array.isArray(query.search);
}

export function singleValue(value: ProductCollectionQueryValue | undefined): string | undefined {
  return typeof value === "string" ? value : undefined;
}

export function collectionPage(query: ProductCollectionQuery): number {
  const value = singleValue(query.page);
  return value !== undefined && /^[1-9]\d*$/.test(value) && Number.isSafeInteger(Number(value)) ? Number(value) : 1;
}

function appendValue(params: URLSearchParams, key: string, value: ProductCollectionQueryValue) {
  if (typeof value !== "string") {
    for (const item of value) params.append(key, item);
  } else {
    params.set(key, value);
  }
}

export function isSearchValidationError(error: unknown): error is ApiError {
  return error instanceof ApiError && error.status === 422 && error.errors.some((item) => item.field === "search");
}

function isDefaultSort(query: ProductCollectionQuery, key: string): boolean {
  return (key === "sort" || key === "sort_direction") && query.sort === "created_at" && query.sort_direction === "desc";
}

function isPageOne(value: ProductCollectionQueryValue | undefined): boolean {
  return value === "1";
}

function resolvePageUpdate(currentPage: ProductCollectionQueryValue | undefined, page: number | undefined): ProductCollectionQuery {
  if (page === undefined) {
    return isPageOne(currentPage) ? { page: undefined } : {};
  }
  if (page === 1) {
    return { page: undefined };
  }
  return { page: String(page) };
}

function validValue<T extends readonly string[]>(value: string | undefined, values: T): T[number] | undefined {
  return value !== undefined && (values as readonly string[]).includes(value) ? value as T[number] : undefined;
}

function minorUnitsToWholeTzs(value: string | undefined): string | null {
  if (!value || !WHOLE_TZS.test(value)) return null;
  if (!isSafeMinorUnitValue(value) || !value.endsWith("00")) return null;
  return value.slice(0, -2).replace(/^0+(?=\d)/, "");
}

function isSafeMinorUnitValue(value: string): boolean {
  return value.length < MAX_SAFE_MINOR_UNITS.length || (value.length === MAX_SAFE_MINOR_UNITS.length && value <= MAX_SAFE_MINOR_UNITS);
}
