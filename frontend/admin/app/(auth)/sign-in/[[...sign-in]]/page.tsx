import { SignIn } from "@clerk/nextjs";
import type { Metadata } from "next";
import Paper from "@mui/material/Paper";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import { PageContainer } from "@/components/ui/page-container";
import { clerkAuthAppearance } from "@/lib/auth/clerk-appearance";

export const metadata: Metadata = { title: "Sign in", robots: { index: false, follow: false } };

export default function SignInPage() {
  return <PageContainer component="main" aria-labelledby="sign-in-title"><Stack spacing={3} sx={{ maxWidth: "var(--content-width-form)", mx: "auto" }}><Typography component="h1" variant="h1" id="sign-in-title">Operational sign in</Typography><Typography color="text.secondary">Sign in with an existing SL Furnitures account. Access requires active STAFF or ADMIN authorization from Laravel.</Typography><Paper variant="outlined" sx={{ p: 3 }}><SignIn appearance={clerkAuthAppearance} withSignUp={false} transferable={false} signUpUrl="/sign-in" forceRedirectUrl="/" /></Paper></Stack></PageContainer>;
}
