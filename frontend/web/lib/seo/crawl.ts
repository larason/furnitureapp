import type { MetadataRoute } from "next";
import type { RequestFunction } from "../api/client";
import { apiRequest as defaultApiRequest } from "../api/client";
import { getCategoryFilterOptions } from "../category/options";
import type { ProductSummary } from "../catalog/types";
import { HOMEPAGE_PRODUCT_FIXTURES } from "../homepage/fixtures";
import { buildCanonicalUrl, getSiteOrigin, SiteUrlError } from "./site";

const SITEMAP_PAGE_SIZE = 100;
const MAX_SITEMAP_PAGES = 500;
const SEARCH_PATH = "/search";

const FACET_PARAMETERS = ["category", "product_type", "availability", "min_price", "max_price", "sort", "sort_direction"] as const;

export function buildRobotsRules(): MetadataRoute.Robots {
  const sitemap = buildCanonicalUrl("/sitemap.xml");

  return {
    rules: {
      userAgent: "*",
      allow: "/",
      disallow: [SEARCH_PATH, ...FACET_PARAMETERS.flatMap((parameter) => [`/*?${parameter}=`, `/*&${parameter}=`])],
    },
    ...(sitemap ? { sitemap } : {}),
  };
}

export async function buildSitemapEntries(apiRequest: RequestFunction = defaultApiRequest): Promise<MetadataRoute.Sitemap> {
  if (!getSiteOrigin()) {
    return [];
  }

  const [productSlugs, categorySlugs] = await Promise.all([listProductSlugs(apiRequest), listCategorySlugs(apiRequest)]);
  const paths = [
    "/",
    "/products",
    ...uniqueSorted(categorySlugs).map((slug) => `/categories/${encodeURIComponent(slug)}`),
    ...uniqueSorted(productSlugs).map((slug) => `/products/${encodeURIComponent(slug)}`),
  ];

  return dedupe(paths).map((path) => ({ url: canonicalUrl(path) }));
}

async function listProductSlugs(apiRequest: RequestFunction): Promise<readonly string[]> {
  const source = process.env.HOMEPAGE_DATA_SOURCE ?? "api";
  if (source === "fixtures") {
    return HOMEPAGE_PRODUCT_FIXTURES.map((product) => product.slug);
  }
  if (source !== "api") {
    throw new Error("Invalid homepage data source.");
  }

  const firstPage = await fetchProductPage(apiRequest, 1);
  const lastPage = firstPage.meta?.pagination?.last_page ?? 1;
  if (lastPage > MAX_SITEMAP_PAGES) {
    throw new Error("Catalog exceeds the safe sitemap page limit.");
  }

  const remainingPages = await Promise.all(
    Array.from({ length: lastPage - 1 }, (_, index) => index + 2).map((page) => fetchProductPage(apiRequest, page)),
  );
  const products: ProductSummary[] = [firstPage, ...remainingPages].flatMap((response) => response.data);

  return products.map((product) => product.slug);
}

async function fetchProductPage(apiRequest: RequestFunction, page: number) {
  const response = await apiRequest<readonly ProductSummary[]>({
    path: "/products",
    cache: "no-store",
    query: { per_page: SITEMAP_PAGE_SIZE, page },
  });
  if (!response) {
    throw new Error("Sitemap product response is missing.");
  }
  return response;
}

async function listCategorySlugs(apiRequest: RequestFunction): Promise<readonly string[]> {
  const categories = await getCategoryFilterOptions(apiRequest);
  return categories.map((category) => category.slug);
}

function canonicalUrl(path: string): string {
  const url = buildCanonicalUrl(path);
  if (!url) {
    throw new SiteUrlError("SITE_URL must be configured to build canonical sitemap URLs.");
  }
  return url;
}

function compareAlphabetically(left: string, right: string): number {
  return left.localeCompare(right);
}

function uniqueSorted(values: readonly string[]): readonly string[] {
  return [...new Set(values.filter((value) => value.length > 0))].sort(compareAlphabetically);
}

function dedupe(paths: readonly string[]): readonly string[] {
  const seen = new Set<string>();
  return paths.filter((path) => {
    if (seen.has(path)) {
      return false;
    }
    seen.add(path);
    return true;
  });
}
