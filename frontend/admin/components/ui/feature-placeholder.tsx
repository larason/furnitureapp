import Box from "@mui/material/Box";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import { StatusIndicator } from "./status-indicator";

interface FeaturePlaceholderProps {
  feature: string;
  phase: string;
}

/** States plainly that a scheduled feature is not available yet. It loads no data. */
export function FeaturePlaceholder({ feature, phase }: Readonly<FeaturePlaceholderProps>) {
  return (
    <Paper component="section" variant="outlined" sx={{ p: 3, maxWidth: "var(--content-width-lead)" }}>
      <Box sx={{ display: "flex", flexDirection: "column", gap: 2 }}>
        <Typography component="h2" variant="h4">
          Not yet implemented
        </Typography>
        <Typography color="text.secondary">
          {feature} is scheduled for {phase} and is not available in this build. This placeholder retrieves no data and performs no operations.
        </Typography>
        <Box sx={{ display: "flex" }}>
          <StatusIndicator status="neutral" label={`Planned for ${phase}`} />
        </Box>
      </Box>
    </Paper>
  );
}
