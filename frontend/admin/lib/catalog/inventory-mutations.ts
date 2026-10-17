import type { ApiErrorItem } from "@/lib/api/types";
import type { InventoryRecord } from "@/lib/catalog/inventory-resource";

/**
 * Inventory-adjustment vocabulary shared by the INV-003 Server Action and the
 * client dialog: the CLOSED reason set and its sign rules, advisory client-side
 * validation, the preview calculation, and the safe outcome catalogue. It carries
 * no server-only import so both sides consume one definition, and Laravel remains
 * authoritative for every rule it mirrors.
 */

export type InventoryReason = "STOCK_RECEIPT" | "CORRECTION" | "DAMAGE" | "RETURN" | "AUDIT_ADJUSTMENT";

/** The CLOSED Version 1 reason set (api-contract.md §30.5.2). */
export const INVENTORY_REASON_VALUES = ["STOCK_RECEIPT", "CORRECTION", "DAMAGE", "RETURN", "AUDIT_ADJUSTMENT"] as const;

export const INVENTORY_QUANTITY_DELTA_FIELD = "quantity_delta";
export const INVENTORY_REASON_FIELD = "reason";

export const INVENTORY_REASON_LABELS: Readonly<Record<InventoryReason, string>> = {
  STOCK_RECEIPT: "Stock receipt",
  CORRECTION: "Correction",
  DAMAGE: "Damage",
  RETURN: "Return",
  AUDIT_ADJUSTMENT: "Audit adjustment",
};

/** Which sign Laravel accepts for each reason; `either` accepts both. */
export type InventoryAdjustmentDirection = "increase" | "decrease" | "either";

const REASON_DIRECTIONS: Readonly<Record<InventoryReason, InventoryAdjustmentDirection>> = {
  STOCK_RECEIPT: "increase",
  CORRECTION: "either",
  DAMAGE: "decrease",
  RETURN: "increase",
  AUDIT_ADJUSTMENT: "either",
};

function isInventoryReason(value: unknown): value is InventoryReason {
  return typeof value === "string" && (INVENTORY_REASON_VALUES as readonly string[]).includes(value);
}

export function inventoryReasonLabel(value: string): string {
  return isInventoryReason(value) ? INVENTORY_REASON_LABELS[value] : "Unrecognised reason";
}

/** The sign Laravel enforces for a reason, or `null` for an unknown reason. */
export function inventoryReasonDirection(reason: string): InventoryAdjustmentDirection | null {
  return isInventoryReason(reason) ? REASON_DIRECTIONS[reason] : null;
}

const INVENTORY_REASON_REQUIRED_MESSAGE = "Choose an adjustment reason.";
const INVENTORY_DELTA_REQUIRED_MESSAGE = "Enter a whole-number quantity change.";
export const INVENTORY_DELTA_ZERO_MESSAGE = "Enter a non-zero quantity change.";
const INVENTORY_RANGE_MESSAGE = "That quantity change is too large to process safely.";
export const INVENTORY_NEGATIVE_RESULT_MESSAGE = "The resulting quantity cannot be negative.";
export const INVENTORY_BELOW_RESERVED_MESSAGE = "The resulting quantity would drop below the reserved quantity.";

const DELTA_PATTERN = /^[+-]?\d+$/;

/** Strict whole-number parse of the operator's input; anything else is `null`. */
export function parseQuantityDelta(raw: string): number | null {
  const trimmed = raw.trim();
  if (!DELTA_PATTERN.test(trimmed)) return null;
  const value = Number(trimmed);
  return Number.isSafeInteger(value) ? value : null;
}

export interface InventoryAdjustmentInput {
  readonly quantity: number;
  readonly reservedQuantity: number;
  readonly quantityDelta: number;
  readonly reason: string;
}

function directionMessage(reason: InventoryReason): string {
  return REASON_DIRECTIONS[reason] === "increase"
    ? `${INVENTORY_REASON_LABELS[reason]} must increase stock; enter a positive change.`
    : `${INVENTORY_REASON_LABELS[reason]} must reduce stock; enter a negative change.`;
}

/**
 * Advisory pre-flight mirroring the INV-003 rules; Laravel re-validates every one
 * of them. Returns the first blocking message, or `null` when the change may be sent.
 */
