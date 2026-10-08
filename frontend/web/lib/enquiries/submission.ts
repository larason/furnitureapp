import type { JsonValue } from "@/lib/api/client";

export const MAX_ENQUIRY_ATTACHMENT_BYTES = 5 * 1024 * 1024;

const ACCEPTED_ATTACHMENT_TYPES = new Set(["image/jpeg", "image/png", "image/webp", "application/pdf"]);
const ACCEPTED_ATTACHMENT_EXTENSIONS = [".jpg", ".jpeg", ".png", ".webp", ".pdf"];

export type EnquiryCategory = "GENERAL" | "PRODUCT" | "DELIVERY" | "OTHER";

export type EnquiryFormValues = Readonly<{
  name: string;
  phone: string;
  email: string;
  subject: string;
  message: string;
  category: EnquiryCategory | "";
  attachment: Blob | null;
}>;

export type EnquiryFieldErrors = Partial<Record<"name" | "contact" | "subject" | "message" | "category" | "attachment", string>>;

export function validateEnquiry(values: EnquiryFormValues): EnquiryFieldErrors {
  const errors: EnquiryFieldErrors = {};
  const subjectLength = values.subject.trim().length;
  const messageLength = values.message.trim().length;

  if (!values.name.trim()) errors.name = "Enter your name.";
  else if (values.name.trim().length > 120) errors.name = "Use 120 characters or fewer.";
  if (!values.phone.trim() && !values.email.trim()) errors.contact = "Enter a phone number or email address.";
  else if (values.phone.trim().length > 30) errors.contact = "Use a phone number with 30 characters or fewer.";
  else if (values.email.trim() && (!isEmail(values.email.trim()) || values.email.trim().length > 255)) errors.contact = "Enter a valid email address with 255 characters or fewer.";
  if (subjectLength < 5 || subjectLength > 200) errors.subject = "Enter a subject with 5 to 200 characters.";
  if (messageLength < 10 || messageLength > 5000) errors.message = "Enter a message with 10 to 5,000 characters.";
  if (values.attachment) addAttachmentError(errors, values.attachment);

  return errors;
}

export function serializeEnquiry(values: EnquiryFormValues): Record<string, JsonValue> | FormData {
  const fields: Record<string, JsonValue> = {
    name: values.name.trim(),
    subject: values.subject.trim(),
    message: values.message.trim(),
  };

  if (values.phone.trim()) fields.phone = values.phone.trim();
  if (values.email.trim()) fields.email = values.email.trim();
  if (values.category) fields.category = values.category;
  if (!values.attachment) return fields;

  const formData = new FormData();
  for (const [key, value] of Object.entries(fields)) formData.append(key, String(value));
  formData.append("attachment", values.attachment);
  return formData;
}

function addAttachmentError(errors: EnquiryFieldErrors, attachment: Blob): void {
  const name = "name" in attachment && typeof attachment.name === "string" ? attachment.name.toLowerCase() : "";
  const acceptedExtension = ACCEPTED_ATTACHMENT_EXTENSIONS.some((extension) => name.endsWith(extension));
  if (!ACCEPTED_ATTACHMENT_TYPES.has(attachment.type) && !acceptedExtension) errors.attachment = "Choose a JPG, PNG, WebP, or PDF file.";
  else if (attachment.size === 0) errors.attachment = "Choose a file that is not empty.";
  else if (attachment.size > MAX_ENQUIRY_ATTACHMENT_BYTES) errors.attachment = "Choose a file no larger than 5 MiB.";
}

function isEmail(value: string): boolean {
  return /^\S+@\S+\.\S+$/.test(value);
}
