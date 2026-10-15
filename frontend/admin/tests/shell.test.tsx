import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { renderToStaticMarkup } from "react-dom/server";
import { ThemeProvider } from "@mui/material/styles";
import { AdminBreadcrumbs } from "../components/layout/admin-breadcrumbs";
import { AdminMobileDrawer } from "../components/layout/admin-mobile-drawer";
import { AdminNavigationList } from "../components/layout/admin-navigation-list";
import { AdminOverviewBody } from "../components/layout/admin-overview-body";
import { AdminRoutePlaceholder } from "../components/layout/admin-route-placeholder";
import { AdminSidebar } from "../components/layout/admin-sidebar";
import { AdminTopbar } from "../components/layout/admin-topbar";
import { AdminUserIdentity } from "../components/layout/admin-user-identity";
import { navigationDrawerReducer } from "../components/layout/navigation-drawer-state";
import { MAIN_CONTENT_ID } from "../components/layout/shell-layout";
import { FeaturePlaceholder } from "../components/ui/feature-placeholder";
import { adminRoute, resolveAdminNavigation } from "../lib/navigation/admin-navigation";
import type { OperationalIdentity } from "../lib/auth/types";
import { theme } from "../theme";

const STAFF: OperationalIdentity = { id: "7", name: "Ama Operator", email: "ama@example.test", role: "STAFF", accountState: "ACTIVE", permissions: ["enquiries.view", "inventory.view", "notifications.read_operational", "products.manage", "requests.view"] };
const ADMIN: OperationalIdentity = { id: "8", name: "Admin", email: "admin@example.test", role: "ADMIN", accountState: "ACTIVE", permissions: [...STAFF.permissions, "audit.view", "users.manage_authorized"] };
const NAMELESS: OperationalIdentity = { id: "9", name: null, email: null, role: "ADMIN", accountState: "ACTIVE", permissions: [] };

const CLEAN_MARKUP = (element: React.ReactElement) => renderToStaticMarkup(<ThemeProvider theme={theme}>{element}</ThemeProvider>);
const read = (relativePath: string) => readFileSync(fileURLToPath(new URL(relativePath, import.meta.url)), "utf8");
/** Visible text only: emotion style blocks and attributes are not user-facing content. */
const visibleText = (markup: string) => markup.replace(/<style[\s\S]*?<\/style>/g, "").replace(/<[^>]*>/g, " ");

test("the desktop sidebar renders accessible, permission-filtered navigation with one active destination", () => {
  const groups = resolveAdminNavigation(STAFF);
  const markup = CLEAN_MARKUP(<AdminSidebar groups={groups} activeItemId="requests" />);
  assert.match(markup, /aria-labelledby="navigation-group-operations"/);
  assert.match(markup, /aria-current="page"/);
  assert.equal((markup.match(/aria-current="page"/g) ?? []).length, 1);
  assert.match(markup, /Made-to-order requests/);
  assert.equal(markup.includes("Audit logs"), false);
  assert.equal(markup.includes("Customers"), false);
});

