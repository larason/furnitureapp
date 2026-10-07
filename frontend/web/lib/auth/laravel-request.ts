import type { ApiRequestOptions, ApiSuccess, RequestFunction } from "../api/client";

export type ClerkSession = Readonly<{
  isAuthenticated: boolean;
  sessionStatus: "active" | "pending" | null;
  getToken: () => Promise<string | null>;
}>;

export type ClerkSessionAccessor = () => Promise<ClerkSession>;

export class ClerkAuthenticationRequiredError extends Error {
  constructor() {
    super("An active Clerk session is required for this request.");
    this.name = "ClerkAuthenticationRequiredError";
  }
}

export function createAuthenticatedLaravelRequest(sessionAccessor: ClerkSessionAccessor, apiRequest: RequestFunction) {
  return async function authenticatedLaravelRequest<T>(request: ApiRequestOptions): Promise<ApiSuccess<T> | undefined> {
    const session = await sessionAccessor();
    if (!session.isAuthenticated || session.sessionStatus !== "active") {
      throw new ClerkAuthenticationRequiredError();
    }

    const token = await session.getToken();
    if (!token) {
      throw new ClerkAuthenticationRequiredError();
    }

    const headers = new Headers(request.headers);
    headers.set("Authorization", `Bearer ${token}`);
    return apiRequest<T>({ ...request, headers, cache: "no-store" });
  };
}
