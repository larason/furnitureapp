import type { Metadata } from "next";
import "../../design-system/tokens.css";
import "./globals.css";
import { SiteShell } from "@/components/layout/site-shell";
import { Providers } from "./providers";

export const metadata: Metadata = {
  title: "SL Furnitures",
  description: "Furniture made for your home.",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="en">
      <body>
        <Providers>
          <SiteShell>{children}</SiteShell>
        </Providers>
      </body>
    </html>
  );
}
