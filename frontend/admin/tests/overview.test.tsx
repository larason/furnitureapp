import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { renderToStaticMarkup } from "react-dom/server";
import { ThemeProvider } from "@mui/material/styles";
import { AdminOverviewBody } from "../components/layout/admin-overview-body";
import { PageHeader } from "../components/ui/page-header";
import { operationalDisplayName, UNKNOWN_DISPLAY_NAME } from "../lib/auth/identity-display";
import type { OperationalIdentity } from "../lib/auth/types";
import { ADMIN_OVERVIEW_HREF, ADMIN_ROUTES, adminRouteStatus, resolveOverviewNavigation } from "../lib/navigation/admin-navigation";
import { theme } from "../theme";

const STAFF: OperationalIdentity = { id: "7", name: "Ama Operator", email: "ama@example.test", role: "STAFF", accountState: "ACTIVE", permissions: ["enquiries.view", "inventory.view", "notifications.read_operational", "products.manage", "requests.view"] };
const ADMIN: OperationalIdentity = { id: "8", name: "Admin", email: "admin@example.test", role: "ADMIN", accountState: "ACTIVE", permissions: [...STAFF.permissions, "audit.view", "users.manage_authorized"] };

const PAGE_SOURCE = read(fileURLToPath(new URL("../app/(protected)/page.tsx", import.meta.url)));
const BODY_SOURCE = read(fileURLToPath(new URL("../components/layout/admin-overview-body.tsx", import.meta.url)));
const NAVIGATION_SOURCE = read(fileURLToPath(new URL("../lib/navigation/admin-navigation.ts", import.meta.url)));
const ROADMAP = read(fileURLToPath(new URL("../../../AGENTS.md", import.meta.url)));

function read(relativeUrl: string): string {
  return readFileSync(relativeUrl, "utf8");
}

const render = (element: React.ReactElement): string => renderToStaticMarkup(<ThemeProvider theme={theme}>{element}</ThemeProvider>);
const visibleText = (markup: string): string => markup.replace(/<style[\s\S]*?<\/style>/g, "").replace(/<[^>]*>/g, " ");
const text = (markup: string): string => visibleText(markup).replace(/\s+/g, " ").trim();

/** Returns the inner markup of the element carrying `id`, for aria-labelledby/aria-describedby assertions. */
function elementById(markup: string, id: string): string {
  const opening = new RegExp(`<([a-zA-Z0-9]+)[^>]*\\bid="${id}"[^>]*>`).exec(markup);
  assert.ok(opening, `element with id "${id}" must exist.`);
  const start = opening.index + opening[0].length;
  const end = markup.indexOf(`</${opening[1]}>`, start);
  assert.notEqual(end, -1, `element with id "${id}" must be closed.`);
  return markup.slice(start, end);
}
const shortcutGroups = (identity: OperationalIdentity) => resolveOverviewNavigation(identity);
const shortcutHrefs = (identity: OperationalIdentity) => shortcutGroups(identity).flatMap((group) => group.items.map((item) => item.href));

test("the welcome heading is built from the sanitized identity helper with its approved fallback", () => {
  assert.match(PAGE_SOURCE, /title=\{`Welcome back, \$\{operationalDisplayName\(access\.identity\)\}`\}/);
  assert.match(PAGE_SOURCE, /from "@\/lib\/auth\/identity-display"/);
  assert.equal(operationalDisplayName({ ...ADMIN, name: null }), UNKNOWN_DISPLAY_NAME);
  assert.equal(operationalDisplayName({ ...ADMIN, name: "  " }), UNKNOWN_DISPLAY_NAME);
  assert.equal(operationalDisplayName(ADMIN), "Admin");
  assert.match(PAGE_SOURCE, /aria-labelledby="page-title"/);
});

test("the page keeps a logical heading hierarchy from h1 to section and group headings", () => {
  const markup = render(
    <>
      <PageHeader title={`Welcome back, ${operationalDisplayName(ADMIN)}`} description="Workspace overview." />
      <AdminOverviewBody identity={ADMIN} />
    </>,
  );
  const levels = [...markup.matchAll(/<(h[1-6])\b/g)].map((match) => Number(match[1][1]));
  assert.ok(levels.length >= 4, "expected the page title, section headings and group headings.");
  assert.equal(levels[0], 1, "the welcome heading must be the single h1.");
  for (let index = 1; index < levels.length; index += 1) {
    assert.ok(levels[index] <= levels[index - 1] + 1, `heading level h${levels[index]} skips a level after h${levels[index - 1]}.`);
  }
  assert.equal(levels.filter((level) => level === 1).length, 1, "the shell already owns the page; only the page title may be h1.");
});

