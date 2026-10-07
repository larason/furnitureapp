import { readdirSync, readFileSync } from "node:fs";
import { join } from "node:path";
import { fileURLToPath } from "node:url";

const layoutDir = fileURLToPath(new URL(".", import.meta.url));
const webDir = fileURLToPath(new URL("../../", import.meta.url));

const read = (path) => readFileSync(path, "utf8");
const layoutSources = readdirSync(layoutDir)
  .filter((file) => file.endsWith(".ts") || file.endsWith(".tsx"))
  .map((file) => ({ file, source: read(join(layoutDir, file)) }));

const failures = [];
const check = (condition, message) => {
  if (!condition) {
    failures.push(message);
  }
};

const sourceOf = (file) =>
  layoutSources.find((entry) => entry.file === file)?.source ?? "";

const shell = sourceOf("site-shell.tsx");
check(shell.includes('component="main"'), "site-shell must render a main landmark");
check(shell.includes('id={MAIN_CONTENT_ID}'), "main landmark must expose the skip target id");
check(shell.includes('href={`#${MAIN_CONTENT_ID}`}'), "skip link must target main content");
check(shell.includes("Skip to main content"), "skip link text is missing");

for (const { file, source } of layoutSources) {
  if (file === "site-shell.tsx") {
    continue;
  }
  check(
    !source.includes('component="main"'),
    `${file} must not declare a second main landmark`,
  );
}

const page = read(join(webDir, "app/page.tsx"));
check(!page.includes('component="main"'), "app/page.tsx must not redeclare a main landmark");

const rootLayout = read(join(webDir, "app/layout.tsx"));
check(
  !/"use client"|'use client'/.test(rootLayout),
  "root layout must remain a Server Component",
);

check(sourceOf("site-header.tsx").includes('component="header"'), "site-header must render a header landmark");
check(sourceOf("site-footer.tsx").includes('component="footer"'), "site-footer must render a footer landmark");

const combined = layoutSources.map((entry) => entry.source).join("\n");
check(!/href=\{?["']#["']\}?/.test(combined), "no href=\"#\" placeholder links are allowed");

const forbiddenRoutes = ["/cart", "/checkout", "/payment", "/order-confirmation"];
for (const route of forbiddenRoutes) {
  check(!combined.includes(route), `request-first shell must not reference ${route}`);
}

const navigation = sourceOf("site-navigation.ts");
check(
  navigation.includes("isSiteRouteImplemented"),
  "shell must expose a route-availability check",
);
check(
  navigation.includes('"/"') && navigation.includes('"/products"'),
  "implemented routes must render as active links",
);
const navLink = sourceOf("nav-link.tsx");
check(
  navLink.includes("isSiteRouteImplemented") && navLink.includes('component="span"'),
  "NavLink must degrade unimplemented routes to non-link elements",
);

const imports = [...combined.matchAll(/from\s+["']([^"']+)["']/g)].map((match) => match[1]);
for (const specifier of imports) {
  if (specifier.includes("icon")) {
    check(
      specifier.startsWith("@mui/icons-material"),
      `unauthorized icon import: ${specifier}`,
    );
  }
}

check(!combined.includes("window.innerWidth"), "must not branch on window.innerWidth");

for (const { file, source } of layoutSources) {
  const isClient = /["']use client["']/.test(source);
  if (isClient) {
    check(file === "mobile-navigation.tsx", `unexpected client boundary in ${file}`);
  }
}

const fixture = sourceOf("category-navigation.fixture.ts");
const authoritativeSlugs = [
  "living-room",
  "bedroom",
  "dining-room-kitchen",
  "home-office-corporate-workspaces",
  "outdoor-patio",
  "entryway-accent",
];
for (const slug of authoritativeSlugs) {
  check(fixture.includes(`"${slug}"`), `category fixture is missing authoritative slug ${slug}`);
}

if (failures.length > 0) {
  const details = failures.map((failure) => " - " + failure).join("\n");
  console.error(`Layout contract: FAIL\n${details}`);
  process.exit(1);
}

console.log("Layout contract: PASS");
