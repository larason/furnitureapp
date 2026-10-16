import Box from "@mui/material/Box";
import MuiLink from "@mui/material/Link";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import { SectionHeader } from "@/components/ui/section-header";
import { formatCategoryTimestamp, safeImageUrl } from "@/lib/catalog/category-presentation";
import type { CategoryDetail } from "@/lib/catalog/category-resource";

/** Read-only authoritative category record. Every value comes from Laravel. */
export function CategoryDetailView({ category }: Readonly<{ category: CategoryDetail }>) {
  const created = formatCategoryTimestamp(category.createdAt);
  const imageUrl = safeImageUrl(category.image?.url);

  return (
    <Box component="section" aria-labelledby="category-details-heading">
      <SectionHeader title="Category details" titleId="category-details-heading" />
      <Paper variant="outlined" sx={{ p: 3, mt: 3 }}>
        <Box sx={{ display: "grid", gridTemplateColumns: { xs: "1fr", sm: "repeat(2, minmax(0, 1fr))" }, gap: 3 }}>
          <DetailField label="Public slug" value={category.slug} />
          <Box sx={{ minWidth: 0 }}>
            <Typography variant="overline" color="text.secondary" component="p">Image</Typography>
            {imageUrl === null
              ? <Typography color="text.secondary">No image is recorded for this category.</Typography>
              : <MuiLink href={imageUrl} target="_blank" rel="noreferrer">Open image</MuiLink>}
          </Box>
          {created === null ? null : <DetailField label="Created" value={created} />}
        </Box>
        <Box sx={{ mt: 3, minWidth: 0 }}>
          <Typography variant="overline" color="text.secondary" component="p">Description</Typography>
          <Typography sx={{ whiteSpace: "pre-wrap", overflowWrap: "anywhere" }}>
            {category.description !== null && category.description.trim().length > 0 ? category.description : "No description is recorded for this category."}
          </Typography>
        </Box>
        <Typography color="text.secondary" sx={{ mt: 3 }}>
          The API returns this category&rsquo;s content only. Hierarchy and active/inactive state are not part of the category resource, so they are not shown. Image upload and management are not available in this release.
        </Typography>
      </Paper>
    </Box>
  );
}

function DetailField({ label, value }: Readonly<{ label: string; value: string }>) {
  return (
    <Box sx={{ minWidth: 0 }}>
      <Typography variant="overline" color="text.secondary" component="p">{label}</Typography>
      <Typography sx={{ overflowWrap: "anywhere" }}>{value}</Typography>
    </Box>
  );
}