export function inventoryAdjustmentError(input: InventoryAdjustmentInput): string | null {
  const shape = inventoryAdjustmentShapeError(input);
  if (shape !== null) return shape;

  const result = input.quantity + input.quantityDelta;
  if (!Number.isSafeInteger(result)) return INVENTORY_RANGE_MESSAGE;
  if (result < 0) return INVENTORY_NEGATIVE_RESULT_MESSAGE;
  if (result < input.reservedQuantity) return INVENTORY_BELOW_RESERVED_MESSAGE;
  return null;
}

/**
 * The state-free half of the rules, applied by the Server Action too because it
 * is directly POST-reachable and never trusts the shape of the client's request.
 */
export function inventoryAdjustmentShapeError(input: Readonly<{ quantityDelta: number; reason: string }>): string | null {
  if (!isInventoryReason(input.reason)) return INVENTORY_REASON_REQUIRED_MESSAGE;
  if (!Number.isSafeInteger(input.quantityDelta)) return INVENTORY_DELTA_REQUIRED_MESSAGE;
  if (input.quantityDelta === 0) return INVENTORY_DELTA_ZERO_MESSAGE;
  const direction = REASON_DIRECTIONS[input.reason];
  if (direction === "increase" && input.quantityDelta < 0) return directionMessage(input.reason);
  if (direction === "decrease" && input.quantityDelta > 0) return directionMessage(input.reason);
  return null;
}

/** The resulting quantity for the live preview, or `null` when it cannot be computed safely. */
export function previewInventoryQuantity(quantity: number, quantityDelta: number): number | null {
  const result = quantity + quantityDelta;
  return Number.isSafeInteger(result) && result >= 0 ? result : null;
}

/** Identifies one logical intent so a retry reuses its key and a change gets a new one. */
export function inventoryAdjustmentSignature(input: Readonly<{ quantityDelta: number; reason: string }>): string {
  return `${input.reason}:${input.quantityDelta}`;
}

/**
 * One idempotency key bound to one logical intent. The owner keeps it across the
 * dialog lifecycle so a retry of an uncertain change reuses the original key.
 */
export interface InventoryAdjustmentIntent {
  readonly signature: string;
  readonly key: string;
}

export type InventoryAdjustmentStatus =
  | "success"
  | "invalid"
  | "forbidden"
  | "session"
  | "not-found"
  | "conflict"
  | "rate-limited"
  | "unavailable"
  | "uncertain";

/** What an adjustment reports back. `uncertain` means the request may still have applied. */
export interface InventoryAdjustmentOutcome {
  readonly status: InventoryAdjustmentStatus;
  /** Safe, operator-facing message. Never carries Laravel internals. */
  readonly message: string;
  readonly retryAfterSeconds: number | null;
}

export const INVENTORY_ADJUSTMENT_SUCCESS_MESSAGE = "Adjustment applied. The record and list have been refreshed.";
export const INVENTORY_ADJUSTMENT_SESSION_MESSAGE = "Your session is no longer valid. Sign out and sign in again.";
export const INVENTORY_ADJUSTMENT_UNAUTHORIZED_MESSAGE = "You are not authorized to adjust inventory.";
export const INVENTORY_ADJUSTMENT_UNAVAILABLE_MESSAGE = "The inventory service is unavailable. Try again shortly.";
export const INVENTORY_ADJUSTMENT_NOT_FOUND_MESSAGE = "This inventory record is no longer available. Refresh the list.";
export const INVENTORY_ADJUSTMENT_CONFLICT_MESSAGE = "This request key was already used for a different change. Change the quantity or reason to start a new adjustment, or close this dialog.";
const INVENTORY_ADJUSTMENT_INVALID_MESSAGE = "Laravel rejected this adjustment. Check the quantity change and reason.";
export const INVENTORY_ADJUSTMENT_UNCERTAIN_MESSAGE = "The connection failed before the result was known. Submit the identical change again to retry it, or change it to start a new adjustment.";
export const INVENTORY_ADJUSTMENT_UNIDENTIFIED_MESSAGE = "This adjustment could not be submitted because its request key or record was missing or invalid. Close this dialog and try again.";
export const INVENTORY_VIEW_UNAUTHORIZED_MESSAGE = "You are not authorized to view inventory.";
export const INVENTORY_TOO_MANY_MESSAGE = "Too many requests. Try again shortly.";
export const INVENTORY_SNAPSHOT_UNAVAILABLE_MESSAGE = "The inventory record could not be loaded. Try again.";
export const INVENTORY_SNAPSHOT_INVALID_MESSAGE = "The inventory service returned a response this application could not read.";

