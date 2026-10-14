import { createTheme } from "@mui/material/styles";
import type { Shadows } from "@mui/material/styles";
import designTokens from "../../design-system/design-tokens.json";

const { primitive, semantic, layout } = designTokens.layers;
const pixels = (value: string) => Number.parseInt(value, 10);
const spacingValues = [0, ...primitive.space.map(pixels)];
const spacing = (factor: number): string => {
  if (!Number.isFinite(factor)) return "0px";
  const sign = factor < 0 ? -1 : 1;
  const absolute = Math.abs(factor);
  const lastIndex = spacingValues.length - 1;
  const lowerIndex = Math.floor(absolute);
  const upperIndex = Math.ceil(absolute);
  if (lowerIndex >= lastIndex) {
    const extension = spacingValues[lastIndex] + (absolute - lastIndex) * (spacingValues[1] ?? 0);
    return `${sign * extension}px`;
  }
  const lower = spacingValues[lowerIndex];
  const upper = spacingValues[upperIndex];
  const interpolated = lower + (upper - lower) * (absolute - lowerIndex);
  return `${sign * interpolated}px`;
};
const shadows = [primitive.elevation.flat, ...new Array(24).fill("var(--elev-raised)")] as Shadows;

export const theme = createTheme({
  palette: {
    mode: "light",
    background: { default: semantic.surface.canvas, paper: semantic.surface.paper },
    text: { primary: semantic.text.primary, secondary: semantic.text.secondary, disabled: semantic.text.muted },
    primary: { main: semantic.action.primary, dark: semantic.action.primaryActive, contrastText: semantic.text.inverse },
    success: { main: semantic.status.success },
    warning: { main: semantic.status.warning },
    error: { main: semantic.status.danger },
    info: { main: semantic.status.info },
    divider: semantic.border.default,
    brand: { accent: semantic.accent.brand },
    surface: { editorial: semantic.surface.editorial, inverse: semantic.surface.inverse },
  },
  typography: {
    fontFamily: "var(--font-ui)",
    h1: { fontFamily: "var(--font-display)", fontSize: primitive.fontSize[5], fontWeight: primitive.fontWeight.regular, lineHeight: primitive.lineHeight.display },
    h2: { fontFamily: "var(--font-display)", fontSize: primitive.fontSize[4], fontWeight: primitive.fontWeight.regular, lineHeight: primitive.lineHeight.display },
    h3: { fontFamily: "var(--font-display)", fontSize: primitive.fontSize[3], fontWeight: primitive.fontWeight.regular, lineHeight: primitive.lineHeight.display },
    h4: { fontSize: primitive.fontSize[3], fontWeight: primitive.fontWeight.medium, lineHeight: primitive.lineHeight.snug },
    body1: { fontSize: primitive.fontSize[2], lineHeight: primitive.lineHeight.body },
    body2: { fontSize: primitive.fontSize[1], lineHeight: primitive.lineHeight.body },
    button: { fontSize: primitive.fontSize[1], fontWeight: primitive.fontWeight.medium, lineHeight: primitive.lineHeight.snug, textTransform: "none" },
  },
  spacing,
  shape: { borderRadius: pixels(primitive.radius[0]) },
  shadows,
  breakpoints: { values: { xs: 0, sm: pixels(layout.breakpoints.phone), md: pixels(layout.breakpoints.tablet), lg: pixels(layout.breakpoints.desktop), xl: pixels(layout.containerMax) } },
  components: {
    MuiButton: { styleOverrides: { root: { minHeight: "var(--space-8)", borderRadius: "var(--radius-pill)", boxShadow: "none", textTransform: "none", "&.Mui-focusVisible": { boxShadow: "var(--focus-ring)" } } } },
    MuiIconButton: { styleOverrides: { root: { "&.Mui-focusVisible": { boxShadow: "var(--focus-ring)" } } } },
    MuiPaper: { defaultProps: { elevation: 0 }, styleOverrides: { root: { backgroundImage: "none" } } },
    MuiCard: { styleOverrides: { root: { boxShadow: "none" } } },
    MuiOutlinedInput: { styleOverrides: { root: { borderRadius: "var(--radius-sm)", "&.Mui-focused .MuiOutlinedInput-notchedOutline": { borderColor: "var(--action-focus)", boxShadow: "var(--focus-ring)" } } } },
    MuiTableCell: { styleOverrides: { root: { borderColor: "var(--border-subtle)" }, head: { fontWeight: primitive.fontWeight.medium } } },
    MuiChip: { styleOverrides: { root: { borderRadius: "var(--radius-pill)" } } },
  },
});
