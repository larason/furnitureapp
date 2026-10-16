import test from "node:test";
import assert from "node:assert/strict";
import { existsSync, readFileSync, readdirSync, statSync } from "node:fs";
import { dirname, join, relative, sep } from "node:path";
import { fileURLToPath } from "node:url";
import { AdminPermissionName } from "../lib/auth/permission-names";
import type { OperationalIdentity } from "../lib/auth/types";
import { ADMIN_ROUTE_PATHS, ADMIN_PROTECTED_MATCHERS, ADMIN_PROTECTED_PATHS } from "../lib/navigation/admin-route-paths";
import {
  ADMIN_OVERVIEW_HREF,
  ADMIN_ROUTES,
  adminRoute,
  buildBreadcrumbs,
  findActiveNavigation,
  findAdminRoute,
  isNavigationHrefActive,
  resolveAdminNavigation,
} from "../lib/navigation/admin-navigation";

const PROTECTED_DIRECTORY = fileURLToPath(new URL("../app/(protected)", import.meta.url));
const LARAVEL_PERMISSION_SOURCE = readFileSync(fileURLToPath(new URL("../../../backend/laravel/app/Support/PermissionName.php", import.meta.url)), "utf8");

const IDENTITY: OperationalIdentity = { id: "1", name: "Operator", email: "operator@example.test", role: "STAFF", accountState: "ACTIVE", permissions: [] };
const STAFF_PERMISSIONS = ["enquiries.view", "inventory.view", "notifications.read_operational", "products.manage", "requests.view"];
const ADMIN_PERMISSIONS = [...STAFF_PERMISSIONS, "audit.view", "users.manage_authorized"];

function identity(overrides: Partial<OperationalIdentity>): OperationalIdentity {
  return { ...IDENTITY, ...overrides };
}

