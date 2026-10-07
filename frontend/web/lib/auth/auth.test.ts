import assert from "node:assert/strict";
import test from "node:test";
import { NextRequest } from "next/server";
import { ApiError, type ApiRequestOptions, type RequestFunction } from "@/lib/api/client";
import { isSiteRouteImplemented } from "@/components/layout/site-navigation";
import { ClerkAuthenticationRequiredError, createAuthenticatedLaravelRequest } from "@/lib/auth/laravel-request";
import { preflightCatalogDetail } from "@/proxy";
import { read } from "../test-utils/frontend-test-helpers";

test("authenticated Laravel requests attach a request-scoped bearer token and remain private", async () => {
  const requests: ApiRequestOptions[] = [];
  const apiRequest: RequestFunction = async <T>(request: ApiRequestOptions) => {
    requests.push(request);
    return { data: {} as T };
  };
  const request = createAuthenticatedLaravelRequest(
    async () => ({ isAuthenticated: true, sessionStatus: "active", getToken: async () => "test-token" }),
    apiRequest,
  );

  await request({ path: "/me", headers: { "X-Request-Source": "test" }, cache: "force-cache" });

  assert.equal(requests.length, 1);
  assert.equal(requests[0].cache, "no-store");
  assert.equal(new Headers(requests[0].headers).get("Authorization"), "Bearer test-token");
  assert.equal(new Headers(requests[0].headers).get("X-Request-Source"), "test");
});

test("pending, signed-out, and tokenless Clerk sessions cannot call Laravel", async () => {
  let callCount = 0;
  const apiRequest: RequestFunction = async <T>(request: ApiRequestOptions) => {
    callCount += 1;
    assert.equal(request.path, "/me");
    return { data: {} as T };
  };
  for (const session of [
    { isAuthenticated: false, sessionStatus: null, getToken: async () => null },
    { isAuthenticated: true, sessionStatus: "pending" as const, getToken: async () => "test-token" },
    { isAuthenticated: true, sessionStatus: "active" as const, getToken: async () => null },
  ]) {
    const request = createAuthenticatedLaravelRequest(async () => session, apiRequest);
    await assert.rejects(request({ path: "/me" }), ClerkAuthenticationRequiredError);
  }
  assert.equal(callCount, 0);
});

test("catalog preflight preserves public routes and returns Laravel detail 404s", async () => {
  const requests: ApiRequestOptions[] = [];
  const requestCatalogResource: RequestFunction = async <T>(request: ApiRequestOptions) => {
    requests.push(request);
    if (request.path.endsWith("missing")) {
      throw new ApiError(404, [{ code: "RESOURCE_NOT_FOUND", message: "Missing" }], "request-id");
    }
    return { data: {} as T };
  };

  const existingProduct = await preflightCatalogDetail(new NextRequest("http://localhost/products/existing"), requestCatalogResource);
  const existingCategory = await preflightCatalogDetail(new NextRequest("http://localhost/categories/existing"), requestCatalogResource);
  const missingProduct = await preflightCatalogDetail(new NextRequest("http://localhost/products/missing"), requestCatalogResource);
  const missingCategory = await preflightCatalogDetail(new NextRequest("http://localhost/categories/missing"), requestCatalogResource);
  const publicRoutes = await Promise.all([
    "/robots.txt",
    "/sitemap.xml",
    "/sign-in",
    "/sign-up",
    "/__clerk/client",
  ].map((path) => preflightCatalogDetail(new NextRequest(`http://localhost${path}`), requestCatalogResource)));

  assert.equal(existingProduct.status, 200);
  assert.equal(existingCategory.status, 200);
  assert.equal(missingProduct.status, 404);
  assert.equal(missingCategory.status, 404);
  assert.deepEqual(publicRoutes.map((response) => response.status), [200, 200, 200, 200, 200]);
  assert.deepEqual(requests.map(({ path, cache }) => ({ path, cache })), [
    { path: "products/existing", cache: "no-store" },
    { path: "categories/existing", cache: "no-store" },
    { path: "products/missing", cache: "no-store" },
    { path: "categories/missing", cache: "no-store" },
  ]);

  const previousDataSource = process.env.HOMEPAGE_DATA_SOURCE;
  process.env.HOMEPAGE_DATA_SOURCE = "fixtures";
  try {
    const fixtureResponse = await preflightCatalogDetail(new NextRequest("http://localhost/products/missing"), requestCatalogResource);
    assert.equal(fixtureResponse.status, 200);
  } finally {
    if (previousDataSource === undefined) {
      delete process.env.HOMEPAGE_DATA_SOURCE;
    } else {
      process.env.HOMEPAGE_DATA_SOURCE = previousDataSource;
    }
  }
  assert.equal(requests.length, 4);
});

