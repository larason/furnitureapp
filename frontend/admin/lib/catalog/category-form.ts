import type { ApiErrorItem, JsonValue } from "@/lib/api/types";
import { safeImageUrl } from "@/lib/catalog/category-presentation";
import type { CategoryDetail } from "@/lib/catalog/category-resource";

/**
 * Form ↔ contract mapping for the approved category create/update requests.
 * Field names are the frozen request keys. Laravel trims `name`/`slug`, requires
 * a kebab-case slug and a well-formed image URL; the form mirrors that as
 * advisory feedback only — the backend stays authoritative.
 */
export const CATEGORY_NAME_MAX_LENGTH = 120;
export const CATEGORY_SLUG_MAX_LENGTH = 255;
export const CATEGORY_DESCRIPTION_MAX_LENGTH = 1000;
export const CATEGORY_IMAGE_MAX_LENGTH = 255;
const CATEGORY_SLUG_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

export const CATEGORY_FORM_FIELDS = {
  name: "name",
  slug: "slug",
  description: "description",
  image: "image",
} as const;

type CategoryFormField = (typeof CATEGORY_FORM_FIELDS)[keyof typeof CATEGORY_FORM_FIELDS];

export interface CategoryFormValues {
  readonly name: string;
  readonly slug: string;
  readonly description: string;
  readonly image: string;
}

export interface CategoryFormState {
  readonly status: "idle" | "error";
  readonly formError?: string;
  readonly fieldErrors?: Readonly<Record<string, string>>;
}

export interface CategoryPayloadResult {
  readonly payload: Readonly<Record<string, JsonValue>> | null;
  readonly fieldErrors: Readonly<Record<string, string>>;
}

export const INITIAL_CATEGORY_FORM_STATE: CategoryFormState = { status: "idle" };

/** Empty create defaults: nothing here is a saved value or a fabricated record. */
export const EMPTY_CATEGORY_FORM_VALUES: CategoryFormValues = { name: "", slug: "", description: "", image: "" };

const API_FIELD_MAP: Readonly<Record<string, CategoryFormField>> = {
  name: "name",
  slug: "slug",
  description: "description",
  image: "image",
};

export function readCategoryFormValues(formData: FormData): CategoryFormValues {
  return {
    name: readText(formData, CATEGORY_FORM_FIELDS.name),
    slug: readText(formData, CATEGORY_FORM_FIELDS.slug),
    description: readText(formData, CATEGORY_FORM_FIELDS.description),
    image: readText(formData, CATEGORY_FORM_FIELDS.image),
  };
}

export function categoryFormValuesFromCategory(category: CategoryDetail): CategoryFormValues {
  return {
    name: category.name,
    slug: category.slug,
    description: category.description ?? "",
    image: category.image?.url ?? "",
  };
}

export function buildCreateCategoryPayload(values: CategoryFormValues): CategoryPayloadResult {
  const fieldErrors = validateValues(values);
  if (Object.keys(fieldErrors).length > 0) return { payload: null, fieldErrors };

  return {
    payload: compactPayload({
      name: values.name.trim(),
      slug: values.slug.trim(),
      description: optionalText(values.description),
      image: optionalText(values.image),
    }),
    fieldErrors,
  };
}

/** Sends only the fields the operator actually changed (partial update). */
export function buildUpdateCategoryPayload(values: CategoryFormValues, original: CategoryFormValues): CategoryPayloadResult {
  const fieldErrors = validateValues(values);
  if (Object.keys(fieldErrors).length > 0) return { payload: null, fieldErrors };

  const payload = compactPayload({
    name: changed(values.name.trim(), original.name.trim()) ? values.name.trim() : undefined,
    slug: changed(values.slug.trim(), original.slug.trim()) ? values.slug.trim() : undefined,
    description: changed(values.description.trim(), original.description.trim()) ? clearableText(values.description) : undefined,
    image: changed(values.image.trim(), original.image.trim()) ? clearableText(values.image) : undefined,
  });

  return Object.keys(payload).length > 0
    ? { payload, fieldErrors }
    : { payload: null, fieldErrors: { ...fieldErrors, [CATEGORY_FORM_FIELDS.name]: "Change at least one field before saving." } };
}

export function fieldErrorsFromApi(errors: readonly ApiErrorItem[]): { readonly fieldErrors: Readonly<Record<string, string>>; readonly unmapped: readonly string[] } {
  const fieldErrors: Record<string, string> = {};
  const unmapped: string[] = [];

  for (const error of errors) {
    const field = typeof error.field === "string" ? API_FIELD_MAP[error.field] ?? null : null;
    if (field === null) unmapped.push(error.message);
    else fieldErrors[field] = error.message;
  }

  return { fieldErrors, unmapped };
}

/** Advisory mirror of the backend rules; Laravel remains authoritative. */
function validateValues(values: CategoryFormValues): Record<string, string> {
  const errors: Record<string, string> = {};
  const name = values.name.trim();
  const slug = values.slug.trim();
  const description = values.description.trim();
  const image = values.image.trim();

  if (name.length === 0) errors[CATEGORY_FORM_FIELDS.name] = "Enter a category name.";
  else if (name.length > CATEGORY_NAME_MAX_LENGTH) errors[CATEGORY_FORM_FIELDS.name] = `Name must be ${CATEGORY_NAME_MAX_LENGTH} characters or fewer.`;

  if (slug.length === 0) errors[CATEGORY_FORM_FIELDS.slug] = "Enter a URL slug.";
  else if (slug.length > CATEGORY_SLUG_MAX_LENGTH || !CATEGORY_SLUG_PATTERN.test(slug)) errors[CATEGORY_FORM_FIELDS.slug] = "Use lowercase letters, numbers and single hyphens.";

  if (description.length > CATEGORY_DESCRIPTION_MAX_LENGTH) errors[CATEGORY_FORM_FIELDS.description] = `Description must be ${CATEGORY_DESCRIPTION_MAX_LENGTH} characters or fewer.`;
  if (image.length > 0 && (image.length > CATEGORY_IMAGE_MAX_LENGTH || safeImageUrl(image) === null)) errors[CATEGORY_FORM_FIELDS.image] = "Enter a full http(s) image address.";

  return errors;
}

function optionalText(value: string): string | undefined {
  const text = value.trim();
  return text.length > 0 ? text : undefined;
}

function clearableText(value: string): string | null {
  const text = value.trim();
  return text.length > 0 ? text : null;
}

function compactPayload(source: Readonly<Record<string, JsonValue | undefined>>): Readonly<Record<string, JsonValue>> {
  return Object.fromEntries(Object.entries(source).filter(([, value]) => value !== undefined)) as Record<string, JsonValue>;
}

function changed(next: string, previous: string): boolean {
  return next !== previous;
}

function readText(formData: FormData, name: string): string {
  const value = formData.get(name);
  return typeof value === "string" ? value : "";
}
