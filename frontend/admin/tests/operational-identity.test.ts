import test from "node:test";
import assert from "node:assert/strict";
import { ApiError, ApiTransportError } from "../lib/api/errors";
import { hasPermission, requirePermission, resolveOperationalIdentity } from "../lib/auth/operational-identity";

const activeAdmin = { id: "4", name: "Admin", email: "admin@example.test", role: "ADMIN", account_state: "ACTIVE", permissions: ["requests.view", "staff.manage"] };

function dependencies(response: unknown) {
  return { getSession: async () => ({ isAuthenticated: true, getToken: async () => "fresh-token" }), getMe: async () => response };
}

test("active Laravel ADMIN is admitted only with active state and deterministic permissions", async () => {
  const result = await resolveOperationalIdentity(dependencies(activeAdmin));
  assert.equal(result.kind, "admitted");
  if (result.kind === "admitted") {
    assert.equal(result.identity.role, "ADMIN");
    assert.equal(hasPermission(result.identity, "staff.manage"), true);
    assert.equal(hasPermission(result.identity, "audit.view"), false);
    assert.equal(requirePermission(result.identity, "staff.manage"), true);
    assert.equal(requirePermission(result.identity, "audit.view"), false);
  }
});

test("active Laravel STAFF is admitted with only its exact effective permissions", async () => {
  const result = await resolveOperationalIdentity(dependencies({ ...activeAdmin, role: "STAFF", permissions: ["requests.view"] }));
  assert.equal(result.kind, "admitted");
  if (result.kind === "admitted") {
    assert.equal(hasPermission(result.identity, "requests.view"), true);
    assert.equal(hasPermission(result.identity, "requests.manage"), false);
    assert.equal(hasPermission(result.identity, "staff.manage"), false);
  }
});

test("customer, inactive, ambiguous, and incomplete Laravel identities fail closed", async () => {
  for (const response of [
    { ...activeAdmin, role: "CUSTOMER" },
    { ...activeAdmin, account_state: "SUSPENDED" },
    { ...activeAdmin, role: ["STAFF", "ADMIN"] },
    { ...activeAdmin, permissions: ["staff.manage", "requests.view"] },
    { id: "4", role: "ADMIN" },
  ]) {
    const result = await resolveOperationalIdentity(dependencies(response));
    assert.equal(result.kind, "denied");
  }
});

test("missing or pending Clerk sessions, Laravel denial, and service outage remain distinct", async () => {
  const signedOut = await resolveOperationalIdentity({ getSession: async () => ({ isAuthenticated: false, getToken: async () => null }), getMe: async () => activeAdmin });
  assert.equal(signedOut.kind, "signed-out");
  const pending = await resolveOperationalIdentity({ getSession: async () => ({ isAuthenticated: true, sessionStatus: "pending" as const, getToken: async () => "fresh-token" }), getMe: async () => activeAdmin });
  assert.equal(pending.kind, "signed-out");
  const denied = await resolveOperationalIdentity({ ...dependencies(activeAdmin), getMe: async () => { throw new ApiError(403, []); } });
  assert.equal(denied.kind, "denied");
  const expired = await resolveOperationalIdentity({ ...dependencies(activeAdmin), getMe: async () => { throw new ApiError(401, []); } });
  assert.equal(expired.kind, "session-invalid");
  const unavailable = await resolveOperationalIdentity({ ...dependencies(activeAdmin), getMe: async () => { throw new ApiTransportError("network"); } });
  assert.equal(unavailable.kind, "unavailable");
});

test("a Laravel 401 under a live Clerk session is recoverable rather than a redirect loop", async () => {
  const result = await resolveOperationalIdentity({
    getSession: async () => ({ isAuthenticated: true, sessionStatus: "active" as const, getToken: async () => "live-token" }),
    getMe: async () => { throw new ApiError(401, [{ code: "INVALID_AUTHENTICATION", message: "The token could not be accepted." }]); },
  });
  assert.equal(result.kind, "session-invalid");
});

test("a genuinely signed-out visitor is still reported as signed-out", async () => {
  const result = await resolveOperationalIdentity({ getSession: async () => ({ isAuthenticated: false, getToken: async () => null }), getMe: async () => activeAdmin });
  assert.equal(result.kind, "signed-out");
});

test("a new session resolves independently and cannot retain the previous account identity", async () => {
  const admin = await resolveOperationalIdentity(dependencies(activeAdmin));
  const customer = await resolveOperationalIdentity(dependencies({ ...activeAdmin, id: "5", role: "CUSTOMER", permissions: [] }));
  assert.equal(admin.kind, "admitted");
  assert.equal(customer.kind, "denied");
});
