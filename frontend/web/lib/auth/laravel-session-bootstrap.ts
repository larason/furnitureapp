import { ClerkOfflineError } from "@clerk/nextjs/errors";
import {
  ApiConfigurationError,
  ApiError,
  ApiTransportError,
  type RequestFunction,
} from "../api/client";
import {
  ClerkAuthenticationRequiredError,
  createAuthenticatedLaravelRequest,
} from "./laravel-request";

const DEFAULT_RETRY_DELAYS_MS = [250, 1_000] as const;

const INVALID_AUTHENTICATION_CODE = "INVALID_AUTHENTICATION";

const INITIAL_GENERATION = 0;

export type ClerkTokenGetter = (options?: Readonly<{ skipCache?: boolean }>) => Promise<string | null>;

export type LaravelSessionBootstrap = Readonly<{
  bootstrap: (sessionId: string, getToken: ClerkTokenGetter) => Promise<boolean>;
  reset: (sessionId: string) => void;
}>;

export type LaravelBootstrapSessionState = Readonly<{
  isAuthLoaded: boolean;
  isUserLoaded: boolean;
  isSignedIn: boolean;
  sessionId: string | null;
  emailVerification: string | null | undefined;
  sessionStatus: "active" | "pending" | null | undefined;
}>;

type BootstrapDependencies = Readonly<{
  apiRequest: RequestFunction;
  retryDelaysMs?: readonly number[];
  sleep?: (milliseconds: number) => Promise<void>;
}>;

export function createLaravelSessionBootstrap({
  apiRequest,
  retryDelaysMs = DEFAULT_RETRY_DELAYS_MS,
  sleep = delay,
}: BootstrapDependencies): LaravelSessionBootstrap {
  const successfulSessions = new Set<string>();
  const blockedSessions = new Set<string>();
  const inFlight = new Map<string, Promise<boolean>>();
  const generations = new Map<string, number>();

  return {
    bootstrap(sessionId, getToken) {
      if (successfulSessions.has(sessionId) || blockedSessions.has(sessionId)) {
        return Promise.resolve(successfulSessions.has(sessionId));
      }

      const existing = inFlight.get(sessionId);
      if (existing) {
        return existing;
      }

      const generation = currentGeneration(generations, sessionId);
      const work: Promise<boolean> = bootstrapSession(
        apiRequest,
        getToken,
        retryDelaysMs,
        sleep,
      )
        .then((outcome) => {
          if (generation !== currentGeneration(generations, sessionId)) {
            return false;
          }
          if (outcome === "success") {
            successfulSessions.add(sessionId);
            return true;
          } else if (outcome === "blocked") {
            blockedSessions.add(sessionId);
          }

          return false;
        })
        .finally(() => {
          if (inFlight.get(sessionId) === work) {
            inFlight.delete(sessionId);
          }
        });

      inFlight.set(sessionId, work);

      return work;
    },
    reset(sessionId) {
      successfulSessions.delete(sessionId);
      blockedSessions.delete(sessionId);
      generations.set(sessionId, currentGeneration(generations, sessionId) + 1);
      inFlight.delete(sessionId);
    },
  };
}

/**
 * A session with no recorded generation is still at the initial generation, so
 * an absent entry must compare equal to it instead of invalidating the
 * bootstrap result it belongs to.
 */
function currentGeneration(
  generations: ReadonlyMap<string, number>,
  sessionId: string,
): number {
  return generations.get(sessionId) ?? INITIAL_GENERATION;
}

export function isEligibleLaravelBootstrapSession({
  isAuthLoaded,
  isUserLoaded,
  isSignedIn,
  sessionId,
  emailVerification,
  sessionStatus,
}: LaravelBootstrapSessionState): boolean {
  return isAuthLoaded
    && isUserLoaded
    && isSignedIn
    && sessionId !== null
    && emailVerification === "verified"
    && (sessionStatus === undefined || sessionStatus === "active");
}

type BootstrapOutcome = "success" | "blocked" | "failed";

async function bootstrapSession(
  apiRequest: RequestFunction,
  getToken: ClerkTokenGetter,
  retryDelaysMs: readonly number[],
  sleep: (milliseconds: number) => Promise<void>,
): Promise<BootstrapOutcome> {
  return attemptBootstrap(apiRequest, getToken, retryDelaysMs, sleep, 0, false);
}

/**
 * Retries strictly sequentially: each attempt depends on the previous outcome,
 * so it cannot be parallelised. `attempt` advances only when a bounded backoff
 * retry is scheduled. A one-time token refresh retries the current attempt
 * instead, so it never consumes the transient retry budget and still happens
 * when no retry remains.
 */
async function attemptBootstrap(
  apiRequest: RequestFunction,
  getToken: ClerkTokenGetter,
  retryDelaysMs: readonly number[],
  sleep: (milliseconds: number) => Promise<void>,
  attempt: number,
  refreshAttempted: boolean,
): Promise<BootstrapOutcome> {
  try {
    const request = createAuthenticatedLaravelRequest(
      () => Promise.resolve({
        isAuthenticated: true,
        sessionStatus: "active",
        getToken: () => getToken(refreshAttempted ? { skipCache: true } : undefined),
      }),
      apiRequest,
    );
    await request({ path: "/me", cache: "no-store" });

    return "success";
  } catch (error) {
    if (isRefreshableAuthenticationFailure(error) && !refreshAttempted) {
      return attemptBootstrap(apiRequest, getToken, retryDelaysMs, sleep, attempt, true);
    }
    if (isBlockedBootstrapError(error)) {
      return "blocked";
    }
    if (!isTemporaryBootstrapError(error) || attempt === retryDelaysMs.length) {
      return "failed";
    }

    await sleep(retryDelaysMs[attempt]);

    return attemptBootstrap(apiRequest, getToken, retryDelaysMs, sleep, attempt + 1, refreshAttempted);
  }
}

function isBlockedBootstrapError(error: unknown): boolean {
  if (error instanceof ApiConfigurationError) {
    return true;
  }

  return error instanceof ApiError && error.status >= 400 && error.status < 500 && error.status !== 429;
}

function isRefreshableAuthenticationFailure(error: unknown): boolean {
  return error instanceof ApiError
    && error.status === 401
    && error.errors.some(({ code }) => code === INVALID_AUTHENTICATION_CODE);
}

function isTemporaryBootstrapError(error: unknown): boolean {
  return ClerkOfflineError.is(error)
    || error instanceof ApiTransportError
    || error instanceof ClerkAuthenticationRequiredError
    || (error instanceof ApiError && (error.status === 429 || error.status >= 500));
}

function delay(milliseconds: number): Promise<void> {
  return new Promise((resolve) => {
    setTimeout(resolve, milliseconds);
  });
}
