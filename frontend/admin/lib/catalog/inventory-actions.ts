"use server";

import { revalidatePath } from "next/cache";
import { ApiError, ApiTransportError } from "@/lib/api/errors";
import { isIdempotencyKey } from "@/lib/api/idempotency";
import { AdminPermissionName } from "@/lib/auth/permission-names";
import { resolveAdminRouteAccess, type AdminRouteAccess } from "@/lib/auth/route-guard";
import { requireOperationalIdentity } from "@/lib/auth/server-operational-identity";
import {
  attemptInventoryRead,
  fetchInventoryRecord,
  submitInventoryAdjustment,
  type InventoryApiFailure,
} from "@/lib/catalog/inventory-api";
import {
  INVENTORY_ADJUSTMENT_NOT_FOUND_MESSAGE,
  INVENTORY_ADJUSTMENT_SESSION_MESSAGE,
  INVENTORY_ADJUSTMENT_UNAVAILABLE_MESSAGE,
  INVENTORY_ADJUSTMENT_UNAUTHORIZED_MESSAGE,
  INVENTORY_ADJUSTMENT_UNIDENTIFIED_MESSAGE,
  INVENTORY_SNAPSHOT_INVALID_MESSAGE,
  INVENTORY_SNAPSHOT_UNAVAILABLE_MESSAGE,
  INVENTORY_TOO_MANY_MESSAGE,
  INVENTORY_VIEW_UNAUTHORIZED_MESSAGE,
  inventoryAdjustmentFailureOutcome,
  inventoryAdjustmentRejection,
  inventoryAdjustmentShapeError,
  inventoryAdjustmentSuccess,
  type InventoryAdjustmentFailure,
  type InventoryAdjustmentOutcome,
  type InventorySnapshotOutcome,
} from "@/lib/catalog/inventory-mutations";
import { adminRoute } from "@/lib/navigation/admin-navigation";
import { ADMIN_ROUTE_PATHS } from "@/lib/navigation/admin-route-paths";

const INVENTORY_ROUTE = adminRoute(ADMIN_ROUTE_PATHS.inventory);
const INVENTORY_IDENTIFIER_PATTERN = /^inv_[0-9a-z]+$/;

const SNAPSHOT_MESSAGES: Readonly<Record<InventoryApiFailure, string>> = {
  session: INVENTORY_ADJUSTMENT_SESSION_MESSAGE,
  forbidden: INVENTORY_VIEW_UNAUTHORIZED_MESSAGE,
  "not-found": INVENTORY_ADJUSTMENT_NOT_FOUND_MESSAGE,
  invalid: INVENTORY_SNAPSHOT_INVALID_MESSAGE,
  unavailable: INVENTORY_SNAPSHOT_UNAVAILABLE_MESSAGE,
  throttled: INVENTORY_TOO_MANY_MESSAGE,
};

/**
 * INV-002. Server Functions are directly POST-reachable, so this repeats the
 * exact Laravel-backed `inventory.view` requirement before reading the record.
 */
export async function loadInventorySnapshotAction(inventoryId: unknown): Promise<InventorySnapshotOutcome> {
  const access = resolveAdminRouteAccess(await requireOperationalIdentity(), {
    permission: INVENTORY_ROUTE.permission,
    role: INVENTORY_ROUTE.role,
  });
  if (access.kind !== "granted") return { ok: false, message: denialMessage(access.kind, INVENTORY_VIEW_UNAUTHORIZED_MESSAGE) };

  const identifier = readInventoryIdentifier(inventoryId);
  if (identifier === null) return { ok: false, message: INVENTORY_ADJUSTMENT_UNIDENTIFIED_MESSAGE };

  const outcome = await attemptInventoryRead(() => fetchInventoryRecord(identifier));
  return outcome.ok ? { ok: true, record: outcome.value } : { ok: false, message: SNAPSHOT_MESSAGES[outcome.failure] };
}

