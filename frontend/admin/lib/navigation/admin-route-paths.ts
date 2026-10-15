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

/**
 * Nested catalog routes that belong to the `/products` destination. They are not
 * navigation entries, so they are deliberately excluded from
 * `ADMIN_PROTECTED_PATHS`/`ADMIN_PROTECTED_MATCHERS` (the `/products` matcher
 * already covers them) and from the navigation registry.
 */
export const ADMIN_PRODUCT_PATHS = {
  list: ADMIN_ROUTE_PATHS.products,
  create: `${ADMIN_ROUTE_PATHS.products}/new`,
  detail: (productId: string) => `${ADMIN_ROUTE_PATHS.products}/${encodeURIComponent(productId)}`,
  edit: (productId: string) => `${ADMIN_ROUTE_PATHS.products}/${encodeURIComponent(productId)}/edit`,
} as const;

export const ADMIN_PROTECTED_PATHS: readonly string[] = Object.values(ADMIN_ROUTE_PATHS);

/** Proxy matcher patterns: each protected path plus its nested routes. */
export const ADMIN_PROTECTED_MATCHERS: readonly string[] = ADMIN_PROTECTED_PATHS.map((path) => (path === "/" ? path : `${path}(.*)`));
