import type { ApiErrorItem, JsonValue } from "@/lib/api/types";
import type { OperationalProduct } from "@/lib/catalog/product-resource";

/**
 * Form ↔ contract mapping for the approved product create/update requests.
 * Field names are the frozen request keys; the form's `price_amount` is entered
 * in major TZS units and converted to Laravel's integer minor units exactly.
 */
export const PRODUCT_NAME_MAX_LENGTH = 200;
export const PRODUCT_SLUG_MAX_LENGTH = 255;
export const PRODUCT_DESCRIPTION_MAX_LENGTH = 5000;
const PRODUCT_CATEGORY_ID_MAX_LENGTH = 100;
const PRODUCT_SLUG_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

const PRODUCT_CURRENCY = "TZS";
const MINOR_UNITS_PER_MAJOR = 100;

/**
 * The frozen write contract types `description` as a non-nullable string, and
 * Laravel's global ConvertEmptyStringsToNull turns a cleared value into `null`,
 * which validation rejects. Silently dropping the change would report a save that
 * never happened, so the save is blocked with this message instead.
 */
export const UNCLEARABLE_DESCRIPTION_MESSAGE = "A saved description cannot be cleared. Leave it unchanged or enter new text.";

export const PRODUCT_FORM_FIELDS = {
  name: "name",
  slug: "slug",
  description: "description",
  productType: "product_type",
  priceAmount: "price_amount",
  categoryId: "category_id",
  isActive: "is_active",
  isPublished: "is_published",
} as const;

type ProductFormField = (typeof PRODUCT_FORM_FIELDS)[keyof typeof PRODUCT_FORM_FIELDS];

export interface ProductFormValues {
  readonly name: string;
  readonly slug: string;
  readonly description: string;
  readonly productType: string;
  readonly priceAmount: string;
  readonly categoryId: string;
  readonly isActive: boolean;
  readonly isPublished: boolean;
}

export interface ProductFormState {
  readonly status: "idle" | "error";
  readonly formError?: string;
  readonly fieldErrors?: Readonly<Record<string, string>>;
}

export interface ProductPayloadResult {
  readonly payload: Readonly<Record<string, JsonValue>> | null;
  readonly fieldErrors: Readonly<Record<string, string>>;
}

export const INITIAL_PRODUCT_FORM_STATE: ProductFormState = { status: "idle" };

/** Empty create defaults: nothing here is a saved value or a fabricated record. */
export const EMPTY_PRODUCT_FORM_VALUES: ProductFormValues = {
  name: "",
  slug: "",
  description: "",
  productType: "IN_STOCK",
  priceAmount: "",
  categoryId: "",
  isActive: true,
  isPublished: false,
};

const API_FIELD_MAP: Readonly<Record<string, ProductFormField>> = {
  name: "name",
  slug: "slug",
  description: "description",
  product_type: "product_type",
  "price.amount": "price_amount",
  "price.currency": "price_amount",
  price: "price_amount",
  category_id: "category_id",
  is_active: "is_active",
  is_published: "is_published",
};

export function readProductFormValues(formData: FormData): ProductFormValues {
  return {
    name: readText(formData, PRODUCT_FORM_FIELDS.name),
    slug: readText(formData, PRODUCT_FORM_FIELDS.slug),
    description: readText(formData, PRODUCT_FORM_FIELDS.description),
    productType: readText(formData, PRODUCT_FORM_FIELDS.productType),
    priceAmount: readText(formData, PRODUCT_FORM_FIELDS.priceAmount),
    categoryId: readText(formData, PRODUCT_FORM_FIELDS.categoryId),
    isActive: formData.get(PRODUCT_FORM_FIELDS.isActive) !== null,
    isPublished: formData.get(PRODUCT_FORM_FIELDS.isPublished) !== null,
  };
}

export function productFormValuesFromProduct(product: OperationalProduct): ProductFormValues {
  return {
    name: product.name,
    slug: product.slug,
    description: product.description ?? "",
    productType: product.productType,
    priceAmount: formatMinorUnitsForInput(product.price.amount),
    categoryId: product.category.id,
    isActive: product.isActive === true,
    isPublished: product.isPublished === true,
  };
}

export function buildCreatePayload(values: ProductFormValues): ProductPayloadResult {
  const fieldErrors = validateValues(values);
  const amount = parseMajorUnitsToMinorUnits(values.priceAmount);
  if (amount === null) fieldErrors[PRODUCT_FORM_FIELDS.priceAmount] = "Enter a valid price amount.";
  if (Object.keys(fieldErrors).length > 0 || amount === null) return { payload: null, fieldErrors };

  return {
    payload: compactPayload({
      name: values.name.trim(),
      slug: values.slug.trim(),
      description: values.description.trim().length > 0 ? values.description.trim() : undefined,
      product_type: values.productType,
      price: { amount, currency: PRODUCT_CURRENCY },
      category_id: values.categoryId.trim(),
      is_active: values.isActive,
      is_published: values.isPublished,
    }),
    fieldErrors,
  };
}

