import { AccessDeniedState, ServiceUnavailableState, SessionInvalidState } from "@/components/auth/admission-state";
import { ErrorState } from "@/components/states/error-state";
import type { ReactElement } from "react";
import type { CategoryApiFailure } from "@/lib/catalog/categories-api";

/**
 * Distinct failure states for a category read. Expected API outcomes are never
 * routed through the route error boundary and never collapsed into one message.
 */
export function CategoryFailureState({ failure, retryAfterSeconds }: Readonly<{ failure: CategoryApiFailure; retryAfterSeconds: number | null }>) {
  return failureState(failure, retryAfterSeconds);
}

function failureState(failure: CategoryApiFailure, retryAfterSeconds: number | null): ReactElement {
  let state = <ErrorState title="Category data unavailable" description="The category service returned a response this application could not read." />;

  if (failure === "session") state = <SessionInvalidState />;
  else if (failure === "forbidden") state = <AccessDeniedState />;
  else if (failure === "unavailable") state = <ServiceUnavailableState />;
  else if (failure === "throttled") state = <ErrorState title="Too many requests" description={retryAfterSeconds === null ? "The category service is rate limiting requests. Try again shortly." : `The category service is rate limiting requests. Try again in ${retryAfterSeconds} seconds.`} />;

  return state;
}
