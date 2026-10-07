export type Money = Readonly<{ amount: number; currency: "TZS" }>;

export type ProductCardData = Readonly<{
  name: string;
  product_type: "IN_STOCK" | "MADE_TO_ORDER";
  price: Money;
  primary_image: Readonly<{ url: string; alt_text: string | null }> | null;
  availability: "available" | "unavailable";
}>;

export type ProductSummary = ProductCardData & Readonly<{
  id: string;
  slug: string;
}>;

export type CategorySummary = Readonly<{
  id: string;
  name: string;
  slug: string;
  image: Readonly<{ url: string }> | null;
}>;

export type CategoryDetail = CategorySummary & Readonly<{
  description: string | null;
}>;

export type ProductImage = Readonly<{ id: string; url: string; alt_text: string | null; sort_order: number; is_primary: boolean }>;
export type ProductVariant = Readonly<{ id: string; sku: string; name: string; price: Money; availability: "available" | "unavailable"; stock_indicator: "IN_STOCK" | "LOW_STOCK" | "MADE_TO_ORDER" }>;
export type ProductDetail = ProductSummary & Readonly<{ description: string | null; category: CategoryDetail; images: readonly ProductImage[]; variants: readonly ProductVariant[]; stock_indicator: "IN_STOCK" | "LOW_STOCK" | "MADE_TO_ORDER"; created_at: string; updated_at: string }>;
