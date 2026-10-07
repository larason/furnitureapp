import { ClerkProvider } from "@clerk/nextjs";
import type { Metadata } from "next";
import localFont from "next/font/local";
import type { CSSProperties } from "react";
import "../../design-system/tokens.css";
import "./globals.css";
import { SiteShell } from "@/components/layout/site-shell";
import { clerkAppearance } from "@/lib/auth/clerk-appearance";
import { getMetadataBase, SITE_DEFAULT_DESCRIPTION, SITE_DEFAULT_TITLE, SITE_NAME } from "@/lib/seo/site";
import { Providers } from "./providers";

const youngSerif = localFont({
  src: "../public/fonts/young-serif-latin.woff2",
  weight: "400",
  display: "swap",
  fallback: ["Georgia", "serif"],
  adjustFontFallback: "Times New Roman",
});

export const metadata: Metadata = {
  metadataBase: getMetadataBase(),
  title: {
    default: `${SITE_DEFAULT_TITLE} | ${SITE_NAME}`,
    template: `%s | ${SITE_NAME}`,
  },
  description: SITE_DEFAULT_DESCRIPTION,
  applicationName: SITE_NAME,
  openGraph: {
    title: SITE_DEFAULT_TITLE,
    description: SITE_DEFAULT_DESCRIPTION,
    siteName: SITE_NAME,
    type: "website",
  },
  twitter: {
    card: "summary",
  },
  icons: {
    icon: "/favicon.svg",
  },
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="en" style={{ "--font-display": youngSerif.style.fontFamily } as CSSProperties}>
      <body>
        <ClerkProvider afterSignOutUrl="/" appearance={clerkAppearance}>
          <Providers>
            <SiteShell>{children}</SiteShell>
          </Providers>
        </ClerkProvider>
      </body>
    </html>
  );
}
