import { readFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import type { ApiRequestOptions, ApiSuccess, RequestFunction } from "@/lib/api/client";

const webRoot = resolve(dirname(fileURLToPath(import.meta.url)), "../..");

export function read(file: string): string {
  return readFileSync(resolve(webRoot, file), "utf8");
}

export async function withApiDataSource<T>(callback: () => T | Promise<T>): Promise<T> {
  const previous = process.env.HOMEPAGE_DATA_SOURCE;
  delete process.env.HOMEPAGE_DATA_SOURCE;

  try {
    return await callback();
  } finally {
    if (previous === undefined) delete process.env.HOMEPAGE_DATA_SOURCE;
    else process.env.HOMEPAGE_DATA_SOURCE = previous;
  }
}

export function stubTransport(
  handler: (request: ApiRequestOptions) => ApiSuccess<unknown> | undefined,
): RequestFunction {
  return handler as unknown as RequestFunction;
}
