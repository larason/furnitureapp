import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import { NavLink } from "@/components/layout/nav-link";
import { productCollectionHref, type ProductCollectionQuery } from "@/lib/catalog/filters";

const paginationLinkSx = { display: "inline-flex", alignItems: "center", minHeight: 44, px: 3, typography: "button" };

type ProductCollectionPaginationProps = Readonly<{
  pathname: "/products" | "/search";
  ariaLabel: string;
  query: ProductCollectionQuery;
  currentPage: number;
  lastPage: number;
  hasPrevious: boolean;
  hasNext: boolean;
}>;

export function ProductCollectionPagination({ pathname, ariaLabel, query, currentPage, lastPage, hasPrevious, hasNext }: ProductCollectionPaginationProps) {
  if (!hasPrevious && !hasNext) return null;
  return (
    <Box component="nav" aria-label={ariaLabel} sx={{ display: "flex", flexWrap: "wrap", alignItems: "center", gap: 3 }}>
      {hasPrevious ? <NavLink href={productCollectionHref(pathname, query, currentPage - 1)} aria-label="Previous product page" sx={paginationLinkSx}>Previous page</NavLink> : null}
      <Typography aria-current="page" variant="body2">Page {currentPage} of {lastPage}</Typography>
      {hasNext ? <NavLink href={productCollectionHref(pathname, query, currentPage + 1)} aria-label="Next product page" sx={paginationLinkSx}>Next page</NavLink> : null}
    </Box>
  );
}
