"use client";

import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Typography from "@mui/material/Typography";
import { useState } from "react";
import { InventoryAdjustmentDialog } from "@/components/catalog/inventory-adjustment-dialog";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import type { InventoryAdjustmentActions, InventoryAdjustmentIntent } from "@/lib/catalog/inventory-mutations";
import {
  formatInventoryQuantity,
  formatInventoryTimestamp,
  inventorySubjectLabel,
  inventoryVariantLabel,
  INVENTORY_SKU_UNAVAILABLE,
  INVENTORY_TIMESTAMP_UNAVAILABLE,
  type InventoryRow,
} from "@/lib/catalog/inventory-presentation";

interface InventoryTableProps {
  readonly rows: readonly InventoryRow[];
  readonly canManage: boolean;
  readonly actions: InventoryAdjustmentActions;
}

/**
 * Operational inventory table. Every value is the one Laravel returned, and the
 * only action opens the record's detail/adjustment dialog — which repeats the
 * read against `INV-002` and shows the adjustment form only when the resolved
 * identity holds `inventory.manage`.
 *
 * The table owns each row's adjustment intent so an uncertain retry keeps its
 * idempotency key when the dialog is closed and the same record is reopened.
 */
export function InventoryTable({ rows, canManage, actions }: Readonly<InventoryTableProps>) {
  const [selected, setSelected] = useState<InventoryRow | null>(null);
  const [intents, setIntents] = useState<Readonly<Record<string, InventoryAdjustmentIntent | null>>>({});

  function setSelectedIntent(intent: InventoryAdjustmentIntent | null): void {
    if (selected === null) return;
    const id = selected.record.id;
    setIntents((current) => ({ ...current, [id]: intent }));
  }

  return (
    <>
      <DataTable columns={columns(canManage, setSelected)} rows={rows} getRowKey={(row) => row.record.id} />
      {selected === null ? null : (
        <InventoryAdjustmentDialog
          key={selected.record.id}
          subject={selected}
          canManage={canManage}
          actions={actions}
          intent={intents[selected.record.id] ?? null}
          onIntentChange={setSelectedIntent}
          onClose={() => setSelected(null)}
        />
      )}
    </>
  );
}

function columns(canManage: boolean, onSelect: (row: InventoryRow) => void): readonly DataTableColumn<InventoryRow>[] {
  return [
    {
      key: "product",
      label: "Product",
      render: (row) => (
        <Box sx={{ display: "flex", flexDirection: "column", gap: 0.5, minWidth: 0 }}>
          <Typography component="span" sx={{ fontWeight: 600, overflowWrap: "anywhere" }}>{inventorySubjectLabel(row.product)}</Typography>
          <Typography component="span" variant="body2" color="text.secondary" sx={{ overflowWrap: "anywhere" }}>{row.product.identifier}</Typography>
        </Box>
      ),
    },
    {
      key: "variant",
      label: "Variant",
      render: (row) => (
        <Box sx={{ display: "flex", flexDirection: "column", gap: 0.5, minWidth: 0 }}>
          <Typography component="span" sx={{ overflowWrap: "anywhere" }}>{inventoryVariantLabel(row.variant)}</Typography>
          <Typography component="span" variant="body2" color="text.secondary" sx={{ overflowWrap: "anywhere" }}>
            {row.variant.sku === null ? INVENTORY_SKU_UNAVAILABLE : `SKU ${row.variant.sku}`} · {row.variant.identifier}
          </Typography>
        </Box>
      ),
    },
    { key: "location", label: "Warehouse location", render: (row) => row.record.warehouseLocation },
    { key: "quantity", label: "Quantity", render: (row) => formatInventoryQuantity(row.record.quantity) },
    { key: "reserved", label: "Reserved", render: (row) => formatInventoryQuantity(row.record.reservedQuantity) },
    { key: "available", label: "Available", render: (row) => formatInventoryQuantity(row.record.availableQuantity) },
    { key: "updated", label: "Last updated", render: (row) => formatInventoryTimestamp(row.record.updatedAt) ?? INVENTORY_TIMESTAMP_UNAVAILABLE },
    {
      key: "actions",
      label: "Actions",
      render: (row) => (
        <Button
          size="small"
          variant="outlined"
          onClick={() => onSelect(row)}
          aria-label={`${canManage ? "Inspect and adjust" : "Inspect"} inventory ${row.record.id} for ${inventorySubjectLabel(row.product)} at ${row.record.warehouseLocation}`}
        >
          {canManage ? "Adjust stock" : "Inspect stock"}
        </Button>
      ),
    },
  ];
}
