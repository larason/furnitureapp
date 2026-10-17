import type { ReactElement } from "react";
import { AccessDeniedState, ServiceUnavailableState, SessionInvalidState } from "@/components/auth/admission-state";
import { ErrorState } from "@/components/states/error-state";
import type { RequestApiFailure } from "@/lib/requests/request-api";

/**
 * Distinct failure states for a request read. An API failure is never collapsed
 * into an empty list, and never routed through the route error boundary: each
 * outcome keeps its own recoverable treatment. An unreadable response is
 * `invalid` (rejected parsing), not `unavailable` (a service outage).
 */
export function RequestFailureState({ failure, retryAfterSeconds }: Readonly<{ failure: RequestApiFailure; retryAfterSeconds: number | null }>) {
  return failureState(failure, retryAfterSeconds);
}

function failureState(failure: RequestApiFailure, retryAfterSeconds: number | null): ReactElement {
  let state = <ErrorState title="Request data unavailable" description="The request service returned a response this application could not read." />;

  if (failure === "session") state = <SessionInvalidState />;
  else if (failure === "forbidden") state = <AccessDeniedState />;
  else if (failure === "unavailable") state = <ServiceUnavailableState />;
  else if (failure === "not-found") state = <ErrorState title="Request unavailable" description="This request could not be found. It may have been removed." />;
  else if (failure === "throttled") state = <ErrorState title="Too many requests" description={retryAfterSeconds === null ? "The request service is rate limiting requests. Try again shortly." : `The request service is rate limiting requests. Try again in ${retryAfterSeconds} seconds.`} />;

  return state;
}
