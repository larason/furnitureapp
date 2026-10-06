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
