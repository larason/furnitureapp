"use client";

import Box from "@mui/material/Box";
import Image from "next/image";
import NextLink from "next/link";
import { ADMIN_OVERVIEW_HREF } from "@/lib/navigation/admin-navigation";

const LOGO_WIDTH = 4666;
const LOGO_HEIGHT = 1920;

export function AdminBrand() {
  return (
    <Box
      component={NextLink}
      href={ADMIN_OVERVIEW_HREF}
      aria-label="SL Furnitures admin overview"
      sx={{
        display: "inline-flex",
        alignItems: "center",
        gap: 1,
        minWidth: 0,
        color: "text.primary",
        textDecoration: "none",
        borderRadius: "var(--radius-sm)",
        "&:focus-visible": { boxShadow: "var(--focus-ring)" },
        "& img": { height: "var(--space-7)", width: "auto", maxWidth: "100%", objectFit: "contain" },
      }}
    >
      <Image src="/brandlogo.svg" alt="" width={LOGO_WIDTH} height={LOGO_HEIGHT} unoptimized />
    </Box>
  );
}
