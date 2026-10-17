import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { createAdminApiClient } from "../lib/api/client";
import { ApiConfigurationError, ApiError } from "../lib/api/errors";
import { isIdempotencyKey, newIdempotencyKey } from "../lib/api/idempotency";

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

// ---------------------------------------------------------------------------
// Idempotency-Key (`INV-003` requires it; every other call must be unaffected)
// ---------------------------------------------------------------------------

const IDEMPOTENCY_KEY = "550e8400-e29b-41d4-a716-446655440000";

test("an inventory adjustment sends the documented Idempotency-Key header and not a body field", async () => {
  let request: Request | undefined;
  let body: string | undefined;
  const client = createAdminApiClient({
    baseUrl: "http://127.0.0.1:8000",
    tokenProvider: async () => "session-token",
    fetchImpl: async (input, init) => {
      request = new Request(input, init);
      body = init?.body as string;
      return new Response(JSON.stringify({ data: { id: "inv_1" } }), { status: 200 });
    },
  });

  await client({ path: "/inventory/inv_1/adjust", method: "POST", body: { quantity_delta: 10, reason: "STOCK_RECEIPT" }, idempotencyKey: IDEMPOTENCY_KEY });

  assert.equal(request?.headers.get("Idempotency-Key"), IDEMPOTENCY_KEY);
  assert.equal(request?.headers.get("Accept"), "application/json", "the documented Accept header must survive the new header.");
  assert.equal(request?.headers.get("Authorization"), "Bearer session-token", "bearer authorization must survive the new header.");
  assert.equal(request?.headers.get("Content-Type"), "application/json");
  assert.equal(request?.cache, "no-store");
  assert.deepEqual(JSON.parse(body ?? "null"), { quantity_delta: 10, reason: "STOCK_RECEIPT" }, "the key is a request header, never a business field.");
  assert.equal((body ?? "").includes(IDEMPOTENCY_KEY), false, "the key must never appear in the serialized body.");
});

test("requests without an idempotency key are byte-for-byte unchanged", async () => {
  let request: Request | undefined;
  const client = createAdminApiClient({
    baseUrl: "http://127.0.0.1:8000",
    tokenProvider: async () => "session-token",
    fetchImpl: async (input, init) => {
      request = new Request(input, init);
      return new Response(JSON.stringify({ data: [] }), { status: 200 });
    },
  });

  await client({ path: "/inventory", query: { page: 1, per_page: 20 } });
  assert.equal(request?.headers.get("Idempotency-Key"), null);
  assert.equal(request?.headers.get("Accept"), "application/json");
  assert.equal(request?.headers.get("Authorization"), "Bearer session-token");
});

test("a malformed idempotency key is rejected before any request leaves the process", async () => {
  let called = false;
  const client = createAdminApiClient({
    baseUrl: "http://127.0.0.1:8000",
    fetchImpl: async () => {
      called = true;
      return new Response(JSON.stringify({ data: {} }), { status: 200 });
    },
  });

  for (const invalid of ["", "not-a-uuid", "550e8400e29b41d4a716446655440000", "550e8400-e29b-41d4-a716-44665544000z", "1"]) {
    await assert.rejects(client({ path: "/inventory/inv_1/adjust", method: "POST", body: {}, idempotencyKey: invalid }), ApiConfigurationError, `${invalid} must be rejected.`);
  }
  assert.equal(called, false, "a rejected key must not reach the network.");
  await assert.rejects(client({ path: "/inventory/inv_1/adjust", method: "POST", body: {}, idempotencyKey: 42 as unknown as string }), ApiConfigurationError);
  assert.equal(called, false);
});

test("the transport builds its own security-sensitive headers from a fixed allow-list", () => {
  const source = readFileSync(fileURLToPath(new URL("../lib/api/client.ts", import.meta.url)), "utf8");
  const written = new Set([...source.matchAll(/headers\.set\(("[^"]+")/g)].map((match) => match[1]));
  assert.deepEqual(
    [...written].sort(),
    ['"Authorization"', '"Content-Type"', '"Idempotency-Key"'].sort(),
    "every header this transport writes must be one of the three documented ones.",
  );
  assert.match(source, /new Headers\(\{ Accept: "application\/json" \}\)/);
  assert.match(source, /headers\.set\("Idempotency-Key", input\.idempotencyKey\)/);
  assert.match(source, /input\.idempotencyKey !== undefined/);
  assert.equal(/headers:\s*input\.headers|\.\.\.input\b/.test(source), false, "a caller must not be able to contribute arbitrary headers.");
  assert.match(source, /idempotencyKey\?: string/, "the option is a narrow, typed field rather than a header bag.");
});

test("a fresh idempotency key is a random UUID that a caller cannot predict or fix", () => {
  const keys = new Set(Array.from({ length: 50 }, () => newIdempotencyKey()));
  assert.equal(keys.size, 50, "every logical operation needs its own key.");
  for (const key of keys) assert.equal(isIdempotencyKey(key), true, `${key} must be a UUID.`);
  assert.equal(isIdempotencyKey(undefined), false);
  assert.equal(isIdempotencyKey("550e8400-e29b-41d4-a716-44665544000"), false);
  const source = readFileSync(fileURLToPath(new URL("../lib/api/idempotency.ts", import.meta.url)), "utf8");
  assert.match(source, /crypto\.randomUUID\(\)/);
  assert.equal(/Date\.now\(|Math\.random\(|performance\.now\(/.test(source), false, "a predictable key is not an idempotency key.");
});
