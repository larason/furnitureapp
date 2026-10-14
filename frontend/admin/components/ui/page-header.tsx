import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import type { ReactNode } from "react";

interface PageHeaderProps { title: string; description?: string; actions?: ReactNode; }

export function PageHeader({ title, description, actions }: Readonly<PageHeaderProps>) {
  return <Box sx={{ display: "flex", flexDirection: { xs: "column", md: "row" }, justifyContent: "space-between", gap: 3, mb: 6 }}>
    <Box sx={{ display: "flex", flexDirection: "column", gap: 1, maxWidth: "var(--content-width-lead)" }}>
      <Typography component="h1" variant="h1" id="page-title">{title}</Typography>
      {description ? <Typography color="text.secondary">{description}</Typography> : null}
    </Box>
    {actions ? <Box sx={{ display: "flex", flexDirection: "row", gap: 1, flexWrap: "wrap" }}>{actions}</Box> : null}
  </Box>;
}