test("long navigation labels render in full without a fixed height", () => {
  const longLabel = "Deprecated internal catalogue reconciliation workspace for retired warehouse transfers";
  const markup = CLEAN_MARKUP(<AdminNavigationList groups={[{ id: "catalog", label: "Catalog", items: [{ ...adminRoute("/products"), id: "long", label: longLabel }] }]} activeItemId={null} />);
  assert.ok(markup.includes(longLabel));
  assert.equal(/(?:^|[;{ ])height:\s*\d+px/.test(markup), false);
});

test("an empty permission set keeps only the admission-only overview usable", () => {
  const groups = resolveAdminNavigation({ ...STAFF, permissions: [] });
  assert.deepEqual(groups.flatMap((group) => group.items.map((item) => item.href)), ["/"]);
  const markup = CLEAN_MARKUP(<AdminSidebar groups={groups} activeItemId="overview" />);
  assert.match(markup, /Overview/);
  for (const href of ["/requests", "/enquiries", "/products", "/categories", "/inventory", "/notifications", "/customers", "/audit-logs"]) {
    assert.equal(markup.includes(`href="${href}"`), false, `${href} must stay hidden without its permission.`);
  }
});

test("the mobile drawer hides its navigation until it is opened and closes on selection or on desktop", () => {
  const groups = resolveAdminNavigation(ADMIN);
  const closed = CLEAN_MARKUP(<AdminMobileDrawer open={false} onClose={() => {}} groups={groups} activeItemId={null} />);
  assert.equal(closed.includes("Made-to-order requests"), false);
  const opened = CLEAN_MARKUP(<AdminMobileDrawer open onClose={() => {}} groups={groups} activeItemId={null} />);
  assert.equal(opened.includes("Made-to-order requests"), false, "the modal drawer mounts only on the client, so no navigation is server-rendered.");

  assert.equal(navigationDrawerReducer(false, { type: "open" }), true);
  assert.equal(navigationDrawerReducer(true, { type: "dismiss" }), false);
  assert.equal(navigationDrawerReducer(true, { type: "navigate" }), false);
  assert.equal(navigationDrawerReducer(true, { type: "desktop" }), false);
});

test("the drawer and navigation list wire an accessible trigger, close control and selection close", () => {
  const trigger = read("../components/layout/admin-topbar.tsx");
  assert.match(trigger, /aria-label="Open navigation menu"/);
  assert.match(trigger, /aria-haspopup="dialog"/);
  assert.match(trigger, /aria-expanded=\{navigationOpen\}/);
  const drawer = read("../components/layout/admin-mobile-drawer.tsx");
  assert.match(drawer, /onClose=\{onClose\}/);
  assert.match(drawer, /aria-label="Close navigation menu"/);
  const list = read("../components/layout/admin-navigation-list.tsx");
  assert.match(list, /onClick=\{onNavigate\}/);
  assert.match(read("../components/layout/admin-mobile-drawer.tsx"), /onNavigate=\{onClose\}/);
});

test("the top bar exposes page context and a named navigation trigger", () => {
  const markup = CLEAN_MARKUP(
    <AdminTopbar
      title="Made-to-order requests"
      breadcrumbs={[{ label: "Overview", href: "/" }, { label: "Made-to-order requests" }]}
      accountControl={<AdminUserIdentity identity={STAFF} />}
      navigationOpen={false}
      onOpenNavigation={() => {}}
    />,
  );
  assert.match(markup, /Made-to-order requests/);
  assert.match(markup, /aria-label="Open navigation menu"/);
  assert.match(markup, /aria-label="Breadcrumb"/);
  assert.match(markup, /Ama Operator/);
});

test("breadcrumbs express the current page and never fabricate a hierarchy", () => {
  const overview = CLEAN_MARKUP(<AdminBreadcrumbs items={[{ label: "Overview" }]} />);
  assert.match(overview, /aria-current="page"/);
  assert.equal((overview.match(/<a /g) ?? []).length, 0);
  const nested = CLEAN_MARKUP(<AdminBreadcrumbs items={[{ label: "Overview", href: "/" }, { label: "Enquiries" }]} />);
  assert.equal((nested.match(/<a /g) ?? []).length, 1);
  assert.match(nested, /aria-current="page"/);
});

test("the account display uses sanitized fields with approved fallbacks and never a credential", () => {
  const markup = CLEAN_MARKUP(<AdminUserIdentity identity={STAFF} />);
  assert.match(markup, /Ama Operator/);
  assert.match(markup, /ama@example\.test/);
  assert.match(markup, /Role: Staff/);
  const fallback = CLEAN_MARKUP(<AdminUserIdentity identity={NAMELESS} />);
  assert.match(fallback, /Operational user/);
  assert.match(fallback, /No email on file/);
  assert.match(fallback, /Role: Administrator/);
  for (const rendered of [markup, fallback]) {
    assert.equal(/Bearer|eyJ|secret|token/i.test(rendered), false);
    assert.equal(rendered.includes("requests.view"), false);
  }
});

test("the account menu signs out through the existing Clerk flow and keeps no identity of its own", () => {
  const menu = read("../components/layout/admin-user-menu.tsx");
  assert.match(menu, /useClerk\(\)/);
  assert.match(menu, /signOut\(\{ redirectUrl: "\/sign-in" \}\)/);
  assert.equal(/localStorage|sessionStorage|cookie/i.test(menu), false);
  const shell = read("../components/layout/admin-shell.tsx");
  assert.match(shell, /useOperationalIdentity\(\)/);
  assert.equal(/localStorage|sessionStorage|cookie|Bearer/i.test(shell), false);
});

test("the protected shell keeps a single main landmark and an accessible skip link", () => {
  const shell = read("../components/layout/admin-shell.tsx");
  assert.match(shell, /component="main"/);
  assert.match(shell, new RegExp(`id=\\{MAIN_CONTENT_ID\\}`));
  assert.match(shell, /<AdminShell|\{children\}/);
  assert.match(read("../components/layout/admin-skip-link.tsx"), /Skip to main content/);
  assert.equal(MAIN_CONTENT_ID.length > 0, true);
});

test("protected placeholders state their phase, render no records and make no requests", () => {
  const markup = CLEAN_MARKUP(<AdminRoutePlaceholder route={adminRoute("/requests")} phase="Phase 19.10" />);
  assert.match(markup, /<h1[^>]*>Made-to-order requests<\/h1>/);
  assert.match(markup, /Not yet implemented/);
  assert.match(markup, /Phase 19\.10/);
  assert.match(markup, /retrieves no data and performs no operations/);
  assert.equal(/<table|<input|<form/i.test(markup), false);
  const placeholder = CLEAN_MARKUP(<FeaturePlaceholder feature="Made-to-order request handling" phase="Phase 19.10" />);
  assert.match(placeholder, /Planned for Phase 19\.10/);
  assert.equal(/<table|<input|<form/i.test(placeholder), false);
});

test("the overview shows only authorized areas and fabricates no operational metrics", () => {
  const staffMarkup = CLEAN_MARKUP(<AdminOverviewBody identity={STAFF} />);
  assert.match(staffMarkup, /Ama Operator/);
  assert.match(staffMarkup, /Role: Staff/);
  assert.match(staffMarkup, /Operational areas/);
  assert.match(staffMarkup, /Not yet available/);
  assert.match(staffMarkup, /Made-to-order requests/);
  assert.equal(staffMarkup.includes('href="/audit-logs"'), false);
  assert.equal(staffMarkup.includes('href="/customers"'), false);
  const text = visibleText(staffMarkup);
  assert.equal(/[$€£%]|\brevenue\b|\bsales\b|conversion|\bunread\b|pending count|last updated|\b\d+\s*(?:orders|requests|enquiries|customers|items)\b/i.test(text), false);
});

test("the shell and overview surfaces contain no fabricated data, endpoint calls or notification counts", () => {
  const surfaces = [
    "../app/(protected)/page.tsx",
    "../components/layout/admin-overview-body.tsx",
    "../components/layout/admin-navigation-list.tsx",
    "../components/layout/admin-topbar.tsx",
    "../components/layout/admin-route-placeholder.tsx",
    "../components/ui/feature-placeholder.tsx",
  ].map(read);
  for (const source of surfaces) {
    for (const forbidden of ["createAdminApiClient", "fetch(", "/api/v1", "axios", "Badge", "unread", "fixture", "mock", "dummy"]) {
      assert.equal(source.toLowerCase().includes(forbidden.toLowerCase()), false, `unexpected ${forbidden} in a shell surface`);
    }
  }
  assert.match(read("../app/(protected)/page.tsx"), /requireAdminRouteAccess\(OVERVIEW_ROUTE\)/);
  assert.match(read("../app/(protected)/loading.tsx"), /LoadingState/);
});
