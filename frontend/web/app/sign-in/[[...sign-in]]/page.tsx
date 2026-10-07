import { SignIn } from "@clerk/nextjs";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import type { Metadata } from "next";
import { SiteSection } from "@/components/layout/site-section";

export const metadata: Metadata = {
  title: "Sign in",
  description: "Sign in to your SL Furnitures account.",
  robots: { index: false, follow: false },
};

export default function SignInPage() {
  return (
    <SiteSection aria-label="Sign in" surface="paper">
      <Stack spacing={6} sx={{ maxWidth: "var(--content-width-form)" }}>
        <Typography component="p" variant="h2">Welcome back</Typography>
        <SignIn />
      </Stack>
    </SiteSection>
  );
}
