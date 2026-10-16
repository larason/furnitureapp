import type { Metadata } from "next";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import { CategoryForm } from "@/components/catalog/category-form";
import { CategoryScopeNotice } from "@/components/catalog/category-scope-notice";
import { PageContainer } from "@/components/ui/page-container";
import { PageHeader } from "@/components/ui/page-header";
import { requireAdminRouteAccess } from "@/lib/auth/server-route-guard";
import { EMPTY_CATEGORY_FORM_VALUES } from "@/lib/catalog/category-form";
import { adminRoute, adminRouteMetadata } from "@/lib/navigation/admin-navigation";
import { ADMIN_CATEGORY_PATHS, ADMIN_ROUTE_PATHS } from "@/lib/navigation/admin-route-paths";

const ROUTE = adminRoute(ADMIN_ROUTE_PATHS.categories);
export const metadata: Metadata = adminRouteMetadata(ROUTE.href);

export default async function NewCategoryPage() {
  const access = await requireAdminRouteAccess(ROUTE);
  if (!access.granted) return access.denial;

  return (
    <PageContainer aria-labelledby="page-title">
      <PageHeader
        title="Create category"
        description="Add a new active top-level category through the approved request contract. Laravel validates the request and returns the authoritative record; nothing is shown as saved before the API confirms it."
        actions={<Button href={ADMIN_CATEGORY_PATHS.list}>Back to categories</Button>}
      />
      <Box sx={{ display: "flex", flexDirection: "column", gap: 3 }}>
        <CategoryScopeNotice />
        <CategoryForm mode="create" values={EMPTY_CATEGORY_FORM_VALUES} />
      </Box>
    </PageContainer>
  );
}
