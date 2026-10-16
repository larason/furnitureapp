"use client";

import Breadcrumbs from "@mui/material/Breadcrumbs";
import Typography from "@mui/material/Typography";
import NextLink from "next/link";
import type { AdminBreadcrumb } from "@/lib/navigation/admin-navigation";

const CRUMB_SX = {
  borderRadius: "var(--radius-sm)",
  "&:focus-visible": { boxShadow: "var(--focus-ring)" },
} as const;

export function AdminBreadcrumbs({ items }: Readonly<{ items: readonly AdminBreadcrumb[] }>) {
  return (
    <Breadcrumbs aria-label="Breadcrumb" sx={{ minWidth: 0, "& .MuiBreadcrumbs-li": { minWidth: 0, display: "flex" } }}>
      {items.map((item, index) => {
        const current = index === items.length - 1;
        if (item.href && !current) {
          return (
            <Typography
              key={item.label}
              component={NextLink}
              href={item.href}
              variant="body2"
              sx={{ ...CRUMB_SX, color: "text.secondary", textDecoration: "underline", whiteSpace: "nowrap" }}
            >
              {item.label}
            </Typography>
          );
        }
        return (
          <Typography key={item.label} component="span" variant="body2" aria-current={current ? "page" : undefined} noWrap sx={{ color: current ? "text.primary" : "text.secondary" }}>
            {item.label}
          </Typography>
        );
      })}
    </Breadcrumbs>
  );
}
