import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Typography from "@mui/material/Typography";
import type { ApiPagination } from "@/lib/api/types";
import { categoryListHref, type CategoryListQuery } from "@/lib/catalog/category-query";

/** Server-provided pagination: totals and boundaries come from the collection metadata. */
export function CategoryListPagination({ pagination, query }: Readonly<{ pagination: ApiPagination; query: CategoryListQuery }>) {
  const previous = categoryListHref({ ...query, page: Math.max(1, pagination.current_page - 1) });
  const next = categoryListHref({ ...query, page: pagination.current_page + 1 });

  return (
    <Box component="nav" aria-label="Category list pagination" sx={{ display: "flex", flexDirection: { xs: "column", sm: "row" }, alignItems: { xs: "flex-start", sm: "center" }, justifyContent: "space-between", gap: 2, mt: 3 }}>
      <Typography color="text.secondary">
        Page {pagination.current_page} of {pagination.last_page} · {pagination.total} categor{pagination.total === 1 ? "y" : "ies"}
      </Typography>
      <Box sx={{ display: "flex", flexDirection: "row", gap: 1, flexWrap: "wrap" }}>
        {pagination.has_previous ? <Button href={previous}>Previous page</Button> : <Button disabled>Previous page</Button>}
        {pagination.has_next ? <Button href={next}>Next page</Button> : <Button disabled>Next page</Button>}
      </Box>
    </Box>
  );
}
