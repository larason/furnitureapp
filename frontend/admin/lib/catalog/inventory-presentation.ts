import {
  inventoryProductSubject,
  inventoryVariantSubject,
  type InventorySubject,
  type InventoryVariantSubject,
  type ResolvedInventoryLabels,
} from "@/lib/catalog/inventory-label";
import { inventoryReasonDirection, inventoryReasonLabel } from "@/lib/catalog/inventory-mutations";
import type { InventoryRecord } from "@/lib/catalog/inventory-resource";

/**
 * Presentation helpers for the inventory module. Values come from Laravel; an
 * unresolved label or an unreadable value renders an explicit unavailable state
 * rather than a guessed one. An opaque ID is only ever shown as a technical
 * reference, never presented as a human-readable name.
 */

const quantityFormatter = new Intl.NumberFormat("en-TZ");

const timestampFormatter = new Intl.DateTimeFormat("en-GB", {
  dateStyle: "medium",
  timeStyle: "short",
  timeZone: "UTC",
});

export const INVENTORY_PRODUCT_UNAVAILABLE = "Product details unavailable";
export const INVENTORY_VARIANT_UNAVAILABLE = "Variant details unavailable";
export const INVENTORY_TIMESTAMP_UNAVAILABLE = "Not recorded";
export const INVENTORY_QUANTITY_UNAVAILABLE = "Unavailable";
export const INVENTORY_SKU_UNAVAILABLE = "SKU unavailable";

/** Formats Laravel's whole-unit stock quantity. */
export function formatInventoryQuantity(value: number): string {
  return Number.isSafeInteger(value) && value >= 0 ? quantityFormatter.format(value) : INVENTORY_QUANTITY_UNAVAILABLE;
}

/** Formats a signed adjustment delta for display, e.g. `+10` or `-3`. */
export function formatInventoryDelta(value: number): string {
  return value > 0 ? `+${quantityFormatter.format(value)}` : quantityFormatter.format(value);
}

/** Formats the API ISO-8601 timestamp; an unreadable value is treated as absent. */
export function formatInventoryTimestamp(value: string | null): string | null {
  if (value === null) return null;
  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? null : timestampFormatter.format(parsed);
}

/**
 * A table row: the authoritative record plus its display-only labels, flattened
 * into the plain objects a Client Component can receive. A Map is not passed
 * across the Server/Client boundary.
 */
export interface InventoryRow {
  readonly record: InventoryRecord;
  readonly product: InventorySubject;
  readonly variant: InventoryVariantSubject;
}

export function inventoryRows(
  records: readonly InventoryRecord[],
  labels: ResolvedInventoryLabels,
): readonly InventoryRow[] {
  return records.map((record) => ({
    record,
    product: inventoryProductSubject(labels, record.productId),
    variant: inventoryVariantSubject(labels, record.variantId),
  }));
}

export function inventorySubjectLabel(subject: InventorySubject): string {
  return subject.name ?? INVENTORY_PRODUCT_UNAVAILABLE;
}

export function inventoryVariantLabel(subject: InventoryVariantSubject): string {
  return subject.name ?? INVENTORY_VARIANT_UNAVAILABLE;
}

/** The deterministic sign guidance Laravel enforces for the selected reason. */
export function inventoryReasonHint(reason: string): string | null {
  const direction = inventoryReasonDirection(reason);
  if (direction === "increase") return `${inventoryReasonLabel(reason)} increases stock; enter a positive change.`;
  if (direction === "decrease") return `${inventoryReasonLabel(reason)} reduces stock; enter a negative change.`;
  if (direction === "either") return `${inventoryReasonLabel(reason)} accepts a positive or negative change.`;
  return null;
}
