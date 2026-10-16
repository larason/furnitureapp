"use client";

import Box from "@mui/material/Box";
import Typography from "@mui/material/Typography";
import Image from "next/image";
import { Component, useState, type ReactNode } from "react";

interface ProductImagePreviewProps {
  readonly src: string;
  readonly alt: string;
  readonly sizes: string;
}

interface PreviewBoundaryState {
  readonly failed: boolean;
}

/**
 * Why a preview is not shown. The causes are kept distinct because the operator
 * response differs: an off-origin URL is a data problem, while a failed render is
 * usually a delivery or image-configuration problem.
 */
export type PreviewFallbackReason = "origin" | "load";

const FALLBACK_MESSAGES: Readonly<Record<PreviewFallbackReason, string>> = {
  origin: "Not previewed: this image is served from an origin the media preview is not configured for.",
  load: "Preview unavailable: the image is unreachable, or its media origin is not allowed by the server's image configuration. Restart the server after changing CATALOG_MEDIA_BASE_URL.",
};

/**
 * A single catalog preview. This is the only client boundary in the media
 * section. Two failure modes are contained so a remote image can never break the
 * product detail page: `onError` for a load failure, and a render boundary for a
 * `src` the image configuration rejects — which can happen when the runtime media
 * origin differs from the one the image allow-list was compiled with.
 */
export function ProductImagePreview({ src, alt, sizes }: Readonly<ProductImagePreviewProps>) {
  const [failed, setFailed] = useState(false);

  if (failed) return <ProductImageFallback reason="load" />;

  return (
    <PreviewBoundary>
      <Image src={src} alt={alt} fill sizes={sizes} onError={() => setFailed(true)} style={{ objectFit: "contain" }} />
    </PreviewBoundary>
  );
}

/** Neutral placeholder for a load failure, a rejected src or a URL outside the allow-list. */
export function ProductImageFallback({ reason }: Readonly<{ reason: PreviewFallbackReason }>) {
  return (
    <Box sx={{ position: "absolute", inset: 0, display: "flex", alignItems: "center", justifyContent: "center", p: 2, textAlign: "center" }}>
      <Typography variant="body2" color="text.secondary">
        {FALLBACK_MESSAGES[reason]}
      </Typography>
    </Box>
  );
}

class PreviewBoundary extends Component<Readonly<{ children: ReactNode }>, PreviewBoundaryState> {
  constructor(props: Readonly<{ children: ReactNode }>) {
    super(props);
    this.state = { failed: false };
  }

  static getDerivedStateFromError(): PreviewBoundaryState {
    return { failed: true };
  }

  render(): ReactNode {
    return this.state.failed ? <ProductImageFallback reason="load" /> : this.props.children;
  }
}
