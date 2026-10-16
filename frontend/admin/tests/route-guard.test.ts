import test from "node:test";
import assert from "node:assert/strict";
import { resolveAdminRouteAccess } from "../lib/auth/route-guard";
import type { AdmissionResult } from "../lib/auth/operational-identity";
import type { OperationalIdentity } from "../lib/auth/types";

const STAFF: OperationalIdentity = { id: "2", name: "Staff", email: "staff@example.test", role: "STAFF", accountState: "ACTIVE", permissions: ["requests.view"] };
const ADMIN: OperationalIdentity = { id: "3", name: "Admin", email: "admin@example.test", role: "ADMIN", accountState: "ACTIVE", permissions: ["requests.view", "users.manage_authorized"] };

function admitted(identity: OperationalIdentity): AdmissionResult {
  return { kind: "admitted", identity };
}

test("an admitted identity with the exact permission is granted the route", () => {
  const access = resolveAdminRouteAccess(admitted(STAFF), { permission: "requests.view" });
  assert.equal(access.kind, "granted");
  if (access.kind === "granted") assert.equal(access.identity.id, "2");
});

test("an admitted identity without the exact permission is forbidden", () => {
  assert.deepEqual(resolveAdminRouteAccess(admitted(STAFF), { permission: "audit.view" }), { kind: "forbidden" });
  assert.deepEqual(resolveAdminRouteAccess(admitted({ ...ADMIN, permissions: ["requests.view"] }), { permission: "users.manage_authorized" }), { kind: "forbidden" });
});

test("a role is never a substitute for a missing permission, and an approved role restriction is enforced", () => {
  assert.deepEqual(resolveAdminRouteAccess(admitted({ ...ADMIN, permissions: [] }), { permission: "users.manage_authorized" }), { kind: "forbidden" });
  assert.deepEqual(resolveAdminRouteAccess(admitted({ ...STAFF, permissions: ["users.manage_authorized"] }), { permission: "users.manage_authorized", role: "ADMIN" }), { kind: "forbidden" });
  assert.equal(resolveAdminRouteAccess(admitted(ADMIN), { permission: "users.manage_authorized", role: "ADMIN" }).kind, "granted");
});

test("an admission-only route requires a valid admitted identity and nothing more", () => {
  assert.equal(resolveAdminRouteAccess(admitted(STAFF)).kind, "granted");
  assert.equal(resolveAdminRouteAccess(admitted({ ...STAFF, permissions: [] })).kind, "granted");
});

test("missing permission data fails closed", () => {
  const malformed = { ...STAFF, permissions: undefined as unknown as readonly string[] };
  assert.deepEqual(resolveAdminRouteAccess(admitted(malformed), { permission: "requests.view" }), { kind: "forbidden" });
  assert.equal(resolveAdminRouteAccess(admitted(malformed), { permission: "requests.view" }).kind, "forbidden");
});

test("denied, signed-out, invalid-session and unavailable admissions keep their distinct states", () => {
  assert.deepEqual(resolveAdminRouteAccess({ kind: "denied", reason: "customer" }, { permission: "requests.view" }), { kind: "account-denied", reason: "customer" });
  assert.deepEqual(resolveAdminRouteAccess({ kind: "denied", reason: "account" }), { kind: "account-denied", reason: "account" });
  assert.deepEqual(resolveAdminRouteAccess({ kind: "signed-out" }), { kind: "signed-out" });
  assert.deepEqual(resolveAdminRouteAccess({ kind: "session-invalid" }), { kind: "session-invalid" });
  assert.deepEqual(resolveAdminRouteAccess({ kind: "unavailable" }), { kind: "unavailable" });
});

test("route access is resolved per request from the sanitized identity only", () => {
  const first = resolveAdminRouteAccess(admitted(STAFF), { permission: "requests.view" });
  const second = resolveAdminRouteAccess(admitted(STAFF), { permission: "requests.view" });
  assert.deepEqual(first, second);
  assert.deepEqual(Object.keys(STAFF).sort(), ["accountState", "email", "id", "name", "permissions", "role"]);
});
