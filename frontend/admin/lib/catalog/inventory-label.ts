import type { InventoryRecord } from "@/lib/catalog/inventory-resource";

/**
 * Resolves display-only product and variant labels for inventory rows, which the
 * Inventory resource intentionally does not carry. Labels come from the public
 * catalog reads (`CAT-002`, `CAT-005`) because they are the only documented
 * endpoints readable with `inventory.view` alone. A label that cannot be resolved
 * is reported as unresolved — never invented and never the opaque ID posing as a
 * name. This module is pure so its mapping is directly testable.
 */

export interface InventoryVariantEntry {
  readonly id: string;
  readonly name: string;
  readonly sku: string;
}

export interface InventoryLabelLookup {
  readonly productId: string;
  readonly productName: string | null;
  readonly variants: readonly InventoryVariantEntry[] | null;
}

export interface ResolvedInventoryLabels {
  readonly products: ReadonlyMap<string, string>;
  readonly variants: ReadonlyMap<string, InventoryVariantEntry>;
}

export function emptyInventoryLabels(): ResolvedInventoryLabels {
  return { products: new Map(), variants: new Map() };
}

/** The distinct product IDs a page of inventory rows refers to, in first-seen order. */
export function uniqueInventoryProductIds(records: readonly InventoryRecord[]): readonly string[] {
  return [...new Set(records.map((record) => record.productId))];
}

/** Reads `name` from a CAT-002 product detail payload; `null` when unreadable. */
export function catalogProductName(data: unknown): string | null {
  if (!isRecord(data)) return null;
  return readString(data.name);
}

/** Reads a CAT-005 variant list into label entries; `null` when the array is unreadable. */
export function catalogVariantEntries(data: unknown): readonly InventoryVariantEntry[] | null {
  if (!Array.isArray(data)) return null;
  return data.flatMap((entry) => {
    if (!isRecord(entry)) return [];
    const id = readString(entry.id);
    const name = readString(entry.name);
    const sku = readString(entry.sku);
    return id === null || name === null || sku === null ? [] : [{ id, name, sku }];
  });
}

export function buildInventoryLabels(lookups: readonly InventoryLabelLookup[]): ResolvedInventoryLabels {
  const products = new Map<string, string>();
  const variants = new Map<string, InventoryVariantEntry>();

  for (const lookup of lookups) {
    if (lookup.productName !== null) products.set(lookup.productId, lookup.productName);
    for (const variant of lookup.variants ?? []) variants.set(variant.id, variant);
  }

  return { products, variants };
}

export interface InventorySubject {
  readonly name: string | null;
  readonly identifier: string;
}

export interface InventoryVariantSubject extends InventorySubject {
  readonly sku: string | null;
}

export function inventoryProductSubject(labels: ResolvedInventoryLabels, productId: string): InventorySubject {
  return { name: labels.products.get(productId) ?? null, identifier: productId };
}

export function inventoryVariantSubject(labels: ResolvedInventoryLabels, variantId: string): InventoryVariantSubject {
  const variant = labels.variants.get(variantId);
  return { name: variant?.name ?? null, sku: variant?.sku ?? null, identifier: variantId };
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function readString(value: unknown): string | null {
  return typeof value === "string" && value.trim().length > 0 ? value : null;
}
