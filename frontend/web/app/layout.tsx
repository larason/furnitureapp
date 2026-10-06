import type { Metadata } from "next";
import localFont from "next/font/local";
import type { CSSProperties } from "react";
import "../../design-system/tokens.css";
import "./globals.css";
import { SiteShell } from "@/components/layout/site-shell";
import { Providers } from "./providers";

const youngSerif = localFont({
  src: "../public/fonts/young-serif-latin.woff2",
  weight: "400",
  display: "swap",
  fallback: ["Georgia", "serif"],
  adjustFontFallback: "Times New Roman",
});

export const metadata: Metadata = {
  title: "SL Furnitures",
  description: "Premium for less.",
  icons: {
    icon: "/favicon.svg",
  },
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="en" style={{ "--font-display": youngSerif.style.fontFamily } as CSSProperties}>
      <body>
        <Providers>
          <SiteShell>{children}</SiteShell>
        </Providers>
      </body>
    </html>
  );
}
