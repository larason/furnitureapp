import { ADMIN_CATEGORY_PATHS } from "@/lib/navigation/admin-route-paths";

/**
 * The `GET /api/v1/categories` collection supports only the shared pagination
 * vocabulary (`page`, `per_page`). No search, sort or state filter exists in the
 * contract, so none is offered and none is inferred.
 */
const CATEGORY_LIST_DEFAULT_PER_PAGE = 20;
const CATEGORY_LIST_MAX_PER_PAGE = 100;

export interface CategoryListQuery {
  readonly page: number;
  readonly perPage: number;
}

export type CategoryListSearchParams = Record<string, string | string[] | undefined>;

export function parseCategoryListQuery(params: CategoryListSearchParams): CategoryListQuery {
  return {
    page: readInteger(params.page, 1, Number.MAX_SAFE_INTEGER, 1),
    perPage: readInteger(params.per_page, 1, CATEGORY_LIST_MAX_PER_PAGE, CATEGORY_LIST_DEFAULT_PER_PAGE),
  };
}

/** Builds the exact API query for the category collection request. */
export function categoryListRequestQuery(query: CategoryListQuery): Readonly<Record<string, string | number>> {
  return { page: query.page, per_page: query.perPage };
}

/** Builds a shareable admin URL, omitting default-valued parameters. */
export function categoryListHref(query: CategoryListQuery): string {
  const params = new URLSearchParams();
  if (query.page > 1) params.set("page", String(query.page));
  if (query.perPage !== CATEGORY_LIST_DEFAULT_PER_PAGE) params.set("per_page", String(query.perPage));
  const search = params.toString();
  return search.length > 0 ? `${ADMIN_CATEGORY_PATHS.list}?${search}` : ADMIN_CATEGORY_PATHS.list;
}

function readFirst(value: string | string[] | undefined): string | null {
  if (typeof value === "string") return value;
  return Array.isArray(value) && typeof value[0] === "string" ? value[0] : null;
}

function readInteger(value: string | string[] | undefined, minimum: number, maximum: number, fallback: number): number {
  const candidate = Number(readFirst(value));
  return Number.isInteger(candidate) && candidate >= minimum && candidate <= maximum ? candidate : fallback;
}
