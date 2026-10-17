import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Typography from "@mui/material/Typography";
import type { ApiPagination } from "@/lib/api/types";
import { requestListHref, type RequestListQuery } from "@/lib/requests/request-query";

/**
 * Server-provided pagination: links preserve the active filters and the page
 * size, and every total comes from the response's `meta.pagination`.
 */
export function RequestListPagination({ pagination, query }: Readonly<{ pagination: ApiPagination; query: RequestListQuery }>) {
  const previous = requestListHref({ ...query, page: Math.max(1, pagination.current_page - 1) });
  const next = requestListHref({ ...query, page: pagination.current_page + 1 });

  return (
    <Box component="nav" aria-label="Request list pagination" sx={{ display: "flex", flexDirection: { xs: "column", sm: "row" }, alignItems: { xs: "flex-start", sm: "center" }, justifyContent: "space-between", gap: 2, mt: 3 }}>
      <Typography color="text.secondary">
        Page {pagination.current_page} of {pagination.last_page} · {pagination.total} requests
      </Typography>
      <Box sx={{ display: "flex", flexDirection: "row", gap: 1 }}>
        <Button href={previous} disabled={!pagination.has_previous}>Previous</Button>
        <Button href={next} disabled={!pagination.has_next}>Next</Button>
      </Box>
    </Box>
  );
}
