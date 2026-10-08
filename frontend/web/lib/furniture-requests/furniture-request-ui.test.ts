import assert from "node:assert/strict";
import test from "node:test";
import { read } from "../test-utils/frontend-test-helpers";

test("furniture request route keeps product context server-resolved and private", () => {
  const page = read("app/furniture-requests/page.tsx");

  assert.match(page, /getProductDetail/);
  assert.match(page, /product_type !== "MADE_TO_ORDER"/);
  assert.match(page, /robots: \{ index: false, follow: true \}/);
  assert.match(page, /FurnitureRequestForm/);
  assert.doesNotMatch(page, /["']use client["']|<main|request_reference|localStorage|sessionStorage/);
});

test("request form uses the single API client with an ephemeral Clerk bearer", () => {
  const form = read("components/furniture-requests/furniture-request-form.tsx");
  const api = read("lib/api/client.ts");

  assert.match(form, /createApiClient\(\{ baseUrl: process\.env\.NEXT_PUBLIC_API_BASE_URL \}\)/);
  assert.match(form, /useAuth\(\)/);
  assert.match(form, /getToken\(\)/);
  assert.match(form, /headers\.set\("Authorization", `Bearer \$\{token\}`\)/);
  assert.match(form, /path: "\/requests"/);
  assert.match(form, /cache: "no-store"/);
  assert.match(form, /component="form" method="post" onSubmit=\{handleSubmit\}/);
  assert.match(form, /timeoutMs: values\.attachment \? ATTACHMENT_TIMEOUT_MS : undefined/);
  assert.match(form, /if \(isLoaded && isSignedIn\)/);
  assert.match(form, /Submit as visitor/);
  assert.match(form, /PRODUCT_UNAVAILABLE_MESSAGES/);
  assert.match(form, /\[409, PRODUCT_UNAVAILABLE_MESSAGE\]/);
  assert.match(form, /hasProductContextError\(error\)/);
  assert.match(form, /\[413, "The attachment is too large/);
  assert.match(form, /status === 429/);
  assert.match(form, /useSubmissionFormState/);
  assert.doesNotMatch(form + api, /X-Upload-Token|localStorage|sessionStorage|Server Action|fetch\(/);
});

test("MTO PDPs expose the request journey without enabling commerce", () => {
  const productPage = read("app/products/[slug]/page.tsx");

  assert.match(productPage, /<Button href=\{`\/furniture-requests\?product=/);
  assert.doesNotMatch(productPage, /component=\{NextLink\}/);
  assert.match(productPage, /Request this furniture/);
  assert.match(productPage, /furniture-requests\?product=/);
  assert.doesNotMatch(productPage, /Add to cart|Buy now|\/checkout|\/payment/);
});
