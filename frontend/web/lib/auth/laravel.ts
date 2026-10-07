import "server-only";

import { auth } from "@clerk/nextjs/server";
import { apiRequest, type ApiSuccess } from "../api/client";
import { createAuthenticatedLaravelRequest } from "./laravel-request";

export type LocalUser = Readonly<{
  id: string;
  name: string | null;
  email: string;
  phone: string | null;
  role: "CUSTOMER" | "STAFF" | "ADMIN";
  email_verified: boolean;
  staff_state: "PENDING" | "ACTIVE" | "SUSPENDED" | null;
  created_at: string;
  updated_at: string;
}>;

export const authenticatedLaravelRequest = createAuthenticatedLaravelRequest(auth, apiRequest);

export async function getCurrentLocalUser(): Promise<LocalUser> {
  const response = await authenticatedLaravelRequest<LocalUser>({ path: "/me", cache: "no-store" });
  return requireUserResponse(response);
}

function requireUserResponse(response: ApiSuccess<LocalUser> | undefined): LocalUser {
  if (!response) {
    throw new Error("The profile response is missing.");
  }
  return response.data;
}
