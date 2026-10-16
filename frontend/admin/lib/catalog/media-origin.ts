/**
 * The public media origin Laravel builds product image URLs from
 * (`R2_PUBLIC_BASE_URL`). It lives in one dependency-free module because both the
 * Next image allow-list (`next.config.ts`) and the server-rendered previews must
 * agree on exactly one origin: a returned URL is either renderable or shown as
 * unavailable, and is never rewritten to make a mismatch disappear.
 */
export interface CatalogMediaOrigin {
  readonly protocol: "https";
  readonly hostname: string;
  readonly port: string;
  /** Normalized origin, for example `https://cdn.example.com` (never carries a path). */
  readonly origin: string;
  /** Path prefix media is served under; `""` means the origin root. */
  readonly basePath: string;
}

export interface CatalogMediaRemotePattern {
  readonly protocol: "https";
  readonly hostname: string;
  readonly port: string;
  readonly pathname: string;
  readonly search: string;
}

/**
 * Reads the configured media origin. A missing, malformed, insecure or
 * credential-bearing value resolves to `null`, which fails closed: previews then
 * render an explicit unavailable state instead of allowing an arbitrary host.
 */
export function resolveCatalogMediaOrigin(value: string | undefined): CatalogMediaOrigin | null {
  const raw = value?.trim();
  if (!raw) return null;

  let url: URL;
  try {
    url = new URL(raw);
  } catch {
    return null;
  }

  if (url.protocol !== "https:" || url.username || url.password || url.search || url.hash) return null;

  return { protocol: "https", hostname: url.hostname, port: url.port, origin: url.origin, basePath: normalizeBasePath(url.pathname) };
}

/** True only when the URL is https and shares the configured origin and base path. */
export function isRenderableMediaUrl(value: string, mediaOrigin: CatalogMediaOrigin | null): boolean {
  if (mediaOrigin === null) return false;

  let url: URL;
  try {
    url = new URL(value);
  } catch {
    return false;
  }

  if (url.protocol !== "https:" || url.origin !== mediaOrigin.origin) return false;
  if (mediaOrigin.basePath === "") return true;
  return url.pathname === mediaOrigin.basePath || url.pathname.startsWith(`${mediaOrigin.basePath}/`);
}

/** The narrowest Next image allow-list entry for the configured origin; `[]` fails closed. */
export function catalogMediaRemotePatterns(mediaOrigin: CatalogMediaOrigin | null): CatalogMediaRemotePattern[] {
  if (mediaOrigin === null) return [];
  return [
    {
      protocol: mediaOrigin.protocol,
      hostname: mediaOrigin.hostname,
      port: mediaOrigin.port,
      pathname: `${mediaOrigin.basePath}/**`,
      search: "",
    },
  ];
}

function normalizeBasePath(pathname: string): string {
  // A trailing `\/+$` replace backtracks quadratically on input such as "///x",
  // so trim the trailing separators directly instead.
  let end = pathname.length;
  while (end > 0 && pathname.charAt(end - 1) === "/") {
    end -= 1;
  }
  const trimmed = pathname.slice(0, end);
  return trimmed === "/" ? "" : trimmed;
}
