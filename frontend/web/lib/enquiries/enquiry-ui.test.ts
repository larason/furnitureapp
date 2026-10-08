import assert from "node:assert/strict";
import test from "node:test";
import { read } from "../test-utils/frontend-test-helpers";

test("contact page remains server-first and private to search indexing", () => {
  const page = read("app/contact/page.tsx");

  assert.match(page, /metadata: Metadata/);
  assert.match(page, /robots: \{ index: false, follow: true \}/);
  assert.match(page, /EnquiryForm/);
  assert.doesNotMatch(page, /["']use client["']|<main|localStorage|sessionStorage/);
});

test("enquiry form uses the existing API client and current Clerk token only while submitting", () => {
  const form = read("components/enquiries/enquiry-form.tsx");
  const api = read("lib/api/client.ts");

  assert.match(form, /createApiClient\(\{ baseUrl: process\.env\.NEXT_PUBLIC_API_BASE_URL \}\)/);
  assert.match(form, /useAuth\(\)/);
  assert.match(form, /getToken\(\)/);
  assert.match(form, /headers\.set\("Authorization", `Bearer \$\{token\}`\)/);
  assert.match(form, /path: "\/enquiries"/);
  assert.match(form, /cache: "no-store"/);
  assert.match(form, /component="form" method="post" onSubmit=\{handleSubmit\}/);
  assert.match(form, /status === 413/);
  assert.match(form, /status === 429/);
  assert.match(form, /attachmentInputRef\.current\.value = ""/);
  assert.doesNotMatch(form + api, /X-Upload-Token|localStorage|sessionStorage|Server Action|fetch\(/);
});
