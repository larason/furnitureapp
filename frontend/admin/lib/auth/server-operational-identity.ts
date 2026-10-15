import "server-only";

import { auth } from "@clerk/nextjs/server";
import { cache } from "react";
import { createAdminApiClient } from "@/lib/api/client";
import { resolveOperationalIdentity, type AdmissionResult } from "@/lib/auth/operational-identity";

/**
 * Resolves the operational identity once per request so the protected layout and
 * the page's own route check share one Laravel admission decision.
 */
export const requireOperationalIdentity = cache(async (): Promise<AdmissionResult> => {
  return resolveOperationalIdentity({
    getSession: async () => {
      const session = await auth();
      return { isAuthenticated: session.isAuthenticated, sessionStatus: session.sessionStatus, getToken: session.getToken };
    },
    getMe: async (token) => {
      const client = createAdminApiClient({ tokenProvider: () => Promise.resolve(token) });
      const response = await client<unknown>({ path: "/me" });
      return response.data;
    },
  });
});
