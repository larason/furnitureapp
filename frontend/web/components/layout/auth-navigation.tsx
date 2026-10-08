"use client";

import { SignInButton, SignUpButton, UserButton, useAuth } from "@clerk/nextjs";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";

export function AuthNavigation() {
  const { isLoaded, isSignedIn } = useAuth();

  if (!isLoaded) {
    return null;
  }

  return (
    <Box sx={{ display: { xs: "none", md: "flex" }, alignItems: "center", gap: 1 }}>
      {isSignedIn ? (
        <UserButton />
      ) : (
        <>
        <SignInButton>
          <Button color="inherit" variant="text">Sign in</Button>
        </SignInButton>
        <SignUpButton>
          <Button variant="contained">Create account</Button>
        </SignUpButton>
        </>
      )}
    </Box>
  );
}