/** Sends only the fields the operator actually changed (partial update). */
export function buildUpdatePayload(values: ProductFormValues, original: ProductFormValues): ProductPayloadResult {
  const fieldErrors = validateValues(values);
  const amount = parseMajorUnitsToMinorUnits(values.priceAmount);
  if (amount === null) fieldErrors[PRODUCT_FORM_FIELDS.priceAmount] = "Enter a valid price amount.";
  if (clearsSavedDescription(values, original)) fieldErrors[PRODUCT_FORM_FIELDS.description] = UNCLEARABLE_DESCRIPTION_MESSAGE;
  if (Object.keys(fieldErrors).length > 0 || amount === null) return { payload: null, fieldErrors };

  const payload = compactPayload({
    name: changed(values.name.trim(), original.name.trim()) ? values.name.trim() : undefined,
    slug: changed(values.slug.trim(), original.slug.trim()) ? values.slug.trim() : undefined,
    description: changed(values.description.trim(), original.description.trim()) ? values.description.trim() : undefined,
    product_type: changed(values.productType, original.productType) ? values.productType : undefined,
    price: amount !== parseMajorUnitsToMinorUnits(original.priceAmount) ? { amount, currency: PRODUCT_CURRENCY } : undefined,
    category_id: changed(values.categoryId, original.categoryId) ? values.categoryId : undefined,
    is_active: values.isActive !== original.isActive ? values.isActive : undefined,
    is_published: values.isPublished !== original.isPublished ? values.isPublished : undefined,
  });

  return Object.keys(payload).length > 0 ? { payload, fieldErrors } : { payload: null, fieldErrors: { ...fieldErrors, [PRODUCT_FORM_FIELDS.name]: "Change at least one field before saving." } };
}

export function fieldErrorsFromApi(errors: readonly ApiErrorItem[]): { readonly fieldErrors: Readonly<Record<string, string>>; readonly unmapped: readonly string[] } {
  const fieldErrors: Record<string, string> = {};
  const unmapped: string[] = [];

  for (const error of errors) {
    const field = mapApiField(error);
    if (field === null) unmapped.push(error.message);
    else fieldErrors[field] = error.message;
  }

  return { fieldErrors, unmapped };
}

export function parseMajorUnitsToMinorUnits(value: string): number | null {
  const match = /^(\d+)(?:\.(\d{1,2}))?$/.exec(value.trim().replace(/,/g, ""));
  if (match === null) return null;
  const minor = Number(match[1]) * MINOR_UNITS_PER_MAJOR + Number((match[2] ?? "").padEnd(2, "0"));
  return Number.isSafeInteger(minor) ? minor : null;
}

export function formatMinorUnitsForInput(amount: number): string {
  if (!Number.isSafeInteger(amount) || amount < 0) return "";
  const major = Math.floor(amount / MINOR_UNITS_PER_MAJOR);
  return `${major}.${String(amount - major * MINOR_UNITS_PER_MAJOR).padStart(2, "0")}`;
}

function validateValues(values: ProductFormValues): Record<string, string> {
  const errors: Record<string, string> = {};
  const name = values.name.trim();
  const slug = values.slug.trim();
  const description = values.description.trim();

  if (name.length === 0) errors[PRODUCT_FORM_FIELDS.name] = "Enter a product name.";
  else if (name.length > PRODUCT_NAME_MAX_LENGTH) errors[PRODUCT_FORM_FIELDS.name] = `Name must be ${PRODUCT_NAME_MAX_LENGTH} characters or fewer.`;

  if (slug.length === 0) errors[PRODUCT_FORM_FIELDS.slug] = "Enter a URL slug.";
  else if (slug.length > PRODUCT_SLUG_MAX_LENGTH || !PRODUCT_SLUG_PATTERN.test(slug)) errors[PRODUCT_FORM_FIELDS.slug] = "Use lowercase letters, numbers and single hyphens.";

  if (description.length > PRODUCT_DESCRIPTION_MAX_LENGTH) errors[PRODUCT_FORM_FIELDS.description] = `Description must be ${PRODUCT_DESCRIPTION_MAX_LENGTH} characters or fewer.`;
  if (values.productType !== "IN_STOCK" && values.productType !== "MADE_TO_ORDER") errors[PRODUCT_FORM_FIELDS.productType] = "Select a product type.";
  if (values.categoryId.trim().length === 0) errors[PRODUCT_FORM_FIELDS.categoryId] = "Select a category.";
  else if (values.categoryId.trim().length > PRODUCT_CATEGORY_ID_MAX_LENGTH) errors[PRODUCT_FORM_FIELDS.categoryId] = "Select a valid category.";

  return errors;
}

function compactPayload(source: Readonly<Record<string, JsonValue | undefined>>): Readonly<Record<string, JsonValue>> {
  return Object.fromEntries(Object.entries(source).filter(([, value]) => value !== undefined)) as Record<string, JsonValue>;
}

function changed(next: string, previous: string): boolean {
  return next !== previous;
}

/** True when an edit empties a description that already has saved text. */
function clearsSavedDescription(values: ProductFormValues, original: ProductFormValues): boolean {
  return original.description.trim().length > 0 && values.description.trim().length === 0;
}

function mapApiField(error: ApiErrorItem): ProductFormField | null {
  return typeof error.field === "string" ? API_FIELD_MAP[error.field] ?? null : null;
}

function readText(formData: FormData, name: string): string {
  const value = formData.get(name);
  return typeof value === "string" ? value : "";
}
