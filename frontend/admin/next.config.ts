import type { NextConfig } from "next";
import { join } from "node:path";
import { catalogMediaRemotePatterns, resolveCatalogMediaOrigin } from "./lib/catalog/media-origin";

/**
 * Only the configured public media origin is renderable. When
 * `CATALOG_MEDIA_BASE_URL` is missing or invalid the allow-list is empty and the
 * UI shows an explicit unavailable state rather than allowing arbitrary hosts.
 */
const mediaRemotePatterns = catalogMediaRemotePatterns(resolveCatalogMediaOrigin(process.env.CATALOG_MEDIA_BASE_URL));

/**
 * Server Action bodies are capped at 1MB by default, below the 5 MiB product
 * image limit Laravel enforces. This raises the admin ingress to accept the
 * backend's own limit plus multipart overhead; it does not change any server
 * limit or the backend constraint.
 */
const SERVER_ACTION_BODY_SIZE_LIMIT = "6mb";

const nextConfig: NextConfig = {
  turbopack: { root: join(__dirname, "..") },
  images: { remotePatterns: mediaRemotePatterns },
  experimental: { serverActions: { bodySizeLimit: SERVER_ACTION_BODY_SIZE_LIMIT } },
};

export default nextConfig;
