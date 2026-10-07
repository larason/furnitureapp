import Box from "@mui/material/Box";
import { ProductCard } from "@/components/catalog/product-card";
import type { ProductSummary } from "@/lib/catalog/types";

const productGridSx = {
  display: "grid",
  gridTemplateColumns: { xs: "minmax(0, 1fr)", sm: "repeat(2, minmax(0, 1fr))", md: "repeat(3, minmax(0, 1fr))" },
  gap: { xs: 7, sm: 5, md: 7 },
  p: 0,
  m: 0,
  listStyle: "none",
};

export function ProductGrid({ products, sizes }: Readonly<{ products: readonly ProductSummary[]; sizes: string }>) {
  return (
    <Box component="ul" sx={productGridSx}>
      {products.map((product) => (
        <Box component="li" key={product.id} sx={{ minWidth: 0 }}>
          <ProductCard product={product} sizes={sizes} />
        </Box>
      ))}
    </Box>
  );
}
