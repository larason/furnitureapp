import type { MetadataRoute } from "next";
import { buildSitemapEntries } from "@/lib/seo/crawl";

export const dynamic = "force-dynamic";

export default function sitemap(): Promise<MetadataRoute.Sitemap> {
  return buildSitemapEntries();
}
