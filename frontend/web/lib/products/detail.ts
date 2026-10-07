import { apiRequest } from "../api/client";
import type { ProductDetail } from "../catalog/types";
import { HOMEPAGE_PRODUCT_DETAIL_FIXTURES } from "../homepage/fixtures";

export type ProductDetailSource = Readonly<{ source: "api" | "fixtures"; product: ProductDetail }>;

export async function getProductDetail(slug: string): Promise<ProductDetailSource | null> {
  const source = process.env.HOMEPAGE_DATA_SOURCE ?? "api";
  if (source !== "api" && source !== "fixtures") {
    throw new Error("Invalid homepage data source.");
  }
  if (source === "fixtures") {
    const product = HOMEPAGE_PRODUCT_DETAIL_FIXTURES[slug];
    return product ? { source, product } : null;
  }

  const response = await apiRequest<ProductDetail>({ path: `products/${encodeURIComponent(slug)}`, cache: "no-store" });
  if (!response) {
    throw new Error("Product detail response is missing.");
  }
  return { source, product: response.data };
}
