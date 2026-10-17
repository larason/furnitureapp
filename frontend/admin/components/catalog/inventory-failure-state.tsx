import type { ReactElement } from "react";
import { AccessDeniedState, ServiceUnavailableState, SessionInvalidState } from "@/components/auth/admission-state";
import { ErrorState } from "@/components/states/error-state";
import type { InventoryApiFailure } from "@/lib/catalog/inventory-api";

/**
 * Distinct failure states for an inventory read. An API failure is never
 * collapsed into an empty list, and never routed through the route error
 * boundary: each outcome keeps its own recoverable treatment.
 */
export function InventoryFailureState({ failure, retryAfterSeconds }: Readonly<{ failure: InventoryApiFailure; retryAfterSeconds: number | null }>) {
  return failureState(failure, retryAfterSeconds);
}

function failureState(failure: InventoryApiFailure, retryAfterSeconds: number | null): ReactElement {
  let state = <ErrorState title="Inventory data unavailable" description="The inventory service returned a response this application could not read." />;

  if (failure === "session") state = <SessionInvalidState />;
  else if (failure === "forbidden") state = <AccessDeniedState />;
  else if (failure === "unavailable") state = <ServiceUnavailableState />;
  else if (failure === "throttled") state = <ErrorState title="Too many requests" description={retryAfterSeconds === null ? "The inventory service is rate limiting requests. Try again shortly." : `The inventory service is rate limiting requests. Try again in ${retryAfterSeconds} seconds.`} />;

  return state;
}
