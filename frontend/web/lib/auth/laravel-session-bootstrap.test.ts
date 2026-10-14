import assert from "node:assert/strict";
import test from "node:test";
import { ClerkOfflineError } from "@clerk/nextjs/errors";
import { ApiError, ApiTransportError, type ApiRequestOptions, type RequestFunction } from "@/lib/api/client";
import {
  createLaravelSessionBootstrap,
  isEligibleLaravelBootstrapSession,
} from "@/lib/auth/laravel-session-bootstrap";

const SESSION_ID = "sess_123";

test("requires loaded, verified, active Clerk session state before bootstrap", () => {
  const eligible = {
    isAuthLoaded: true,
    isUserLoaded: true,
    isSignedIn: true,
    sessionId: SESSION_ID,
    emailVerification: "verified",
    sessionStatus: "active" as const,
  };

  assert.equal(isEligibleLaravelBootstrapSession(eligible), true);
  assert.equal(isEligibleLaravelBootstrapSession({ ...eligible, isAuthLoaded: false }), false);
  assert.equal(isEligibleLaravelBootstrapSession({ ...eligible, isSignedIn: false }), false);
  assert.equal(isEligibleLaravelBootstrapSession({ ...eligible, emailVerification: "unverified" }), false);
  assert.equal(isEligibleLaravelBootstrapSession({ ...eligible, sessionStatus: "pending" }), false);
});

test("bootstraps an eligible Clerk session once and sends an ephemeral bearer token", async () => {
  const requests: ApiRequestOptions[] = [];
  const bootstrap = createLaravelSessionBootstrap({
    apiRequest: async <T>(request: ApiRequestOptions) => {
      requests.push(request);
      return { data: {} as T };
    },
  });

  await bootstrap.bootstrap(SESSION_ID, async () => "fresh-token");
  await bootstrap.bootstrap(SESSION_ID, async () => "fresh-token");

  assert.equal(requests.length, 1);
  assert.equal(requests[0].path, "/me");
  assert.equal(requests[0].cache, "no-store");
  assert.equal(new Headers(requests[0].headers).get("Authorization"), "Bearer fresh-token");
});

test("bootstraps a different Clerk session and permits a signed-out session to start again", async () => {
  let calls = 0;
  const bootstrap = createLaravelSessionBootstrap({
    apiRequest: async <T>() => {
      calls += 1;
      return { data: {} as T };
    },
  });

  await bootstrap.bootstrap(SESSION_ID, async () => "first-token");
  await bootstrap.bootstrap("sess_456", async () => "second-token");
  bootstrap.reset(SESSION_ID);
  await bootstrap.bootstrap(SESSION_ID, async () => "third-token");

  assert.equal(calls, 3);
});

test("deduplicates concurrent session bootstrap calls", async () => {
  let calls = 0;
  let release: (() => void) | undefined;
  const pending = new Promise<void>((resolve) => {
    release = resolve;
  });
  const bootstrap = createLaravelSessionBootstrap({
    apiRequest: async <T>() => {
      calls += 1;
      await pending;
      return { data: {} as T };
    },
  });

  const first = bootstrap.bootstrap(SESSION_ID, async () => "fresh-token");
  const second = bootstrap.bootstrap(SESSION_ID, async () => "fresh-token");
  release?.();
  await Promise.all([first, second]);

  assert.equal(calls, 1);
});

test("invalidates stale work on reset without deleting a replacement bootstrap", async () => {
  let calls = 0;
  let resolveFirst: (() => void) | undefined;
  let resolveSecond: (() => void) | undefined;
  const firstPending = new Promise<void>((resolve) => {
    resolveFirst = resolve;
  });
  const secondPending = new Promise<void>((resolve) => {
    resolveSecond = resolve;
  });
  const bootstrap = createLaravelSessionBootstrap({
    apiRequest: async <T>() => {
      calls += 1;
      await (calls === 1 ? firstPending : secondPending);
      return { data: {} as T };
    },
  });

  const stale = bootstrap.bootstrap(SESSION_ID, async () => "old-token");
  bootstrap.reset(SESSION_ID);
  const current = bootstrap.bootstrap(SESSION_ID, async () => "new-token");
  resolveFirst?.();
  await stale;
  resolveSecond?.();
  await current;
  await bootstrap.bootstrap(SESSION_ID, async () => "new-token");
  await bootstrap.bootstrap(SESSION_ID, async () => "new-token");

  assert.equal(calls, 2);
});

