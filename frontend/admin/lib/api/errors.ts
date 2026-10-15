import type { ApiErrorItem } from "./types";

export class ApiConfigurationError extends Error {
  constructor(message: string) { super(message); this.name = "ApiConfigurationError"; }
}

export class ApiError extends Error {
  constructor(readonly status: number, readonly errors: readonly ApiErrorItem[], readonly requestId?: string, readonly retryAfterSeconds?: number) {
    super("The API request failed.");
    this.name = "ApiError";
  }
}

export class ApiTransportError extends Error {
  constructor(readonly kind: "network" | "timeout" | "aborted", cause?: unknown) { super(transportMessage(kind), { cause }); this.name = "ApiTransportError"; }
}

const TRANSPORT_MESSAGES: Readonly<Record<ApiTransportError["kind"], string>> = {
  network: "The API could not be reached.",
  timeout: "The API request timed out.",
  aborted: "The API request was cancelled.",
};

function transportMessage(kind: ApiTransportError["kind"]): string {
  return TRANSPORT_MESSAGES[kind];
}
