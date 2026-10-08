import Alert from "@mui/material/Alert";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import type { ReactNode } from "react";
import { SiteSection } from "@/components/layout/site-section";

export function InformationPage({ title, introduction, children }: Readonly<{ title: string; introduction: string; children: ReactNode }>) {
  return <SiteSection aria-label={title} surface="paper"><Stack spacing={7}><Stack spacing={3}><Typography component="h1" variant="h2">{title}</Typography><Typography>{introduction}</Typography></Stack>{children}</Stack></SiteSection>;
}

export function DocumentSection({ title, children }: Readonly<{ title: string; children: ReactNode }>) {
  return <Stack component="section" spacing={3}><Typography component="h2" variant="h4">{title}</Typography><Stack spacing={2}>{children}</Stack></Stack>;
}

export function DraftLegalNotice() {
  return <Alert severity="warning"><Typography variant="subtitle2">DRAFT — LEGAL REVIEW REQUIRED</Typography><Typography variant="body2">This development draft describes the currently verified product behavior. It is not an approved legal policy or a substitute for legal advice.</Typography></Alert>;
}
