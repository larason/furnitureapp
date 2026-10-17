import type { ApiPagination } from "@/lib/api/types";

/**
 * Defensive parser for the operational Inventory resource. Every documented
 * field is read from Laravel; an incomplete or derived-locally quantity is never
 * reconstructed. `updated_at` is the one value the backend may itself emit as
 * null, so it is carried as an explicit unavailable state rather than an error.
 */
export interface InventoryRecord {
  readonly id: string;
  readonly productId: string;
  readonly variantId: string;
  readonly warehouseLocation: string;
  readonly quantity: number;
  readonly reservedQuantity: number;
  readonly availableQuantity: number;
  readonly updatedAt: string | null;
}

export interface InventoryPage {
  readonly records: readonly InventoryRecord[];
  readonly pagination: ApiPagination;
}

/** Raised when a response cannot be read as the documented inventory resource. */
export class InventoryResourceError extends Error {
  constructor() {
    super("The inventory response did not match the documented resource.");
    this.name = "InventoryResourceError";
  }
}

export function parseInventoryRecord(value: unknown): InventoryRecord {
  if (!isRecord(value)) throw new InventoryResourceError();

  const id = readString(value.id);
  const productId = readString(value.product_id);
  const variantId = readString(value.variant_id);
  const warehouseLocation = readString(value.warehouse_location);
  const quantity = readNonNegativeInteger(value.quantity);
  const reservedQuantity = readNonNegativeInteger(value.reserved_quantity);
  const availableQuantity = readNonNegativeInteger(value.available_quantity);

  if (
    id === null ||
    productId === null ||
    variantId === null ||
    warehouseLocation === null ||
    quantity === null ||
    reservedQuantity === null ||
    availableQuantity === null
  ) {
    throw new InventoryResourceError();
  }

  return {
    id,
    productId,
    variantId,
    warehouseLocation,
    quantity,
    reservedQuantity,
    availableQuantity,
    updatedAt: readString(value.updated_at),
  };
}

export function parseInventoryPage(value: unknown): InventoryPage {
  if (!isRecord(value)) throw new InventoryResourceError();
  return { records: parseInventoryList(value.data), pagination: parsePagination(value.meta) };
}

function parseInventoryList(value: unknown): readonly InventoryRecord[] {
  if (!Array.isArray(value)) throw new InventoryResourceError();
  return value.map(parseInventoryRecord);
}

function parsePagination(meta: unknown): ApiPagination {
  if (!isRecord(meta) || !isRecord(meta.pagination)) throw new InventoryResourceError();
  const source = meta.pagination;
  const currentPage = readPositiveInteger(source.current_page);
  const perPage = readPositiveInteger(source.per_page);
  const total = readNonNegativeInteger(source.total);
  const lastPage = readPositiveInteger(source.last_page);

  if (currentPage === null || perPage === null || total === null || lastPage === null) {
    throw new InventoryResourceError();
  }

  return {
    current_page: currentPage,
    per_page: perPage,
    total,
    last_page: lastPage,
    has_next: source.has_next === true,
    has_previous: source.has_previous === true,
  };
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function readString(value: unknown): string | null {
  return typeof value === "string" && value.trim().length > 0 ? value : null;
}

function readPositiveInteger(value: unknown): number | null {
  return typeof value === "number" && Number.isInteger(value) && value >= 1 ? value : null;
}

function readNonNegativeInteger(value: unknown): number | null {
  return typeof value === "number" && Number.isInteger(value) && value >= 0 ? value : null;
}
