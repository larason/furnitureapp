import type { ReactNode } from "react";
import { redirect } from "next/navigation";
import { AccessDeniedState, AccountUnavailableState, ServiceUnavailableState, SessionInvalidState } from "@/components/auth/admission-state";
import { OperationalIdentityProvider } from "@/components/auth/operational-identity-provider";
import { ConfigurationState } from "@/components/states/configuration-state";
import { PageContainer } from "@/components/ui/page-container";
import { getClientConfig } from "@/lib/config/client-config";
import { requireOperationalIdentity } from "@/lib/auth/server-operational-identity";

export const dynamic = "force-dynamic";

export default async function ProtectedLayout({ children }: Readonly<{ children: ReactNode }>) {
  if (!getClientConfig().clerkPublishableKey) return <ConfigurationState />;
  const admission = await requireOperationalIdentity();
  if (admission.kind === "signed-out") redirect("/sign-in");
  if (admission.kind === "session-invalid") return <PageContainer component="main"><SessionInvalidState /></PageContainer>;
  if (admission.kind === "unavailable") return <PageContainer component="main"><ServiceUnavailableState /></PageContainer>;
  if (admission.kind === "denied") return <PageContainer component="main">{admission.reason === "customer" ? <AccessDeniedState /> : <AccountUnavailableState />}</PageContainer>;
  return <OperationalIdentityProvider identity={admission.identity}>{children}</OperationalIdentityProvider>;
}
