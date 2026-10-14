import type { Metadata } from "next";
import type { ReactNode } from "react";
import "./globals.css";
import { Providers } from "@/components/providers/providers";

export const metadata: Metadata = {
  title: { default: "SL Furnitures Admin", template: "%s | SL Furnitures Admin" },
  description: "Private SL Furnitures operational workspace.",
  applicationName: "SL Furnitures Admin",
  robots: { index: false, follow: false },
};

export default function RootLayout({ children }: Readonly<{ children: ReactNode }>) {
  return <html lang="en"><body><Providers>{children}</Providers></body></html>;
}
