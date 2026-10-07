import type { CategorySummary, ProductDetail, ProductSummary } from "../catalog/types";

const fixtureRoot = "/furnitures/fixtures";

export const HOMEPAGE_MEDIA = {
  hero: {
    url: `${fixtureRoot}/hero-image/hero-image.jpg`,
    alt: "A timber-framed bed with an upholstered headboard, matching bedside tables and wooden storage furniture",
  },
  editorial: {
    url: `${fixtureRoot}/editorial/made-to-order.jpg`,
    alt: "A light grey corner sofa and round wooden coffee table in a sunlit living room",
  },
} as const;

export const HOMEPAGE_CATEGORY_FIXTURES: readonly CategorySummary[] = [
  { id: "fixture_living", name: "Living Room", slug: "living-room", image: { url: `${fixtureRoot}/categories/living-room.jpg` } },
  { id: "fixture_bedroom", name: "Bedroom", slug: "bedroom", image: { url: `${fixtureRoot}/categories/bedroom.jpg` } },
  { id: "fixture_dining", name: "Dining Room & Kitchen", slug: "dining-room-kitchen", image: { url: `${fixtureRoot}/categories/dining.jpg` } },
  { id: "fixture_office", name: "Home Office & Corporate Workspaces", slug: "home-office-corporate-workspaces", image: { url: `${fixtureRoot}/categories/office.jpg` } },
];

export const HOMEPAGE_PRODUCT_FIXTURES: readonly ProductSummary[] = [
  {
    id: "fixture_armchair", slug: "fixture-open-frame-armchair", name: "lounge chair",
    price: { amount: 10000000, currency: "TZS" }, product_type: "MADE_TO_ORDER", availability: "available", stock_indicator: "MADE_TO_ORDER",
    primary_image: { url: `${fixtureRoot}/products/lounge-chair.jpg`, alt_text: "An open wooden armchair with cream seat and back cushions" },
  },
  {
    id: "fixture_sofa", slug: "fixture-soft-two-seat-sofa", name: "Soft two-seat sofa",
    price: { amount: 24500000, currency: "TZS" }, product_type: "MADE_TO_ORDER", availability: "available", stock_indicator: "MADE_TO_ORDER",
    primary_image: { url: `${fixtureRoot}/products/white-sofa.jpg`, alt_text: "A cream two-seat sofa with broad upholstered arms" },
  },
  {
    id: "fixture_barrelchair", slug: "fixture-barrel-chair", name: "Barrel chair",
    price: { amount: 10000000, currency: "TZS" }, product_type: "MADE_TO_ORDER", availability: "available", stock_indicator: "MADE_TO_ORDER",
    primary_image: { url: `${fixtureRoot}/products/barrel-armchair.jpg`, alt_text: "A cream barrel chair with a curved back" },
  },
];

export const FIXTURE_PRODUCTS_BY_CATEGORY_SLUG: Readonly<Record<string, readonly ProductSummary[]>> = {
  "living-room": HOMEPAGE_PRODUCT_FIXTURES,
};

const fixtureCategory = { id: "fixture_living", name: "Living Room", slug: "living-room", description: "Furniture for living spaces.", image: null };
export const HOMEPAGE_PRODUCT_DETAIL_FIXTURES: Readonly<Record<string, ProductDetail>> = Object.fromEntries(
  HOMEPAGE_PRODUCT_FIXTURES.map((product) => [product.slug, {
    ...product,
    category: fixtureCategory,
    description: "A made-to-order furniture piece shown as part of the design preview.",
    images: product.primary_image ? [{ id: `${product.id}_image`, url: product.primary_image.url, alt_text: product.primary_image.alt_text, sort_order: 0, is_primary: true }] : [],
    variants: [],
    created_at: "2026-01-01T00:00:00Z",
    updated_at: "2026-01-01T00:00:00Z",
  }]),
);
