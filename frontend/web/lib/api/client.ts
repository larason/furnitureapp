export type JsonValue =
  | string
  | number
  | boolean
  | null
  | readonly JsonValue[]
  | { readonly [key: string]: JsonValue };

export type ApiMethod = "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
export type ApiQueryValue = string | number | boolean | null | undefined;
export type ApiQuery = Readonly<Record<string, ApiQueryValue | readonly ApiQueryValue[]>> | URLSearchParams;

export interface ApiPagination {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  has_next: boolean;
  has_previous: boolean;
}

export interface ApiMeta {
  pagination?: ApiPagination;
  unread_count?: number;
  request_id?: string;
}

export interface ApiSuccess<T> {
  data: T;
  meta?: ApiMeta;
}

export interface ApiErrorItem {
  code: string;
  message: string;
  field?: string;
  details?: Readonly<Record<string, unknown>>;
}

export type ApiRequestBody = JsonValue | FormData;

export interface ApiRequestOptions {
  path: string;
  method?: ApiMethod;
  query?: ApiQuery;
  headers?: HeadersInit;
  body?: ApiRequestBody;
  signal?: AbortSignal;
  timeoutMs?: number;
  cache?: RequestCache;
  next?: {
    revalidate?: number | false;
    tags?: readonly string[];
  };
}

export interface ApiClientOptions {
  baseUrl?: string;
  fetchImpl?: typeof fetch;
  defaultTimeoutMs?: number;
}

export class ApiConfigurationError extends Error {
  constructor(message: string) {
    super(message);
    this.name = "ApiConfigurationError";
  }
}

export class ApiError extends Error {
  readonly kind: "api" | "invalid-response";

  constructor(
    readonly status: number,
    readonly errors: readonly ApiErrorItem[],
    readonly requestId?: string,
    readonly retryAfterSeconds?: number,
    kind: "api" | "invalid-response" = "api",
  ) {
    super(kind === "api" ? "The API request failed." : "The API returned an invalid response.");
    this.name = "ApiError";
    this.kind = kind;
  }
}

export class ApiTransportError extends Error {
  constructor(readonly kind: "network" | "timeout" | "aborted", cause?: unknown) {
    super(transportMessage(kind), { cause });
    this.name = "ApiTransportError";
  }
}

const API_PREFIX = "/api/v1/";
const DEFAULT_TIMEOUT_MS = 10_000;
const MAX_TIMEOUT_MS = 2_147_483_647;
const LOCAL_HOSTS = new Set(["localhost", "127.0.0.1", "::1", "[::1]"]);

export type RequestFunction = <T = unknown>(options: ApiRequestOptions) => Promise<ApiSuccess<T> | undefined>;

export function createApiClient(options: ApiClientOptions = {}): RequestFunction {
  const fetchImpl = options.fetchImpl ?? fetch;
  const defaultTimeoutMs = options.defaultTimeoutMs ?? DEFAULT_TIMEOUT_MS;

  return async function apiRequest<T = unknown>(request: ApiRequestOptions): Promise<ApiSuccess<T> | undefined> {
    const baseUrl = resolveBaseUrl(options.baseUrl);
    const timeoutMs = validateTimeout(request.timeoutMs ?? defaultTimeoutMs);
    const method = request.method ?? "GET";
    const url = buildApiUrl(baseUrl, request.path, request.query);
    const init = buildRequestInit(request, method);
    const abort = createAbortContext(request.signal, timeoutMs);

    try {
      assertRequestActive(abort.didTimeout(), request.signal);
      const response = await fetchImpl(url, { ...init, signal: abort.controller.signal });
      assertRequestActive(abort.didTimeout(), request.signal);

      const result = await decodeResponse<T>(response);
      assertRequestActive(abort.didTimeout(), request.signal);

      return result;
    } catch (error) {
      throw normalizeTransportError(error, abort.didTimeout(), request.signal);
    } finally {
      abort.cleanup();
    }
  };
}

function resolveBaseUrl(configuredUrl?: string): string {
  const value = configuredUrl ?? (typeof process === "undefined" ? undefined : process.env.API_BASE_URL);
  if (!value?.trim()) {
    throw new ApiConfigurationError("API_BASE_URL is required to call the Laravel API.");
  }

  let url: URL;
  try {
    url = new URL(value);
  } catch {
    throw new ApiConfigurationError("API_BASE_URL must be a valid HTTP(S) origin.");
  }

  const validProtocol = url.protocol === "https:" || (url.protocol === "http:" && LOCAL_HOSTS.has(url.hostname));
  if (!validProtocol || url.username || url.password || url.pathname !== "/" || url.search || url.hash) {
    throw new ApiConfigurationError("API_BASE_URL must be an HTTPS origin; HTTP is allowed only for localhost development.");
  }

  return url.origin;
}

function buildApiUrl(baseUrl: string, path: string, query?: ApiQuery): string {
  const segments = normalizePath(path);
  const url = new URL(`${API_PREFIX}${segments}`, baseUrl);
  appendQuery(url.searchParams, query);
  return url.toString();
}

