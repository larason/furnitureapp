import assert from "node:assert/strict";
import test from "node:test";
import { isSiteRouteImplemented } from "@/components/layout/site-navigation";
import { read } from "../test-utils/frontend-test-helpers";

const INFORMATION_ROUTES = [
  ["app/privacy-policy/page.tsx", "/privacy-policy", "Privacy Policy"],
  ["app/terms-of-service/page.tsx", "/terms-of-service", "Terms of Service"],
  ["app/about/page.tsx", "/about", "About SL Furnitures"],
] as const;

test("information routes are server-rendered, canonical, and intentionally noindex", () => {
  for (const [file, path, heading] of INFORMATION_ROUTES) {
    const page = read(file);

    assert.match(page, new RegExp(`canonicalPath: "${path}"`));
    assert.match(page, /robots: NOINDEX_FOLLOW/);
    assert.match(page, new RegExp(heading));
    assert.doesNotMatch(page, /["']use client["']|<main|localStorage|sessionStorage/);
  }
});

test("draft legal pages disclose their review status without invented legal commitments", () => {
  const notice = read("components/information/information-page.tsx");

  assert.match(notice, /DRAFT — LEGAL REVIEW REQUIRED/);
  for (const file of ["app/privacy-policy/page.tsx", "app/terms-of-service/page.tsx"]) {
    const page = read(file);

    assert.match(page, /DraftLegalNotice/);
    assert.doesNotMatch(page, /governing law|warranty duration|return period|refund entitlement/i);
  }
});

test("footer exposes the implemented information routes", () => {
  const footer = read("components/layout/site-footer.tsx");
  const navigation = read("components/layout/site-navigation.ts");

  for (const [, path] of INFORMATION_ROUTES) assert.equal(isSiteRouteImplemented(path), true);
  assert.match(footer, /SITE_INFORMATION_LINKS/);
  for (const label of ["About Us", "Privacy Policy", "Terms of Service", "Contact Us"]) assert.match(navigation, new RegExp(label));
});
