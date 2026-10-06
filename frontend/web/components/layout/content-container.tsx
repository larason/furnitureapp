import Box from "@mui/material/Box";
import type { ElementType, ReactNode } from "react";

export type ContentContainerProps = {
  children: ReactNode;
  component?: ElementType;
  id?: string;
};

export function ContentContainer({
  children,
  component = "div",
  id,
}: Readonly<ContentContainerProps>) {
  return (
    <Box
      component={component}
      id={id}
      sx={{
        width: "100%",
        maxWidth: "var(--container-max)",
        mx: "auto",
        px: {
          xs: "var(--container-gutter-phone)",
          sm: "var(--container-gutter-tablet)",
          lg: "var(--container-gutter-desktop)",
        },
      }}
    >
      {children}
    </Box>
  );
}