test("Clerk integration keeps public catalog routes public and uses canonical auth routes", () => {
  const layout = read("app/layout.tsx");
  const proxy = read("proxy.ts");
  const bridge = read("lib/auth/laravel.ts");
  const apiClient = read("lib/api/client.ts");
  const signIn = read("app/sign-in/[[...sign-in]]/page.tsx");
  const signUp = read("app/sign-up/[[...sign-up]]/page.tsx");
  const navigation = read("components/layout/auth-navigation.tsx");
  const appearance = read("lib/auth/clerk-appearance.ts");
  const authLayout = read("components/auth/auth-page-layout.tsx");

  assert.match(layout, /<ClerkProvider afterSignOutUrl="\/" appearance=\{clerkAppearance\}>/);
  assert.match(proxy, /clerkMiddleware/);
  assert.match(proxy, /preflightCatalogDetail/);
  assert.match(proxy, /"\/__clerk\/\(\.\*\)"/);
  assert.match(proxy, /"\/categories\/:path\*"/);
  assert.match(proxy, /"\/products\/:slug"/);
  assert.doesNotMatch(proxy, /createRouteMatcher|auth\.protect/);
  assert.match(bridge, /import "server-only"/);
  assert.match(bridge, /createAuthenticatedLaravelRequest\(auth, apiRequest\)/);
  assert.match(bridge, /path: "\/me", cache: "no-store"/);
  assert.doesNotMatch(apiClient, /@clerk/);
  assert.match(signIn, /<AuthPageLayout title="Welcome back">\s*<SignIn appearance=\{clerkAuthAppearance\} \/>/);
  assert.match(signUp, /<AuthPageLayout title="Create your account">\s*<SignUp appearance=\{clerkAuthAppearance\} \/>/);
  assert.match(signIn + signUp, /robots: \{ index: false, follow: false \}/);
  assert.match(navigation, /useAuth/);
  assert.match(navigation, /if \(!isLoaded\)/);
  assert.match(navigation, /UserButton/);
  assert.match(appearance, /options: \{\s*elevation: "flush"/);
  assert.match(appearance, /headerTitle: \{\s*fontFamily: "var\(--font-display\)"/);
  assert.doesNotMatch(appearance.match(/export const clerkAuthAppearance[\s\S]*/)?.[0] ?? "", /boxShadow/);
  assert.match(appearance, /colorBorder: "var\(--text-primary\)"/);
  assert.match(appearance, /borderRadius: "0"/);
  assert.match(appearance, /border: "var\(--border-width\) solid var\(--text-primary\)"/);
  assert.match(appearance, /padding: "var\(--space-6\)"/);
  assert.doesNotMatch(appearance.match(/export const clerkAppearance[\s\S]*?as const;/)?.[0] ?? "", /card:/);
  assert.match(authLayout, /src=\{AUTH_HERO_SOURCE\}/);
  assert.match(authLayout, /aspectRatio: "var\(--media-editorial\)"/);
  assert.match(authLayout, /lg: '"title \." "form hero"'/);
  assert.match(authLayout, /objectFit: "contain"/);
  assert.doesNotMatch(authLayout, /priority/);
  assert.equal(isSiteRouteImplemented("/sign-in"), true);
  assert.equal(isSiteRouteImplemented("/sign-up"), true);
  assert.equal(isSiteRouteImplemented("/products"), true);
  assert.equal(isSiteRouteImplemented("/search"), true);
});
