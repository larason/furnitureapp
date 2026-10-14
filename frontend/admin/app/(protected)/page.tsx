import { SignOutButton } from "@/components/auth/sign-out-button";
import { PageContainer } from "@/components/ui/page-container";
import { PageHeader } from "@/components/ui/page-header";

export default function ProtectedFoundationPage() {
  return (
    <PageContainer component="main" aria-labelledby="page-title">
      <PageHeader
        title="Operational access confirmed"
        description="This protected foundation is ready for later operational screens."
        actions={<SignOutButton />}
      />
    </PageContainer>
  );
}