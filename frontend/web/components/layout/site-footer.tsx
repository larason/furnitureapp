import Box from "@mui/material/Box";
import Divider from "@mui/material/Divider";
import Typography from "@mui/material/Typography";
import { CATEGORY_NAVIGATION_FIXTURE } from "./category-navigation.fixture";
import { BrandMark } from "./brand-mark";
import { ContentContainer } from "./content-container";
import { NavLink } from "./nav-link";
import { SITE_SERVICE_LINKS } from "./site-navigation";

const footerLinkSx = {
  display: "inline-flex",
  alignItems: "center",
  minHeight: 36,
  color: "text.secondary",
  typography: "body2",
};

type FooterGroupProps = {
  title: string;
  links: readonly { label: string; href: string; inactive?: boolean }[];
};

function FooterGroup({ title, links }: Readonly<FooterGroupProps>) {
  return (
    <Box component="nav" aria-label={title}>
      <Typography
        component="h2"
        variant="subtitle2"
         sx={{ mb: "var(--space-3)", color: "text.primary" }}
      >
        {title}
      </Typography>
      <Box component="ul" sx={{ m: 0, p: 0, listStyle: "none" }}>
        {links.map((link) => (
          <Box component="li" key={`${title}-${link.href}`}>
            <NavLink href={link.href} inactive={link.inactive} sx={footerLinkSx}>
              {link.label}
            </NavLink>
          </Box>
        ))}
      </Box>
    </Box>
  );
}

export function SiteFooter() {
  const year = new Date().getFullYear();
  const categoryLinks = CATEGORY_NAVIGATION_FIXTURE.map((category) => ({
    label: category.name,
    href: `/categories/${category.slug}`,
    inactive: true,
  }));

  return (
    <Box
      component="footer"
      sx={{
        backgroundColor: "var(--surface-editorial)",
        borderTop: "1px solid var(--border-subtle)",
        py: { xs: 6, md: 8 },
      }}
    >
      <ContentContainer>
        <Box
          sx={{
            display: "grid",
            gridTemplateColumns: { xs: "1fr", sm: "1fr 1fr", md: "1.4fr 1fr 1fr" },
            gap: { xs: 5, md: 6 },
          }}
        >
          <Box sx={{ maxWidth: "var(--content-width-lead)" }}>
            <Box sx={{ mb: 1 }}>
              <BrandMark />
            </Box>
            <Typography variant="body2" color="text.secondary">
              Furniture that makes you feel at home.
            </Typography>
          </Box>
          <FooterGroup title="Furniture" links={categoryLinks} />
          <FooterGroup title="Services" links={SITE_SERVICE_LINKS} />
        </Box>
        <Divider sx={{ my: { xs: 4, md: 6 } }} />
        <Typography variant="caption" color="text.secondary">
          © {year} SL Furnitures. All rights reserved.
        </Typography>
      </ContentContainer>
    </Box>
  );
}
