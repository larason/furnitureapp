import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import ts from "typescript";

const source = await readFile(new URL("../client.ts", import.meta.url), "utf8");
const { outputText } = ts.transpileModule(source, {
  compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 },
});
const clientModule = await import(`data:text/javascript;base64,${Buffer.from(outputText).toString("base64")}`);
const { ApiError, ApiTransportError, createApiClient } = clientModule;

const createClient = (fetchImpl, options = {}) =>
  createApiClient({ baseUrl: "https://api.test/", fetchImpl, ...options });

test("constructs the versioned URL and safely serializes query values", async () => {
  let requestedUrl;
  const apiRequest = createClient(async (url) => {
    requestedUrl = new URL(url);
    return Response.json({ data: [] });
  });

  await apiRequest({
    path: "/products",
    query: { search: "chair & table/é", enabled: false, tag: ["wood", "linen"], omitted: undefined },
  });

  assert.equal(requestedUrl.pathname, "/api/v1/products");
  assert.equal(requestedUrl.searchParams.get("search"), "chair & table/é");
  assert.deepEqual(requestedUrl.searchParams.getAll("tag"), ["wood", "linen"]);
  assert.equal(requestedUrl.searchParams.get("enabled"), "false");
  assert.equal(requestedUrl.searchParams.has("omitted"), false);
});

test("URL-encodes Unicode and spaces in resource path segments", async () => {
  let requestedUrl;
  const apiRequest = createClient(async (url) => {
    requestedUrl = new URL(url);
    return Response.json({ data: {} });
  });

  await apiRequest({ path: "/products/chair & linen/é" });

  assert.equal(requestedUrl.pathname, "/api/v1/products/chair%20&%20linen/%C3%A9");
});

test("rejects absolute paths and duplicate API version prefixes", async () => {
  const apiRequest = createClient(async () => Response.json({ data: {} }));

  await assert.rejects(apiRequest({ path: "https://other.test/products" }), /resource path/);
  await assert.rejects(apiRequest({ path: "/api/v1/products" }), /omit the \/api\/v1 prefix/);
});

test("sets JSON defaults and preserves caller-provided authorization", async () => {
  let requestInit;
  const apiRequest = createClient(async (_url, init) => {
    requestInit = init;
    return Response.json({ data: { id: "product-1" } });
  });
  const callerHeaders = new Headers({ Authorization: "Bearer test-token", "X-Trace": "one" });

  const result = await apiRequest({ path: "/products", method: "POST", body: { name: "Table" }, headers: callerHeaders });

  assert.equal(requestInit.headers.get("Accept"), "application/json");
  assert.equal(requestInit.headers.get("Content-Type"), "application/json");
  assert.equal(requestInit.headers.get("Authorization"), "Bearer test-token");
  assert.equal(requestInit.body, JSON.stringify({ name: "Table" }));
  assert.equal(callerHeaders.has("Accept"), false);
  assert.deepEqual(result.data, { id: "product-1" });
});

test("does not leak request headers into subsequent calls", async () => {
  const requests = [];
  const apiRequest = createClient(async (_url, init) => {
    requests.push(new Headers(init.headers));
    return Response.json({ data: null });
  });

  await apiRequest({ path: "/one", headers: { Authorization: "Bearer private" } });
  await apiRequest({ path: "/two" });

  assert.equal(requests[1].has("Authorization"), false);
});

test("does not add JSON headers to bodyless calls and supports FormData", async () => {
  const requests = [];
  const apiRequest = createClient(async (_url, init) => {
    requests.push(init);
    return Response.json({ data: {} });
  });
  const form = new FormData();
  form.set("attachment", new Blob(["file"]), "furniture.txt");

  await apiRequest({ path: "/products" });
  await apiRequest({ path: "/uploads", method: "POST", body: form });

  assert.equal(requests[0].headers.get("Content-Type"), null);
  assert.equal(requests[1].headers.get("Content-Type"), null);
  assert.equal(requests[1].body, form);
});

test("rejects bodies on GET requests", async () => {
  const apiRequest = createClient(async () => Response.json({ data: {} }));

  await assert.rejects(apiRequest({ path: "/products", body: {} }), /cannot include a body/);
});

test("preserves pagination and notification unread-count metadata and handles 204", async () => {
  const apiRequest = createClient(async (_url, init) =>
    init.method === "DELETE"
      ? new Response(null, { status: 204 })
      : Response.json({ data: [], meta: { pagination: { current_page: 1, per_page: 20, total: 0, last_page: 1, has_next: false, has_previous: false }, unread_count: 3 } }),
  );

  const collection = await apiRequest({ path: "/products" });
  const removed = await apiRequest({ path: "/products/item", method: "DELETE" });

  assert.equal(collection.meta.pagination.last_page, 1);
  assert.equal(collection.meta.unread_count, 3);
  assert.equal(removed, undefined);
});

test("rejects invalid notification unread-count metadata", async () => {
  const apiRequest = createClient(async () =>
    Response.json({ data: [], meta: { unread_count: -1 } }),
  );

  await assert.rejects(
    apiRequest({ path: "/me/notifications" }),
    (error) => error instanceof ApiError && error.kind === "invalid-response",
  );
});

