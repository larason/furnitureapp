import { PageContainer } from "@/components/ui/page-container";
import { PageHeader } from "@/components/ui/page-header";
import { FeaturePlaceholder } from "@/components/ui/feature-placeholder";
import type { AdminRoute } from "@/lib/navigation/admin-navigation";

interface AdminRoutePlaceholderProps {
  route: AdminRoute;
  phase: string;
}

export function AdminRoutePlaceholder({ route, phase }: Readonly<AdminRoutePlaceholderProps>) {
  return (
    <PageContainer aria-labelledby="page-title">
      <PageHeader title={route.label} description={route.description} />
      <FeaturePlaceholder feature={route.label} phase={phase} />
    </PageContainer>
  );
}
