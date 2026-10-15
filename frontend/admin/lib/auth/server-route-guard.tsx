import "server-only";

import { redirect } from "next/navigation";
import type { ReactNode } from "react";
import { AccessDeniedState, AccountUnavailableState, ServiceUnavailableState, SessionInvalidState } from "@/components/auth/admission-state";
import { PageContainer } from "@/components/ui/page-container";
import { resolveAdminRouteAccess } from "@/lib/auth/route-guard";
import type { AdminRouteAccess, AdminRouteRequirement } from "@/lib/auth/route-guard";
import { requireOperationalIdentity } from "@/lib/auth/server-operational-identity";
import type { OperationalIdentity } from "@/lib/auth/types";

export type AdminRouteGate =
  | { readonly granted: true; readonly identity: OperationalIdentity }
  | { readonly granted: false; readonly denial: ReactNode };

/**
 * Server-side authorization for a single protected route. The protected layout
 * already admits the request; this repeats the exact route requirement so a
 * deep link can never rely on navigation visibility alone.
 */
export async function requireAdminRouteAccess(requirement: AdminRouteRequirement = {}): Promise<AdminRouteGate> {
  const access = resolveAdminRouteAccess(await requireOperationalIdentity(), requirement);
  if (access.kind === "granted") return { granted: true, identity: access.identity };
  if (access.kind === "signed-out") redirect("/sign-in");
  return { granted: false, denial: <PageContainer>{renderDenialState(access)}</PageContainer> };
}

function renderDenialState(access: AdminRouteAccess): ReactNode {
  if (access.kind === "session-invalid") return <SessionInvalidState />;
  if (access.kind === "unavailable") return <ServiceUnavailableState />;
  if (access.kind === "account-denied") return access.reason === "customer" ? <AccessDeniedState /> : <AccountUnavailableState />;
  return <AccessDeniedState />;
}
