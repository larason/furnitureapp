import "server-only";

import { auth } from "@clerk/nextjs/server";
import { createAdminApiClient, type AdminApiRequest } from "@/lib/api/client";
import type { ApiSuccess } from "@/lib/api/types";

/**
 * Request-scoped admin API access. The current Clerk session token is retrieved
 * per request and only forwarded as a bearer header; it is never retained,
 * logged or serialized into a Client Component.
 */
export async function requestOperationalApi<T>(request: AdminApiRequest): Promise<ApiSuccess<T>> {
  const session = await auth();
  const token = await session.getToken();
  const client = createAdminApiClient({ tokenProvider: () => Promise.resolve(token) });
  return client<T>(request);
}

/** Public catalog access through the same transport, without a bearer token. */
export async function requestPublicApi<T>(request: AdminApiRequest): Promise<ApiSuccess<T>> {
  const client = createAdminApiClient();
  return client<T>(request);
}
