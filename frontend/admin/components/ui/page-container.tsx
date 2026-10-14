import Box from "@mui/material/Box";
import type { BoxProps } from "@mui/material/Box";

export function PageContainer({ children, ...props }: BoxProps) {
  return <Box {...props} sx={{ width: "100%", maxWidth: "var(--container-max)", mx: "auto", px: { xs: "var(--container-gutter-phone)", sm: "var(--container-gutter-tablet)", lg: "var(--container-gutter-desktop)" }, py: { xs: 4, md: 6, lg: 7 }, ...props.sx }}>{children}</Box>;
}
