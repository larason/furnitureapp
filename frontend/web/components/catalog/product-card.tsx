import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import Image from "next/image";
import { NavLink } from "@/components/layout/nav-link";
import { formatMoney } from "@/lib/catalog/format-money";
import type { ProductCardData } from "@/lib/catalog/types";

export type ProductCardProps = Readonly<{
  product: ProductCardData;
  sizes: string;
  href?: string;
}>;

export function ProductCard({ product, sizes, href }: ProductCardProps) {
  const madeToOrder = product.product_type === "MADE_TO_ORDER";
  const status = madeToOrder ? "Made to order" : product.availability === "available" ? "In stock" : "Currently unavailable";

  return (
    <Box component="article" sx={{ minWidth: 0 }}>
      <Box sx={{ position: "relative", aspectRatio: "var(--media-product-card)", bgcolor: "background.paper" }}>
        {product.primary_image ? (
          <Image
            src={product.primary_image.url}
            alt={product.primary_image.alt_text || product.name}
            fill
            sizes={sizes}
            style={{ objectFit: "contain" }}
          />
        ) : (
          <Box sx={{ height: "100%", display: "grid", placeItems: "center", p: 4 }}>
            <Typography variant="body2" color="text.secondary">Photograph to follow</Typography>
          </Box>
        )}
      </Box>
      <Box sx={{ pt: 4, display: "grid", gap: 2 }}>
        <Typography component="h3" variant="subtitle1" sx={{ overflowWrap: "anywhere" }}>
          {href ? <NavLink href={href}>{product.name}</NavLink> : product.name}
        </Typography>
        <Typography variant="body2">{madeToOrder ? "From " : ""}{formatMoney(product.price)}</Typography>
        <Typography variant="caption" color="text.secondary">{status}</Typography>
      </Box>
    </Box>
  );
}
