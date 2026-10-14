"use client";

import Button from "@mui/material/Button";
import { ErrorState } from "@/components/states/error-state";

// Next.js forwards `error` to this boundary, but the props are declared without
// it: no exception message, digest, or stack may reach this UI.
export default function AdminErrorBoundary({ reset }: Readonly<{ reset: () => void }>) {
  return (
    <ErrorState
      title="The admin application could not load"
      description="Try again. If the problem continues, contact an administrator."
      action={<Button onClick={reset}>Try again</Button>}
    />
  );
}
