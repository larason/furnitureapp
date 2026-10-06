import { SiteSection } from "@/components/layout/site-section";
import {
  PageMessage,
  PageMessageLink,
} from "@/components/states/page-message";

export default function NotFound() {
  return (
    <SiteSection>
      <PageMessage
        eyebrow="404"
        title="We couldn't find that page."
        description="The address may have changed, or the page may no longer exist."
      >
        <PageMessageLink href="/">Return home</PageMessageLink>
      </PageMessage>
    </SiteSection>
  );
}
