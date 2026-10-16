"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { resolveAdminRouteAccess } from "@/lib/auth/route-guard";
import { requireOperationalIdentity } from "@/lib/auth/server-operational-identity";
import { categoryWriteErrors, submitCategoryCreate, submitCategoryUpdate } from "@/lib/catalog/categories-api";
import { buildCreateCategoryPayload, buildUpdateCategoryPayload, fieldErrorsFromApi, readCategoryFormValues, type CategoryFormState, type CategoryFormValues } from "@/lib/catalog/category-form";
import type { CategoryDetail } from "@/lib/catalog/category-resource";
import { adminRoute } from "@/lib/navigation/admin-navigation";
import { ADMIN_CATEGORY_PATHS } from "@/lib/navigation/admin-route-paths";

const UNAUTHORIZED_MESSAGE = "You are not authorized to manage categories.";
const SESSION_MESSAGE = "Your session is no longer valid. Sign out and sign in again.";
const MISSING_RESOURCE_MESSAGE = "The selected category is no longer available.";
const RETRY_MESSAGE = "The category service is unavailable. Try again shortly.";
const UNIDENTIFIED_MESSAGE = "The category could not be identified for this update.";

const CATEGORY_ROUTE = adminRoute(ADMIN_CATEGORY_PATHS.list);

type WriteResult = { readonly ok: true; readonly category: CategoryDetail } | { readonly ok: false; readonly state: CategoryFormState };

/**
 * Server Functions are directly POST-reachable, so both write actions repeat the
 * exact Laravel-backed permission requirement before touching category data.
 */
export async function createCategoryAction(previous: CategoryFormState, formData: FormData): Promise<CategoryFormState> {
  const denial = await authorizationFailure();
  if (denial) return denial;

  const { payload, fieldErrors } = buildCreateCategoryPayload(readCategoryFormValues(formData));
  if (payload === null) return { status: "error", fieldErrors };

  const result = await writeCategory(() => submitCategoryCreate(payload));
  if (!result.ok) return result.state;

  revalidatePath(ADMIN_CATEGORY_PATHS.list);
  redirect(ADMIN_CATEGORY_PATHS.detail(result.category.id));
}

export async function updateCategoryAction(previous: CategoryFormState, formData: FormData): Promise<CategoryFormState> {
  const denial = await authorizationFailure();
  if (denial) return denial;

  const identifier = readTextValue(formData, "category_id");
  const original = readOriginalValues(formData);
  if (identifier === null || original === null) return { status: "error", formError: UNIDENTIFIED_MESSAGE };

  const { payload, fieldErrors } = buildUpdateCategoryPayload(readCategoryFormValues(formData), original);
  if (payload === null) return { status: "error", fieldErrors };

  const result = await writeCategory(() => submitCategoryUpdate(identifier, payload));
  if (!result.ok) return result.state;

  revalidatePath(ADMIN_CATEGORY_PATHS.list);
  revalidatePath(ADMIN_CATEGORY_PATHS.detail(result.category.id));
  redirect(ADMIN_CATEGORY_PATHS.detail(result.category.id));
}

async function authorizationFailure(): Promise<CategoryFormState | null> {
  const access = resolveAdminRouteAccess(await requireOperationalIdentity(), { permission: CATEGORY_ROUTE.permission, role: CATEGORY_ROUTE.role });
  return access.kind === "granted" ? null : { status: "error", formError: UNAUTHORIZED_MESSAGE };
}

async function writeCategory(operation: () => Promise<CategoryDetail>): Promise<WriteResult> {
  try {
    return { ok: true, category: await operation() };
  } catch (error) {
    return { ok: false, state: writeFailureState(error) };
  }
}

function writeFailureState(error: unknown): CategoryFormState {
  const { status, errors, retryAfterSeconds } = categoryWriteErrors(error);
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

function readOriginalValues(formData: FormData): CategoryFormValues | null {
  const raw = formData.get("original_values");
  if (typeof raw !== "string") return null;
  try {
    const parsed: unknown = JSON.parse(raw);
    return isCategoryFormValues(parsed) ? parsed : null;
  } catch {
    return null;
  }
}

function isCategoryFormValues(value: unknown): value is CategoryFormValues {
  if (typeof value !== "object" || value === null || Array.isArray(value)) return false;
  const record = value as Record<string, unknown>;
  return ["name", "slug", "description", "image"].every((key) => typeof record[key] === "string");
}

function readTextValue(formData: FormData, name: string): string | null {
  const value = formData.get(name);
  return typeof value === "string" && value.trim().length > 0 ? value : null;
}
