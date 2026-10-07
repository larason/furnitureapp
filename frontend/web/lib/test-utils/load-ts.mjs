import { readFileSync, existsSync } from "node:fs";
import { createRequire } from "node:module";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import vm from "node:vm";
import ts from "typescript";

const require = createRequire(import.meta.url);
const web = resolve(dirname(fileURLToPath(import.meta.url)), "../..");

export const read = (file) => readFileSync(resolve(web, file), "utf8");

export async function withApiDataSource(callback) {
  const previous = process.env.HOMEPAGE_DATA_SOURCE;
  delete process.env.HOMEPAGE_DATA_SOURCE;

  try {
    return await callback();
  } finally {
    if (previous === undefined) delete process.env.HOMEPAGE_DATA_SOURCE;
    else process.env.HOMEPAGE_DATA_SOURCE = previous;
  }
}

export function loadTs(file, overrides = {}) {
  const path = resolve(web, file);
  const output = ts.transpileModule(readFileSync(path, "utf8"), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022, jsx: ts.JsxEmit.ReactJSX, esModuleInterop: true },
  }).outputText;
  const compiled = { exports: {} };
  const importer = (specifier) => {
    if (specifier in overrides) return overrides[specifier];
    if (specifier.startsWith(".") || specifier.startsWith("@/")) {
      const target = specifier.startsWith("@/") ? resolve(web, specifier.slice(2)) : resolve(dirname(path), specifier);
      if (specifier.endsWith(".json")) return require(target);
      const extension = [".tsx", ".ts"].find((candidate) => existsSync(`${target}${candidate}`));
      if (!extension) return require(target);
      return loadTs(`${target}${extension}`, overrides);
    }
    return require(specifier);
  };
  const moduleFactory = new vm.Script(`(function(require, module, exports) { ${output}\n})`, { filename: path }).runInThisContext({ timeout: 1_000 });
  moduleFactory(importer, compiled, compiled.exports);
  return compiled.exports;
}
