const LOCAL_HOSTS = new Set(["localhost", "127.0.0.1", "::1", "[::1]"]);

export const SITE_NAME = "SL Furnitures";
export const SITE_DEFAULT_TITLE = "Furniture for the way you live";
export const SITE_DEFAULT_DESCRIPTION = "Discover furniture for your home from SL Furnitures, including made-to-order pieces shaped around your space.";

export class SiteUrlError extends Error {
  constructor(message: string) {
    super(message);
    this.name = "SiteUrlError";
  }
}

let warnedAboutMissingSiteUrl = false;

export function getSiteOrigin(): string | undefined {
  const value = process.env.SITE_URL?.trim();
  if (!value) {
    const isBuildPhase = process.env.NEXT_PHASE === "phase-production-build";
    if (process.env.NODE_ENV === "production" && !isBuildPhase && !warnedAboutMissingSiteUrl) {
      warnedAboutMissingSiteUrl = true;
      console.warn("SITE_URL is not configured. Canonical and social metadata will omit absolute URLs.");
    }
    return undefined;
  }

  let url: URL;
  try {
    url = new URL(value);
  } catch {
    throw new SiteUrlError("SITE_URL must be a valid absolute origin, for example https://example.com.");
  }

  const allowLocalHttp = process.env.NODE_ENV !== "production";
  const validProtocol = url.protocol === "https:" || (allowLocalHttp && url.protocol === "http:" && LOCAL_HOSTS.has(url.hostname));
  if (!validProtocol || url.username || url.password || url.pathname !== "/" || url.search || url.hash) {
    throw new SiteUrlError("SITE_URL must be an HTTPS origin; HTTP is allowed only for local development.");
  }

  return url.origin;
}

export function getMetadataBase(): URL | undefined {
  const origin = getSiteOrigin();
  return origin ? new URL(origin) : undefined;
}

export function buildCanonicalPath(pathname: string): string {
  const [rawPath = "", rawQuery] = pathname.split("?");
  let path = rawPath.startsWith("/") ? rawPath : `/${rawPath}`;
  path = path.replace(/(^|[^/])\/+$/, "$1") || "/";
  return rawQuery ? `${path}?${rawQuery}` : path;
}

export function buildCanonicalUrl(pathname: string): string | undefined {
  const origin = getSiteOrigin();
  if (!origin) {
    return undefined;
  }
  return new URL(buildCanonicalPath(pathname), origin).toString();
}
