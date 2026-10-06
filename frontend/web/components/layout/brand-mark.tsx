import Box from "@mui/material/Box";
import Image from "next/image";
import NextLink from "next/link";

const LOGO_WIDTH = 3500;
const LOGO_HEIGHT = 1440;

export function BrandMark() {
  return (
    <Box
      sx={{
        display: "contents",
        "& a": {
          display: "inline-flex",
          alignItems: "center",
          borderRadius: "var(--radius-sm)",
        },
        "& a:focus-visible": { boxShadow: "var(--focus-ring)" },
        "& img": {
          height: { xs: "var(--space-7)", md: "var(--space-8)" },
          width: "auto",
        },
      }}
    >
      <NextLink href="/" aria-label="SL Furnitures home">
        <Image
          src="/brandlogo.png"
          alt="SL Furnitures"
          width={LOGO_WIDTH}
          height={LOGO_HEIGHT}
          priority
        />
      </NextLink>
    </Box>
  );
}
