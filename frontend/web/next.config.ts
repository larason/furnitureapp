import type { NextConfig } from "next";
import { join } from "node:path";

const nextConfig: NextConfig = {
  images: {
    remotePatterns: process.env.CATALOG_MEDIA_BASE_URL
      ? [new URL(`${process.env.CATALOG_MEDIA_BASE_URL.replace(/\/$/, "")}/**`)]
      : [],
  },
  turbopack: {
    // The web app consumes the sibling canonical design-system package.
    root: join(__dirname, ".."),
  },
};

export default nextConfig;
