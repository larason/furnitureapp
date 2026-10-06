import { readFileSync } from "node:fs";

const themeSource = readFileSync(new URL("./theme.ts", import.meta.url), "utf8");
const packageJson = JSON.parse(readFileSync(new URL("../package.json", import.meta.url), "utf8"));
const tokens = JSON.parse(readFileSync(new URL("../../design-system/design-tokens.json", import.meta.url), "utf8"));
const { primitive, semantic } = tokens.layers;

const expectedMappings = [
  ["background default", "default: semantic.surface.canvas"],
  ["background paper", "paper: semantic.surface.paper"],
  ["primary action", "main: semantic.action.primary"],
  ["brand accent", "brand: { accent: semantic.accent.brand }"],
  ["display font", 'fontFamily: "var(--font-display)"'],
  ["utility font", 'fontFamily: "var(--font-ui)"'],
  ["phone breakpoint", "sm: pixels(layout.breakpoints.phone)"],
  ["canonical spacing", "const spacingValues = [0, ...primitive.space.map(pixels)]"],
  ["restrained overlays", '...new Array(24).fill("var(--elev-raised)")'],
];

for (const [name, mapping] of expectedMappings) {
  if (!themeSource.includes(mapping)) {
    throw new Error(`Missing ${name} token mapping.`);
  }
}

if (/#(?:[0-9a-f]{3}|[0-9a-f]{6})\b/i.test(themeSource)) {
  throw new Error("Theme source must not introduce raw hexadecimal colors.");
}

if (semantic.action.primary !== semantic.text.primary || primitive.fontFamily.display[0] !== "Young Serif") {
  throw new Error("The synchronized token contract is not suitable for the MUI adapter.");
}

if (!packageJson.dependencies["@mui/icons-material"]) {
  throw new Error("The web frontend requires @mui/icons-material as its only UI icon package.");
}

console.log("Theme contract: PASS");
