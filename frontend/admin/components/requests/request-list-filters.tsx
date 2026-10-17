import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import MenuItem from "@mui/material/MenuItem";
import TextField from "@mui/material/TextField";
import { REQUEST_STATUS_LABELS, REQUEST_STATUS_VALUES } from "@/lib/requests/request-mutations";
import {
  REQUEST_LIST_DEFAULT_PER_PAGE,
  REQUEST_PRODUCT_FILTER_MAX_LENGTH,
  REQUEST_SEARCH_MAX_LENGTH,
  type RequestListQuery,
} from "@/lib/requests/request-query";
import { ADMIN_ROUTE_PATHS } from "@/lib/navigation/admin-route-paths";

/**
 * Server-side filtering through a native GET form, so the list stays a Server
 * Component, the URL remains the single source of filter state, and no request
 * is issued per keystroke. Only contract-approved parameters are submitted.
 *
 * Each control is uncontrolled and carries its own `key`, so it resets to the
 * URL value when the query changes. A form-level `key` would remount the whole
 * form and cause a hydration mismatch, so it is deliberately not used.
 */
export function RequestListFilters({ query }: Readonly<{ query: RequestListQuery }>) {
  return (
    <Box
      component="form"
      method="get"
      action={ADMIN_ROUTE_PATHS.requests}
      aria-label="Filter made-to-order requests"
      sx={{ display: "grid", gridTemplateColumns: { xs: "1fr", sm: "repeat(2, minmax(0, 1fr))", lg: "repeat(3, minmax(0, 1fr))" }, gap: 2, alignItems: "start", mb: 4 }}
    >
      <TextField
        key={`search:${query.search ?? ""}`}
        name="search"
        label="Search"
        defaultValue={query.search ?? ""}
        size="small"
        slotProps={{ htmlInput: { maxLength: REQUEST_SEARCH_MAX_LENGTH } }}
        helperText={`Name, email, phone, request reference or linked product, up to ${REQUEST_SEARCH_MAX_LENGTH} characters.`}
      />
      <TextField
        key={`status:${query.requestStatus ?? ""}`}
        select
        name="request_status"
        label="Status"
        defaultValue={query.requestStatus ?? ""}
        size="small"
      >
        <MenuItem value="">Any status</MenuItem>
        {REQUEST_STATUS_VALUES.map((value) => (
          <MenuItem key={value} value={value}>{REQUEST_STATUS_LABELS[value]}</MenuItem>
        ))}
      </TextField>
      <TextField
        key={`product:${query.productId ?? ""}`}
        name="product_id"
        label="Product identifier"
        defaultValue={query.productId ?? ""}
        size="small"
        slotProps={{ htmlInput: { maxLength: REQUEST_PRODUCT_FILTER_MAX_LENGTH } }}
        helperText="Exact Product ID (prod_…). This is not a product-name search."
      />
      <TextField
        key={`from:${query.createdFrom ?? ""}`}
        name="created_from"
        label="Submitted from"
        type="date"
        defaultValue={query.createdFrom ?? ""}
        size="small"
        slotProps={{ inputLabel: { shrink: true }, htmlInput: { max: query.createdTo ?? undefined } }}
        helperText="Inclusive lower bound, UTC (from 00:00:00)."
      />
      <TextField
        key={`to:${query.createdTo ?? ""}`}
        name="created_to"
        label="Submitted to"
        type="date"
        defaultValue={query.createdTo ?? ""}
        size="small"
        slotProps={{ inputLabel: { shrink: true }, htmlInput: { min: query.createdFrom ?? undefined } }}
        helperText="Inclusive upper bound, UTC (to 23:59:59)."
      />
      {query.perPage === REQUEST_LIST_DEFAULT_PER_PAGE ? null : <input type="hidden" name="per_page" value={query.perPage} />}
      <Box sx={{ display: "flex", flexDirection: "row", gap: 1, flexWrap: "wrap", gridColumn: { xs: "auto", sm: "1 / -1" } }}>
        <Button type="submit" variant="contained">Apply filters</Button>
        <Button href={ADMIN_ROUTE_PATHS.requests}>Clear filters</Button>
      </Box>
    </Box>
  );
}
