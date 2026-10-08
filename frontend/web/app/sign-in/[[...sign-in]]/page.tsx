import { SignIn } from "@clerk/nextjs";
import type { Metadata } from "next";
import { AuthPageLayout } from "@/components/auth/auth-page-layout";
import { clerkAuthAppearance } from "@/lib/auth/clerk-appearance";

export const metadata: Metadata = {
  title: "Sign in",
  description: "Sign in to your SL Furnitures account.",
  robots: { index: false, follow: false },
};

export default function SignInPage() {
  return (
    <AuthPageLayout title="Welcome back">
      <SignIn appearance={clerkAuthAppearance} />
    </AuthPageLayout>
  );
}
