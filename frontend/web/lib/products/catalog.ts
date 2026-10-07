import type { ApiPagination, RequestFunction } from "../api/client";
import { apiRequest as defaultApiRequest } from "../api/client";
import type { ProductSummary } from "../catalog/types";
import type { ProductCollectionQuery } from "../catalog/filters";
import { HOMEPAGE_PRODUCT_FIXTURES } from "../homepage/fixtures";

export type ProductCatalog = Readonly<{
  source: "api" | "fixtures";
  products: readonly ProductSummary[];
  pagination?: ApiPagination;
}>;

export type ProductCatalogQuery = ProductCollectionQuery;

export async function getProductCatalog(query: ProductCatalogQuery = {}, apiRequest: RequestFunction = defaultApiRequest): Promise<ProductCatalog> {
  const source = process.env.HOMEPAGE_DATA_SOURCE ?? "api";
  if (source !== "api" && source !== "fixtures") {
    throw new Error("Invalid homepage data source.");
  }

  if (source === "fixtures") {
    return fixtureCatalog(query);
  }

  const request = Object.keys(query).length === 0
    ? { path: "/products", cache: "no-store" as const }
    : { path: "/products", cache: "no-store" as const, query };
  const response = await apiRequest<readonly ProductSummary[]>(request);
  if (!response) {
    throw new Error("Product response is missing.");
  }

  return { source, products: response.data, pagination: response.meta?.pagination };
}

function fixtureCatalog(query: ProductCatalogQuery): ProductCatalog {
  const search = typeof query.search === "string" ? query.search.trim().toLocaleLowerCase() : "";
  const category = typeof query.category === "string" ? query.category : "";
  const productType = typeof query.product_type === "string" ? query.product_type : "";
  const availability = typeof query.availability === "string" ? query.availability : "";
  const minPrice = parseFixturePrice(query.min_price);
  const maxPrice = parseFixturePrice(query.max_price);
  const products = HOMEPAGE_PRODUCT_FIXTURES.filter((product) => (
    (!search || product.name.toLocaleLowerCase().includes(search)) &&
    (!category || category === "living-room") &&
    (!productType || product.product_type === productType) &&
    (!availability || product.availability === availability) &&
    (minPrice === null || product.price.amount >= minPrice) &&
    (maxPrice === null || product.price.amount <= maxPrice)
  )).toSorted((left, right) => fixtureComparison(left, right, query));
  const page = typeof query.page === "string" && /^[1-9]\d*$/.test(query.page) ? Number(query.page) : 1;
  const perPage = 20;
  const currentPage = Number.isSafeInteger(page) ? page : 1;
  const total = products.length;
  const lastPage = Math.max(1, Math.ceil(total / perPage));
  return { source: "fixtures", products: products.slice((currentPage - 1) * perPage, currentPage * perPage), pagination: { current_page: currentPage, per_page: perPage, total, last_page: lastPage, has_next: currentPage < lastPage, has_previous: currentPage > 1 } };
}

function parseFixturePrice(value: ProductCollectionQuery["min_price"]): number | null {
  return typeof value === "string" && /^\d+$/.test(value) && Number.isSafeInteger(Number(value)) ? Number(value) : null;
}

function fixtureComparison(left: ProductSummary, right: ProductSummary, query: ProductCatalogQuery): number {
  const direction = query.sort_direction === "asc" ? 1 : -1;
  if (query.sort === "price") return (left.price.amount - right.price.amount) * direction;
  if (query.sort === "name") return left.name.localeCompare(right.name) * direction;
  return left.id.localeCompare(right.id);
}
