import type { ApiPagination } from "@/lib/api/types";

/**
 * Defensive parsers for the operational product resource. The frontend never
 * reconstructs product data: it either reads a Laravel-returned value or reports
 * the response as unusable rather than rendering a guessed one.
 */
export interface ProductPrice {
  readonly amount: number;
  readonly currency: string;
}

export interface ProductCategoryReference {
  readonly id: string;
  readonly slug: string;
  readonly name: string;
  readonly description: string | null;
}

export interface ProductImageSummary {
  readonly id: string;
  readonly url: string;
  readonly altText: string | null;
}

export interface ProductImageDetail extends ProductImageSummary {
  readonly sortOrder: number;
  readonly isPrimary: boolean;
}

export interface ProductVariantSummary {
  readonly id: string;
  readonly sku: string;
  readonly name: string;
  readonly price: ProductPrice;
  readonly availability: string;
  readonly stockIndicator: string;
}

export interface OperationalProduct {
  readonly id: string;
  readonly name: string;
  readonly slug: string;
  readonly productType: string;
  readonly price: ProductPrice;
  readonly category: ProductCategoryReference;
  readonly primaryImage: ProductImageSummary | null;
  readonly availability: string;
  readonly stockIndicator: string;
  readonly description: string | null;
  readonly images: readonly ProductImageDetail[];
  readonly variants: readonly ProductVariantSummary[];
  readonly createdAt: string | null;
  readonly updatedAt: string | null;
  readonly isActive: boolean | null;
  readonly isPublished: boolean | null;
}

export interface OperationalProductPage {
  readonly products: readonly OperationalProduct[];
  readonly pagination: ApiPagination;
}

/** Raised when a response cannot be read as the documented product resource. */
export class ProductResourceError extends Error {
  constructor() {
    super("The product response did not match the documented resource.");
    this.name = "ProductResourceError";
  }
}

export function parseOperationalProduct(value: unknown): OperationalProduct {
  if (!isRecord(value)) throw new ProductResourceError();
  const id = readString(value.id);
  const name = readString(value.name);
  const slug = readString(value.slug);
  const productType = readString(value.product_type);
  const price = parsePrice(value.price);
  const category = parseCategory(value.category);

  if (id === null || name === null || slug === null || productType === null || price === null || category === null) {
    throw new ProductResourceError();
  }

  return {
    id,
    name,
    slug,
    productType,
    price,
    category,
    primaryImage: parseImageSummary(value.primary_image),
    availability: readString(value.availability) ?? UNKNOWN_VALUE,
    stockIndicator: readString(value.stock_indicator) ?? UNKNOWN_VALUE,
    description: typeof value.description === "string" ? value.description : null,
    images: parseImageDetails(value.images),
    variants: parseVariants(value.variants),
    createdAt: readString(value.created_at),
    updatedAt: readString(value.updated_at),
    isActive: readBoolean(value.is_active),
    isPublished: readBoolean(value.is_published),
  };
}

function parseOperationalProductList(value: unknown): readonly OperationalProduct[] {
  if (!Array.isArray(value)) throw new ProductResourceError();
  return value.map(parseOperationalProduct);
}

export function parseProductPage(value: unknown): OperationalProductPage {
  if (!isRecord(value)) throw new ProductResourceError();
  return { products: parseOperationalProductList(value.data), pagination: parsePagination(value.meta) };
}

function parsePagination(meta: unknown): ApiPagination {
  if (!isRecord(meta) || !isRecord(meta.pagination)) throw new ProductResourceError();
  const source = meta.pagination;
  const currentPage = readPositiveInteger(source.current_page);
  const perPage = readPositiveInteger(source.per_page);
  const total = readNonNegativeInteger(source.total);
  const lastPage = readPositiveInteger(source.last_page);

  if (currentPage === null || perPage === null || total === null || lastPage === null) {
    throw new ProductResourceError();
  }

  return {
    current_page: currentPage,
    per_page: perPage,
    total,
    last_page: lastPage,
    has_next: source.has_next === true,
    has_previous: source.has_previous === true,
  };
}

const UNKNOWN_VALUE = "UNKNOWN";

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function readString(value: unknown): string | null {
  return typeof value === "string" && value.trim().length > 0 ? value : null;
}

function readBoolean(value: unknown): boolean | null {
  return typeof value === "boolean" ? value : null;
}

function readPositiveInteger(value: unknown): number | null {
  return typeof value === "number" && Number.isInteger(value) && value >= 1 ? value : null;
}

function readNonNegativeInteger(value: unknown): number | null {
  return typeof value === "number" && Number.isInteger(value) && value >= 0 ? value : null;
}

function parsePrice(value: unknown): ProductPrice | null {
  if (!isRecord(value)) return null;
  const amount = readNonNegativeInteger(value.amount);
  const currency = readString(value.currency);
  return amount === null || currency === null ? null : { amount, currency };
}

function parseCategory(value: unknown): ProductCategoryReference | null {
  if (!isRecord(value)) return null;
  const id = readString(value.id);
  const slug = readString(value.slug);
  const name = readString(value.name);
  if (id === null || slug === null || name === null) return null;
  return { id, slug, name, description: typeof value.description === "string" ? value.description : null };
}

function parseImageSummary(value: unknown): ProductImageSummary | null {
  if (!isRecord(value)) return null;
  const id = readString(value.id);
  const url = readString(value.url);
  if (id === null || url === null) return null;
  return { id, url, altText: typeof value.alt_text === "string" ? value.alt_text : null };
}

/**
 * The gallery is always sent by the operational detail resource, so a response
 * without a readable array — or with an unreadable entry in it — is a broken
 * response, not an empty gallery. Reconstructing one would hide the regression and
 * leave the page offering media operations over incomplete data.
 */
function parseImageDetails(value: unknown): readonly ProductImageDetail[] {
  if (!Array.isArray(value)) throw new ProductResourceError();
  return value.map((entry) => {
    const summary = parseImageSummary(entry);
    if (summary === null) throw new ProductResourceError();
    return {
      ...summary,
      sortOrder: readNonNegativeInteger(isRecord(entry) ? entry.sort_order : null) ?? 0,
      isPrimary: isRecord(entry) && entry.is_primary === true,
    };
  });
}

function parseVariants(value: unknown): readonly ProductVariantSummary[] {
  if (!Array.isArray(value)) return [];
  return value.flatMap((entry) => {
    if (!isRecord(entry)) return [];
    const id = readString(entry.id);
    const sku = readString(entry.sku);
    const name = readString(entry.name);
    const price = parsePrice(entry.price);
    if (id === null || sku === null || name === null || price === null) return [];
    return [{
      id,
      sku,
      name,
      price,
      availability: readString(entry.availability) ?? UNKNOWN_VALUE,
      stockIndicator: readString(entry.stock_indicator) ?? UNKNOWN_VALUE,
    }];
  });
}