/**
 * INV-003. The action re-checks the exact `inventory.manage` requirement, rejects
 * a malformed key or payload instead of generating a replacement key, and never
 * retries: the caller owns the key's lifecycle, so an uncertain outcome stays
 * retryable with the identical key.
 */
export async function adjustInventoryAction(request: unknown): Promise<InventoryAdjustmentOutcome> {
  const access = resolveAdminRouteAccess(await requireOperationalIdentity(), { permission: AdminPermissionName.INVENTORY_MANAGE });
  if (access.kind !== "granted") return denialOutcome(access.kind);

  const prepared = prepareAdjustment(request);
  if (!prepared.ok) return prepared.outcome;

  try {
    await submitInventoryAdjustment(prepared.inventoryId, prepared.quantityDelta, prepared.reason, prepared.idempotencyKey);
  } catch (error) {
    return inventoryAdjustmentFailureOutcome(adjustmentFailure(error));
  }

  revalidatePath(ADMIN_ROUTE_PATHS.inventory);
  return inventoryAdjustmentSuccess();
}

type PreparedAdjustment =
  | {
      readonly ok: true;
      readonly inventoryId: string;
      readonly quantityDelta: number;
      readonly reason: string;
      readonly idempotencyKey: string;
    }
  | { readonly ok: false; readonly outcome: InventoryAdjustmentOutcome };

function prepareAdjustment(value: unknown): PreparedAdjustment {
  const record = isRecord(value) ? value : {};
  const inventoryId = readInventoryIdentifier(record.inventoryId);
  const quantityDelta = typeof record.quantityDelta === "number" ? record.quantityDelta : Number.NaN;
  const reason = typeof record.reason === "string" ? record.reason : "";
  const idempotencyKey = typeof record.idempotencyKey === "string" ? record.idempotencyKey : "";

  if (inventoryId === null || !isIdempotencyKey(idempotencyKey)) {
    return { ok: false, outcome: inventoryAdjustmentRejection(INVENTORY_ADJUSTMENT_UNIDENTIFIED_MESSAGE) };
  }

  const shape = inventoryAdjustmentShapeError({ quantityDelta, reason });
  return shape !== null
    ? { ok: false, outcome: inventoryAdjustmentRejection(shape) }
    : { ok: true, inventoryId, quantityDelta, reason, idempotencyKey };
}

function adjustmentFailure(error: unknown): InventoryAdjustmentFailure {
  if (error instanceof ApiTransportError) {
    return { status: null, errors: [], transportKind: error.kind, retryAfterSeconds: null };
  }

  return error instanceof ApiError
    ? { status: error.status, errors: error.errors, transportKind: null, retryAfterSeconds: error.retryAfterSeconds ?? null }
    : { status: null, errors: [], transportKind: null, retryAfterSeconds: null };
}

function denialOutcome(kind: AdminRouteAccess["kind"]): InventoryAdjustmentOutcome {
  if (kind === "signed-out" || kind === "session-invalid") {
    return { status: "session", message: INVENTORY_ADJUSTMENT_SESSION_MESSAGE, retryAfterSeconds: null };
  }

  return kind === "unavailable"
    ? { status: "unavailable", message: INVENTORY_ADJUSTMENT_UNAVAILABLE_MESSAGE, retryAfterSeconds: null }
    : { status: "forbidden", message: INVENTORY_ADJUSTMENT_UNAUTHORIZED_MESSAGE, retryAfterSeconds: null };
}

function denialMessage(kind: AdminRouteAccess["kind"], unauthorized: string): string {
  if (kind === "signed-out" || kind === "session-invalid") return INVENTORY_ADJUSTMENT_SESSION_MESSAGE;
  return kind === "unavailable" ? INVENTORY_SNAPSHOT_UNAVAILABLE_MESSAGE : unauthorized;
}

function readInventoryIdentifier(value: unknown): string | null {
  return typeof value === "string" && INVENTORY_IDENTIFIER_PATTERN.test(value) ? value : null;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}
