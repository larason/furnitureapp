"use client";

import { useClerk } from "@clerk/nextjs";
import Button from "@mui/material/Button";
import { ErrorState } from "@/components/states/error-state";
import { EmptyState } from "@/components/states/empty-state";

export function AccessDeniedState() {
  const { signOut } = useClerk();
  return <ErrorState title="Access denied" description="This account is not authorized for the SL Furnitures operational workspace." action={<Button onClick={() => signOut({ redirectUrl: "/sign-in" })}>Sign out</Button>} />;
}

export function AccountUnavailableState() {
  const { signOut } = useClerk();
  return <ErrorState title="Account unavailable" description="This account cannot access the operational workspace." action={<Button onClick={() => signOut({ redirectUrl: "/sign-in" })}>Sign out</Button>} />;
}

/**
 * Laravel is unreachable or erroring. The user may still hold a valid Clerk
 * session, so this state must offer sign-out rather than stranding them.
 */
export function ServiceUnavailableState() {
  const { signOut } = useClerk();
  return <EmptyState title="Operational access unavailable" description="The authorization service could not confirm access. Try again shortly." action={<Button onClick={() => signOut({ redirectUrl: "/sign-in" })}>Sign out</Button>} />;
}

/**
 * Laravel rejected the token for a session Clerk still considers active, for
 * example an authorized-party mismatch. Redirecting to /sign-in would strand
 * the user because SignIn cannot render for an already signed-in user, so the
 * recovery path is an explicit Clerk sign-out.
 */
export function SessionInvalidState() {
  const { signOut } = useClerk();
  return <ErrorState title="Session not accepted" description="Your sign-in could not be verified by the operational service. Sign out and sign in again." action={<Button onClick={() => signOut({ redirectUrl: "/sign-in" })}>Sign out</Button>} />;
}
