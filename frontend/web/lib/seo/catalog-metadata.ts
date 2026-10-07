import type { Metadata } from "next";
import { collectionPage, singleValue, type ProductCollectionQuery } from "../catalog/filters";
import { selectPrimaryImage } from "../catalog/media";
import type { CategoryDetail, ProductDetail } from "../catalog/types";
import { createPageMetadata, NOINDEX_FOLLOW, type SocialImage } from "./metadata";
import { SITE_DEFAULT_DESCRIPTION, SITE_DEFAULT_TITLE, SITE_NAME } from "./site";

const MAX_DESCRIPTION_LENGTH = 300;
const MAX_SEARCH_TERM_LENGTH = 60;

const FACET_QUERY_KEYS = ["category", "product_type", "availability", "min_price", "max_price", "sort", "sort_direction"] as const;

const PRODUCTS_DESCRIPTION = "Browse furniture from SL Furnitures, including ready-made pieces and made-to-order designs.";
const SEARCH_DESCRIPTION = "Search the SL Furnitures catalog by furniture name and product details.";

export function buildHomeMetadata(): Metadata {
  return createPageMetadata({
    title: SITE_DEFAULT_TITLE,
    description: SITE_DEFAULT_DESCRIPTION,
    canonicalPath: "/",
  });
}

export function buildProductsMetadata(query: ProductCollectionQuery): Metadata {
  const faceted = FACET_QUERY_KEYS.some((key) => query[key] !== undefined);
  const page = collectionPage(query);

  return createPageMetadata({
    title: "Furniture",
    description: PRODUCTS_DESCRIPTION,
    canonicalPath: faceted || page <= 1 ? "/products" : `/products?page=${page}`,
    robots: faceted ? NOINDEX_FOLLOW : undefined,
  });
}

export function buildSearchMetadata(query: ProductCollectionQuery): Metadata {
  const term = singleValue(query.search)?.trim() ?? "";

  return createPageMetadata({
    title: term ? `Search results for \u201C${boundText(term, MAX_SEARCH_TERM_LENGTH)}\u201D` : "Search furniture",
    description: SEARCH_DESCRIPTION,
    canonicalPath: "/search",
    robots: NOINDEX_FOLLOW,
  });
}

export function buildProductMetadata(product: ProductDetail): Metadata {
  const image = selectPrimaryImage(product.images);

  return createPageMetadata({
    title: product.name,
    description: normalizeDescription(product.description) ?? `${product.name} in the ${SITE_NAME} furniture collection.`,
    canonicalPath: `/products/${encodeURIComponent(product.slug)}`,
    images: image ? [toSocialImage(image.url, image.alt_text, product.name)] : [],
  });
}

export function buildCategoryMetadata(category: CategoryDetail, page: number): Metadata {
  const basePath = `/categories/${encodeURIComponent(category.slug)}`;

  return createPageMetadata({
    title: category.name,
    description: normalizeDescription(category.description) ?? `${category.name} furniture from ${SITE_NAME}.`,
    canonicalPath: page > 1 ? `${basePath}?page=${page}` : basePath,
    images: category.image ? [toSocialImage(category.image.url, null, category.name)] : [],
  });
}

function toSocialImage(url: string, alt: string | null, fallbackAlt: string): SocialImage {
  return { url, alt: alt?.trim() || fallbackAlt };
}

function normalizeDescription(value: string | null | undefined): string | undefined {
  if (!value) {
    return undefined;
  }
  const normalized = value.replace(/\s+/g, " ").trim();
  return normalized ? boundText(normalized, MAX_DESCRIPTION_LENGTH) : undefined;
}

function boundText(value: string, maxLength: number): string {
  return value.length > maxLength ? `${value.slice(0, maxLength - 1).trimEnd()}\u2026` : value;
}
