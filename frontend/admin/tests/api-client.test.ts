import test from "node:test";
import assert from "node:assert/strict";
import { createAdminApiClient } from "../lib/api/client";
import { ApiConfigurationError, ApiError } from "../lib/api/errors";

test("admin API client sends a request-scoped bearer token and preserves pagination", async () => {
  let request: Request | undefined;
  const client = createAdminApiClient({
    baseUrl: "http://127.0.0.1:8000",
    tokenProvider: async () => "session-token",
    fetchImpl: async (input, init) => {
      request = new Request(input, init);
      return new Response(JSON.stringify({ data: [{ id: "req_1" }], meta: { pagination: { current_page: 1, per_page: 20, total: 1, last_page: 1, has_next: false, has_previous: false } } }), { status: 200 });
    },
  });
  const result = await client<{ id: string }[]>({ path: "/requests", query: { page: 1, per_page: 20 } });
  assert.equal(result.meta?.pagination?.total, 1);
  assert.equal(request?.url, "http://127.0.0.1:8000/api/v1/requests?page=1&per_page=20");
  assert.equal(request?.headers.get("Authorization"), "Bearer session-token");
  assert.equal(request?.cache, "no-store");
});

test("admin API client distinguishes forbidden responses", async () => {
  const client = createAdminApiClient({ baseUrl: "http://127.0.0.1:8000", fetchImpl: async () => new Response(JSON.stringify({ errors: [{ code: "FORBIDDEN", message: "Forbidden" }] }), { status: 403 }) });
  await assert.rejects(client({ path: "/admin/staff" }), (error: unknown) => error instanceof ApiError && error.status === 403);
});

test("admin API client rejects an API path with its version prefix", async () => {
  const client = createAdminApiClient({ baseUrl: "http://127.0.0.1:8000" });
  await assert.rejects(client({ path: "/api/v1/requests" }), ApiConfigurationError);
});

test("admin API client preserves an already-aborted caller signal", async () => {
  const controller = new AbortController();
  controller.abort();
  let receivedSignal: AbortSignal | null | undefined;
  const client = createAdminApiClient({ baseUrl: "http://127.0.0.1:8000", fetchImpl: async (_input, init) => {
    receivedSignal = init?.signal;
    return new Response(JSON.stringify({ data: [] }), { status: 200 });
  } });
  await assert.rejects(client({ path: "/requests", signal: controller.signal }), (error: unknown) => error instanceof Error && error.name === "ApiTransportError");
  assert.equal(receivedSignal?.aborted, true);
});

test("admin API client rejects encoded traversal and malformed JSON envelopes", async () => {
  const pathClient = createAdminApiClient({ baseUrl: "http://127.0.0.1:8000", fetchImpl: async () => new Response(JSON.stringify({ data: [] }), { status: 200 }) });
  await assert.rejects(pathClient({ path: "%2e%2e/admin" }), ApiConfigurationError);
  const malformedClient = createAdminApiClient({ baseUrl: "http://127.0.0.1:8000", fetchImpl: async () => new Response("null", { status: 200 }) });
  await assert.rejects(malformedClient({ path: "/requests" }), (error: unknown) => error instanceof ApiError && error.errors[0]?.code === "INVALID_RESPONSE");
});

test("admin API client accepts a 204 No Content mutation without parsing a body", async () => {
  let request: Request | undefined;
  const client = createAdminApiClient({
    baseUrl: "http://127.0.0.1:8000",
    tokenProvider: async () => "session-token",
    fetchImpl: async (input, init) => {
      request = new Request(input, init);
      return new Response(null, { status: 204 });
    },
  });

  const result = await client<void>({ path: "/products/prd_1/images/img_1", method: "DELETE" });
  assert.equal(result.data, undefined);
  assert.equal(request?.method, "DELETE");
  assert.equal(request?.headers.get("Authorization"), "Bearer session-token");
  assert.equal(request?.cache, "no-store");
});

test("admin API client still rejects an empty successful response that is not 204", async () => {
  const client = createAdminApiClient({ baseUrl: "http://127.0.0.1:8000", fetchImpl: async () => new Response("", { status: 200 }) });
  await assert.rejects(client({ path: "/requests" }), (error: unknown) => error instanceof ApiError && error.errors[0]?.code === "INVALID_RESPONSE");
});
