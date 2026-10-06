import { readFileSync } from "node:fs";
import { join } from "node:path";
import { fileURLToPath } from "node:url";

const statesDir = fileURLToPath(new URL(".", import.meta.url));
const webDir = fileURLToPath(new URL("../../", import.meta.url));
const appDir = join(webDir, "app");

const read = (path) => readFileSync(path, "utf8");
const files = {
  error: read(join(appDir, "error.tsx")),
  loading: read(join(appDir, "loading.tsx")),
  notFound: read(join(appDir, "not-found.tsx")),
  rootLayout: read(join(appDir, "layout.tsx")),
  pageMessage: read(join(statesDir, "page-message.tsx")),
};

const failures = [];
const check = (condition, message) => {
  if (!condition) {
    failures.push(message);
  }
};

const hasClientDirective = (source) => /["']use client["']/.test(source);

check(hasClientDirective(files.error), "app/error.tsx must be a Client Component");
check(!hasClientDirective(files.loading), "app/loading.tsx must not be a Client Component");
check(!hasClientDirective(files.notFound), "app/not-found.tsx must not be a Client Component");
check(!hasClientDirective(files.rootLayout), "the root layout must remain a Server Component");

check(files.error.includes("retry"), "the error boundary must use the framework retry recovery");
check(!files.error.includes("reset("), "the error boundary should use retry, not reset");
check(!files.error.includes("window.location.reload"), "retry must not reload the page");
check(!/console\.(error|warn|log)/.test(files.error), "the error boundary must not introduce console logging");

const combined = Object.values(files).join("\n");
for (const leak of ["error.message", "error.stack", "error.digest", ".digest", "process.env", "window."]) {
  check(!combined.includes(leak), `state UI must not reference ${leak}`);
}

check(files.notFound.includes('href="/"'), "not-found must offer safe home navigation");
check(files.pageMessage.includes('component="h1"'), "state messages must own a meaningful heading");
check(files.loading.includes('role="status"'), "loading must expose a concise accessible status");

check(!/setTimeout|await sleep|new Promise/.test(combined), "state UI must not add artificial delays");
check(
  !/product|category|search/i.test(files.loading),
  "the global loading state must stay generic",
);

const forbiddenRoutes = ["/cart", "/checkout", "/payment", "/order-confirmation", "/account/orders"];
for (const route of forbiddenRoutes) {
  check(!combined.includes(route), `state UI must not reference ${route}`);
}

if (/#(?:[0-9a-f]{3}|[0-9a-f]{6})\b/i.test(combined)) {
  failures.push("state UI must not introduce raw hexadecimal colors");
}

const imports = [...combined.matchAll(/from\s+["']([^"']+)["']/g)].map((match) => match[1]);
for (const specifier of imports) {
  if (specifier.includes("icon")) {
    check(specifier.startsWith("@mui/icons-material"), `unauthorized icon import: ${specifier}`);
  }
}

if (failures.length > 0) {
  console.error(`States contract: FAIL\n${failures.map((failure) => ` - ${failure}`).join("\n")}`);
  process.exit(1);
}

console.log("States contract: PASS");
