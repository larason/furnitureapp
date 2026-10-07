import { SignUp } from "@clerk/nextjs";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import type { Metadata } from "next";
import { SiteSection } from "@/components/layout/site-section";

export const metadata: Metadata = {
  title: "Create your account",
  description: "Create an SL Furnitures account.",
  robots: { index: false, follow: false },
};

export default function SignUpPage() {
  return (
    <SiteSection aria-label="Create your account" surface="paper">
      <Stack spacing={6} sx={{ maxWidth: "var(--content-width-form)" }}>
        <Typography component="p" variant="h2">Create your account</Typography>
        <SignUp />
      </Stack>
    </SiteSection>
  );
}
