import type { ProductImage } from "./types";

export function selectPrimaryImage(images: readonly ProductImage[]): ProductImage | undefined {
  return images.find((image) => image.is_primary) ?? images[0];
}
