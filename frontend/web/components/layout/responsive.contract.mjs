import { readFileSync } from "node:fs";
import { join } from "node:path";
import { fileURLToPath } from "node:url";

const layoutDir = fileURLToPath(new URL(".", import.meta.url));
const webDir = fileURLToPath(new URL("../../", import.meta.url));
const rootDir = fileURLToPath(new URL("../../../", import.meta.url));
const read = (path) => readFileSync(path, "utf8");
const failures = [];
const check = (condition, message) => {
  if (!condition) {
    failures.push(message);
  }
};

const tokens = JSON.parse(read(join(rootDir, "design-system/design-tokens.json")));
const { layout } = tokens.layers;
const theme = read(join(webDir, "theme/theme.ts"));
const container = read(join(layoutDir, "content-container.tsx"));
const section = read(join(layoutDir, "site-section.tsx"));
const header = read(join(layoutDir, "site-header.tsx"));
const categoryNavigation = read(join(layoutDir, "primary-category-navigation.tsx"));
const mobileNavigation = read(join(layoutDir, "mobile-navigation.tsx"));
const page = read(join(webDir, "app/page.tsx"));
const globals = read(join(webDir, "app/globals.css"));
const foundationSources = [
  theme,
  container,
  section,
  header,
  mobileNavigation,
  page,
  globals,
].join("\n");
const nonBehavioralSources = [theme, container, section, header, page, globals].join("\n");

check(
  layout.breakpoints.phone === "640px" &&
    layout.breakpoints.tablet === "960px" &&
    layout.breakpoints.desktop === "1024px" &&
    layout.containerMax === "1440px",
  "design-system breakpoint tokens must retain the canonical 640/960/1024/1440px mapping",
);
check(
  theme.includes("values: { xs: 0, sm: pixels(layout.breakpoints.phone), md: pixels(layout.breakpoints.tablet), lg: pixels(layout.breakpoints.desktop), xl: pixels(layout.containerMax) }"),
  "MUI breakpoints must map directly to the canonical design tokens",
);
check(
  container.includes('maxWidth: "var(--container-max)"') &&
    container.includes('xs: "var(--container-gutter-phone)"') &&
    container.includes('sm: "var(--container-gutter-tablet)"') &&
    container.includes('lg: "var(--container-gutter-desktop)"'),
  "ContentContainer must own canonical width and gutters",
);
check(
  section.includes('width = "contained"') && section.includes("<ContentContainer>{children}</ContentContainer>"),
  "SiteSection must own contained composition through ContentContainer",
);
check(!page.includes("@mui/material/Container"), "app/page.tsx must not create a second container authority");
check(/<SiteSection(?:\s|>)/.test(page), "app/page.tsx must use the canonical section/container composition");
check(
  categoryNavigation.includes('display: { xs: "none", md: "block" }') &&
    mobileNavigation.includes('display: { xs: "inline-flex", md: "none" }'),
  "desktop and mobile navigation must transition at the canonical md breakpoint",
);
check(
  mobileNavigation.includes('useMediaQuery(theme.breakpoints.up("md"))') &&
    mobileNavigation.includes("open={open && !desktopNavigation}"),
  "an open mobile drawer must be closed while the viewport uses desktop navigation",
);
check(!header.includes("maxWidth: { md: 480 }"), "header must not use a raw responsive search width");
check(!mobileNavigation.includes("88vw") && !mobileNavigation.includes("360px"), "drawer sizing must use existing tokens");
check(
  mobileNavigation.includes('width: "min(calc(100% - var(--space-6)), var(--content-width-form))"'),
  "drawer must preserve a tokenized viewport margin and maximum width",
);
check(globals.includes("prefers-reduced-motion: reduce"), "global styles must honor reduced-motion preferences");

for (const forbidden of [
  "window.innerWidth",
  "navigator.userAgent",
  "100vw",
  "calc(50% - 50vw)",
  "overflow-x: hidden",
]) {
  check(!foundationSources.includes(forbidden), `responsive foundation must not include ${forbidden}`);
}
check(
  !nonBehavioralSources.includes("useMediaQuery") && mobileNavigation.includes("useMediaQuery"),
  "useMediaQuery is allowed only for mobile-drawer behavior at the navigation transition",
);

check(
  !/@media\s*\(\s*(min|max)-width/.test(foundationSources),
  "responsive foundation must use MUI breakpoint mappings instead of raw width media queries",
);

if (failures.length > 0) {
  const details = failures.map((failure) => " - " + failure).join("\n");
  console.error(`Responsive contract: FAIL\n${details}`);
  process.exit(1);
}

console.log("Responsive contract: PASS");
