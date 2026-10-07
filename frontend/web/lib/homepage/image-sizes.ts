import tokens from "../../../design-system/design-tokens.json";

const { breakpoints, gutters, containerMax } = tokens.layers.layout;
const space = tokens.layers.primitive.space;

export const HERO_IMAGE_SIZES = [
  `(min-width: ${containerMax}) calc((${containerMax} - ${gutters.desktop} * 2 - ${space[7]}) * 2 / 3)`,
  `(min-width: ${breakpoints.desktop}) calc((100vw - ${gutters.desktop} * 2 - ${space[7]}) * 2 / 3)`,
  `(min-width: ${breakpoints.tablet}) calc((100vw - ${gutters.tablet} * 2 - ${space[7]}) * 2 / 3)`,
  `(min-width: ${breakpoints.phone}) and (max-width: ${breakpoints.tablet}) calc(100vw - ${gutters.tablet} * 2)`,
  `calc(100vw - ${gutters.phone} * 2)`,
].join(", ");

export const CATEGORY_IMAGE_SIZES = [
  `(min-width: ${containerMax}) calc((${containerMax} - ${gutters.desktop} * 2 - ${space[5]} * 3) / 4)`,
  `(min-width: ${breakpoints.desktop}) calc((100vw - ${gutters.desktop} * 2 - ${space[5]} * 3) / 4)`,
  `(min-width: ${breakpoints.tablet}) calc((100vw - ${gutters.tablet} * 2 - ${space[5]} * 3) / 4)`,
  `(min-width: ${breakpoints.phone}) and (max-width: ${breakpoints.tablet}) calc((100vw - ${gutters.tablet} * 2 - ${space[5]}) / 2)`,
  `calc((100vw - ${gutters.phone} * 2 - ${space[3]}) / 2)`,
].join(", ");

const PRODUCT_GRID_IMAGE_SIZES = [
  `(min-width: ${containerMax}) calc((${containerMax} - ${gutters.desktop} * 2 - ${space[6]} * 2) / 3)`,
  `(min-width: ${breakpoints.desktop}) calc((100vw - ${gutters.desktop} * 2 - ${space[6]} * 2) / 3)`,
  `(min-width: ${breakpoints.tablet}) calc((100vw - ${gutters.tablet} * 2 - ${space[6]} * 2) / 3)`,
  `(min-width: ${breakpoints.phone}) and (max-width: ${breakpoints.tablet}) calc((100vw - ${gutters.tablet} * 2 - ${space[4]}) / 2)`,
  `calc(100vw - ${gutters.phone} * 2)`,
].join(", ");

export const PRODUCT_IMAGE_SIZES = PRODUCT_GRID_IMAGE_SIZES;

export const CATEGORY_PRODUCT_IMAGE_SIZES = PRODUCT_GRID_IMAGE_SIZES;

export const PRODUCT_DETAIL_IMAGE_SIZES = [
  `(min-width: ${containerMax}) calc((${containerMax} - ${gutters.desktop} * 2 - ${space[7]}) / 2)`,
  `(min-width: ${breakpoints.desktop}) calc((100vw - ${gutters.desktop} * 2 - ${space[7]}) / 2)`,
  `(min-width: ${breakpoints.tablet}) calc((100vw - ${gutters.tablet} * 2 - ${space[7]}) / 2)`,
  `(min-width: ${breakpoints.phone}) calc(100vw - ${gutters.tablet} * 2)`,
  `calc(100vw - ${gutters.phone} * 2)`,
].join(", ");

export const EDITORIAL_IMAGE_SIZES = HERO_IMAGE_SIZES;
