import type { CategorySummary, ProductSummary } from "../catalog/types";

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
    price: { amount: 10000000, currency: "TZS" }, product_type: "MADE_TO_ORDER", availability: "available",
    primary_image: { url: `${fixtureRoot}/products/lounge-chair.jpg`, alt_text: "An open wooden armchair with cream seat and back cushions" },
  },
  {
    id: "fixture_sofa", slug: "fixture-soft-two-seat-sofa", name: "Soft two-seat sofa",
    price: { amount: 24500000, currency: "TZS" }, product_type: "MADE_TO_ORDER", availability: "available",
    primary_image: { url: `${fixtureRoot}/products/white-sofa.jpg`, alt_text: "A cream two-seat sofa with broad upholstered arms" },
  },
  {
    id: "fixture_barrelchair", slug: "fixture-barrel-chair", name: "Barrel chair",
    price: { amount: 10000000, currency: "TZS" }, product_type: "MADE_TO_ORDER", availability: "available",
    primary_image: { url: `${fixtureRoot}/products/barrel-armchair.jpg`, alt_text: "A cream barrel chair with a curved back" },
  },
];

export const FIXTURE_PRODUCTS_BY_CATEGORY_SLUG: Readonly<Record<string, readonly ProductSummary[]>> = {
  "living-room": HOMEPAGE_PRODUCT_FIXTURES,
};
