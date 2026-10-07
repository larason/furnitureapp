import type { RequestFunction } from "../api/client";
import { apiRequest as defaultApiRequest } from "../api/client";
import type { CategorySummary, ProductSummary } from "../catalog/types";
import { HOMEPAGE_CATEGORY_FIXTURES, HOMEPAGE_PRODUCT_FIXTURES } from "./fixtures";

export async function getHomepageCatalog(apiRequest: RequestFunction = defaultApiRequest) {
  const source = process.env.HOMEPAGE_DATA_SOURCE ?? "api";
  if (source !== "api" && source !== "fixtures") {
    throw new Error("Invalid homepage data source.");
  }

  if (source === "fixtures") {
    return { source, categories: HOMEPAGE_CATEGORY_FIXTURES, products: HOMEPAGE_PRODUCT_FIXTURES };
  }

  const [categories, products] = await Promise.all([
    apiRequest<readonly CategorySummary[]>({ path: "/categories", cache: "no-store" }),
    apiRequest<readonly ProductSummary[]>({
      path: "/products", cache: "no-store",
      query: { product_type: "MADE_TO_ORDER", per_page: 3, sort: "created_at", sort_direction: "desc" },
    }),
  ]);

  if (!categories || !products) {
    throw new Error("Catalog response is missing.");
  }

  return { source, categories: categories.data.slice(0, 4), products: products.data };
}

export type HomepageCatalog = Awaited<ReturnType<typeof getHomepageCatalog>>;
