"use client";

import Button from "@mui/material/Button";
import { SiteSection } from "@/components/layout/site-section";
import {
  PageMessage,
  PageMessageLink,
} from "@/components/states/page-message";

export default function Error({ retry }: { retry: () => void }) {
  return (
    <SiteSection>
      <PageMessage
        eyebrow="Error"
        title="We couldn't load this page."
        description="Something went wrong on our side. Please try again."
      >
        <Button variant="contained" onClick={() => retry()}>
          Try again
        </Button>
        <PageMessageLink href="/" emphasis="secondary">
          Return home
        </PageMessageLink>
      </PageMessage>
    </SiteSection>
  );
}
