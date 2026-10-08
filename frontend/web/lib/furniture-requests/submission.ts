import type { JsonValue } from "@/lib/api/client";

export const MAX_ATTACHMENT_BYTES = 5 * 1024 * 1024;

const ACCEPTED_ATTACHMENT_TYPES = new Set([
  "image/jpeg",
  "image/png",
  "image/webp",
  "application/pdf",
]);
const ACCEPTED_ATTACHMENT_EXTENSIONS = [".jpg", ".jpeg", ".png", ".webp", ".pdf"];

export type FurnitureRequestFormValues = Readonly<{
  name: string;
  phone: string;
  email: string;
  quantity: string;
  length: string;
  width: string;
  height: string;
  material: string;
  color: string;
  notes: string;
  attachment: Blob | null;
}>;

export type FurnitureRequestFieldErrors = Partial<Record<"name" | "contact" | "quantity" | "length" | "width" | "height" | "material" | "color" | "notes" | "attachment", string>>;

type RequestDimensions = Readonly<{ length?: number; width?: number; height?: number; unit: "cm" }>;
type MutableRequestDimensions = { length?: number; width?: number; height?: number };
type RequestPayload = Record<string, JsonValue>;

export function validateFurnitureRequest(values: FurnitureRequestFormValues): FurnitureRequestFieldErrors {
  const errors: FurnitureRequestFieldErrors = {};
  const dimensions = ["length", "width", "height"] as const;
  const name = values.name.trim();
  const contactProvided = Boolean(values.phone.trim() || values.email.trim());

  if (!name) errors.name = "Enter your name.";
  else if (name.length > 120) errors.name = "Use 120 characters or fewer.";
  if (!contactProvided) errors.contact = "Enter a phone number or email address.";
  if (values.phone.trim().length > 30) errors.contact = "Use a phone number with 30 characters or fewer.";
  if (values.email.trim() && (!isEmail(values.email.trim()) || values.email.trim().length > 255)) errors.contact = "Enter a valid email address with 255 characters or fewer.";
  if (values.quantity.trim() && !isWholeNumberInRange(values.quantity, 1, 100)) errors.quantity = "Quantity must be a whole number from 1 to 100.";

  for (const field of dimensions) {
    if (values[field].trim() && !isPositiveDimension(values[field])) {
      errors[field] = "Enter a measurement greater than 0 and no more than 10,000 cm.";
    }
  }
  if (values.material.length > 500) errors.material = "Use 500 characters or fewer.";
  if (values.color.length > 200) errors.color = "Use 200 characters or fewer.";
  if (values.notes.length > 5000) errors.notes = "Use 5,000 characters or fewer.";
  if (values.attachment) addAttachmentError(errors, values.attachment);

  return errors;
}

export function serializeFurnitureRequest(values: FurnitureRequestFormValues, productId?: string): RequestPayload | FormData {
  const fields = requestFields(values, productId);
  if (!values.attachment) return fields;

  const formData = new FormData();
  for (const [key, value] of Object.entries(fields)) appendMultipartValue(formData, key, value);
  formData.append("attachment", values.attachment);
  return formData;
}

function requestFields(values: FurnitureRequestFormValues, productId?: string): RequestPayload {
  const dimensions = parsedDimensions(values);
  const fields: RequestPayload = { name: values.name.trim() };
  const optionalText = [
    ["product_id", productId],
    ["phone", values.phone.trim()],
    ["email", values.email.trim()],
    ["material", values.material],
    ["color", values.color],
    ["notes", values.notes],
  ] as const;

  for (const [key, value] of optionalText) if (value) fields[key] = value;
  if (values.quantity.trim()) fields.quantity = Number(values.quantity);
  if (dimensions) fields.dimensions = dimensions;
  return fields;
}

function parsedDimensions(values: FurnitureRequestFormValues): RequestDimensions | undefined {
  const dimensions = ["length", "width", "height"] as const;
  const result: MutableRequestDimensions = {};

  for (const field of dimensions) if (values[field].trim()) result[field] = Number(values[field]);
  return Object.keys(result).length ? { ...result, unit: "cm" } : undefined;
}

function appendMultipartValue(formData: FormData, key: string, value: JsonValue): void {
  if (key === "dimensions" && isDimensions(value)) {
    for (const [dimension, amount] of Object.entries(value)) formData.append(`dimensions[${dimension}]`, String(amount));
    return;
  }
  formData.append(key, String(value));
}

function addAttachmentError(errors: FurnitureRequestFieldErrors, attachment: Blob): void {
  const name = "name" in attachment && typeof attachment.name === "string" ? attachment.name.toLowerCase() : "";
  const acceptedExtension = ACCEPTED_ATTACHMENT_EXTENSIONS.some((extension) => name.endsWith(extension));
  if (!ACCEPTED_ATTACHMENT_TYPES.has(attachment.type) && !acceptedExtension) errors.attachment = "Choose a JPG, PNG, WebP, or PDF file.";
  else if (attachment.size === 0) errors.attachment = "Choose a file that is not empty.";
  else if (attachment.size > MAX_ATTACHMENT_BYTES) errors.attachment = "Choose a file no larger than 5 MiB.";
}

function isWholeNumberInRange(value: string, minimum: number, maximum: number): boolean {
  const number = Number(value);
  return Number.isInteger(number) && number >= minimum && number <= maximum;
}

function isPositiveDimension(value: string): boolean {
  const number = Number(value);
  return Number.isFinite(number) && number > 0 && number <= 10_000;
}

function isEmail(value: string): boolean {
  return /^\S+@\S+\.\S+$/.test(value);
}

function isDimensions(value: JsonValue): value is RequestDimensions {
  return typeof value === "object" && value !== null && !Array.isArray(value) && "unit" in value;
}
