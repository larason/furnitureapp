/**
 * Admin route paths only. Kept free of UI, icon and router imports so the
 * request proxy can consume them without bundling presentation code.
 */
export const ADMIN_ROUTE_PATHS = {
  overview: "/",
  requests: "/requests",
  enquiries: "/enquiries",
  notifications: "/notifications",
  products: "/products",
  categories: "/categories",
  inventory: "/inventory",
  customers: "/customers",
  auditLogs: "/audit-logs",
} as const;

export const ADMIN_PROTECTED_PATHS: readonly string[] = Object.values(ADMIN_ROUTE_PATHS);

/** Proxy matcher patterns: each protected path plus its nested routes. */
export const ADMIN_PROTECTED_MATCHERS: readonly string[] = ADMIN_PROTECTED_PATHS.map((path) => (path === "/" ? path : `${path}(.*)`));
