import { serializeJsonLd, type JsonLdNode } from "@/lib/seo/structured-data";

export function JsonLd({ data }: Readonly<{ data: JsonLdNode }>) {
  return <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: serializeJsonLd(data) }} />;
}
