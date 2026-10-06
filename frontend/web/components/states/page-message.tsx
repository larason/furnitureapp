import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import type { ReactNode } from "react";
import { NavLink } from "@/components/layout/nav-link";

export type PageMessageProps = {
  eyebrow?: string;
  title: string;
  description?: string;
  children?: ReactNode;
};

export function PageMessage({
  eyebrow,
  title,
  description,
  children,
}: Readonly<PageMessageProps>) {
  return (
    <Box
      sx={{
        maxWidth: "var(--content-width-lead)",
        display: "flex",
        flexDirection: "column",
        gap: { xs: 3, md: 4 },
      }}
    >
      {eyebrow ? (
        <Typography component="p" variant="overline" color="text.secondary">
          {eyebrow}
        </Typography>
      ) : null}
      <Box sx={{ display: "flex", flexDirection: "column", gap: 2 }}>
        <Typography component="h1" variant="h2">
          {title}
        </Typography>
        {description ? (
          <Typography variant="body1" color="text.secondary">
            {description}
          </Typography>
        ) : null}
      </Box>
      {children ? (
        <Box
          sx={{
            display: "flex",
            flexWrap: "wrap",
            alignItems: "center",
            gap: 2,
            pt: 1,
          }}
        >
          {children}
        </Box>
      ) : null}
    </Box>
  );
}

export type PageMessageLinkProps = {
  href: string;
  children: ReactNode;
  emphasis?: "primary" | "secondary";
};

const actionBaseSx = {
  display: "inline-flex",
  alignItems: "center",
  justifyContent: "center",
  minHeight: 44,
  px: 3,
  borderRadius: "var(--radius-pill)",
  typography: "button",
};

const primaryActionSx = {
  ...actionBaseSx,
  backgroundColor: "var(--action-primary)",
  color: "var(--text-inverse)",
  transition: "background-color var(--motion-base) var(--ease-standard)",
  "&:hover": { backgroundColor: "var(--action-primary-hover)" },
};

const secondaryActionSx = {
  ...actionBaseSx,
  border: "1px solid var(--border-default)",
  color: "text.primary",
  transition: "border-color var(--motion-base) var(--ease-standard)",
  "&:hover": { borderColor: "var(--border-strong)" },
};

export function PageMessageLink({
  href,
  children,
  emphasis = "primary",
}: Readonly<PageMessageLinkProps>) {
  return (
    <NavLink
      href={href}
      sx={emphasis === "primary" ? primaryActionSx : secondaryActionSx}
    >
      {children}
    </NavLink>
  );
}
