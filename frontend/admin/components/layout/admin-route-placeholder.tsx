import { PageContainer } from "@/components/ui/page-container";
import { PageHeader } from "@/components/ui/page-header";
import { FeaturePlaceholder } from "@/components/ui/feature-placeholder";
import { adminRouteStatus, type AdminRoute } from "@/lib/navigation/admin-navigation";

interface AdminRoutePlaceholderProps {
  route: AdminRoute;
}

export function AdminRoutePlaceholder({ route }: Readonly<AdminRoutePlaceholderProps>) {
  return (
    <PageContainer aria-labelledby="page-title">
      <PageHeader title={route.label} description={route.description} />
      <FeaturePlaceholder feature={route.label} phase={route.plannedFor} status={adminRouteStatus(route) ?? undefined} />
    </PageContainer>
  );
}
