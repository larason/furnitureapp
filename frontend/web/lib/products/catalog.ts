import type { ApiPagination, RequestFunction } from "../api/client";
import { apiRequest as defaultApiRequest } from "../api/client";
import type { ProductSummary } from "../catalog/types";
import { HOMEPAGE_PRODUCT_FIXTURES } from "../homepage/fixtures";

export type ProductCatalog = Readonly<{
  source: "api" | "fixtures";
  products: readonly ProductSummary[];
  pagination?: ApiPagination;
}>;

export type ProductCatalogQuery = Readonly<{ search?: string }>;

export async function getProductCatalog(page: number, apiRequest: RequestFunction = defaultApiRequest, query: ProductCatalogQuery = {}): Promise<ProductCatalog> {
  const source = process.env.HOMEPAGE_DATA_SOURCE ?? "api";
  if (source !== "api" && source !== "fixtures") {
    throw new Error("Invalid homepage data source.");
  }

  if (source === "fixtures") {
    return { source, products: fixtureProducts(query.search) };
  }

  const search = query.search?.trim();
  const requestQuery = { ...(search ? { search } : {}), ...(page === 1 ? {} : { page }) };
  const request = Object.keys(requestQuery).length === 0
    ? { path: "/products", cache: "no-store" as const }
    : { path: "/products", cache: "no-store" as const, query: requestQuery };
  const response = await apiRequest<readonly ProductSummary[]>(request);
  if (!response) {
    throw new Error("Product response is missing.");
  }

  return { source, products: response.data, pagination: response.meta?.pagination };
}

function fixtureProducts(search: string | undefined): readonly ProductSummary[] {
  const term = search?.trim().toLocaleLowerCase();
  if (!term) {
    return HOMEPAGE_PRODUCT_FIXTURES;
  }

  return HOMEPAGE_PRODUCT_FIXTURES.filter((product) => product.name.toLocaleLowerCase().includes(term));
}