function laravelPermissionIdentifiers(): ReadonlySet<string> {
  return new Set([...LARAVEL_PERMISSION_SOURCE.matchAll(/case\s+[A-Z_]+\s*=\s*'([^']+)'/g)].map((match) => match[1]));
}

function protectedPageFiles(directory: string): readonly string[] {
  return readdirSync(directory).flatMap((entry) => {
    const path = join(directory, entry);
    if (statSync(path).isDirectory()) return protectedPageFiles(path);
    return entry === "page.tsx" ? [path] : [];
  });
}

function pageFileFor(href: string): string {
  return href === ADMIN_OVERVIEW_HREF ? join(PROTECTED_DIRECTORY, "page.tsx") : join(PROTECTED_DIRECTORY, href.slice(1), "page.tsx");
}

test("every configured route has a stable identifier, a unique absolute path and navigation metadata", () => {
  const ids = new Set<string>();
  const hrefs = new Set<string>();
  for (const route of ADMIN_ROUTES) {
    assert.match(route.id, /^[a-z][a-z0-9-]*$/, `${route.href} needs a stable identifier.`);
    assert.equal(ids.has(route.id), false, `${route.id} is duplicated.`);
    assert.equal(hrefs.has(route.href), false, `${route.href} is duplicated.`);
    assert.match(route.href, /^\/$|^\/[a-z0-9-]+(?:\/[a-z0-9-]+)*$/, `${route.href} must be an absolute admin path.`);
    assert.ok(route.label.trim().length > 0 && route.description.trim().length > 0, `${route.href} needs a label and description.`);
    assert.ok(route.icon, `${route.href} needs an icon.`);
    ids.add(route.id);
    hrefs.add(route.href);
  }
});

test("every protected entry maps to an approved Laravel permission and no permission is invented", () => {
  const approved = laravelPermissionIdentifiers();
  assert.ok(approved.size > 0, "PermissionName identifiers could not be read from Laravel.");
  const configured = ADMIN_ROUTES.filter((route) => route.permission).map((route) => route.permission as string);
  assert.ok(configured.length > 0, "Navigation must use exact permissions.");
  for (const permission of configured) assert.ok(approved.has(permission), `${permission} is not a Laravel PermissionName identifier.`);
  for (const permission of Object.values(AdminPermissionName)) assert.ok(approved.has(permission), `${permission} is not a Laravel PermissionName identifier.`);
  assert.equal(ADMIN_ROUTES.some((route) => route.permission === "staff.manage"), false, "Staff management stays omitted while its backend endpoints return 501.");
});

test("staff management is not advertised while its backend endpoints are unimplemented", () => {
  assert.equal(findAdminRoute("/staff"), undefined);
});

test("the overview route is admission-only and every other protected entry declares an exact permission", () => {
  for (const route of ADMIN_ROUTES) {
    if (route.href === ADMIN_OVERVIEW_HREF) assert.equal(route.permission, undefined);
    else assert.ok(route.permission, `${route.href} must declare an exact permission.`);
  }
});

test("navigation is filtered from Laravel-resolved permissions", () => {
  const staffGroups = resolveAdminNavigation(identity({ permissions: STAFF_PERMISSIONS }));
  const staffItems = staffGroups.flatMap((group) => group.items.map((item) => item.href));
  assert.deepEqual([...staffItems].sort(), ["/", "/categories", "/enquiries", "/inventory", "/notifications", "/products", "/requests"].sort());

  const adminGroups = resolveAdminNavigation(identity({ role: "ADMIN", permissions: ADMIN_PERMISSIONS }));
  const adminItems = adminGroups.flatMap((group) => group.items.map((item) => item.href));
  assert.deepEqual([...adminItems].sort(), [...staffItems, "/audit-logs", "/customers"].sort());
});

test("an ADMIN role alone does not reveal a link for a permission the identity does not hold", () => {
  const groups = resolveAdminNavigation(identity({ role: "ADMIN", permissions: ["requests.view"] }));
  const hrefs = groups.flatMap((group) => group.items.map((item) => item.href));
  assert.deepEqual(hrefs, [ADMIN_OVERVIEW_HREF, "/requests"]);
});

test("a STAFF identity with an exact permission sees that link and a STAFF identity without it does not", () => {
  const withPermission = resolveAdminNavigation(identity({ permissions: ["enquiries.view"] }));
  assert.ok(withPermission.some((group) => group.items.some((item) => item.href === "/enquiries")));
  const withoutPermission = resolveAdminNavigation(identity({ permissions: ["requests.view"] }));
  assert.equal(withoutPermission.some((group) => group.items.some((item) => item.href === "/enquiries")), false);
});

test("role-restricted entries need both the exact permission and the approved role", () => {
  const staffWithPermission = resolveAdminNavigation(identity({ role: "STAFF", permissions: ADMIN_PERMISSIONS }));
  assert.equal(staffWithPermission.some((group) => group.items.some((item) => item.href === "/customers")), false);
  const adminWithoutPermission = resolveAdminNavigation(identity({ role: "ADMIN", permissions: ["audit.view"] }));
  assert.equal(adminWithoutPermission.some((group) => group.items.some((item) => item.href === "/customers")), false);
  const adminWithPermission = resolveAdminNavigation(identity({ role: "ADMIN", permissions: ["users.manage_authorized"] }));
  assert.ok(adminWithPermission.some((group) => group.items.some((item) => item.href === "/customers")));
});

test("missing or malformed permission data fails closed and an empty set stays usable", () => {
  assert.deepEqual(resolveAdminNavigation(identity({ permissions: undefined as unknown as readonly string[] })), []);
  assert.deepEqual(resolveAdminNavigation(identity({ permissions: "requests.view" as unknown as readonly string[] })), []);
  const empty = resolveAdminNavigation(identity({ permissions: [] }));
  assert.equal(empty.flatMap((group) => group.items.map((item) => item.href)).includes("/requests"), false);
  assert.equal(buildBreadcrumbs(findActiveNavigation(empty, "/")).length, 1);
});

test("active-route matching is exact for the overview and prefix-safe elsewhere", () => {
  assert.equal(isNavigationHrefActive(ADMIN_OVERVIEW_HREF, "/"), true);
  assert.equal(isNavigationHrefActive(ADMIN_OVERVIEW_HREF, "/requests"), false);
  assert.equal(isNavigationHrefActive("/requests", "/requests"), true);
  assert.equal(isNavigationHrefActive("/requests", "/requests/42"), true);
  assert.equal(isNavigationHrefActive("/requests", "/requests-archive"), false);
  assert.equal(isNavigationHrefActive("/products", "/products-archive"), false);
  assert.equal(isNavigationHrefActive("/products", "/categories"), false);
});

test("only one destination is active and the most specific configured route wins", () => {
  const groups = resolveAdminNavigation(identity({ role: "ADMIN", permissions: ADMIN_PERMISSIONS }));
  assert.equal(findActiveNavigation(groups, "/")?.item.href, ADMIN_OVERVIEW_HREF);
  assert.equal(findActiveNavigation(groups, "/requests")?.item.href, "/requests");
  assert.equal(findActiveNavigation(groups, "/requests/42")?.item.href, "/requests");
  assert.equal(findActiveNavigation(groups, "/unknown-section"), null);
  const nested = [{ id: "operations" as const, label: "Operations", items: [adminRoute("/requests"), { ...adminRoute("/requests"), id: "requests-detail", href: "/requests/detail" }] }];
  assert.equal(findActiveNavigation(nested, "/requests/detail/9")?.item.href, "/requests/detail");
});

test("a hidden navigation entry cannot become visible or active through route state", () => {
  const groups = resolveAdminNavigation(identity({ permissions: [] }));
  assert.equal(findActiveNavigation(groups, "/customers"), null);
  assert.equal(findActiveNavigation(groups, "/audit-logs/1"), null);
});

test("breadcrumbs reflect the route hierarchy", () => {
  const groups = resolveAdminNavigation(identity({ role: "ADMIN", permissions: ADMIN_PERMISSIONS }));
  assert.deepEqual(buildBreadcrumbs(findActiveNavigation(groups, "/")), [{ label: "Overview" }]);
  assert.deepEqual(buildBreadcrumbs(findActiveNavigation(groups, "/requests")), [{ label: "Overview", href: "/" }, { label: "Made-to-order requests" }]);
  assert.deepEqual(buildBreadcrumbs(null), [{ label: "SL Furnitures Admin" }]);
});

test("every configured route has a protected page that repeats its requirement and resolves its own route", () => {
  for (const route of ADMIN_ROUTES) {
    const pageFile = pageFileFor(route.href);
    assert.ok(existsSync(pageFile), `${route.href} has no protected page at ${pageFile}.`);
    const source = readFileSync(pageFile, "utf8");
    const pathKey = Object.entries(ADMIN_ROUTE_PATHS).find(([, value]) => value === route.href)?.[0];
    assert.ok(pathKey, `${route.href} must be declared in ADMIN_ROUTE_PATHS.`);
    assert.equal(source.includes(`adminRoute(ADMIN_ROUTE_PATHS.${pathKey})`), true, `${route.href} must resolve its own configured route.`);
    assert.match(source, /requireAdminRouteAccess\(/, `${route.href} must repeat server-side authorization.`);
    assert.match(source, /if \(!access\.granted\) return access\.denial;/, `${route.href} must render the denial state when unauthorized.`);
  }
});

test("a route without an implemented workflow still loads no business data", () => {
  for (const route of ADMIN_ROUTES.filter((candidate) => candidate.placeholder === true)) {
    const source = readFileSync(pageFileFor(route.href), "utf8");
    for (const forbidden of ["createAdminApiClient", "fetch(", "/api/v1", "axios"]) {
      assert.equal(source.includes(forbidden), false, `${route.href} must not call business APIs while it is a placeholder (found ${forbidden}).`);
    }
  }
});

test("an implemented route performs data access only after authorization succeeds", () => {
  for (const route of ADMIN_ROUTES.filter((candidate) => candidate.placeholder !== true && candidate.href !== ADMIN_OVERVIEW_HREF)) {
    const source = readFileSync(pageFileFor(route.href), "utf8");
    const body = source.split("\n").filter((line) => !line.trimStart().startsWith("import ")).join("\n");
    assert.match(source, /requireAdminRouteAccess\(/, `${route.href} must keep the route guard.`);
    const denialIndex = body.indexOf("if (!access.granted) return access.denial;");
    assert.notEqual(denialIndex, -1, `${route.href} must render the denial state when unauthorized.`);
    for (const access of ["attemptProductRead(", "fetchOperationalProductPage(", "fetchOperationalProduct(", "fetchCategoryOptions(", "attemptCategoryRead(", "fetchCategoryPage(", "fetchCategory("]) {
      const accessIndex = body.indexOf(access);
      if (accessIndex === -1) continue;
      assert.ok(denialIndex < accessIndex, `${route.href} must authorize before calling ${access}.`);
    }
  }
});

test("the products route uses the operational endpoint, never the public catalog resource", () => {
  const source = readFileSync(join(PROTECTED_DIRECTORY, "products", "page.tsx"), "utf8");
  assert.match(source, /fetchOperationalProductPage/);
  assert.equal(/fetchPublicApi|requestPublicApi/.test(source), false, "the list must not be read through the public catalog resource.");
});

test("the request proxy pre-empts signed-out access to every protected route without deciding authorization", () => {
  assert.deepEqual([...ADMIN_PROTECTED_PATHS].sort(), ADMIN_ROUTES.map((route) => route.href).sort());
  const toRegExp = (matcher: string) => new RegExp(`^${matcher.split("(.*)").map((part) => part.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")).join("(.*)")}$`);
  for (const route of ADMIN_ROUTES) {
    const matcher = ADMIN_PROTECTED_MATCHERS.find((candidate) => toRegExp(candidate).test(route.href));
    assert.ok(matcher, `${route.href} is not covered by the early signed-out redirect.`);
    if (route.href !== ADMIN_OVERVIEW_HREF) assert.ok(toRegExp(matcher).test(`${route.href}/nested`), `${route.href} must cover its nested routes.`);
  }
  const proxy = readFileSync(fileURLToPath(new URL("../proxy.ts", import.meta.url)), "utf8");
  assert.match(proxy, /createRouteMatcher\(\[\.\.\.ADMIN_PROTECTED_MATCHERS\]\)/);
  assert.equal(/permission|\brole\b|isAdmin|ADMIN\b/.test(proxy), false, "the proxy must not decide business authorization.");
});

test("every protected page belongs to a configured route", () => {
  const configured = new Set(ADMIN_ROUTES.map((route) => route.href));
  for (const pageFile of protectedPageFiles(PROTECTED_DIRECTORY)) {
    const directory = relative(PROTECTED_DIRECTORY, dirname(pageFile)).split(sep).join("/");
    const topLevelHref = directory === "" ? ADMIN_OVERVIEW_HREF : `/${directory.split("/")[0]}`;
    assert.ok(configured.has(topLevelHref), `${pageFile} does not belong to a configured route.`);
  }
});
