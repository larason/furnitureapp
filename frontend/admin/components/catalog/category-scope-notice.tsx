import Alert from "@mui/material/Alert";

/**
 * States the exact scope of the category collection. The only listing endpoint
 * returns active top-level categories, so this notice prevents the list from
 * ever being mistaken for the complete category tree.
 */
export function CategoryScopeNotice() {
  return (
    <Alert severity="info" sx={{ maxWidth: "var(--content-width-lead)" }}>
      This screen lists the active top-level categories published to the storefront — the categories directly beneath the catalog&rsquo;s root category. The root category, inactive categories and deeper sub-categories are not returned by the category endpoint, so they cannot be viewed or managed here. Creating a category always adds a new active top-level category.
    </Alert>
  );
}
