import { AccessDeniedState, ServiceUnavailableState, SessionInvalidState } from "@/components/auth/admission-state";
import { ErrorState } from "@/components/states/error-state";
import type { ReactElement } from "react";
import type { ProductApiFailure } from "@/lib/catalog/products-api";

/**
 * Distinct failure states for a product read. Expected API outcomes are never
 * routed through the route error boundary and never collapsed into one message.
 */
export function ProductFailureState({ failure, retryAfterSeconds }: Readonly<{ failure: ProductApiFailure; retryAfterSeconds: number | null }>) {
  return failureState(failure, retryAfterSeconds);
}

function failureState(failure: ProductApiFailure, retryAfterSeconds: number | null): ReactElement {
  let state = <ErrorState title="Product data unavailable" description="The product service returned a response this application could not read." />;

  if (failure === "session") state = <SessionInvalidState />;
  else if (failure === "forbidden") state = <AccessDeniedState />;
  else if (failure === "unavailable") state = <ServiceUnavailableState />;
  else if (failure === "throttled") state = <ErrorState title="Too many requests" description={retryAfterSeconds === null ? "The product service is rate limiting requests. Try again shortly." : `The product service is rate limiting requests. Try again in ${retryAfterSeconds} seconds.`} />;

  return state;
}
