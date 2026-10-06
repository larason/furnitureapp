import "@mui/material/styles";

declare module "@mui/material/styles" {
  interface Palette {
    brand: { accent: string };
    surface: { editorial: string; inverse: string };
  }

  interface PaletteOptions {
    brand?: { accent: string };
    surface?: { editorial: string; inverse: string };
  }
}

export {};
