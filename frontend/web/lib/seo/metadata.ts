import type { Metadata } from "next";
import { buildCanonicalUrl, getSiteOrigin, SITE_NAME } from "./site";

export type SocialImage = Readonly<{ url: string; alt: string }>;

export const NOINDEX_FOLLOW = { index: false, follow: true } as const;

export type PageMetadataInput = Readonly<{
  title: string;
  description: string;
  canonicalPath: string;
  images?: readonly SocialImage[];
  robots?: Metadata["robots"];
}>;

export function createPageMetadata(input: PageMetadataInput): Metadata {
  const canonical = buildCanonicalUrl(input.canonicalPath);
  const images = getSiteOrigin() ? input.images ?? [] : [];

  return {
    title: input.title,
    description: input.description,
    ...(input.robots ? { robots: input.robots } : {}),
    ...(canonical ? { alternates: { canonical } } : {}),
    openGraph: {
      title: input.title,
      description: input.description,
      siteName: SITE_NAME,
      type: "website",
      ...(canonical ? { url: canonical } : {}),
      ...(images.length ? { images: images.map((image) => ({ url: image.url, alt: image.alt })) } : {}),
    },
    twitter: {
      card: images.length ? "summary_large_image" : "summary",
      title: input.title,
      description: input.description,
      ...(images.length ? { images: images.map((image) => image.url) } : {}),
    },
  };
}
