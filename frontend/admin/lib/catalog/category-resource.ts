import type { ApiPagination } from "@/lib/api/types";

/**
 * Defensive parsers for the category resource (`CAT-003` summary / `CAT-004`
 * detail). The public collection exposes active top-level categories only, so
 * the parsed shape carries no hierarchy or state — those fields are absent from
 * the contract and are never reconstructed here.
 */
export interface CategoryImage {
  readonly url: string;
}

export interface CategorySummary {
  readonly id: string;
  readonly name: string;
  readonly slug: string;
  readonly image: CategoryImage | null;
}

export interface CategoryDetail extends CategorySummary {
  readonly description: string | null;
  readonly createdAt: string | null;
}

export interface CategoryPage {
  readonly categories: readonly CategorySummary[];
  readonly pagination: ApiPagination;
}

/** Raised when a response cannot be read as the documented category resource. */
export class CategoryResourceError extends Error {
  constructor() {
    super("The category response did not match the documented resource.");
    this.name = "CategoryResourceError";
  }
}

export function parseCategoryPage(value: unknown): CategoryPage {
  if (!isRecord(value)) throw new CategoryResourceError();
  return { categories: parseCategoryList(value.data), pagination: parsePagination(value.meta) };
}

export function parseCategoryDetail(value: unknown): CategoryDetail {
  const summary = parseCategorySummary(value);
  if (!isRecord(value)) throw new CategoryResourceError();
  return {
    ...summary,
    description: typeof value.description === "string" ? value.description : null,
    createdAt: readString(value.created_at),
  };
}

function parseCategoryList(value: unknown): readonly CategorySummary[] {
  if (!Array.isArray(value)) throw new CategoryResourceError();
  return value.map(parseCategorySummary);
}

function parseCategorySummary(value: unknown): CategorySummary {
  if (!isRecord(value)) throw new CategoryResourceError();
  const id = readString(value.id);
  const name = readString(value.name);
  const slug = readString(value.slug);

  if (id === null || name === null || slug === null) throw new CategoryResourceError();

  return { id, name, slug, image: parseImage(value.image) };
}

function parseImage(value: unknown): CategoryImage | null {
  if (!isRecord(value)) return null;
  const url = readString(value.url);
  return url === null ? null : { url };
}

function parsePagination(meta: unknown): ApiPagination {
  if (!isRecord(meta) || !isRecord(meta.pagination)) throw new CategoryResourceError();
  const source = meta.pagination;
  const currentPage = readPositiveInteger(source.current_page);
  const perPage = readPositiveInteger(source.per_page);
  const total = readNonNegativeInteger(source.total);
  const lastPage = readPositiveInteger(source.last_page);

  if (currentPage === null || perPage === null || total === null || lastPage === null) throw new CategoryResourceError();

  return {
    current_page: currentPage,
    per_page: perPage,
    total,
    last_page: lastPage,
    has_next: source.has_next === true,
    has_previous: source.has_previous === true,
  };
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function readString(value: unknown): string | null {
  return typeof value === "string" && value.trim().length > 0 ? value : null;
}

function readPositiveInteger(value: unknown): number | null {
  return typeof value === "number" && Number.isInteger(value) && value >= 1 ? value : null;
}

function readNonNegativeInteger(value: unknown): number | null {
  return typeof value === "number" && Number.isInteger(value) && value >= 0 ? value : null;
}