function normalizePath(path: string): string {
  if (!path || path.includes("?") || path.includes("#") || path.includes("\\") || /^(?:[a-z][a-z\d+.-]*:|\/\/)/i.test(path)) {
    throw new ApiConfigurationError("API resource path must be a relative path without query or fragment.");
  }

  const segments = path.replace(/^\//, "").split("/");
  if (segments.some((segment) => !segment || isDotSegment(segment))) {
    throw new ApiConfigurationError("API resource path contains an invalid segment.");
  }
  if (segments[0] === "api" && segments[1] === "v1") {
    throw new ApiConfigurationError("API resource paths must omit the /api/v1 prefix.");
  }

  return segments.join("/");
}

function isDotSegment(segment: string): boolean {
  try {
    const decoded = decodeURIComponent(segment);
    return decoded === "." || decoded === "..";
  } catch {
    throw new ApiConfigurationError("API resource path contains invalid encoding.");
  }
}

function appendQuery(params: URLSearchParams, query?: ApiQuery): void {
  if (query instanceof URLSearchParams) {
    for (const [key, value] of query) {
      params.append(key, value);
    }
    return;
  }
  if (!query) {
    return;
  }

  for (const [key, value] of Object.entries(query)) {
    const values = Array.isArray(value) ? value : [value];
    for (const item of values) {
      appendQueryValue(params, key, item);
    }
  }
}

function appendQueryValue(
  params: URLSearchParams,
  key: string,
  value: ApiQueryValue,
): void {
  if (value === undefined || value === null) {
    return;
  }
  if (typeof value === "number" && !Number.isFinite(value)) {
    throw new ApiConfigurationError(`Query parameter "${key}" must be a finite number.`);
  }
  if (typeof value !== "string" && typeof value !== "number" && typeof value !== "boolean") {
    throw new ApiConfigurationError(`Query parameter "${key}" has an unsupported value.`);
  }
  params.append(key, String(value));
}

function buildRequestInit(
  request: ApiRequestOptions,
  method: ApiMethod,
): RequestInit & { next?: NonNullable<ApiRequestOptions["next"]> } {
  const headers = new Headers(request.headers);
  if (!headers.has("Accept")) {
    headers.set("Accept", "application/json");
  }

  const init: RequestInit & { next?: NonNullable<ApiRequestOptions["next"]> } = {
    method,
    headers,
    body: buildRequestBody(request.body, method, headers),
    cache: request.cache,
  };
  if (request.next && typeof window === "undefined") {
    init.next = { ...request.next, tags: request.next.tags ? [...request.next.tags] : undefined };
  }
  return init;
}

function buildRequestBody(
  body: ApiRequestOptions["body"],
  method: ApiMethod,
  headers: Headers,
): BodyInit | undefined {
  if (body === undefined) {
    return undefined;
  }
  if (method === "GET") {
    throw new ApiConfigurationError("GET requests cannot include a body.");
  }
  if (isFormData(body)) {
    if (headers.has("Content-Type")) {
      throw new ApiConfigurationError("Do not set Content-Type when sending FormData.");
    }
    return body;
  }
  if (!headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }
  return JSON.stringify(body);
}

function isFormData(body: ApiRequestBody): body is FormData {
  return typeof FormData !== "undefined" && body instanceof FormData;
}

function validateTimeout(timeoutMs: number): number {
  if (!Number.isSafeInteger(timeoutMs) || timeoutMs < 1 || timeoutMs > MAX_TIMEOUT_MS) {
    throw new ApiConfigurationError(`timeoutMs must be an integer from 1 to ${MAX_TIMEOUT_MS}.`);
  }
  return timeoutMs;
}

function assertRequestActive(timedOut: boolean, signal?: AbortSignal): void {
  if (timedOut) {
    throw new ApiTransportError("timeout");
  }
  if (signal?.aborted) {
    throw new ApiTransportError("aborted", signal.reason);
  }
}

function createAbortContext(signal: AbortSignal | undefined, timeoutMs: number) {
  const controller = new AbortController();
  let timedOut = false;
  const abortForCaller = () => controller.abort(signal?.reason);
  if (signal?.aborted) {
    abortForCaller();
  } else {
    signal?.addEventListener("abort", abortForCaller, { once: true });
  }

  const timer = setTimeout(() => {
    timedOut = true;
    controller.abort();
  }, timeoutMs);

  return {
    controller,
    didTimeout: () => timedOut,
    cleanup: () => {
      clearTimeout(timer);
      signal?.removeEventListener("abort", abortForCaller);
    },
  };
}

async function decodeResponse<T>(response: Response): Promise<ApiSuccess<T> | undefined> {
  if (response.status === 204) {
    return undefined;
  }

  const responseId = response.headers.get("X-Request-Id") ?? undefined;
  const text = await response.text();
  let payload: unknown;
  try {
    payload = JSON.parse(text) as unknown;
  } catch {
    throw invalidApiResponse(response.status, responseId, response.status === 429 ? retryAfter(response) : undefined);
  }

  if (!response.ok) {
    throw parseApiError(response, payload, responseId);
  }
  return parseSuccess<T>(response.status, payload, responseId);
}

function parseSuccess<T>(status: number, payload: unknown, responseId?: string): ApiSuccess<T> {
  if (!isRecord(payload) || !("data" in payload)) {
    throw invalidApiResponse(status, responseId);
  }
  const meta = parseMeta(payload.meta, status, responseId);
  if (responseId && !meta?.request_id) {
    return { data: payload.data as T, meta: { ...meta, request_id: responseId } };
  }
  return { data: payload.data as T, meta };
}

function parseMeta(value: unknown, status: number, responseId?: string): ApiMeta | undefined {
  if (value === undefined) {
    return responseId ? { request_id: responseId } : undefined;
  }
  if (!isRecord(value)) {
    throw invalidApiResponse(status, responseId);
  }

  const meta: ApiMeta = {};
  if (value.request_id !== undefined) {
    if (typeof value.request_id !== "string") {
      throw invalidApiResponse(status, responseId);
    }
    meta.request_id = value.request_id;
  }
  if (value.pagination !== undefined) {
    meta.pagination = parsePagination(value.pagination, status, responseId);
  }
  if (value.unread_count !== undefined) {
    if (
      typeof value.unread_count !== "number" ||
      !Number.isSafeInteger(value.unread_count) ||
      value.unread_count < 0
    ) {
      throw invalidApiResponse(status, responseId);
    }
    meta.unread_count = value.unread_count;
  }
  return meta;
}

function parsePagination(value: unknown, status: number, responseId?: string): ApiPagination {
  if (!isRecord(value)) {
    throw invalidApiResponse(status, responseId);
  }
  const { current_page, per_page, total, last_page, has_next, has_previous } = value;
  const numericFields = [current_page, per_page, total, last_page];
  if (
    numericFields.some((field) => typeof field !== "number" || !Number.isSafeInteger(field)) ||
    typeof has_next !== "boolean" || typeof has_previous !== "boolean" ||
    (current_page as number) < 1 || (per_page as number) < 1 || (per_page as number) > 100 ||
    (total as number) < 0 || (last_page as number) < 1
  ) {
    throw invalidApiResponse(status, responseId);
  }
  return { current_page: current_page as number, per_page: per_page as number, total: total as number, last_page: last_page as number, has_next, has_previous };
}

function parseApiError(response: Response, payload: unknown, responseId?: string): ApiError {
  if (!isRecord(payload) || !Array.isArray(payload.errors) || !isRecord(payload.meta) || typeof payload.meta.request_id !== "string") {
    return invalidApiResponse(response.status, responseId, response.status === 429 ? retryAfter(response) : undefined);
  }

  const errors = payload.errors.map(parseErrorItem);
  if (errors.some((error) => error === undefined) || errors.length === 0) {
    return invalidApiResponse(response.status, responseId, response.status === 429 ? retryAfter(response) : undefined);
  }
  return new ApiError(response.status, errors as ApiErrorItem[], payload.meta.request_id, response.status === 429 ? retryAfter(response) : undefined);
}

function parseErrorItem(value: unknown): ApiErrorItem | undefined {
  if (!isRecord(value) || typeof value.code !== "string" || typeof value.message !== "string") {
    return undefined;
  }
  if (value.field !== undefined && typeof value.field !== "string") {
    return undefined;
  }
  if (value.details !== undefined && !isRecord(value.details)) {
    return undefined;
  }
  return { code: value.code, message: value.message, field: value.field as string | undefined, details: value.details as Record<string, unknown> | undefined };
}

function invalidApiResponse(status: number, requestId?: string, retryAfterSeconds?: number): ApiError {
  return new ApiError(status, [], requestId, retryAfterSeconds, "invalid-response");
}

function retryAfter(response: Response): number | undefined {
  const value = response.headers.get("Retry-After");
  if (!value || !/^\d+$/.test(value)) {
    return undefined;
  }
  const seconds = Number(value);
  return Number.isSafeInteger(seconds) ? seconds : undefined;
}

function normalizeTransportError(error: unknown, timedOut: boolean, signal?: AbortSignal): Error {
  if (error instanceof ApiError || error instanceof ApiTransportError || error instanceof ApiConfigurationError) {
    return error;
  }
  if (timedOut) {
    return new ApiTransportError("timeout", error);
  }
  if (signal?.aborted) {
    return new ApiTransportError("aborted", error);
  }
  return new ApiTransportError("network", error);
}

function transportMessage(kind: ApiTransportError["kind"]): string {
  if (kind === "timeout") {
    return "The API request timed out.";
  }
  if (kind === "aborted") {
    return "The API request was cancelled.";
  }
  return "The API could not be reached.";
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

const apiRequest = createApiClient();

export { apiRequest };
