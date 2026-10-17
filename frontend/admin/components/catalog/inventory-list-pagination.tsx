import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Typography from "@mui/material/Typography";
import type { ApiPagination } from "@/lib/api/types";
import { inventoryListHref, type InventoryListQuery } from "@/lib/catalog/inventory-query";

/**
 * Server-provided pagination: links preserve the active filters and the page
 * size, and every total comes from the response's `meta.pagination`.
 */
export function InventoryListPagination({ pagination, query }: Readonly<{ pagination: ApiPagination; query: InventoryListQuery }>) {
  const previous = inventoryListHref({ ...query, page: Math.max(1, pagination.current_page - 1) });
  const next = inventoryListHref({ ...query, page: pagination.current_page + 1 });

  return (
    <Box component="nav" aria-label="Inventory list pagination" sx={{ display: "flex", flexDirection: { xs: "column", sm: "row" }, alignItems: { xs: "flex-start", sm: "center" }, justifyContent: "space-between", gap: 2, mt: 3 }}>
      <Typography color="text.secondary">
        Page {pagination.current_page} of {pagination.last_page} · {pagination.total} inventory record{pagination.total === 1 ? "" : "s"}
      </Typography>
      <Box sx={{ display: "flex", flexDirection: "row", gap: 1, flexWrap: "wrap" }}>
        {pagination.has_previous ? <Button href={previous}>Previous page</Button> : <Button disabled>Previous page</Button>}
        {pagination.has_next ? <Button href={next}>Next page</Button> : <Button disabled>Next page</Button>}
      </Box>
    </Box>
  );
}
