import type { ApiPagination } from "../api/client";
import { apiRequest } from "../api/client";
import type { CategoryDetail, ProductSummary } from "../catalog/types";
import {
  FIXTURE_PRODUCTS_BY_CATEGORY_SLUG,
  HOMEPAGE_CATEGORY_FIXTURES,
} from "../homepage/fixtures";

export type CategoryCatalog = Readonly<{
  source: "api" | "fixtures";
  category: CategoryDetail;
  products: readonly ProductSummary[];
  pagination?: ApiPagination;
}>;

export async function getCategoryCatalog(slug: string, page: number): Promise<CategoryCatalog | null> {
  const source = process.env.HOMEPAGE_DATA_SOURCE ?? "api";
  if (source !== "api" && source !== "fixtures") {
    throw new Error("Invalid homepage data source.");
  }

  if (source === "fixtures") {
    const category = HOMEPAGE_CATEGORY_FIXTURES.find((item) => item.slug === slug);
    if (!category) {
      return null;
    }
    return {
      source,
      category: { ...category, description: null },
      products: FIXTURE_PRODUCTS_BY_CATEGORY_SLUG[category.slug] ?? [],
    };
  }

  const category = await apiRequest<CategoryDetail>({ path: `/categories/${slug}`, cache: "no-store" });
  if (!category) {
    throw new Error("Category response is missing.");
  }
  const products = await apiRequest<readonly ProductSummary[]>({
    path: "/products",
    cache: "no-store",
    query: page === 1 ? { category: category.data.slug } : { category: category.data.slug, page },
  });
  if (!products) {
    throw new Error("Category product response is missing.");
  }

  return {
    source,
    category: category.data,
    products: products.data,
    pagination: products.meta?.pagination,
  };
}
