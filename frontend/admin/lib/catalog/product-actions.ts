"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { resolveAdminRouteAccess } from "@/lib/auth/route-guard";
import { requireOperationalIdentity } from "@/lib/auth/server-operational-identity";
import { buildCreatePayload, buildUpdatePayload, fieldErrorsFromApi, readProductFormValues, type ProductFormState, type ProductFormValues } from "@/lib/catalog/product-form";
import { productWriteErrors, submitProductCreate, submitProductUpdate } from "@/lib/catalog/products-api";
import type { OperationalProduct } from "@/lib/catalog/product-resource";
import { adminRoute } from "@/lib/navigation/admin-navigation";
import { ADMIN_PRODUCT_PATHS } from "@/lib/navigation/admin-route-paths";

const UNAUTHORIZED_MESSAGE = "You are not authorized to manage products.";
const SESSION_MESSAGE = "Your session is no longer valid. Sign out and sign in again.";
const MISSING_RESOURCE_MESSAGE = "The selected category or product is no longer available.";
const RETRY_MESSAGE = "The product service is unavailable. Try again shortly.";
const UNIDENTIFIED_MESSAGE = "The product could not be identified for this update.";

const PRODUCT_ROUTE = adminRoute(ADMIN_PRODUCT_PATHS.list);

type WriteResult = { readonly ok: true; readonly product: OperationalProduct } | { readonly ok: false; readonly state: ProductFormState };

/**
 * Server Functions are directly POST-reachable, so both write actions repeat the
 * exact Laravel-backed permission requirement before touching product data.
 */
export async function createProductAction(previous: ProductFormState, formData: FormData): Promise<ProductFormState> {
  const denial = await authorizationFailure();
  if (denial) return denial;

  const values = readProductFormValues(formData);
  const { payload, fieldErrors } = buildCreatePayload(values);
  if (payload === null) return { status: "error", fieldErrors };

  const result = await writeProduct(() => submitProductCreate(payload));
  if (!result.ok) return result.state;

  revalidatePath(ADMIN_PRODUCT_PATHS.list);
  redirect(ADMIN_PRODUCT_PATHS.detail(result.product.id));
}

export async function updateProductAction(previous: ProductFormState, formData: FormData): Promise<ProductFormState> {
  const denial = await authorizationFailure();
  if (denial) return denial;

  const identifier = readTextValue(formData, "product_id");
  const original = readOriginalValues(formData);
  if (identifier === null || original === null) return { status: "error", formError: UNIDENTIFIED_MESSAGE };

  const values = readProductFormValues(formData);
  const { payload, fieldErrors } = buildUpdatePayload(values, original);
  if (payload === null) return { status: "error", fieldErrors };

  const result = await writeProduct(() => submitProductUpdate(identifier, payload));
  if (!result.ok) return result.state;

  revalidatePath(ADMIN_PRODUCT_PATHS.list);
  revalidatePath(ADMIN_PRODUCT_PATHS.detail(result.product.id));
  redirect(ADMIN_PRODUCT_PATHS.detail(result.product.id));
}

async function authorizationFailure(): Promise<ProductFormState | null> {
  const access = resolveAdminRouteAccess(await requireOperationalIdentity(), { permission: PRODUCT_ROUTE.permission, role: PRODUCT_ROUTE.role });
  return access.kind === "granted" ? null : { status: "error", formError: UNAUTHORIZED_MESSAGE };
}

async function writeProduct(operation: () => Promise<OperationalProduct>): Promise<WriteResult> {
  try {
    return { ok: true, product: await operation() };
  } catch (error) {
    return { ok: false, state: writeFailureState(error) };
  }
}

function writeFailureState(error: unknown): ProductFormState {
  const { status, errors, retryAfterSeconds } = productWriteErrors(error);
  if (status === 422 || status === 409) {
    const { fieldErrors, unmapped } = fieldErrorsFromApi(errors);
    return { status: "error", fieldErrors, formError: unmapped.length > 0 ? unmapped.join(" ") : undefined };
  }

  return { status: "error", formError: statusMessage(status, retryAfterSeconds) };
}

function statusMessage(status: number | null, retryAfterSeconds: number | null): string {
  let message = RETRY_MESSAGE;
  if (status === 401) message = SESSION_MESSAGE;
  else if (status === 403) message = UNAUTHORIZED_MESSAGE;
  else if (status === 404) message = MISSING_RESOURCE_MESSAGE;
  else if (status === 429) message = retryAfterSeconds === null ? "Too many attempts. Try again shortly." : `Too many attempts. Try again in ${retryAfterSeconds} seconds.`;
  return message;
}

function readOriginalValues(formData: FormData): ProductFormValues | null {
  const raw = formData.get("original_values");
  if (typeof raw !== "string") return null;
  try {
    const parsed: unknown = JSON.parse(raw);
    return isProductFormValues(parsed) ? parsed : null;
  } catch {
    return null;
  }
}

function isProductFormValues(value: unknown): value is ProductFormValues {
  if (typeof value !== "object" || value === null || Array.isArray(value)) return false;
  const record = value as Record<string, unknown>;
  return ["name", "slug", "description", "productType", "priceAmount", "categoryId"].every((key) => typeof record[key] === "string")
    && typeof record.isActive === "boolean"
    && typeof record.isPublished === "boolean";
}

function readTextValue(formData: FormData, name: string): string | null {
  const value = formData.get(name);
  return typeof value === "string" && value.trim().length > 0 ? value : null;
}
