import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import type { Metadata } from "next";
import { EnquiryForm } from "@/components/enquiries/enquiry-form";
import { SiteSection } from "@/components/layout/site-section";
import { createPageMetadata } from "@/lib/seo/metadata";

export const metadata: Metadata = {
  ...createPageMetadata({ title: "Contact", description: "Send an enquiry to SL Furnitures.", canonicalPath: "/contact", robots: { index: false, follow: true } }),
};

export default function ContactPage() {
  return (
    <SiteSection aria-label="Contact" surface="paper">
      <Stack spacing={7}>
        <Stack spacing={3} sx={{ maxWidth: "var(--content-width-lead)" }}>
          <Typography component="h1" variant="h2">Let&apos;s talk furniture.</Typography>
          <Typography>Ask about a product, delivery, materials, or an idea you would like to explore.</Typography>
          <Typography variant="body2" color="text.secondary">Share your details below and we will review your enquiry.</Typography>
        </Stack>
        <EnquiryForm />
      </Stack>
    </SiteSection>
  );
}
