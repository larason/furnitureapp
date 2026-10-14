import Paper from "@mui/material/Paper";
import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import type { ReactNode } from "react";

interface EmptyStateProps { title: string; description: string; action?: ReactNode; }

export function EmptyState({ title, description, action }: Readonly<EmptyStateProps>) {
  return <Paper component="section" variant="outlined" role="status" sx={{ flex: 1, p: 3, minWidth: 0 }}><Box sx={{ display: "flex", flexDirection: "column", gap: 2 }}><Typography component="h2" variant="h4">{title}</Typography><Typography color="text.secondary">{description}</Typography>{action ? <Box sx={{ display: "flex", flexDirection: "row" }}>{action}</Box> : null}</Box></Paper>;
}
