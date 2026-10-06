import { createTheme } from "@mui/material/styles";
import type { Shadows } from "@mui/material/styles";
import designTokens from "../../design-system/design-tokens.json";

const { primitive, semantic, layout } = designTokens.layers;
const pixels = (value: string) => Number.parseInt(value, 10);
const spacingValues = [0, ...primitive.space.map(pixels)];
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
    h1: { fontFamily: "var(--font-display)", fontSize: primitive.fontSize[7], fontWeight: primitive.fontWeight.regular, lineHeight: primitive.lineHeight.display, letterSpacing: primitive.letterSpacing.display },
    h2: { fontFamily: "var(--font-display)", fontSize: primitive.fontSize[6], fontWeight: primitive.fontWeight.regular, lineHeight: primitive.lineHeight.display, letterSpacing: primitive.letterSpacing.display },
    h3: { fontFamily: "var(--font-display)", fontSize: primitive.fontSize[5], fontWeight: primitive.fontWeight.regular, lineHeight: primitive.lineHeight.display, letterSpacing: primitive.letterSpacing.display },
    h4: { fontSize: primitive.fontSize[4], fontWeight: primitive.fontWeight.medium, lineHeight: primitive.lineHeight.snug },
    h5: { fontSize: primitive.fontSize[3], fontWeight: primitive.fontWeight.medium, lineHeight: primitive.lineHeight.snug },
    h6: { fontSize: primitive.fontSize[2], fontWeight: primitive.fontWeight.medium, lineHeight: primitive.lineHeight.snug },
    body1: { fontSize: primitive.fontSize[2], lineHeight: primitive.lineHeight.body },
    body2: { fontSize: primitive.fontSize[1], lineHeight: primitive.lineHeight.body },
    subtitle1: { fontSize: primitive.fontSize[3], fontWeight: primitive.fontWeight.medium, lineHeight: primitive.lineHeight.snug },
    subtitle2: { fontSize: primitive.fontSize[2], fontWeight: primitive.fontWeight.medium, lineHeight: primitive.lineHeight.snug },
    button: { fontSize: primitive.fontSize[1], fontWeight: primitive.fontWeight.medium, lineHeight: primitive.lineHeight.snug, textTransform: "none" },
    caption: { fontSize: primitive.fontSize[1], lineHeight: primitive.lineHeight.snug },
    overline: { fontSize: primitive.fontSize[0], fontWeight: primitive.fontWeight.medium, lineHeight: primitive.lineHeight.snug, textTransform: "none" },
  },
  spacing: (factor: number) => `${spacingValues[factor] ?? 0}px`,
  shape: { borderRadius: pixels(primitive.radius[0]) },
  shadows,
  transitions: {
    duration: {
      shortest: pixels(primitive.motion.fast),
      shorter: pixels(primitive.motion.fast),
      short: pixels(primitive.motion.fast),
      standard: pixels(primitive.motion.base),
      complex: pixels(primitive.motion.base),
      enteringScreen: pixels(primitive.motion.base),
      leavingScreen: pixels(primitive.motion.fast),
    },
    easing: {
      easeInOut: primitive.motion.easing,
      easeOut: primitive.motion.easing,
      easeIn: primitive.motion.easing,
      sharp: primitive.motion.easing,
    },
  },
  breakpoints: {
    values: { xs: 0, sm: pixels(layout.breakpoints.phone), md: pixels(layout.breakpoints.tablet), lg: pixels(layout.breakpoints.desktop), xl: pixels(layout.containerMax) },
  },
  zIndex: {
    mobileStepper: primitive.zIndex.base,
    fab: primitive.zIndex.sticky,
    speedDial: primitive.zIndex.sticky,
    appBar: primitive.zIndex.sticky,
    drawer: primitive.zIndex.menu,
    modal: primitive.zIndex.dialog,
    snackbar: primitive.zIndex.dialog,
    tooltip: primitive.zIndex.dialog,
  },
  components: {
    MuiButton: {
      styleOverrides: {
        root: {
          borderRadius: "var(--radius-pill)",
          boxShadow: "none",
          textTransform: "none",
          transition: `background-color ${primitive.motion.base} ${primitive.motion.easing}, color ${primitive.motion.base} ${primitive.motion.easing}, border-color ${primitive.motion.base} ${primitive.motion.easing}`,
          "&.Mui-focusVisible": { boxShadow: "var(--focus-ring)" },
        },
      },
    },
    MuiPaper: {
      defaultProps: { elevation: 0 },
      styleOverrides: { root: { backgroundImage: "none" } },
    },
    MuiCard: { styleOverrides: { root: { boxShadow: "none" } } },
    MuiOutlinedInput: {
      styleOverrides: {
        root: {
          borderRadius: "var(--radius-sm)",
          "&.Mui-focused .MuiOutlinedInput-notchedOutline": {
            borderColor: "var(--action-focus)",
            boxShadow: "var(--focus-ring)",
          },
        },
      },
    },
    MuiLink: {
      styleOverrides: {
        root: {
          color: "inherit",
          "&:focus-visible": { boxShadow: "var(--focus-ring)" },
        },
      },
    },
  },
});
