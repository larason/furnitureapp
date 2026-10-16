"use client";

import Link from "next/link";
import Box from "@mui/material/Box";
import MuiLink from "@mui/material/Link";
import Typography from "@mui/material/Typography";
import { DataTable } from "@/components/ui/data-table";
import type { DataTableColumn } from "@/components/ui/data-table";
import { safeImageUrl } from "@/lib/catalog/category-presentation";
import type { CategorySummary } from "@/lib/catalog/category-resource";
import { ADMIN_CATEGORY_PATHS } from "@/lib/navigation/admin-route-paths";

const NOT_SET = "Not set";
const OPEN_IMAGE = "Open image";

const COLUMNS: readonly DataTableColumn<CategorySummary>[] = [
  {
    key: "category",
    label: "Category",
    render: (category) => (
      <Box sx={{ display: "flex", flexDirection: "column", gap: 0.5, minWidth: 0 }}>
        <Typography component="span" sx={{ fontWeight: 600, overflowWrap: "anywhere" }}>{category.name}</Typography>
        <Typography component="span" variant="body2" color="text.secondary" sx={{ overflowWrap: "anywhere" }}>{category.slug}</Typography>
      </Box>
    ),
  },
  {
    key: "image",
    label: "Image",
    render: (category) => {
      const url = safeImageUrl(category.image?.url);
      return url === null
        ? <Typography component="span" color="text.secondary">{NOT_SET}</Typography>
        : <MuiLink href={url} target="_blank" rel="noreferrer">{OPEN_IMAGE}</MuiLink>;
    },
  },
  {
    key: "actions",
    label: "Actions",
    render: (category) => (
      <Box sx={{ display: "flex", flexDirection: "row", gap: 2, flexWrap: "wrap" }}>
        <Link href={ADMIN_CATEGORY_PATHS.detail(category.id)}>View {category.name}</Link>
        <Link href={ADMIN_CATEGORY_PATHS.edit(category.id)}>Edit {category.name}</Link>
      </Box>
    ),
  },
];

export function CategoryTable({ categories }: Readonly<{ categories: readonly CategorySummary[] }>) {
  return <DataTable columns={COLUMNS} rows={categories} getRowKey={(category) => category.id} empty={<Typography color="text.secondary">No categories were returned.</Typography>} />;
}
