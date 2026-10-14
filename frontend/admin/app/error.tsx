"use client";

import Button from "@mui/material/Button";
import { ErrorState } from "@/components/states/error-state";

export default function Error({ reset }: { error: Error & { digest?: string }; reset: () => void }) {
  return <ErrorState title="The admin application could not load" description="Try again. If the problem continues, contact an administrator." action={<Button onClick={reset}>Try again</Button>} />;
}
