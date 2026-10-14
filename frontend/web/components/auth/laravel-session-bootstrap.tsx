"use client";

import { useAuth, useUser } from "@clerk/nextjs";
import { useEffect, useRef } from "react";
import { createApiClient } from "@/lib/api/client";
import {
  createLaravelSessionBootstrap,
  isEligibleLaravelBootstrapSession,
} from "@/lib/auth/laravel-session-bootstrap";

const browserApiRequest = createApiClient({ baseUrl: process.env.NEXT_PUBLIC_API_BASE_URL });
const sessionBootstrap = createLaravelSessionBootstrap({ apiRequest: browserApiRequest });
const MAX_SESSION_RECOVERY_ATTEMPTS = 2;

export function LaravelSessionBootstrap() {
  const { getToken, isLoaded: isAuthLoaded, isSignedIn, sessionClaims, sessionId } = useAuth();
  const { isLoaded: isUserLoaded, user } = useUser();
  const previousSessionId = useRef<string | null>(null);
  const recoveryAttempts = useRef(new Map<string, number>());
  const emailVerification = user?.primaryEmailAddress?.verification.status;
  const sessionStatus = sessionClaims?.sts;
  const sessionKey = sessionId ?? null;
  const ready = isEligibleLaravelBootstrapSession({
    isAuthLoaded,
    isUserLoaded,
    isSignedIn: isSignedIn === true,
    sessionId: sessionKey,
    emailVerification,
    sessionStatus,
  });

  useEffect(() => {
    if (!isAuthLoaded || !isUserLoaded) {
      return;
    }

    if (!ready || sessionKey === null) {
      if (isSignedIn === false && previousSessionId.current !== null) {
        sessionBootstrap.reset(previousSessionId.current);
        recoveryAttempts.current.delete(previousSessionId.current);
        previousSessionId.current = null;
      }

      return;
    }

    if (previousSessionId.current !== null && previousSessionId.current !== sessionKey) {
      sessionBootstrap.reset(previousSessionId.current);
      recoveryAttempts.current.delete(previousSessionId.current);
    }
    previousSessionId.current = sessionKey;
    void sessionBootstrap.bootstrap(sessionKey, getToken);

    const recover = () => {
      const attempts = recoveryAttempts.current.get(sessionKey) ?? 0;
      if (attempts >= MAX_SESSION_RECOVERY_ATTEMPTS) {
        return;
      }

      recoveryAttempts.current.set(sessionKey, attempts + 1);
      void sessionBootstrap.bootstrap(sessionKey, getToken);
    };
    const recoverWhenVisible = () => {
      if (document.visibilityState === "visible") {
        recover();
      }
    };

    window.addEventListener("online", recover);
    document.addEventListener("visibilitychange", recoverWhenVisible);

    return () => {
      window.removeEventListener("online", recover);
      document.removeEventListener("visibilitychange", recoverWhenVisible);
    };
  }, [getToken, isAuthLoaded, isSignedIn, isUserLoaded, ready, sessionKey]);

  return null;
}
