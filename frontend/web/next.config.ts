import type { NextConfig } from "next";
import { join } from "node:path";

const catalogMediaBaseUrl = process.env.CATALOG_MEDIA_BASE_URL ?? process.env.API_BASE_URL;

const nextConfig: NextConfig = {
  images: {
    remotePatterns: catalogMediaBaseUrl
      ? [new URL(`${catalogMediaBaseUrl.replace(/\/$/, "")}/**`)]
      : [],
  },
  turbopack: {
    // The web app consumes the sibling canonical design-system package.
    root: join(__dirname, ".."),
  },
};

export default nextConfig;
