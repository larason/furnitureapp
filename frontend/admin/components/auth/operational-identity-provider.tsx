"use client";

import { createContext, useContext } from "react";
import type { ReactNode } from "react";
import type { OperationalIdentity } from "@/lib/auth/types";

const OperationalIdentityContext = createContext<OperationalIdentity | null>(null);

export function OperationalIdentityProvider({ children, identity }: Readonly<{ children: ReactNode; identity: OperationalIdentity }>) {
  return <OperationalIdentityContext.Provider key={identity.id} value={identity}>{children}</OperationalIdentityContext.Provider>;
}

export function useOperationalIdentity(): OperationalIdentity {
  const identity = useContext(OperationalIdentityContext);
  if (!identity) throw new Error("Operational identity is unavailable outside the protected route boundary.");
  return identity;
}
