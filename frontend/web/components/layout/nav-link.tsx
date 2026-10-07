import Box from "@mui/material/Box";
import type { SxProps, Theme } from "@mui/material/styles";
import NextLink from "next/link";
import type { ReactNode } from "react";
import { theme } from "@/theme/theme";
import { isSiteRouteImplemented } from "./site-navigation";

export type NavLinkSx = Exclude<SxProps<Theme>, ReadonlyArray<unknown>>;

export type NavLinkProps = {
  href: string;
  children: ReactNode;
  sx?: NavLinkSx;
  "aria-label"?: string;
  onClick?: () => void;
};

export function NavLink({
  href,
  children,
  sx,
  onClick,
  ...rest
}: Readonly<NavLinkProps>) {
  const resolvedSx = typeof sx === "function" ? sx(theme) : sx;

  if (!isSiteRouteImplemented(href)) {
    return (
      <Box component="span" sx={resolvedSx} {...rest}>
        {children}
      </Box>
    );
  }

  return (
    <Box
      sx={{
        display: "contents",
        "& a": {
          color: "inherit",
          ...resolvedSx,
          textDecoration: "none",
          fontWeight: "var(--font-weight-regular)",
          textTransform: "none",
        },
        "& a:hover": {
          textDecoration: "underline",
        },
        "& a:focus-visible": {
          boxShadow: "var(--focus-ring)",
          borderRadius: "var(--radius-sm)",
        },
      }}
    >
      <NextLink href={href} onClick={onClick} {...rest}>
        {children}
      </NextLink>
    </Box>
  );
}
