export const clerkAppearance = {
  variables: {
    colorBackground: "var(--surface-paper)",
    colorBorder: "var(--text-primary)",
    colorDanger: "var(--color-danger)",
    colorForeground: "var(--text-primary)",
    colorInput: "var(--surface-paper)",
    colorInputForeground: "var(--text-primary)",
    colorMuted: "var(--surface-editorial)",
    colorMutedForeground: "var(--text-secondary)",
    colorNeutral: "var(--text-secondary)",
    colorPrimary: "var(--action-primary)",
    colorPrimaryForeground: "var(--text-inverse)",
    colorRing: "var(--action-focus)",
    colorSuccess: "var(--color-success)",
    colorWarning: "var(--color-warning)",
    fontFamily: "var(--font-ui)",
    fontFamilyButtons: "var(--font-ui)",
    fontSize: "var(--text-sm)",
    borderRadius: "var(--radius-sm)",
    spacing: "var(--space-4)",
  },
} as const;

export const clerkAuthAppearance = {
  ...clerkAppearance,
  variables: {
    ...clerkAppearance.variables,
    borderRadius: "0",
  },
  options: {
    elevation: "flush",
  },
  elements: {
    card: {
      border: "var(--border-width) solid var(--text-primary)",
      borderRadius: "0",
      padding: "var(--space-6)",
    },
    headerTitle: {
      fontFamily: "var(--font-display)",
    },
  },
} as const;
