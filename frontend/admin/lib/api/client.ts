import { ApiConfigurationError, ApiError, ApiTransportError } from "./errors";
import type { ApiErrorItem, ApiMeta, ApiMethod, ApiQuery, ApiRequestBody, ApiSuccess } from "./types";

const API_PREFIX = "/api/v1/";
const DEFAULT_TIMEOUT_MS = 10_000;
const LOCAL_HOSTS = new Set(["localhost", "127.0.0.1", "::1", "[::1]"]);

export interface AdminApiClientOptions {
  baseUrl?: string;
  tokenProvider?: () => Promise<string | null>;
  fetchImpl?: typeof fetch;
  defaultTimeoutMs?: number;
}

export interface AdminApiRequest { path: string; method?: ApiMethod; query?: ApiQuery; body?: ApiRequestBody; signal?: AbortSignal; timeoutMs?: number; }
export type AdminApiClient = <T>(request: AdminApiRequest) => Promise<ApiSuccess<T>>;

export function createAdminApiClient(options: AdminApiClientOptions = {}): AdminApiClient {
  const fetchImpl = options.fetchImpl ?? fetch;
  const defaultTimeoutMs = options.defaultTimeoutMs ?? DEFAULT_TIMEOUT_MS;

  return async function request<T>(input: AdminApiRequest): Promise<ApiSuccess<T>> {
    const baseUrl = resolveBaseUrl(options.baseUrl);
    const method = input.method ?? "GET";
    const token = options.tokenProvider ? await options.tokenProvider() : null;
    const headers = new Headers({ Accept: "application/json" });
    if (token) headers.set("Authorization", `Bearer ${token}`);
    const body = createBody(input.body, method, headers);
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), validateTimeout(input.timeoutMs ?? defaultTimeoutMs));
    const signal = input.signal ? combineSignals(input.signal, controller.signal) : controller.signal;

    try {
      const response = await fetchImpl(buildUrl(baseUrl, input.path, input.query), { method, headers, body, signal, cache: token ? "no-store" : undefined });
      if (signal.aborted) throw new ApiTransportError("aborted");
      const payload = await decodePayload(response);
      if (!response.ok) throw new ApiError(response.status, payload.errors ?? [], payload.meta?.request_id, retryAfter(response.headers));
      if (!isSuccessPayload<T>(payload)) throw new ApiError(response.status, [{ code: "INVALID_RESPONSE", message: "The API returned an invalid response." }]);
      return payload;
    } catch (error) {
      if (error instanceof ApiConfigurationError || error instanceof ApiError || error instanceof ApiTransportError) throw error;
      if (input.signal?.aborted) throw new ApiTransportError("aborted", error);
      if (controller.signal.aborted) throw new ApiTransportError("timeout", error);
      throw new ApiTransportError("network", error);
    } finally {
      clearTimeout(timeout);
    }
  };
}

function resolveBaseUrl(value?: string): string {
  const configured = value ?? process.env.API_BASE_URL;
  if (!configured?.trim()) throw new ApiConfigurationError("API_BASE_URL is required to call the Laravel API.");
  let url: URL;
  try { url = new URL(configured); } catch { throw new ApiConfigurationError("API_BASE_URL must be a valid HTTP(S) origin."); }
  const validProtocol = url.protocol === "https:" || (url.protocol === "http:" && LOCAL_HOSTS.has(url.hostname));
  if (!validProtocol || url.username || url.password || url.pathname !== "/" || url.search || url.hash) throw new ApiConfigurationError("API_BASE_URL must be an HTTPS origin; HTTP is allowed only for localhost development.");
  return url.origin;
}

function buildUrl(baseUrl: string, path: string, query?: ApiQuery): string {
  if (!path || path.includes("?") || path.includes("#") || path.includes("\\") || /^(?:[a-z][a-z\d+.-]*:|\/\/)/i.test(path)) throw new ApiConfigurationError("API resource path must be relative and must not include a query or fragment.");
  const segments = path.replace(/^\//, "").split("/");
  if (segments.some(isInvalidPathSegment)) throw new ApiConfigurationError("API resource path contains an invalid segment.");
  if (segments[0] === "api" && segments[1] === "v1") throw new ApiConfigurationError("API resource paths must omit the /api/v1 prefix.");
  const url = new URL(`${API_PREFIX}${segments.join("/")}`, baseUrl);
  if (!url.pathname.startsWith(API_PREFIX)) throw new ApiConfigurationError("API resource path escaped the /api/v1 namespace.");
  if (query instanceof URLSearchParams) query.forEach((value, key) => url.searchParams.append(key, value));
  else Object.entries(query ?? {}).forEach(([key, value]) => (Array.isArray(value) ? value : [value]).forEach((item) => item !== null && item !== undefined && url.searchParams.append(key, String(item))));
  return url.toString();
}

function isInvalidPathSegment(segment: string): boolean {
  if (!segment) return true;
  let decoded: string;
  try { decoded = decodeURIComponent(segment); } catch { throw new ApiConfigurationError("API resource path contains invalid encoding."); }
  return decoded === "." || decoded === ".." || decoded.includes("/") || decoded.includes("\\");
}

function createBody(body: ApiRequestBody | undefined, method: ApiMethod, headers: Headers): BodyInit | undefined {
  if (method === "GET" && body !== undefined) throw new ApiConfigurationError("GET requests cannot have a body.");
  if (body instanceof FormData) return body;
  if (body === undefined) return undefined;
  headers.set("Content-Type", "application/json");
  return JSON.stringify(body);
}

async function decodePayload(response: Response): Promise<{ data?: unknown; meta?: ApiMeta; errors?: readonly ApiErrorItem[] }> {
  try {
    const payload: unknown = await response.json();
    if (!isRecord(payload)) throw new Error("Response payload is not an object.");
    return payload as { data?: unknown; meta?: ApiMeta; errors?: readonly ApiErrorItem[] };
  }
  catch { throw new ApiError(response.status, [{ code: "INVALID_RESPONSE", message: "The API returned an invalid response." }]); }
}

function isRecord(value: unknown): value is Record<string, unknown> { return typeof value === "object" && value !== null && !Array.isArray(value); }
function isSuccessPayload<T>(payload: { data?: unknown }): payload is ApiSuccess<T> { return Object.hasOwn(payload, "data"); }
function retryAfter(headers: Headers): number | undefined { const value = Number(headers.get("Retry-After")); return Number.isFinite(value) && value >= 0 ? value : undefined; }
function validateTimeout(value: number): number {
  if (!Number.isFinite(value) || value <= 0) {
    throw new ApiConfigurationError("API timeout must be a positive finite number.");
  }

  return value;
}
function combineSignals(first: AbortSignal, second: AbortSignal): AbortSignal {
  const controller = new AbortController();
  const abort = () => controller.abort();
  if (first.aborted || second.aborted) {
    controller.abort();
    return controller.signal;
  }
  first.addEventListener("abort", abort, { once: true });
  second.addEventListener("abort", abort, { once: true });
  return controller.signal;
}
