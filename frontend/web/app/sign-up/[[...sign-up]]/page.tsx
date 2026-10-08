import { SignUp } from "@clerk/nextjs";
import type { Metadata } from "next";
import { AuthPageLayout } from "@/components/auth/auth-page-layout";
import { clerkAuthAppearance } from "@/lib/auth/clerk-appearance";

export const metadata: Metadata = {
  title: "Create your account",
  description: "Create an SL Furnitures account.",
  robots: { index: false, follow: false },
};

export default function SignUpPage() {
  return (
    <AuthPageLayout title="Create your account">
      <SignUp appearance={clerkAuthAppearance} />
    </AuthPageLayout>
  );
}