test("retries temporary failures with bounded backoff", async () => {
  let calls = 0;
  const delays: number[] = [];
  const bootstrap = createLaravelSessionBootstrap({
    apiRequest: async <T>() => {
      calls += 1;
      if (calls < 3) {
        throw new ApiTransportError("network");
      }
      return { data: {} as T };
    },
    retryDelaysMs: [1, 2],
    sleep: async (milliseconds) => {
      delays.push(milliseconds);
    },
  });

  await bootstrap.bootstrap(SESSION_ID, async () => "fresh-token");

  assert.equal(calls, 3);
  assert.deepEqual(delays, [1, 2]);
});

test("retries Clerk offline token acquisition with bounded backoff", async () => {
  let tokenCalls = 0;
  const delays: number[] = [];
  const bootstrap = createLaravelSessionBootstrap({
    apiRequest: async <T>() => ({ data: {} as T }),
    retryDelaysMs: [1, 2],
    sleep: async (milliseconds) => {
      delays.push(milliseconds);
    },
  });

  await bootstrap.bootstrap(SESSION_ID, async () => {
    tokenCalls += 1;
    if (tokenCalls < 3) {
      throw new ClerkOfflineError("offline");
    }
    return "fresh-token";
  });

  assert.equal(tokenCalls, 3);
  assert.deepEqual(delays, [1, 2]);
});

test("refreshes a rejected token once, then blocks an identity that remains rejected", async () => {
  let calls = 0;
  const tokenOptions: Array<Readonly<{ skipCache?: boolean }> | undefined> = [];
  const apiRequest: RequestFunction = async () => {
    calls += 1;
    throw new ApiError(401, [{ code: "INVALID_AUTHENTICATION", message: "Rejected" }]);
  };
  const bootstrap = createLaravelSessionBootstrap({ apiRequest });
  const getToken = async (options?: Readonly<{ skipCache?: boolean }>) => {
    tokenOptions.push(options);
    return "invalid-token";
  };

  await bootstrap.bootstrap(SESSION_ID, getToken);
  await bootstrap.bootstrap(SESSION_ID, getToken);
  bootstrap.reset(SESSION_ID);
  await bootstrap.bootstrap(SESSION_ID, getToken);

  assert.equal(calls, 4);
  assert.deepEqual(tokenOptions, [undefined, { skipCache: true }, undefined, { skipCache: true }]);
});

test("refreshes a rejected token once even when no transient retry budget remains", async () => {
  let calls = 0;
  const tokenOptions: Array<Readonly<{ skipCache?: boolean }> | undefined> = [];
  const bootstrap = createLaravelSessionBootstrap({
    apiRequest: async <T>() => {
      calls += 1;
      if (calls === 1) {
        throw new ApiError(401, [{ code: "INVALID_AUTHENTICATION", message: "Rejected" }]);
      }

      return { data: {} as T };
    },
    retryDelaysMs: [],
  });

  await bootstrap.bootstrap(SESSION_ID, async (options) => {
    tokenOptions.push(options);

    return "fresh-token";
  });

  assert.equal(calls, 2);
  assert.deepEqual(tokenOptions, [undefined, { skipCache: true }]);
});

test("keeps the token refresh independent of the transient retry count", async () => {
  let calls = 0;
  const delays: number[] = [];
  const tokenOptions: Array<Readonly<{ skipCache?: boolean }> | undefined> = [];
  const bootstrap = createLaravelSessionBootstrap({
    apiRequest: async <T>() => {
      calls += 1;
      if (calls === 1) {
        throw new ApiTransportError("network");
      }
      if (calls === 2) {
        throw new ApiError(401, [{ code: "INVALID_AUTHENTICATION", message: "Rejected" }]);
      }

      return { data: {} as T };
    },
    retryDelaysMs: [1],
    sleep: async (milliseconds) => {
      delays.push(milliseconds);
    },
  });

  await bootstrap.bootstrap(SESSION_ID, async (options) => {
    tokenOptions.push(options);

    return "fresh-token";
  });

  assert.equal(calls, 3);
  assert.deepEqual(delays, [1]);
  assert.deepEqual(tokenOptions, [undefined, undefined, { skipCache: true }]);
});

test("allows a later lifecycle change to retry an unsuccessful temporary bootstrap", async () => {
  let tokenCalls = 0;
  const bootstrap = createLaravelSessionBootstrap({
    apiRequest: async () => {
      throw new ApiTransportError("timeout");
    },
    retryDelaysMs: [],
  });

  const unavailableToken = async () => {
    tokenCalls += 1;
    return null;
  };
  await bootstrap.bootstrap(SESSION_ID, unavailableToken);
  await bootstrap.bootstrap(SESSION_ID, unavailableToken);

  assert.equal(tokenCalls, 2);
});
