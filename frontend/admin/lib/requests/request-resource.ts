import type { ApiPagination } from "@/lib/api/types";

/**
 * Defensive parser for the operational Furniture Request resource (REQ-004/005).
 * Every documented field is read from Laravel. Required and structural values —
 * identifiers, status, reference, timestamps, the attachment collection and the
 * nullable discriminators (`product_id`, `user_id`, an attachment `url`) — are
 * rejected when unreadable rather than reconstructed, because a guessed value
 * would fabricate a different business state.
 */
export interface RequestProductSummary {
  readonly id: string;
  readonly name: string;
  readonly slug: string;
}

export interface RequestDimensions {
  readonly length: number | null;
  readonly width: number | null;
  readonly height: number | null;
  readonly unit: string;
}

export interface RequestAttachmentRecord {
  readonly id: string;
  readonly filename: string;
  readonly contentType: string;
  readonly size: number;
  readonly url: string | null;
}

export interface OperationalRequest {
  readonly id: string;
  readonly productId: string | null;
  readonly product: RequestProductSummary | null;
  readonly quantity: number | null;
  readonly name: string;
  readonly phone: string | null;
  readonly email: string | null;
  readonly dimensions: RequestDimensions | null;
  readonly material: string | null;
  readonly color: string | null;
  readonly notes: string | null;
  readonly requestStatus: string;
  readonly requestReference: string;
  readonly staffInternalNotes: string | null;
  readonly userId: string | null;
  readonly attachments: readonly RequestAttachmentRecord[];
  readonly createdAt: string;
  readonly updatedAt: string;
}

export interface RequestPage {
  readonly requests: readonly OperationalRequest[];
  readonly pagination: ApiPagination;
}

/** Raised when a response cannot be read as the documented request resource. */
export class RequestResourceError extends Error {
  constructor() {
    super("The request response did not match the documented resource.");
    this.name = "RequestResourceError";
  }
}

export function parseOperationalRequest(value: unknown): OperationalRequest {
  if (!isRecord(value)) throw new RequestResourceError();

  const id = readString(value.id);
  const name = readString(value.name);
  const requestStatus = readString(value.request_status);
  const requestReference = readString(value.request_reference);
  const createdAt = readString(value.created_at);
  const updatedAt = readString(value.updated_at);

  if (
    id === null ||
    name === null ||
    requestStatus === null ||
    requestReference === null ||
    createdAt === null ||
    updatedAt === null
  ) {
    throw new RequestResourceError();
  }

  return {
    id,
    productId: readNullableString(value.product_id),
    product: parseProduct(value.product),
    quantity: parseQuantity(value.quantity),
    name,
    phone: readNullableString(value.phone),
    email: readNullableString(value.email),
    dimensions: parseDimensions(value.dimensions),
    material: readNullableString(value.material),
    color: readNullableString(value.color),
    notes: readNullableString(value.notes),
    requestStatus,
    requestReference,
    staffInternalNotes: readNullableString(value.staff_internal_notes),
    userId: readNullableString(value.user_id),
    attachments: parseAttachments(value.attachments),
    createdAt,
    updatedAt,
  };
}

export function parseRequestPage(value: unknown): RequestPage {
  if (!isRecord(value)) throw new RequestResourceError();
  return { requests: parseRequestList(value.data), pagination: parsePagination(value.meta) };
}

function parseRequestList(value: unknown): readonly OperationalRequest[] {
  if (!Array.isArray(value)) throw new RequestResourceError();
  return value.map(parseOperationalRequest);
}

/** A malformed `quantity` is a broken response, never "unspecified". */
function parseQuantity(value: unknown): number | null {
  if (value === null || value === undefined) return null;
  const quantity = readNonNegativeInteger(value);
  if (quantity === null) throw new RequestResourceError();
  return quantity;
}

function parseProduct(value: unknown): RequestProductSummary | null {
  if (value === null || value === undefined) return null;
  if (!isRecord(value)) throw new RequestResourceError();
  const id = readString(value.id);
  const name = readString(value.name);
  const slug = readString(value.slug);
  if (id === null || name === null || slug === null) throw new RequestResourceError();
  return { id, name, slug };
}

function parseDimensions(value: unknown): RequestDimensions | null {
  if (value === null || value === undefined) return null;
  if (!isRecord(value)) throw new RequestResourceError();
  const unit = readString(value.unit);
  if (unit === null) throw new RequestResourceError();
  return {
    length: readNullableMeasurement(value.length),
    width: readNullableMeasurement(value.width),
    height: readNullableMeasurement(value.height),
    unit,
  };
}

function parseAttachments(value: unknown): readonly RequestAttachmentRecord[] {
  if (!Array.isArray(value)) throw new RequestResourceError();
  return value.map((entry) => {
    if (!isRecord(entry)) throw new RequestResourceError();
    const id = readString(entry.id);
    const filename = readString(entry.filename);
    const contentType = readString(entry.content_type);
    const size = readNonNegativeInteger(entry.size);
    if (id === null || filename === null || contentType === null || size === null) {
      throw new RequestResourceError();
    }
    return { id, filename, contentType, size, url: readNullableString(entry.url) };
  });
}

function parsePagination(meta: unknown): ApiPagination {
  if (!isRecord(meta) || !isRecord(meta.pagination)) throw new RequestResourceError();
  const source = meta.pagination;
  const currentPage = readPositiveInteger(source.current_page);
  const perPage = readPositiveInteger(source.per_page);
  const total = readNonNegativeInteger(source.total);
  const lastPage = readPositiveInteger(source.last_page);

  if (currentPage === null || perPage === null || total === null || lastPage === null) {
    throw new RequestResourceError();
  }

  return {
    current_page: currentPage,
    per_page: perPage,
    total,
    last_page: lastPage,
    has_next: source.has_next === true,
    has_previous: source.has_previous === true,
  };
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function readString(value: unknown): string | null {
  return typeof value === "string" && value.trim().length > 0 ? value : null;
}

/** `null`/absent is a real absence; any other non-string is an unreadable response. */
function readNullableString(value: unknown): string | null {
  if (value === null || value === undefined) return null;
  if (typeof value !== "string") throw new RequestResourceError();
  return value.trim().length > 0 ? value : null;
}

function readNullableMeasurement(value: unknown): number | null {
  if (value === null || value === undefined) return null;
  if (typeof value === "number" && Number.isFinite(value) && value >= 0) return value;
  throw new RequestResourceError();
}

function readPositiveInteger(value: unknown): number | null {
  return typeof value === "number" && Number.isInteger(value) && value >= 1 ? value : null;
}

function readNonNegativeInteger(value: unknown): number | null {
  return typeof value === "number" && Number.isInteger(value) && value >= 0 ? value : null;
}