test("preserves structured API errors, validation details, request ID, and Retry-After", async () => {
  const apiRequest = createClient(async () =>
    Response.json(
      { errors: [{ code: "INVALID_VALUE", message: "Choose a valid option.", field: "product_type", details: { allowed: ["IN_STOCK"] } }], meta: { request_id: "req-123" } },
      { status: 429, headers: { "Retry-After": "12", "X-Request-Id": "header-id" } },
    ),
  );

  await assert.rejects(apiRequest({ path: "/products" }), (error) => {
    assert.ok(error instanceof ApiError);
    assert.equal(error.status, 429);
    assert.equal(error.errors[0].field, "product_type");
    assert.equal(error.errors[0].details.allowed[0], "IN_STOCK");
    assert.equal(error.requestId, "req-123");
    assert.equal(error.retryAfterSeconds, 12);
    assert.equal(error.message.includes("Choose a valid"), false);
    return true;
  });
});

test("preserves authentication, authorization, not-found, and validation statuses", async () => {
  for (const status of [401, 403, 404, 422]) {
    const apiRequest = createClient(async () =>
      Response.json({ errors: [{ code: "RESOURCE_NOT_FOUND", message: "Unavailable." }], meta: { request_id: "req-123" } }, { status }),
    );

    await assert.rejects(apiRequest({ path: "/products/item" }), (error) => error instanceof ApiError && error.status === status);
  }
});

test("represents non-JSON server errors safely", async () => {
  const apiRequest = createClient(async () => new Response("database password secret", { status: 500, headers: { "Content-Type": "text/html" } }));

  await assert.rejects(apiRequest({ path: "/products" }), (error) => {
    assert.ok(error instanceof ApiError);
    assert.equal(error.status, 500);
    assert.deepEqual(error.errors, []);
    assert.equal(error.message.includes("database password"), false);
    assert.equal(error.kind, "invalid-response");
    return true;
  });
});

test("distinguishes network failures", async () => {
  const apiRequest = createClient(async () => {
    throw new TypeError("socket details");
  });

  await assert.rejects(apiRequest({ path: "/products" }), (error) => error instanceof ApiTransportError && error.kind === "network");
});

test("distinguishes timeouts from caller cancellation", async () => {
  const apiRequest = createClient((_url, init) => new Promise((_resolve, reject) => {
    init.signal.addEventListener("abort", () => reject(init.signal.reason), { once: true });
  }));

  await assert.rejects(apiRequest({ path: "/products", timeoutMs: 5 }), (error) => error instanceof ApiTransportError && error.kind === "timeout");

  const controller = new AbortController();
  const aborted = apiRequest({ path: "/products", signal: controller.signal });
  controller.abort();
  await assert.rejects(aborted, (error) => error instanceof ApiTransportError && error.kind === "aborted");

  const alreadyAborted = new AbortController();
  alreadyAborted.abort();
  let wasFetched = false;
  const cancelledClient = createClient(async () => {
    wasFetched = true;
    return Response.json({ data: {} });
  });
  await assert.rejects(cancelledClient({ path: "/products", signal: alreadyAborted.signal }), (error) => error instanceof ApiTransportError && error.kind === "aborted");
  assert.equal(wasFetched, false);
});

test("forwards cache and Next.js revalidation options", async () => {
  let requestInit;
  const apiRequest = createClient(async (_url, init) => {
    requestInit = init;
    return Response.json({ data: {} });
  });

  await apiRequest({ path: "/products", cache: "force-cache", next: { revalidate: 60, tags: ["catalog"] } });

  assert.equal(requestInit.cache, "force-cache");
  assert.equal(requestInit.next.revalidate, 60);
  assert.deepEqual(requestInit.next.tags, ["catalog"]);
});

test("omits Next.js-only fetch options in browser runtime", async () => {
  const previousWindow = globalThis.window;
  let requestInit;
  globalThis.window = {};
  try {
    const apiRequest = createClient(async (_url, init) => {
      requestInit = init;
      return Response.json({ data: {} });
    });
    await apiRequest({ path: "/products", cache: "no-store", next: { revalidate: 30, tags: ["catalog"] } });
  } finally {
    if (previousWindow === undefined) {
      delete globalThis.window;
    } else {
      globalThis.window = previousWindow;
    }
  }

  assert.equal(requestInit.cache, "no-store");
  assert.equal("next" in requestInit, false);
});

test("validates required origin configuration", async () => {
  const missing = createApiClient({ baseUrl: "" });
  const invalid = createApiClient({ baseUrl: "http://api.test" });

  await assert.rejects(missing({ path: "/products" }), /API_BASE_URL/);
  await assert.rejects(invalid({ path: "/products" }), /HTTPS/);
});

test("uses API_BASE_URL when configured and has no production localhost fallback", async () => {
  const originalEnvironment = process.env.NODE_ENV;
  const originalApiBaseUrl = process.env.API_BASE_URL;
  let requestedUrl;
  try {
    process.env.NODE_ENV = "development";
    process.env.API_BASE_URL = "http://127.0.0.1:8000";
    const developmentClient = createApiClient({ fetchImpl: async (url) => {
      requestedUrl = new URL(url);
      return Response.json({ data: {} });
    } });
    await developmentClient({ path: "/products" });

    process.env.NODE_ENV = "production";
    delete process.env.API_BASE_URL;
    const productionClient = createApiClient({ fetchImpl: async () => Response.json({ data: {} }) });
    await assert.rejects(productionClient({ path: "/products" }), /API_BASE_URL/);
  } finally {
    if (originalEnvironment === undefined) {
      delete process.env.NODE_ENV;
    } else {
      process.env.NODE_ENV = originalEnvironment;
    }
    if (originalApiBaseUrl === undefined) {
      delete process.env.API_BASE_URL;
    } else {
      process.env.API_BASE_URL = originalApiBaseUrl;
    }
  }

  assert.equal(requestedUrl.origin, "http://127.0.0.1:8000");
  assert.equal(requestedUrl.pathname, "/api/v1/products");
});
