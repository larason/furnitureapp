"use server";

import { revalidatePath } from "next/cache";
import { resolveAdminRouteAccess } from "@/lib/auth/route-guard";
import { requireOperationalIdentity } from "@/lib/auth/server-operational-identity";
import { imageValidationMessage, readProductImageDraft, PRODUCT_IMAGE_FIELD, type ProductImageFormState } from "@/lib/catalog/product-image-form";
import { productWriteErrors, submitProductImage } from "@/lib/catalog/products-api";
import { adminRoute } from "@/lib/navigation/admin-navigation";
import { ADMIN_PRODUCT_PATHS, ADMIN_ROUTE_PATHS } from "@/lib/navigation/admin-route-paths";

const UNAUTHORIZED_MESSAGE = "You are not authorized to manage products.";
const SESSION_MESSAGE = "Your session is no longer valid. Sign out and sign in again.";
const MISSING_RESOURCE_MESSAGE = "The product is no longer available.";
const RETRY_MESSAGE = "The product service is unavailable. Try again shortly.";
const TOO_LARGE_MESSAGE = "The image is larger than the server accepts. Choose a smaller file.";
const INVALID_IMAGE_MESSAGE = "The image could not be accepted. Check the file type and size.";
const SUCCESS_MESSAGE = "Image uploaded.";

const PRODUCT_ROUTE = adminRoute(ADMIN_ROUTE_PATHS.products);

type UploadPreparation =
  | { readonly ok: true; readonly productId: string; readonly form: FormData }
  | { readonly ok: false; readonly state: ProductImageFormState };

/**
 * Server Functions are directly POST-reachable, so the upload repeats the exact
 * Laravel-backed `products.manage` requirement before reading the file.
 */
export async function uploadProductImageAction(previous: ProductImageFormState, formData: FormData): Promise<ProductImageFormState> {
  const prepared = await prepareUpload(formData);
  return prepared.ok ? submitUpload(prepared) : prepared.state;
}

async function prepareUpload(formData: FormData): Promise<UploadPreparation> {
  const access = resolveAdminRouteAccess(await requireOperationalIdentity(), { permission: PRODUCT_ROUTE.permission, role: PRODUCT_ROUTE.role });
  if (access.kind !== "granted") return { ok: false, state: { status: "error", formError: UNAUTHORIZED_MESSAGE } };

  const draft = readProductImageDraft(formData);
  if (!draft.ok) return { ok: false, state: draft.state };

  return { ok: true, productId: draft.productId, form: imageOnlyForm(draft.file) };
}

async function submitUpload(prepared: Extract<UploadPreparation, { ok: true }>): Promise<ProductImageFormState> {
  try {
    await submitProductImage(prepared.productId, prepared.form);
  } catch (error) {
    return uploadFailureState(error);
  }

  revalidatePath(ADMIN_PRODUCT_PATHS.detail(prepared.productId));
  return { status: "success", message: SUCCESS_MESSAGE };
}

function imageOnlyForm(file: File): FormData {
  const form = new FormData();
  form.set(PRODUCT_IMAGE_FIELD, file, file.name);
  return form;
}

function uploadFailureState(error: unknown): ProductImageFormState {
  const { status, errors, retryAfterSeconds } = productWriteErrors(error);
  if (status === 422) return { status: "error", fieldError: imageValidationMessage(errors, INVALID_IMAGE_MESSAGE) };
  return { status: "error", formError: statusMessage(status, retryAfterSeconds) };
}

const STATUS_MESSAGES: Readonly<Record<number, string>> = {
  401: SESSION_MESSAGE,
  403: UNAUTHORIZED_MESSAGE,
  404: MISSING_RESOURCE_MESSAGE,
  413: TOO_LARGE_MESSAGE,
};

function statusMessage(status: number | null, retryAfterSeconds: number | null): string {
  if (status === 429) return retryAfterSeconds === null ? "Too many attempts. Try again shortly." : `Too many attempts. Try again in ${retryAfterSeconds} seconds.`;
  return status === null ? RETRY_MESSAGE : STATUS_MESSAGES[status] ?? RETRY_MESSAGE;
}
