import "server-only";

import { auth } from "@clerk/nextjs/server";
import { createAdminApiClient } from "@/lib/api/client";
import { resolveOperationalIdentity, type AdmissionResult } from "@/lib/auth/operational-identity";

export async function requireOperationalIdentity(): Promise<AdmissionResult> {
  return resolveOperationalIdentity({
    getSession: async () => {
      const session = await auth();
      return { isAuthenticated: session.isAuthenticated, sessionStatus: session.sessionStatus, getToken: session.getToken };
    },
    getMe: async (token) => {
      const client = createAdminApiClient({ tokenProvider: async () => token });
      const response = await client<unknown>({ path: "/me" });
      return response.data;
    },
  });
}
