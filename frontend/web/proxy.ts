import { clerkMiddleware } from "@clerk/nextjs/server";
import { NextResponse } from "next/server";
import type { NextFetchEvent, NextRequest } from "next/server";
import { ApiError, apiRequest, type RequestFunction } from "@/lib/api/client";

const clerkProxy = clerkMiddleware(async (_auth, request) => preflightCatalogDetail(request));

export function proxy(request: NextRequest, event: NextFetchEvent) {
  return clerkProxy(request, event);
}

export async function preflightCatalogDetail(request: NextRequest, requestCatalogResource: RequestFunction = apiRequest) {
  if (process.env.HOMEPAGE_DATA_SOURCE === "fixtures") {
    return NextResponse.next();
  }

  const path = request.nextUrl.pathname.split("/").filter(Boolean);
  const [resource, slug] = path;
  if (!isCatalogDetail(resource, slug, path)) {
    return NextResponse.next();
  }

  const apiPath = resource === "products" ? `products/${encodeURIComponent(slug)}` : `categories/${encodeURIComponent(slug)}`;

  try {
    await requestCatalogResource({ path: apiPath, cache: "no-store" });
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) {
      return NextResponse.next({ status: 404 });
    }
  }

  return NextResponse.next();
}

function isCatalogDetail(resource: string | undefined, slug: string | undefined, path: string[]): boolean {
  return path.length === 2 && Boolean(slug) && (resource === "products" || resource === "categories");
}

export const config = {
  matcher: [
    "/((?!_next|[^?]*\\.(?:html?|css|js(?!on)|jpe?g|webp|png|gif|svg|ttf|woff2?|ico|csv|docx?|xlsx?|zip|webmanifest)).*)",
    "/(api|trpc)(.*)",
    "/__clerk/(.*)",
    "/categories/:path*",
    "/products/:slug",
  ],
};
