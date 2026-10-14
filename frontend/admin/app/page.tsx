import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import { EmptyState } from "@/components/states/empty-state";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { PageContainer } from "@/components/ui/page-container";
import { PageHeader } from "@/components/ui/page-header";
import { StatusIndicator } from "@/components/ui/status-indicator";

export default function FoundationPage() {
  return <PageContainer component="main" aria-labelledby="page-title">
    <PageHeader title="Admin foundation" description="A private operational workspace foundation for the request-only release. No business data is loaded in this phase." />
    <Box sx={{ display: "flex", flexDirection: "column", gap: 4 }}>
      <section aria-labelledby="foundation-preview-title">
        <Typography component="h2" variant="h2" id="foundation-preview-title" sx={{ mb: 2 }}>Design system preview</Typography>
        <Box sx={{ display: "flex", flexDirection: { xs: "column", sm: "row" }, gap: 2, flexWrap: "wrap" }}><StatusIndicator status="success" label="Ready" /><StatusIndicator status="warning" label="Needs review" /><StatusIndicator status="neutral" label="Not connected" /></Box>
      </section>
      <Box sx={{ display: "flex", flexDirection: { xs: "column", lg: "row" }, gap: 2 }}>
        <EmptyState title="No operational records" description="Queues will appear in later admin phases when Laravel resources are connected." />
        <LoadingState label="Loading foundation state" />
        <ErrorState title="Example recoverable state" description="This is a non-operational preview of the shared error treatment." />
      </Box>
    </Box>
  </PageContainer>;
}
