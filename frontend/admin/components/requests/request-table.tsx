"use client";

import Link from "next/link";
import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { StatusIndicator } from "@/components/ui/status-indicator";
import { ADMIN_REQUEST_PATHS } from "@/lib/navigation/admin-route-paths";
import {
  formatRequestQuantity,
  formatRequestTimestamp,
  requestContactLines,
  requestFurnitureLabel,
  requestHasAttachment,
  requestReferenceDisplay,
  requestStatusDisplay,
  REQUEST_TIMESTAMP_UNAVAILABLE,
} from "@/lib/requests/request-presentation";
import type { OperationalRequest } from "@/lib/requests/request-resource";

const COLUMNS: readonly DataTableColumn<OperationalRequest>[] = [
  {
    key: "reference",
    label: "Reference",
    render: (request) => <Typography component="span" sx={{ fontWeight: 600 }}>{requestReferenceDisplay(request)}</Typography>,
  },
  {
    key: "submitted",
    label: "Submitted",
    render: (request) => formatRequestTimestamp(request.createdAt) ?? REQUEST_TIMESTAMP_UNAVAILABLE,
  },
  {
    key: "customer",
    label: "Customer",
    render: (request) => <CustomerCell request={request} />,
  },
  {
    key: "furniture",
    label: "Furniture",
    render: (request) => requestFurnitureLabel(request),
  },
  { key: "quantity", label: "Quantity", render: (request) => formatRequestQuantity(request.quantity) },
  {
    key: "status",
    label: "Status",
    render: (request) => {
      const display = requestStatusDisplay(request.requestStatus);
      return <StatusIndicator status={display.tone} label={display.label} />;
    },
  },
  {
    key: "attachment",
    label: "Attachment",
    render: (request) => (requestHasAttachment(request) ? "Attachment submitted" : "No attachment"),
  },
  {
    key: "actions",
    label: "Actions",
    render: (request) => (
      <Link href={ADMIN_REQUEST_PATHS.detail(request.id)} style={{ overflowWrap: "anywhere" }}>
        Open request {requestReferenceDisplay(request)}
      </Link>
    ),
  },
];

/**
 * Operational request inbox. Every value is the one Laravel returned; the only
 * action opens the request detail. `user_id` is deliberately never rendered, and
 * no customer profile is resolved here.
 */
export function RequestTable({ requests }: Readonly<{ requests: readonly OperationalRequest[] }>) {
  return <DataTable columns={COLUMNS} rows={requests} getRowKey={(request) => request.id} />;
}

function CustomerCell({ request }: Readonly<{ request: OperationalRequest }>) {
  const contact = requestContactLines(request);
  return (
    <Box sx={{ display: "flex", flexDirection: "column", gap: 0.5, minWidth: 0 }}>
      <Typography component="span" sx={{ overflowWrap: "anywhere" }}>{contact[0].value}</Typography>
      <Typography component="span" variant="body2" color="text.secondary" sx={{ overflowWrap: "anywhere" }}>
        {contact[1].value} · {contact[2].value}
      </Typography>
    </Box>
  );
}
