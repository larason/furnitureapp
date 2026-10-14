import test from "node:test";
import assert from "node:assert/strict";
import { renderToStaticMarkup } from "react-dom/server";
import { ThemeProvider } from "@mui/material/styles";
import { EmptyState } from "../components/states/empty-state";
import { LoadingState } from "../components/states/loading-state";
import { StatusIndicator } from "../components/ui/status-indicator";
import { theme } from "../theme";

function render(element: React.ReactElement): string { return renderToStaticMarkup(<ThemeProvider theme={theme}>{element}</ThemeProvider>); }

test("status indicator exposes its state as readable text", () => {
  const markup = render(<StatusIndicator status="warning" label="Needs review" />);
  assert.match(markup, /Status: Needs review/);
  assert.match(markup, /Needs review/);
});

test("empty and loading states expose semantic status regions", () => {
  assert.match(render(<EmptyState title="No records" description="Nothing is available." />), /role="status"/);
  assert.match(render(<LoadingState label="Loading records" />), /aria-busy="true"/);
  assert.match(render(<LoadingState label="Loading records" />), /Loading records/);
});
