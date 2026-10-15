import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";

const source = readFileSync(fileURLToPath(new URL("../components/auth/admission-state.tsx", import.meta.url)), "utf8");

function componentBody(name: string): string {
  const start = source.indexOf(`export function ${name}(`);
  assert.notEqual(start, -1, `${name} must exist`);
  const end = source.indexOf("\nexport function ", start + 1);
  return source.slice(start, end === -1 ? undefined : end);
}

test("every state rendered for a possibly-authenticated visitor offers Clerk sign out", () => {
  for (const name of ["AccessDeniedState", "AccountUnavailableState", "ServiceUnavailableState", "SessionInvalidState"]) {
    const body = componentBody(name);
    assert.match(body, /useClerk/, `${name} must use the Clerk hook to sign out.`);
    assert.match(body, /signOut/, `${name} must provide a sign-out action.`);
  }
});

test("the unavailable state discloses no internal enforcement detail", () => {
  const body = componentBody("ServiceUnavailableState");
  assert.match(body, /Operational access unavailable/);
  assert.doesNotMatch(body, /\b401\b|\b403\b|bearer|clerk_token|session token/i);
});

test("the customer denial explains the account mismatch and prescribes signing back in", () => {
  const body = componentBody("AccessDeniedState");
  assert.match(body, /signed in to a customer account/);
  assert.match(body, /replaces this session/);
  assert.match(body, /Sign out and sign in/);
  assert.doesNotMatch(body, /\b401\b|\b403\b|bearer|clerk_token|session token/i);
});