test("shortcuts are generated from the single navigation registry with no hardcoded path", () => {
  assert.match(BODY_SOURCE, /resolveOverviewNavigation/);
  assert.match(BODY_SOURCE, /adminRouteStatus/);
  assert.match(BODY_SOURCE, /from "@\/lib\/navigation\/admin-navigation"/);
  assert.doesNotMatch(BODY_SOURCE, /href:\s*"\//, "the overview must not hardcode a destination.");
  assert.match(NAVIGATION_SOURCE, /export function resolveOverviewNavigation/);
  const markup = render(<AdminOverviewBody identity={STAFF} />);
  const configured = new Set(ADMIN_ROUTES.map((route) => route.href));
  for (const href of [...markup.matchAll(/href="([^"]+)"/g)].map((match) => match[1])) {
    assert.ok(configured.has(href), `${href} is not a configured admin route.`);
  }
});

test("only destinations the Laravel-resolved permissions allow are shown, for every permission shape", () => {
  const staff = render(<AdminOverviewBody identity={STAFF} />);
  for (const href of shortcutHrefs(STAFF)) assert.ok(staff.includes(`href="${href}"`), `${href} must render for its permission.`);
  assert.equal(staff.includes('href="/audit-logs"'), false);
  assert.equal(staff.includes('href="/customers"'), false);

  const admin = render(<AdminOverviewBody identity={ADMIN} />);
  for (const href of ["/requests", "/enquiries", "/notifications", "/products", "/categories", "/inventory", "/customers", "/audit-logs"]) {
    assert.ok(admin.includes(`href="${href}"`), `${href} must render for its permission.`);
  }
});

test("the overview stays useful for a small permission set and never links to itself", () => {
  const minimal: OperationalIdentity = { ...STAFF, permissions: ["requests.view"] };
  assert.deepEqual(shortcutHrefs(minimal), ["/requests"]);
  const markup = render(<AdminOverviewBody identity={minimal} />);
  assert.match(markup, /Made-to-order requests/);
  assert.match(markup, /Operational areas/);
  assert.equal(markup.includes(`href="${ADMIN_OVERVIEW_HREF}"`), false, "the landing page must not link to itself.");
  assert.equal(markup.includes("href=\"/\""), false, "the landing page must not link to itself.");
});

test("missing or malformed permissions fail closed to an empty workspace explanation", () => {
  const malformed = { ...STAFF, permissions: undefined as unknown as readonly string[] };
  assert.deepEqual(shortcutHrefs(malformed), []);
  const markup = render(<AdminOverviewBody identity={malformed} />);
  assert.match(markup, /No operational areas are available for this account\./);
  for (const href of ["/requests", "/enquiries", "/products", "/inventory", "/notifications"]) {
    assert.equal(markup.includes(`href="${href}"`), false);
  }
  const nameless: OperationalIdentity = { ...ADMIN, permissions: [] };
  assert.deepEqual(shortcutHrefs(nameless), []);
  const namelessMarkup = render(<AdminOverviewBody identity={nameless} />);
  assert.match(namelessMarkup, /Your access/);
  assert.equal(namelessMarkup.includes('href="/audit-logs"'), false, "an ADMIN role without the permission is still closed.");
});

test("shell availability and unfinished workflows are stated distinctly", () => {
  const visible = visibleText(render(<AdminOverviewBody identity={STAFF} />));
  assert.match(visible, /The authenticated shell and navigation are available/);
  assert.match(visible, /Products is implemented and available in this build/);
  assert.match(visible, /the areas listed below remain scheduled/);
  assert.match(visible, /does not mean the workflow behind it is functional yet/);
  assert.match(visible, /Opening one shows its implementation status/);
  assert.match(visible, /navigation availability, not operational state/);
  assert.match(PAGE_SOURCE, /business workflows arrive in scheduled phases/);
});

test("every displayed roadmap status matches the approved Group S roadmap", () => {
  const markup = render(<AdminOverviewBody identity={ADMIN} />);
  const displayed = [...visibleText(markup).matchAll(/Phase 19\.\d+/g)].map((match) => match[0]);
  assert.ok(displayed.length >= 8, "statuses for the scheduled areas and the shortcuts must render.");
  for (const phase of new Set(displayed)) {
    const number = phase.match(/^Phase (19\.\d+)$/);
    assert.ok(number, `${phase} must name an approved roadmap phase number.`);
    const heading = new RegExp(`^### ${number[1].replace(".", "\\.")} — `, "m");
    assert.ok(heading.test(ROADMAP), `${phase} is not an approved roadmap phase.`);
  }
  for (const route of ADMIN_ROUTES) {
    if (route.href === ADMIN_OVERVIEW_HREF) {
      assert.equal(route.placeholder, undefined, "the overview itself is implemented and is not a placeholder.");
      assert.equal(route.plannedFor, undefined, "the overview itself is implemented and has no planned phase.");
      assert.equal(adminRouteStatus(route), null);
      continue;
    }
    if (route.placeholder !== true) {
      assert.equal(adminRouteStatus(route), null, `${route.href} is implemented and must not advertise a not-implemented status.`);
      continue;
    }
    assert.equal(route.placeholder, true, `${route.href} must be marked as a placeholder.`);
    const status = adminRouteStatus(route);
    assert.ok(status, `${route.href} must state that it is not yet implemented.`);
    assert.ok(status.startsWith("Not yet implemented"), `${route.href} must use the restrained not-implemented status.`);
    if (route.plannedFor) {
      assert.equal(status, `Not yet implemented — ${route.plannedFor}`);
    } else {
      assert.equal(status, "Not yet implemented", `${route.href} has no approved phase, so none may be implied.`);
    }
  }
  assert.equal(adminRouteStatus(adminRouteSafe(ADMIN_OVERVIEW_HREF)), null);
});

test("no route claims an approved phase the roadmap does not name", () => {
  for (const route of ADMIN_ROUTES) {
    if (!route.plannedFor) continue;
    const number = route.plannedFor.match(/^Phase (19\.\d+)$/);
    assert.ok(number, `${route.href} must name a phase exactly, not prose: ${route.plannedFor}`);
    const heading = new RegExp(`^### ${number[1].replace(".", "\\.")} — `, "m");
    assert.ok(heading.test(ROADMAP), `${route.plannedFor} is not an approved roadmap phase.`);
  }
});

test("a route declaring a phase is always marked as a placeholder", () => {
  for (const route of ADMIN_ROUTES) {
    if (route.plannedFor) {
      assert.equal(route.placeholder, true, `${route.href} declares a phase but is not marked as a placeholder.`);
    }
  }
});

function adminRouteSafe(href: string) {
  const route = ADMIN_ROUTES.find((candidate) => candidate.href === href);
  assert.ok(route, `${href} must be configured.`);
  return route;
}

test("the overview shows no fabricated analytics, counts, charts or sample records", () => {
  const markup = render(<AdminOverviewBody identity={ADMIN} />);
  const text = visibleText(markup);
  assert.equal(/[$€£%]|\brevenue\b|\bsales\b|conversion|\bunread\b|pending count|last updated|(?<![\d.])\b\d+\s+(?:orders|requests|enquiries|customers|items)\b/i.test(text), false);
  for (const pattern of [/<table\b/i, /<canvas\b/i, /<input\b/i, /<form\b/i]) assert.equal(pattern.test(markup), false);
  assert.doesNotMatch(BODY_SOURCE, /Badge|unread|fixture|mock|dummy/i);
});

test("the overview introduces no business API call of its own", () => {
  for (const source of [PAGE_SOURCE, BODY_SOURCE]) {
    for (const forbidden of ["createAdminApiClient", "fetch(", "/api/v1", "axios"]) {
      assert.equal(source.includes(forbidden), false, `unexpected ${forbidden} in the overview.`);
    }
  }
  assert.match(PAGE_SOURCE, /<AdminOverviewBody identity=\{access\.identity\} \/>/);
});

test("the overview route keeps the existing admission guard and renders the denial state", () => {
  assert.match(PAGE_SOURCE, /requireAdminRouteAccess\(OVERVIEW_ROUTE\)/);
  assert.match(PAGE_SOURCE, /if \(!access\.granted\) return access\.denial;/);
  assert.equal(PAGE_SOURCE.includes("component=\"main\""), false, "the shell owns the single main landmark.");
  assert.equal(BODY_SOURCE.includes("component=\"main\""), false, "the shell owns the single main landmark.");
});

test("the page passes only the serializable sanitized identity across the Server/Client boundary", () => {
  assert.match(BODY_SOURCE, /^"use client";/);
  assert.equal(/=\{[a-zA-Z]+\}[\s\S]*=>/.test(PAGE_SOURCE), false, "no function prop may cross into the client component.");
  assert.match(PAGE_SOURCE, /identity=\{access\.identity\}/);
});

test("displayed identity information is sanitized and never an internal payload", () => {
  const markup = render(<AdminOverviewBody identity={STAFF} />);
  assert.match(markup, /Ama Operator/);
  assert.match(markup, /ama@example\.test/);
  assert.match(markup, /Role: Staff/);
  for (const rendered of [markup, render(<AdminOverviewBody identity={ADMIN} />)]) {
    assert.equal(/Bearer|eyJ|secret|token/i.test(rendered), false);
    assert.equal(/\b(requests\.view|products\.manage|audit\.view|users\.manage_authorized|inventory\.view|enquiries\.view)\b/.test(rendered), false, "permission identifiers are internal payloads.");
  }
});

test("each shortcut's accessible name is exactly its label, with the rest described", () => {
  const markup = render(<AdminOverviewBody identity={ADMIN} />);
  const anchors = [...markup.matchAll(/<a ([^>]*)href="([^"]+)"([^>]*)>([\s\S]*?)<\/a>/g)];
  assert.ok(anchors.length >= 8, "every eligible destination must render as a link.");
  for (const group of shortcutGroups(ADMIN)) {
    for (const item of group.items) {
      const anchor = anchors.find((candidate) => candidate[2] === item.href);
      assert.ok(anchor, `${item.href} must render as a link.`);
      const attrs = `${anchor[1]} ${anchor[3]}`;

      const labelId = attrs.match(/aria-labelledby="([^"]+)"/)?.[1];
      assert.ok(labelId, `${item.href} must reference its accessible name with aria-labelledby.`);
      const labelText = text(elementById(markup, labelId));
      assert.equal(labelText, item.label, `${item.href} accessible name must equal the label exactly, not include the description or status.`);

      const describedBy = attrs.match(/aria-describedby="([^"]+)"/)?.[1];
      assert.ok(describedBy, `${item.href} must keep its description reachable with aria-describedby.`);
      const described = describedBy.split(" ").map((id) => text(elementById(markup, id))).join(" ");
      const status = adminRouteStatus(item);
      assert.equal(described, status ? `${item.description} ${status}` : item.description, `${item.href} description must carry the description and status only.`);

      assert.equal(/<button\b/.test(anchor[4]), false, "a shortcut must not contain a nested control.");
    }
  }
  assert.equal(/<button\b/.test(markup), false, "the overview introduces no button.");
  assert.doesNotMatch(BODY_SOURCE, /aria-label=\{?""/, "no empty accessible name.");
});

test("shortcut layouts reflow responsively without fixed geometry or clipped labels", () => {
  assert.ok(BODY_SOURCE.includes('gridTemplateColumns: { xs: "1fr", sm: "repeat(2, minmax(0, 1fr))", xl: "repeat(3, minmax(0, 1fr))" }'), "shortcuts must reflow from one to two to three columns.");
  assert.match(BODY_SOURCE, /minHeight: 44/);
  assert.match(BODY_SOURCE, /overflowWrap: "anywhere"/);
  assert.equal(/whiteSpace:\s*"nowrap"/.test(BODY_SOURCE), false, "long labels must wrap.");
  assert.equal(/(?:^|[;{ ])height:\s*\d+px/.test(BODY_SOURCE), false, "no fixed card height.");
  assert.equal(/(?:^|[;{ ])width:\s*\d+px/.test(BODY_SOURCE), false, "no fixed card width.");
});

test("keyboard focus stays visible on every shortcut", () => {
  assert.match(BODY_SOURCE, /&:focus-visible/);
  assert.match(BODY_SOURCE, /var\(--focus-ring\)/);
  assert.match(BODY_SOURCE, /&:hover/);
});
