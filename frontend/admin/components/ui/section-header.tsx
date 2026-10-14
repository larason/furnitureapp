import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import type { ReactNode } from "react";

interface SectionHeaderProps { title: string; description?: string; actions?: ReactNode; }

export function SectionHeader({ title, description, actions }: Readonly<SectionHeaderProps>) {
  return <Box sx={{ display: "flex", flexDirection: { xs: "column", sm: "row" }, justifyContent: "space-between", gap: 2 }}>
    <Box sx={{ display: "flex", flexDirection: "column", gap: 0.5 }}><Typography component="h2" variant="h2">{title}</Typography>{description ? <Typography color="text.secondary">{description}</Typography> : null}</Box>
    {actions ? <Box sx={{ display: "flex", flexDirection: "row", gap: 1 }}>{actions}</Box> : null}
  </Box>;
}
