import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { ApiError, apiRequest } from "@/lib/api/client";

export async function proxy(request: NextRequest) {
  const slug = request.nextUrl.pathname.split("/").pop();
  if (!slug) {
    return NextResponse.next();
  }

  try {
    await apiRequest({ path: `categories/${encodeURIComponent(slug)}`, cache: "no-store" });
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) {
      return NextResponse.next({ status: 404 });
    }
  }

  return NextResponse.next();
}

export const config = {
  matcher: "/categories/:path*",
};
