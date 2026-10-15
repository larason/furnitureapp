import type { ProductPrice } from "@/lib/catalog/product-resource";

/**
 * Presentation mapping for product type, state, availability and stock. Values
 * come from Laravel and are shown verbatim through a fixed label; an unknown
 * future value falls back to a neutral label rather than a guessed state.
 */
export type ProductTone = "success" | "warning" | "info" | "neutral";

export interface ProductStateDescriptor {
  readonly key: string;
  readonly label: string;
  readonly tone: ProductTone;
}

const PRODUCT_TYPE_LABELS: Readonly<Record<string, string>> = {
  IN_STOCK: "In stock",
  MADE_TO_ORDER: "Made to order",
};

const AVAILABILITY_LABELS: Readonly<Record<string, string>> = {
  available: "Available",
  unavailable: "Unavailable",
};

const STOCK_INDICATOR_LABELS: Readonly<Record<string, string>> = {
  IN_STOCK: "In stock",
  LOW_STOCK: "Low stock",
  MADE_TO_ORDER: "Made to order",
};

const UNRECOGNISED = "Unrecognised value";
const PRICE_UNAVAILABLE = "Price unavailable";

const formatter = new Intl.NumberFormat("en-TZ", {
  style: "currency",
  currency: "TZS",
  currencyDisplay: "code",
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

const timestampFormatter = new Intl.DateTimeFormat("en-GB", {
  dateStyle: "medium",
  timeStyle: "short",
  timeZone: "UTC",
});

export function productTypeLabel(value: string): string {
  return PRODUCT_TYPE_LABELS[value] ?? UNRECOGNISED;
}

export function availabilityLabel(value: string): string {
  return AVAILABILITY_LABELS[value] ?? "Availability unknown";
}

export function stockIndicatorLabel(value: string): string {
  return STOCK_INDICATOR_LABELS[value] ?? "Stock unknown";
}

/** Formats Laravel's integer minor-unit amount without floating-point drift. */
export function formatProductPrice(price: ProductPrice): string {
  if (!Number.isSafeInteger(price.amount) || price.amount < 0 || price.currency !== "TZS") {
    return PRICE_UNAVAILABLE;
  }

  return formatter.format(price.amount / 100);
}

export function formatProductTimestamp(value: string | null): string | null {
  if (value === null) return null;
  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? null : timestampFormatter.format(parsed);
}

/** The product's real state: Laravel stores `is_active` and `is_published`. */
export function productStateDescriptors(product: {
  readonly isActive: boolean | null;
  readonly isPublished: boolean | null;
}): readonly ProductStateDescriptor[] {
  return [activeDescriptor(product.isActive), publishedDescriptor(product.isPublished)];
}

function activeDescriptor(value: boolean | null): ProductStateDescriptor {
  if (value === null) return { key: "is_active", label: "Active: unknown", tone: "neutral" };
  return value
    ? { key: "is_active", label: "Active", tone: "success" }
    : { key: "is_active", label: "Inactive", tone: "warning" };
}

function publishedDescriptor(value: boolean | null): ProductStateDescriptor {
  if (value === null) return { key: "is_published", label: "Published: unknown", tone: "neutral" };
  return value
    ? { key: "is_published", label: "Published", tone: "info" }
    : { key: "is_published", label: "Unpublished", tone: "neutral" };
}
