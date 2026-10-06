import Box from "@mui/material/Box";
import type { SxProps, Theme } from "@mui/material/styles";
import NextLink from "next/link";
import type { ReactNode } from "react";

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
}: NavLinkProps) {
  return (
    <Box sx={{ display: "contents", "& a": sx }}>
      <NextLink href={href} onClick={onClick} {...rest}>
        {children}
      </NextLink>
    </Box>
  );
}
