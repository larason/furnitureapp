import { hasPermission } from "@/lib/auth/operational-identity";
import type { AdmissionResult } from "@/lib/auth/operational-identity";
import type { OperationalIdentity, OperationalRole } from "@/lib/auth/types";

/** The exact requirement a protected admin route repeats server-side. */
export interface AdminRouteRequirement {
  readonly permission?: string;
  readonly role?: OperationalRole;
}

export type AdminRouteAccess =
  | { readonly kind: "granted"; readonly identity: OperationalIdentity }
  | { readonly kind: "signed-out" }
  | { readonly kind: "session-invalid" }
  | { readonly kind: "unavailable" }
  | { readonly kind: "account-denied"; readonly reason: "customer" | "account" }
  | { readonly kind: "forbidden" };

/**
 * Applies the admitted identity to a route requirement. A role never authorizes
 * on its own: an exact permission is still required when the route declares one,
 * and missing permission data fails closed.
 */
export function resolveAdminRouteAccess(admission: AdmissionResult, requirement: AdminRouteRequirement = {}): AdminRouteAccess {
  switch (admission.kind) {
    case "admitted": {
      if (requirement.permission && !hasPermission(admission.identity, requirement.permission)) return { kind: "forbidden" };
      if (requirement.role && requirement.role !== admission.identity.role) return { kind: "forbidden" };
      return { kind: "granted", identity: admission.identity };
    }
    case "denied":
      return { kind: "account-denied", reason: admission.reason === "customer" ? "customer" : "account" };
    case "signed-out":
      return { kind: "signed-out" };
    case "session-invalid":
      return { kind: "session-invalid" };
    case "unavailable":
      return { kind: "unavailable" };
  }
}
