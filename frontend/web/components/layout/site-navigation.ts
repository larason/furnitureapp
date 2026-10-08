export type ServiceLink = {
  label: string;
  href: string;
};

export const SITE_SERVICE_LINKS: readonly ServiceLink[] = [
  { label: "Made to Order", href: "/furniture-requests" },
  { label: "Contact Us", href: "/contact" },
];

export const SITE_INFORMATION_LINKS: readonly ServiceLink[] = [
  { label: "About Us", href: "/about" },
  { label: "Privacy Policy", href: "/privacy-policy" },
  { label: "Terms of Service", href: "/terms-of-service" },
];

// Routes in web/ROUTING.md that currently resolve to an implemented App Router
// page. Reserved routes render as non-interactive structural content until the
// owning phase ships, so the shell never exposes a broken navigation link.
export const IMPLEMENTED_SITE_ROUTES: readonly string[] = ["/", "/products", "/search", "/sign-in", "/sign-up", "/furniture-requests", "/contact", "/about", "/privacy-policy", "/terms-of-service"];

const IMPLEMENTED_DYNAMIC_ROUTE_PATTERNS = [/^\/categories\/[^/]+$/, /^\/products\/[^/]+$/];

export function isSiteRouteImplemented(href: string): boolean {
  const pathname = href.split(/[?#]/, 1)[0];
  return IMPLEMENTED_SITE_ROUTES.includes(pathname) || IMPLEMENTED_DYNAMIC_ROUTE_PATTERNS.some((pattern) => pattern.test(pathname));
}
