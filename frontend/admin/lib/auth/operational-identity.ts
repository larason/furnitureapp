import { ApiConfigurationError, ApiError, ApiTransportError } from "@/lib/api/errors";
import type { OperationalIdentity, OperationalRole } from "@/lib/auth/types";

const OPERATIONAL_ROLES = new Set(["STAFF", "ADMIN"]);
export type { OperationalIdentity, OperationalRole } from "@/lib/auth/types";

export type AdmissionResult =
  | { kind: "admitted"; identity: OperationalIdentity }
  | { kind: "signed-out" }
  | { kind: "session-invalid" }
  | { kind: "denied"; reason: "customer" | "account" | "identity" }
  | { kind: "unavailable" };

interface AdmissionDependencies {
  getSession: () => Promise<{ isAuthenticated: boolean; sessionStatus?: "active" | "pending" | null; getToken: () => Promise<string | null> }>;
  getMe: (token: string) => Promise<unknown>;
}

export async function resolveOperationalIdentity(dependencies: AdmissionDependencies): Promise<AdmissionResult> {
  const session = await dependencies.getSession();
  if (!session.isAuthenticated || session.sessionStatus === "pending") return { kind: "signed-out" };

  try {
    const token = await session.getToken();
    if (!token) return { kind: "signed-out" };
    const resolution = parseOperationalIdentity(await dependencies.getMe(token));
    return resolution.identity ? { kind: "admitted", identity: resolution.identity } : { kind: "denied", reason: resolution.reason };
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) return { kind: "session-invalid" };
    if (error instanceof ApiError && error.status === 403) return { kind: "denied", reason: "account" };
    if (error instanceof ApiTransportError || error instanceof ApiConfigurationError || error instanceof ApiError) return { kind: "unavailable" };
    return { kind: "unavailable" };
  }
}

export function hasPermission(identity: OperationalIdentity, permission: string): boolean {
  return identity.permissions.includes(permission);
}

export function requirePermission(identity: OperationalIdentity, permission: string): boolean {
  return hasPermission(identity, permission);
}

function parseOperationalIdentity(value: unknown): { identity: OperationalIdentity | null; reason: "customer" | "account" | "identity" } {
  if (!isRecord(value) || !isNonEmptyString(value.id) || !isNonEmptyString(value.role)) return { identity: null, reason: "identity" };
  if (value.role === "CUSTOMER") return { identity: null, reason: "customer" };
  if (!OPERATIONAL_ROLES.has(value.role) || value.account_state !== "ACTIVE" || !isDeterministicPermissionList(value.permissions)) return { identity: null, reason: "account" };
  return { identity: {
    id: value.id,
    name: typeof value.name === "string" ? value.name : null,
    email: typeof value.email === "string" ? value.email : null,
    role: value.role as OperationalRole,
    accountState: "ACTIVE",
    permissions: value.permissions,
  }, reason: "identity" };
}

function isNonEmptyString(value: unknown): value is string { return typeof value === "string" && value.trim().length > 0; }
function isRecord(value: unknown): value is Record<string, unknown> { return typeof value === "object" && value !== null && !Array.isArray(value); }
function isDeterministicPermissionList(value: unknown): value is readonly string[] {
  return Array.isArray(value) && value.every(isNonEmptyString) && value.every((permission, index, permissions) => index === 0 || permissions[index - 1] < permission);
}
