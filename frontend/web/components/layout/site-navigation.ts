export type ServiceLink = {
  label: string;
  href: string;
};

export const SITE_SERVICE_LINKS: readonly ServiceLink[] = [
  { label: "Made to Order", href: "/furniture-requests" },
  { label: "Furniture Enquiries", href: "/contact" },
];

// Routes in web/ROUTING.md that currently resolve to an implemented App Router
// page. Reserved routes render as non-interactive structural content until the
// owning phase ships, so the shell never exposes a broken navigation link.
export const IMPLEMENTED_SITE_ROUTES: readonly string[] = ["/"];

export function isSiteRouteImplemented(href: string): boolean {
  return IMPLEMENTED_SITE_ROUTES.includes(href);
}
