import { clerkMiddleware, createRouteMatcher } from "@clerk/nextjs/server";
import { NextResponse } from "next/server";
import type { NextFetchEvent, NextRequest } from "next/server";

const isProtectedRoute = createRouteMatcher(["/"]);
const hasClerkConfiguration = Boolean(process.env.NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY?.trim());
const clerkProxy = hasClerkConfiguration ? clerkMiddleware(async (auth, request) => {
  if (isProtectedRoute(request)) {
    const session = await auth();
    if (!session.isAuthenticated) return NextResponse.redirect(new URL("/sign-in", request.url));
  }
  return NextResponse.next();
}) : null;

export default function proxy(request: NextRequest, event: NextFetchEvent) {
  return clerkProxy ? clerkProxy(request, event) : NextResponse.next();
}

export const config = { matcher: ["/((?!_next|[^?]*[.](?:html?|css|js(?!on)|jpe?g|webp|png|gif|svg|ttf|woff2?|ico|csv|docx?|xlsx?|zip|webmanifest)).*)", "/(api|trpc)(.*)", "/__clerk/(.*)"] };
