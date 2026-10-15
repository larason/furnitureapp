import type { OperationalIdentity, OperationalRole } from "@/lib/auth/types";

/** Approved display fallbacks for sanitized Laravel identity fields. */
export const UNKNOWN_DISPLAY_NAME = "Operational user";
const UNKNOWN_EMAIL = "No email on file";

const OPERATIONAL_ROLE_LABELS: Readonly<Record<OperationalRole, string>> = {
  STAFF: "Staff",
  ADMIN: "Administrator",
};

export function operationalDisplayName(identity: OperationalIdentity): string {
  return identity.name?.trim() || UNKNOWN_DISPLAY_NAME;
}

export function operationalDisplayEmail(identity: OperationalIdentity): string {
  return identity.email?.trim() || UNKNOWN_EMAIL;
}

export function operationalRoleLabel(role: OperationalRole): string {
  return OPERATIONAL_ROLE_LABELS[role];
}
