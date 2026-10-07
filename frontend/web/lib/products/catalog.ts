import type { ApiPagination, RequestFunction } from "../api/client";
import { apiRequest as defaultApiRequest } from "../api/client";
import type { ProductSummary } from "../catalog/types";
import { HOMEPAGE_PRODUCT_FIXTURES } from "../homepage/fixtures";

export type ProductCatalog = Readonly<{
  source: "api" | "fixtures";
  products: readonly ProductSummary[];
  pagination?: ApiPagination;
}>;

export async function getProductCatalog(page: number, apiRequest: RequestFunction = defaultApiRequest): Promise<ProductCatalog> {
  const source = process.env.HOMEPAGE_DATA_SOURCE ?? "api";
  if (source !== "api" && source !== "fixtures") {
    throw new Error("Invalid homepage data source.");
  }

  if (source === "fixtures") {
    return { source, products: HOMEPAGE_PRODUCT_FIXTURES };
  }

  const request = page === 1
    ? { path: "/products", cache: "no-store" as const }
    : { path: "/products", cache: "no-store" as const, query: { page } };
  const response = await apiRequest<readonly ProductSummary[]>(request);
  if (!response) {
    throw new Error("Product response is missing.");
  }

  return { source, products: response.data, pagination: response.meta?.pagination };
}