export function inventoryAdjustmentSuccess(): InventoryAdjustmentOutcome {
  return { status: "success", message: INVENTORY_ADJUSTMENT_SUCCESS_MESSAGE, retryAfterSeconds: null };
}

/** A rejected request that never reached Laravel, e.g. a malformed key or payload. */
export function inventoryAdjustmentRejection(message: string): InventoryAdjustmentOutcome {
  return { status: "invalid", message, retryAfterSeconds: null };
}

/** A failed adjustment as classified from the shared API error envelope. */
export interface InventoryAdjustmentFailure {
  readonly status: number | null;
  readonly errors: readonly ApiErrorItem[];
  readonly transportKind: "network" | "timeout" | "aborted" | null;
  readonly retryAfterSeconds: number | null;
}

export function inventoryAdjustmentFailureOutcome(failure: InventoryAdjustmentFailure): InventoryAdjustmentOutcome {
  const { status } = failure;
  if (failure.transportKind !== null) return outcome("uncertain", INVENTORY_ADJUSTMENT_UNCERTAIN_MESSAGE, failure);
  if (status === 401) return outcome("session", INVENTORY_ADJUSTMENT_SESSION_MESSAGE, failure);
  if (status === 403) return outcome("forbidden", INVENTORY_ADJUSTMENT_UNAUTHORIZED_MESSAGE, failure);
  if (status === 404) return outcome("not-found", INVENTORY_ADJUSTMENT_NOT_FOUND_MESSAGE, failure);
  if (status === 409) return outcome("conflict", INVENTORY_ADJUSTMENT_CONFLICT_MESSAGE, failure);
  if (status === 422) return outcome("invalid", invalidMessage(failure.errors), failure);
  if (status === 429) return outcome("rate-limited", rateLimitedMessage(failure.retryAfterSeconds), failure);
  return outcome("unavailable", INVENTORY_ADJUSTMENT_UNAVAILABLE_MESSAGE, failure);
}

function outcome(status: InventoryAdjustmentStatus, message: string, failure: InventoryAdjustmentFailure): InventoryAdjustmentOutcome {
  return { status, message, retryAfterSeconds: failure.status === 429 ? failure.retryAfterSeconds : null };
}

/** Prefers Laravel's own field message over the generic fallback. */
function invalidMessage(errors: readonly ApiErrorItem[]): string {
  const chosen =
    errors.find((item) => item.field === INVENTORY_QUANTITY_DELTA_FIELD) ??
    errors.find((item) => item.field === INVENTORY_REASON_FIELD) ??
    errors[0];
  return chosen !== undefined && chosen.message.trim().length > 0 ? chosen.message : INVENTORY_ADJUSTMENT_INVALID_MESSAGE;
}

function rateLimitedMessage(retryAfterSeconds: number | null): string {
  return retryAfterSeconds === null ? "Too many adjustments. Try again shortly." : `Too many adjustments. Try again in ${retryAfterSeconds} seconds.`;
}

export type InventoryAdjustmentSeverity = "success" | "warning" | "error";

export function inventoryAdjustmentSeverity(status: InventoryAdjustmentStatus): InventoryAdjustmentSeverity {
  if (status === "success") return "success";
  return status === "uncertain" || status === "rate-limited" ? "warning" : "error";
}

/** A fresh, identical-intent request the dialog passes to the Server Action. */
export interface InventoryAdjustmentRequest {
  readonly inventoryId: string;
  readonly idempotencyKey: string;
  readonly quantityDelta: number;
  readonly reason: string;
}

/** The INV-002 refresh the dialog runs when it opens and after a confirmed change. */
export type InventorySnapshotOutcome =
  | { readonly ok: true; readonly record: InventoryRecord }
  | { readonly ok: false; readonly message: string };

/**
 * The two operations the dialog invokes. They are Server Actions supplied by the
 * page, so the client component never imports the server-only transport.
 */
export interface InventoryAdjustmentActions {
  readonly loadSnapshot: (inventoryId: string) => Promise<InventorySnapshotOutcome>;
  readonly adjust: (request: InventoryAdjustmentRequest) => Promise<InventoryAdjustmentOutcome>;
}
