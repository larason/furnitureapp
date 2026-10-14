import test from "node:test";
import assert from "node:assert/strict";
import { theme } from "../theme";

test("admin theme spacing interpolates fractions and preserves negative factors", () => {
  assert.equal(theme.spacing(0.5), "2px");
  assert.equal(theme.spacing(-1), "-4px");
});
