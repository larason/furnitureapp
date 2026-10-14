"use client";

import { useClerk } from "@clerk/nextjs";
import Button from "@mui/material/Button";
import { useOperationalIdentity } from "@/components/auth/operational-identity-provider";

export function SignOutButton() {
  const { signOut } = useClerk();
  const { name, role } = useOperationalIdentity();

  return (
    <Button variant="outlined" onClick={() => signOut({ redirectUrl: "/sign-in" })}>
      Sign out{ name ? ` (${name}, ${role})` : ` (${role})` }
    </Button>
  );
}