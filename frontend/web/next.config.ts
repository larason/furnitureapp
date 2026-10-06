import type { NextConfig } from "next";
import { join } from "node:path";

const nextConfig: NextConfig = {
  turbopack: {
    // The web app consumes the sibling canonical design-system package.
    root: join(__dirname, ".."),
  },
};

export default nextConfig;
