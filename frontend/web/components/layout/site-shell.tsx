import Box from "@mui/material/Box";
import type { ReactNode } from "react";
import { SiteFooter } from "./site-footer";
import { SiteHeader } from "./site-header";

export const MAIN_CONTENT_ID = "main-content";

export function SiteShell({ children }: Readonly<{ children: ReactNode }>) {
  return (
    <Box
      sx={{
        display: "flex",
        flexDirection: "column",
        minHeight: "100dvh",
        backgroundColor: "var(--surface-canvas)",
      }}
    >
      <Box
        component="a"
        href={`#${MAIN_CONTENT_ID}`}
        sx={{
          position: "absolute",
          width: "1px",
          height: "1px",
          p: 0,
          m: "-1px",
          border: 0,
          overflow: "hidden",
          clip: "rect(0 0 0 0)",
          whiteSpace: "nowrap",
          "&:focus": {
            position: "fixed",
            top: "var(--space-4)",
            left: "var(--space-4)",
            width: "auto",
            height: "auto",
            m: 0,
            p: "var(--space-3)",
            overflow: "visible",
            clip: "auto",
            whiteSpace: "normal",
            zIndex: "var(--z-dialog)",
            backgroundColor: "var(--surface-inverse)",
            color: "var(--text-inverse)",
            borderRadius: "var(--radius-sm)",
            boxShadow: "var(--focus-ring)",
          },
        }}
      >
        Skip to main content
      </Box>
      <SiteHeader />
      <Box
        component="main"
        id={MAIN_CONTENT_ID}
        tabIndex={-1}
        sx={{ flexGrow: 1 }}
      >
        {children}
      </Box>
      <SiteFooter />
    </Box>
  );
}
