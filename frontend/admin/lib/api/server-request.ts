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

const UPLOAD_TIMEOUT_MS = 30_000;

/**
 * Multipart upload path. The transport forwards the native `FormData` unchanged,
 * so the runtime sets the multipart boundary and no JSON serializer touches the
 * file bytes. Only the file field is forwarded; no other request field is added.
 */
export async function requestOperationalUpload<T>(request: Omit<AdminApiRequest, "body" | "timeoutMs"> & Readonly<{ form: FormData }>): Promise<ApiSuccess<T>> {
  const { form, ...rest } = request;
  return requestOperationalApi<T>({ ...rest, body: form, timeoutMs: UPLOAD_TIMEOUT_MS });
}
