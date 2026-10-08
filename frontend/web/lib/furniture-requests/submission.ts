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
type RequestFieldValue = string | number | RequestDimensions;
type RequestPayload = Record<string, RequestFieldValue>;
const DIMENSION_FIELDS = ["length", "width", "height"] as const;

export function validateFurnitureRequest(values: FurnitureRequestFormValues): FurnitureRequestFieldErrors {
  const errors: FurnitureRequestFieldErrors = {};
  addContactErrors(errors, values);
  addQuantityError(errors, values.quantity);
  addDimensionErrors(errors, values);
  addOptionalTextErrors(errors, values);
  if (values.attachment) addAttachmentError(errors, values.attachment);

  return errors;
}

function addContactErrors(errors: FurnitureRequestFieldErrors, values: FurnitureRequestFormValues): void {
  const name = values.name.trim();
  const phone = values.phone.trim();
  const email = values.email.trim();

  if (!name) errors.name = "Enter your name.";
  else if (name.length > 120) errors.name = "Use 120 characters or fewer.";
  if (!phone && !email) errors.contact = "Enter a phone number or email address.";
  if (phone.length > 30) errors.contact = "Use a phone number with 30 characters or fewer.";
  if (email && (!isEmail(email) || email.length > 255)) errors.contact = "Enter a valid email address with 255 characters or fewer.";
}

function addQuantityError(errors: FurnitureRequestFieldErrors, quantity: string): void {
  if (quantity.trim() && !isWholeNumberInRange(quantity, 1, 100)) errors.quantity = "Quantity must be a whole number from 1 to 100.";
}

function addDimensionErrors(errors: FurnitureRequestFieldErrors, values: FurnitureRequestFormValues): void {
  for (const field of DIMENSION_FIELDS) {
    if (values[field].trim() && !isPositiveDimension(values[field])) errors[field] = "Enter a measurement greater than 0 and no more than 10,000 cm.";
  }
}

function addOptionalTextErrors(errors: FurnitureRequestFieldErrors, values: FurnitureRequestFormValues): void {
  if (values.material.length > 500) errors.material = "Use 500 characters or fewer.";
  if (values.color.length > 200) errors.color = "Use 200 characters or fewer.";
  if (values.notes.length > 5000) errors.notes = "Use 5,000 characters or fewer.";
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
  const result: MutableRequestDimensions = {};

  for (const field of DIMENSION_FIELDS) if (values[field].trim()) result[field] = Number(values[field]);
  return Object.keys(result).length ? { ...result, unit: "cm" } : undefined;
}

function appendMultipartValue(formData: FormData, key: string, value: RequestFieldValue): void {
  if (typeof value === "object") {
    for (const [dimension, amount] of Object.entries(value)) formData.append(`dimensions[${dimension}]`, String(amount));
    return;
  }
  formData.append(key, value.toString());
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
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}
