import type { MetadataRoute } from "next";
import { buildRobotsRules } from "@/lib/seo/crawl";

export const dynamic = "force-dynamic";

export default function robots(): MetadataRoute.Robots {
  return buildRobotsRules();
}
