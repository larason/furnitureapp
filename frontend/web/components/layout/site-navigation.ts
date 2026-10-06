export type ServiceLink = {
  label: string;
  href: string;
};

export const SITE_SERVICE_LINKS: readonly ServiceLink[] = [
  { label: "Made to Order", href: "/furniture-requests" },
  { label: "Furniture Enquiries", href: "/contact" },
];
