import type { RequestFunction } from "../api/client";
import { apiRequest as defaultApiRequest } from "../api/client";
import type { CategorySummary } from "../catalog/types";
import { HOMEPAGE_CATEGORY_FIXTURES } from "../homepage/fixtures";

export async function getCategoryFilterOptions(apiRequest: RequestFunction = defaultApiRequest): Promise<readonly CategorySummary[]> {
  const source = process.env.HOMEPAGE_DATA_SOURCE ?? "api";
  if (source !== "api" && source !== "fixtures") throw new Error("Invalid homepage data source.");
  if (source === "fixtures") return HOMEPAGE_CATEGORY_FIXTURES;

  const firstPage = await apiRequest<readonly CategorySummary[]>({ path: "/categories", cache: "no-store" });
  if (!firstPage) throw new Error("Category response is missing.");

  const categories = [...firstPage.data];
  const lastPage = firstPage.meta?.pagination?.last_page ?? 1;
  for (let page = 2; page <= lastPage; page += 1) {
    const response = await apiRequest<readonly CategorySummary[]>({ path: "/categories", cache: "no-store", query: { page } });
    if (!response) throw new Error("Category response is missing.");
    categories.push(...response.data);
  }
  return categories;
}
