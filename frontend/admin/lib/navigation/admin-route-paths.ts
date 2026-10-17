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

/**
 * Nested category routes that belong to the `/categories` destination. Like the
 * product paths, they are not navigation entries and are already covered by the
 * `/categories(.*)` protected matcher.
 */
export const ADMIN_CATEGORY_PATHS = {
  list: ADMIN_ROUTE_PATHS.categories,
  create: `${ADMIN_ROUTE_PATHS.categories}/new`,
  detail: (categoryId: string) => `${ADMIN_ROUTE_PATHS.categories}/${encodeURIComponent(categoryId)}`,
  edit: (categoryId: string) => `${ADMIN_ROUTE_PATHS.categories}/${encodeURIComponent(categoryId)}/edit`,
} as const;

/**
 * Nested made-to-order request routes. Like the product and category paths they
 * are not navigation entries, and the `/requests(.*)` protected matcher already
 * covers them.
 */
export const ADMIN_REQUEST_PATHS = {
  list: ADMIN_ROUTE_PATHS.requests,
  detail: (requestId: string) => `${ADMIN_ROUTE_PATHS.requests}/${encodeURIComponent(requestId)}`,
} as const;

export const ADMIN_PROTECTED_PATHS: readonly string[] = Object.values(ADMIN_ROUTE_PATHS);

/** Proxy matcher patterns: each protected path plus its nested routes. */
export const ADMIN_PROTECTED_MATCHERS: readonly string[] = ADMIN_PROTECTED_PATHS.map((path) => (path === "/" ? path : `${path}(.*)`));
