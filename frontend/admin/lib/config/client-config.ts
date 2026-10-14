export interface AdminClientConfig {
  clerkPublishableKey: string | undefined;
  adminOrigin: string | undefined;
}

export function getClientConfig(): AdminClientConfig {
  return {
    clerkPublishableKey: process.env.NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY?.trim() || undefined,
    adminOrigin: process.env.NEXT_PUBLIC_ADMIN_ORIGIN?.trim() || undefined,
  };
}
