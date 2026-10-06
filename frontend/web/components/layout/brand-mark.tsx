import Image from "next/image";
import { NavLink } from "./nav-link";

const LOGO_WIDTH = 3500;
const LOGO_HEIGHT = 1440;

export function BrandMark() {
  return (
    <NavLink
      href="/"
      aria-label="SL Furnitures home"
      sx={{
        display: "inline-flex",
        alignItems: "center",
        "& img": {
          height: { xs: "var(--space-7)", md: "var(--space-8)" },
          width: "auto",
        },
      }}
    >
      <Image
        src="/brandlogo.svg"
        alt="SL Furnitures"
        width={LOGO_WIDTH}
        height={LOGO_HEIGHT}
        priority
      />
    </NavLink>
  );
}
