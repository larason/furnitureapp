import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import { PageContainer } from "@/components/ui/page-container";

export function ConfigurationState() {
  return <PageContainer component="main" aria-labelledby="configuration-title"><Box sx={{ display: "flex", flexDirection: "column", gap: 2 }} role="alert"><Typography component="h1" variant="h1" id="configuration-title">Admin configuration required</Typography><Alert severity="warning">Clerk is not configured for this environment. Add the publishable key from `.env.example`; no authenticated admin session is assumed.</Alert></Box></PageContainer>;
}
