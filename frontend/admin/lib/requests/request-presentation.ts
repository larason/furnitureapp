import { requestStatusLabel, requestStatusTone, type RequestStatusTone } from "@/lib/requests/request-mutations";
import type { RequestDimensions } from "@/lib/requests/request-resource";

/**
 * Presentation helpers for the request module. Values come from Laravel; an
 * absent value renders an explicit statement rather than a blank, a zero or a
 * guessed substitute.
 */

const timestampFormatter = new Intl.DateTimeFormat("en-GB", {
  dateStyle: "medium",
  timeStyle: "short",
  timeZone: "UTC",
});

const integerFormatter = new Intl.NumberFormat("en-TZ");

export const REQUEST_NOT_PROVIDED = "Not provided";
export const REQUEST_QUANTITY_UNSPECIFIED = "Not specified";
const REQUEST_DIMENSIONS_UNSPECIFIED = "Not specified";
const REQUEST_MEASUREMENT_UNSPECIFIED = "Unspecified";
export const REQUEST_TIMESTAMP_UNAVAILABLE = "Not recorded";
export const REQUEST_CUSTOM_FURNITURE = "Custom / general request";
export const REQUEST_FURNITURE_UNAVAILABLE = "Linked product details unavailable";
export const REQUEST_ANONYMOUS_SUBMISSION = "Anonymous submission";
const REQUEST_REFERENCE_UNAVAILABLE = "Reference unavailable";
export const REQUEST_NOTES_EMPTY = "No internal notes recorded.";
export const REQUEST_NOTES_UNAUTHORIZED = "Internal notes are not available with your permissions.";
export const REQUEST_ATTACHMENT_NONE = "No attachment was submitted with this request.";
export const REQUEST_ATTACHMENT_UNAVAILABLE = "Preview or download is not available for this attachment.";
export const REQUEST_ATTACHMENT_TTL_NOTICE = "Opened links are time-limited and may expire; reload the request to refresh them.";
const REQUEST_ATTACHMENT_SIZE_UNAVAILABLE = "Size unavailable";

const CONTENT_TYPE_LABELS: Readonly<Record<string, string>> = {
  "image/jpeg": "JPEG image",
  "image/png": "PNG image",
  "image/webp": "WebP image",
  "application/pdf": "PDF document",
};

const KIB = 1024;
const MIB = 1024 * 1024;

export function formatRequestTimestamp(value: string | null): string | null {
  if (value === null) return null;
  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? null : timestampFormatter.format(parsed);
}

/** A `null` quantity is genuinely unspecified; a number is formatted as returned. */
export function formatRequestQuantity(value: number | null): string {
  if (value === null) return REQUEST_QUANTITY_UNSPECIFIED;
  return Number.isSafeInteger(value) && value >= 0 ? integerFormatter.format(value) : REQUEST_QUANTITY_UNSPECIFIED;
}

interface RequestMeasurement {
  readonly label: string;
  readonly value: string;
}

/** Labelled measurements, with absent ones stated rather than rendered blank. */
function requestDimensionEntries(dimensions: RequestDimensions | null): readonly RequestMeasurement[] {
  if (dimensions === null) return [];
  return [
    { label: "Length", value: measurementValue(dimensions.length, dimensions.unit) },
    { label: "Width", value: measurementValue(dimensions.width, dimensions.unit) },
    { label: "Height", value: measurementValue(dimensions.height, dimensions.unit) },
  ];
}

export function formatRequestDimensions(dimensions: RequestDimensions | null): string {
  if (dimensions === null) return REQUEST_DIMENSIONS_UNSPECIFIED;
  return requestDimensionEntries(dimensions)
    .map((entry) => `${entry.label} ${entry.value}`)
    .join(" · ");
}

export function formatAttachmentSize(bytes: number): string {
  if (!Number.isSafeInteger(bytes) || bytes < 0) return REQUEST_ATTACHMENT_SIZE_UNAVAILABLE;
  if (bytes >= MIB) return `${(bytes / MIB).toFixed(1)} MiB`;
  if (bytes >= KIB) return `${(bytes / KIB).toFixed(1)} KiB`;
  return `${bytes} B`;
}

/** A readable label for the server-detected type; the raw type is the honest fallback. */
export function attachmentContentTypeLabel(contentType: string): string {
  return CONTENT_TYPE_LABELS[contentType] ?? contentType;
}

export interface RequestContactLine {
  readonly label: string;
  readonly value: string;
}

export function requestContactLines(request: Readonly<{ name: string; phone: string | null; email: string | null }>): readonly RequestContactLine[] {
  return [
    { label: "Name", value: request.name },
    { label: "Phone", value: request.phone ?? REQUEST_NOT_PROVIDED },
    { label: "Email", value: request.email ?? REQUEST_NOT_PROVIDED },
  ];
}

/** The linked product name, or an explicit statement when there is no product. */
export function requestFurnitureLabel(request: Readonly<{ productId: string | null; product: Readonly<{ name: string }> | null }>): string {
  if (request.product !== null) return request.product.name;
  return request.productId === null ? REQUEST_CUSTOM_FURNITURE : REQUEST_FURNITURE_UNAVAILABLE;
}

export interface RequestStatusDisplay {
  readonly label: string;
  readonly tone: RequestStatusTone;
}

export function requestStatusDisplay(value: string): RequestStatusDisplay {
  return { label: requestStatusLabel(value), tone: requestStatusTone(value) };
}

/**
 * The internal-note rendering. Without `requests.manage` Laravel never populates
 * the field, so an empty state is stated only when the permission allows the note
 * to be read at all.
 */
export function requestNotesDisplay(record: Readonly<{ staffInternalNotes: string | null }>, notesAuthorized: boolean): string {
  if (!notesAuthorized) return REQUEST_NOTES_UNAUTHORIZED;
  return record.staffInternalNotes === null ? REQUEST_NOTES_EMPTY : record.staffInternalNotes;
}

/** The customer identity statement: anonymous is explicit and never a link. */
export function requestIdentityLabel(userId: string | null): string {
  return userId === null ? REQUEST_ANONYMOUS_SUBMISSION : userId;
}

export function requestHasAttachment(request: Readonly<{ attachments: readonly unknown[] }>): boolean {
  return request.attachments.length > 0;
}

export function requestReferenceDisplay(request: Readonly<{ requestReference: string }>): string {
  return request.requestReference.trim().length > 0 ? request.requestReference : REQUEST_REFERENCE_UNAVAILABLE;
}

function measurementValue(value: number | null, unit: string): string {
  if (value === null) return REQUEST_MEASUREMENT_UNSPECIFIED;
  return `${value} ${unit}`;
}
