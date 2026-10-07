import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { ApiError, apiRequest } from "@/lib/api/client";

export async function proxy(request: NextRequest) {
  if (process.env.HOMEPAGE_DATA_SOURCE === "fixtures") {
    return NextResponse.next();
  }

  const path = request.nextUrl.pathname.split("/").filter(Boolean);
  const resource = path[0];
  const slug = path.at(-1);
  if (!slug) {
    return NextResponse.next();
  }

  const apiPath = resource === "products" ? `products/${encodeURIComponent(slug)}` : `categories/${encodeURIComponent(slug)}`;

  try {
    await apiRequest({ path: apiPath, cache: "no-store" });
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) {
      return NextResponse.next({ status: 404 });
    }
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/categories/:path*", "/products/:slug"],
};
