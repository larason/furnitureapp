import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Typography from "@mui/material/Typography";
import type { Metadata } from "next";
import { InformationPage, DocumentSection } from "@/components/information/information-page";
import { NOINDEX_FOLLOW, createPageMetadata } from "@/lib/seo/metadata";

export const metadata: Metadata = createPageMetadata({ title: "About SL Furnitures", description: "Learn about the SL Furnitures catalog and made-to-order request service.", canonicalPath: "/about", robots: NOINDEX_FOLLOW });

export default function AboutPage() {
  return <InformationPage title="About SL Furnitures" introduction="Furniture for the way you live, with a calm place to explore pieces and share an idea for something made to order."><DocumentSection title="What we offer"><Typography>SL Furnitures presents a public furniture catalog and a separate made-to-order request experience. Visitors can explore the catalog, ask a general question, or describe furniture they would like to have made.</Typography></DocumentSection><DocumentSection title="Our approach"><Typography>We focus the website on clear product information, useful details, and a straightforward way to start a conversation about furniture for your space.</Typography></DocumentSection><Box sx={{ display: "flex", flexWrap: "wrap", gap: 3 }}><Button href="/products" variant="contained">Explore furniture</Button><Button href="/furniture-requests" variant="outlined">Request custom furniture</Button><Button href="/contact" variant="text">Contact us</Button></Box></InformationPage>;
}
