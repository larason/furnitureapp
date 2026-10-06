# Website API Client

`client.ts` is the single generic transport boundary from the Next.js website to Laravel. It uses native `fetch`, has no React dependency, and does not implement domain endpoints or business rules.

## Configuration And URL Convention

Set server-only `API_BASE_URL` to the HTTPS API **origin**, without a path, query, or trailing API prefix. HTTP is accepted only for localhost development. Do not use `NEXT_PUBLIC_` for this configuration; it may identify internal infrastructure. There is no frontend `.env.example` convention in this app, so configure it in the deployment environment. Missing or invalid configuration fails explicitly; there is no localhost fallback.

Calls provide an unversioned resource suffix, for example `path: "/products"`; the transport adds `/api/v1` exactly once. Do not pass absolute URLs or user-controlled paths. Browser callers that are intentionally approved for direct API access must provide an explicit, public HTTPS `baseUrl` when creating their client; the server-only environment value is not exposed for browser use.

## Usage

```ts
const result = await apiRequest<ProductCollection>({
  method: "GET",
  path: "/products",
  query: { page: 1, per_page: 20 },
  next: { revalidate: 60, tags: ["public-catalog"] },
});

const products = result?.data;
const pagination = result?.meta?.pagination;
```

`T` describes the success envelope's `data`; it does not runtime-validate the resource. Pagination follows the frozen `meta.pagination` fields. Query values support strings, finite numbers, booleans, repeated scalar arrays, and omitted null/undefined values.

## Errors And Transport

- `ApiError` preserves HTTP status, frozen `errors[]` entries (`code`, `message`, optional `field` and `details`), envelope `meta.request_id`, and numeric-seconds `Retry-After` for 429 responses. Its `Error.message` is a safe generic description; inspect the structured fields for contract behavior. It never redirects or remaps 401/403/404/422.
- Invalid or non-JSON responses become `ApiError` with `kind: "invalid-response"`, status preserved, and no raw body.
- `ApiTransportError.kind` distinguishes `network`, `timeout`, and caller `aborted`; its message excludes transport internals.
- Requests default to a 10-second configurable timeout. Callers may supply an `AbortSignal`; no automatic retries occur.
- JSON bodies receive `Content-Type: application/json`; `FormData` is passed through without setting a multipart content type. GET bodies are rejected.
- `Accept: application/json` is the default. Per-request headers are copied and merged; an Authorization header can be injected by a future caller but is never stored by the client.

## Cache And Authentication Boundaries

The transport selects no global cache policy. Consumers may provide native `cache` options and Next.js `next.revalidate`/`next.tags` options; Next-only options are omitted in browser runtime. Domain/query clients choose freshness later.

Clerk token acquisition, persistence, and user/session state do not belong here. A future authenticated caller obtains a current Clerk token outside the transport and passes `Authorization` for that request only. Laravel remains authoritative for authorization and all business behavior. The client performs no logging of URLs, headers, tokens, bodies, or response internals.
