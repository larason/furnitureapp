import { LoadingState } from "@/components/states/loading-state";
import { PageContainer } from "@/components/ui/page-container";

export default function ProtectedLoading() {
  return (
    <PageContainer>
      <LoadingState label="Loading page" />
    </PageContainer>
  );
}
