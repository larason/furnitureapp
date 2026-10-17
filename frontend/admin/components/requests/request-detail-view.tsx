import Box from "@mui/material/Box";
import Link from "@mui/material/Link";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import type { ReactNode } from "react";
import { RequestAttachmentPanel } from "@/components/requests/request-attachment-panel";
import { RequestOperationalForm } from "@/components/requests/request-operational-form";
import { SectionHeader } from "@/components/ui/section-header";
import type { RequestUpdateActions } from "@/lib/requests/request-mutations";
import {
  formatRequestDimensions,
  formatRequestQuantity,
  requestContactLines,
  requestIdentityLabel,
  REQUEST_CUSTOM_FURNITURE,
  REQUEST_FURNITURE_UNAVAILABLE,
  REQUEST_NOT_PROVIDED,
} from "@/lib/requests/request-presentation";
import type { OperationalRequest } from "@/lib/requests/request-resource";

interface RequestDetailViewProps {
  readonly request: OperationalRequest;
  /** The admin product route, or `null` when the identity cannot open it. */
  readonly productHref: string | null;
  readonly canManage: boolean;
  readonly actions: RequestUpdateActions;
}

/**
 * The authoritative REQ-005 record. Every value is the one Laravel returned; a
 * null is stated explicitly rather than rendered blank, zero or inferred. The
 * request's own contact snapshot is the only customer data shown — `user_id` is
 * never resolved into a profile, linked, or treated as an authorization signal.
 */
export function RequestDetailView({ request, productHref, canManage, actions }: Readonly<RequestDetailViewProps>) {
  return (
    <Box sx={{ display: "grid", gridTemplateColumns: { xs: "1fr", lg: "minmax(0, 2fr) minmax(0, 1fr)" }, gap: 3, alignItems: "start" }}>
      <Box sx={{ display: "flex", flexDirection: "column", gap: 3, minWidth: 0 }}>
        <DetailSection headingId="request-customer-heading" title="Customer">
          {requestContactLines(request).map((line) => (
            <DefinitionRow key={line.label} term={line.label} value={line.value} />
          ))}
          <DefinitionRow term="Account reference" value={requestIdentityLabel(request.userId)} />
        </DetailSection>
        <DetailSection headingId="request-furniture-heading" title="Furniture and specification">
          <DefinitionRow term="Product" value={<FurnitureValue request={request} productHref={productHref} />} />
          <DefinitionRow term="Quantity" value={formatRequestQuantity(request.quantity)} />
          <DefinitionRow term="Dimensions" value={formatRequestDimensions(request.dimensions)} />
          <DefinitionRow term="Material" value={request.material ?? REQUEST_NOT_PROVIDED} />
          <DefinitionRow term="Color" value={request.color ?? REQUEST_NOT_PROVIDED} />
          <DefinitionRow term="Notes" value={request.notes ?? REQUEST_NOT_PROVIDED} />
        </DetailSection>
        <RequestAttachmentPanel attachments={request.attachments} />
      </Box>
      <RequestOperationalForm
        record={{ id: request.id, requestStatus: request.requestStatus, staffInternalNotes: request.staffInternalNotes }}
        canManage={canManage}
        actions={actions}
      />
    </Box>
  );
}

function DetailSection({ headingId, title, children }: Readonly<{ headingId: string; title: string; children: ReactNode }>) {
  return (
    <Paper component="section" variant="outlined" aria-labelledby={headingId} sx={{ p: 3, display: "flex", flexDirection: "column", gap: 2 }}>
      <SectionHeader title={title} titleId={headingId} />
      <Box component="dl" sx={{ display: "grid", gridTemplateColumns: { xs: "1fr", sm: "auto minmax(0, 1fr)" }, columnGap: 3, rowGap: 1, m: 0 }}>
        {children}
      </Box>
    </Paper>
  );
}

function DefinitionRow({ term, value }: Readonly<{ term: string; value: ReactNode }>) {
  return (
    <>
      <Typography component="dt" variant="body2" color="text.secondary">{term}</Typography>
      <Typography component="dd" sx={{ m: 0, overflowWrap: "anywhere" }}>{value}</Typography>
    </>
  );
}

/**
 * The linked product, or an explicit statement when there is none. The product
 * route is offered only when the identity holds `products.manage`; a
 * request-only operator is never sent to a route they cannot open.
 */
function FurnitureValue({ request, productHref }: Readonly<{ request: OperationalRequest; productHref: string | null }>) {
  if (request.product === null) {
    return request.productId === null ? REQUEST_CUSTOM_FURNITURE : REQUEST_FURNITURE_UNAVAILABLE;
  }

  return (
    <Box sx={{ display: "flex", flexDirection: "column", gap: 0.5 }}>
      {productHref === null ? (
        <Typography component="span">{request.product.name}</Typography>
      ) : (
        <Link href={productHref}>{request.product.name}</Link>
      )}
      <Typography component="span" variant="body2" color="text.secondary">{request.product.slug}</Typography>
    </Box>
  );
}
