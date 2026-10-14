export type JsonValue = string | number | boolean | null | readonly JsonValue[] | { readonly [key: string]: JsonValue };
export type ApiMethod = "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
export type ApiQueryValue = string | number | boolean | null | undefined;
export type ApiQuery = Readonly<Record<string, ApiQueryValue | readonly ApiQueryValue[]>> | URLSearchParams;
export type ApiRequestBody = JsonValue | FormData;

export interface ApiPagination { current_page: number; per_page: number; total: number; last_page: number; has_next: boolean; has_previous: boolean; }
export interface ApiMeta { pagination?: ApiPagination; unread_count?: number; request_id?: string; }
export interface ApiSuccess<T> { data: T; meta?: ApiMeta; }
export interface ApiErrorItem { code: string; message: string; field?: string; details?: Readonly<Record<string, unknown>>; }
