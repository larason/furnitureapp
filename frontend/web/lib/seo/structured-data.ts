import { selectPrimaryImage } from "../catalog/media";
import type { ProductDetail } from "../catalog/types";
import { buildCanonicalUrl, getSiteOrigin, SITE_NAME } from "./site";
import { normalizeDescription } from "./text";

export type JsonLdNode = Readonly<Record<string, unknown>>;
export type BreadcrumbItem = Readonly<{ name: string; path: string }>;

const SCRIPT_BREAKOUT_PATTERNS: readonly [RegExp, string][] = [
  [/</g, "\\u003c"],
  [/>/g, "\\u003e"],
  [/&/g, "\\u0026"],
  [/\u2028/g, "\\u2028"],
  [/\u2029/g, "\\u2029"],
];

export function serializeJsonLd(data: JsonLdNode): string {
  const json = JSON.stringify(data) ?? "null";
  return SCRIPT_BREAKOUT_PATTERNS.reduce((result, [pattern, replacement]) => result.replace(pattern, replacement), json);
}

export function buildSiteStructuredData(): JsonLdNode | undefined {
  const origin = getSiteOrigin();
  if (!origin) {
    return undefined;
  }

  return {
    "@context": "https://schema.org",
    "@graph": [
      { "@type": "WebSite", "@id": `${origin}/#website`, url: `${origin}/`, name: SITE_NAME },
      { "@type": "Organization", "@id": `${origin}/#organization`, name: SITE_NAME, url: `${origin}/` },
    ],
  };
}

export function buildProductStructuredData(product: ProductDetail): JsonLdNode | undefined {
  const origin = getSiteOrigin();
  const canonical = buildCanonicalUrl(`/products/${encodeURIComponent(product.slug)}`);
  if (!origin || !canonical) {
    return undefined;
  }

  const node: Record<string, unknown> = {
    "@context": "https://schema.org",
    "@type": "Product",
    "@id": `${canonical}#product`,
    name: product.name,
    url: canonical,
  };

  const description = normalizeDescription(product.description);
  if (description) {
    node.description = description;
  }

  const image = selectPrimaryImage(product.images);
  if (image) {
    node.image = [absoluteMediaUrl(image.url, origin)];
  }

  if (product.category.name) {
    node.category = product.category.name;
  }

  return node;
}

export function buildBreadcrumbStructuredData(items: readonly BreadcrumbItem[]): JsonLdNode | undefined {
  if (!getSiteOrigin() || items.length === 0) {
    return undefined;
  }

  return {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: items.map((item, index) => ({
      "@type": "ListItem",
      position: index + 1,
      name: item.name,
      item: buildCanonicalUrl(item.path),
    })),
  };
}

function absoluteMediaUrl(url: string, origin: string): string {
  try {
    return new URL(url).toString();
  } catch {
    return new URL(url.startsWith("/") ? url : `/${url}`, origin).toString();
  }
}
