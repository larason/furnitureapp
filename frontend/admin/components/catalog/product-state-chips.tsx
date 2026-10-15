import Box from "@mui/material/Box";
import { StatusIndicator } from "@/components/ui/status-indicator";
import type { ProductStateDescriptor } from "@/lib/catalog/product-presentation";

/** Renders real API-derived state as labelled chips; colour is never the only signal. */
export function ProductStateChips({ descriptors }: Readonly<{ descriptors: readonly ProductStateDescriptor[] }>) {
  return (
    <Box sx={{ display: "flex", flexWrap: "wrap", gap: 0.5, minWidth: 0 }}>
      {descriptors.map((descriptor) => <StatusIndicator key={descriptor.key} status={descriptor.tone} label={descriptor.label} />)}
    </Box>
  );
}
