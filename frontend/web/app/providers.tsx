"use client";

import CssBaseline from "@mui/material/CssBaseline";
import { ThemeProvider } from "@mui/material/styles";
import { AppRouterCacheProvider } from "@mui/material-nextjs/v16-appRouter";
import type { ReactNode } from "react";
import { LaravelSessionBootstrap } from "@/components/auth/laravel-session-bootstrap";
import { theme } from "@/theme";

export function Providers({ children }: Readonly<{ children: ReactNode }>) {
  return (
    <AppRouterCacheProvider>
      <ThemeProvider theme={theme}>
        <CssBaseline />
        <LaravelSessionBootstrap />
        {children}
      </ThemeProvider>
    </AppRouterCacheProvider>
  );
}
