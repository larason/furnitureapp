"use client";

import { ClerkProvider } from "@clerk/nextjs";
import CssBaseline from "@mui/material/CssBaseline";
import { ThemeProvider } from "@mui/material/styles";
import { AppRouterCacheProvider } from "@mui/material-nextjs/v16-appRouter";
import type { ReactNode } from "react";
import { ConfigurationState } from "@/components/states/configuration-state";
import { getClientConfig } from "@/lib/config/client-config";
import { theme } from "@/theme";

export function Providers({ children }: Readonly<{ children: ReactNode }>) {
  const config = getClientConfig();
  const content = <AppRouterCacheProvider><ThemeProvider theme={theme}><CssBaseline />{children}</ThemeProvider></AppRouterCacheProvider>;

  if (!config.clerkPublishableKey) {
    return <AppRouterCacheProvider><ThemeProvider theme={theme}><CssBaseline /><ConfigurationState /></ThemeProvider></AppRouterCacheProvider>;
  }

  return <ClerkProvider publishableKey={config.clerkPublishableKey}>{content}</ClerkProvider>;
}
