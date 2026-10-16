"use client";

import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import { useRef, useState } from "react";
import { ProductImageAltTextEditor } from "@/components/catalog/product-image-alt-text-editor";
import { ProductImageFallback, ProductImagePreview } from "@/components/catalog/product-image-preview";
import { EmptyState } from "@/components/states/empty-state";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { StatusIndicator } from "@/components/ui/status-indicator";
import { isRenderableMediaUrl, type CatalogMediaOrigin } from "@/lib/catalog/media-origin";
import {
  canMoveProductImage,
  movedProductImageIds,
  normalizeProductImageAltText,
  productImageAltTextError,
  type ProductImageGalleryActions,
  type ProductImageMoveDirection,
  type ProductImageMutationKind,
  type ProductImageMutationOutcome,
} from "@/lib/catalog/product-image-mutations";
import type { ProductImageDetail } from "@/lib/catalog/product-resource";

const PREVIEW_SIZES = "(min-width: 900px) 30vw, (min-width: 600px) 45vw, 90vw";
const UNEXPECTED_MESSAGE = "The change could not be completed. Reload the page and try again.";

interface ProductImageGalleryProps {
  readonly productId: string;
  readonly productName: string;
  readonly images: readonly ProductImageDetail[];
  readonly mediaOrigin: CatalogMediaOrigin | null;
  readonly actions: ProductImageGalleryActions;
}

interface AltTextEditorState {
  readonly imageId: string;
  readonly draft: string;
}

interface MutationFeedback {
  readonly kind: ProductImageMutationKind;
  readonly status: ProductImageMutationOutcome["status"];
  readonly message: string;
}

/**
 * Product media gallery. Images always render from the operational product
 * resource, and the four approved mutations (CAT-015–CAT-018) run through Server
 * Actions supplied by the page. Each of them revalidates that resource, so this
 * component never presents a local order, alt text or primary selection as
 * persisted before Laravel has confirmed it.
 *
 * One mutation runs at a time: every one of them can invalidate the image
 * collection the others were built from.
 */
export function ProductImageGallery({ productId, productName, images, mediaOrigin, actions }: Readonly<ProductImageGalleryProps>) {
  const [pendingKind, setPendingKind] = useState<ProductImageMutationKind | null>(null);
  const [feedback, setFeedback] = useState<MutationFeedback | null>(null);
  const [editor, setEditor] = useState<AltTextEditorState | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<ProductImageDetail | null>(null);
  /** Claimed immediately — before any await — so a second invocation cannot slip through before React re-renders. */
  const mutationInFlight = useRef(false);

  const pending = pendingKind !== null;
  const orderedIds = images.map((image) => image.id);
  /** A draft is dropped as soon as its image is gone from the refreshed resource. */
  const activeEditor = editor !== null && images.some((image) => image.id === editor.imageId) ? editor : null;
  /** The editor owns genuine validation failures so the operator's draft survives them. */
  const altTextError = feedback !== null && feedback.kind === "alt-text" && feedback.status === "error" ? feedback.message : null;
  /** Every other outcome is announced in the feedback region, including a stale gallery. */
  const visibleFeedback = feedback !== null && (feedback.kind !== "alt-text" || feedback.status !== "error") ? feedback : null;
  /** A target the refreshed resource no longer contains is dropped, exactly like a missing editor draft. */
  const activeDeleteTarget = deleteTarget !== null && orderedIds.includes(deleteTarget.id) ? deleteTarget : null;
  const deletePosition = activeDeleteTarget === null ? 0 : orderedIds.indexOf(activeDeleteTarget.id) + 1;

  /** Starts one mutation; reports whether it was accepted, so a refused caller can keep its own state intact. */
  function runMutation(kind: ProductImageMutationKind, operation: () => Promise<ProductImageMutationOutcome>, onSuccess?: () => void): boolean {
    if (mutationInFlight.current) return false;
    mutationInFlight.current = true;
    setPendingKind(kind);
    setFeedback(null);
    void (async () => {
      const outcome = await settle(operation);
      mutationInFlight.current = false;
      setPendingKind(null);
      setFeedback({ kind, status: outcome.status, message: outcome.message });
      if (outcome.status === "success") onSuccess?.();
    })();
    return true;
  }

  function clearAltTextFeedback(): void {
    setFeedback((current) => (current?.kind === "alt-text" ? null : current));
  }

  function move(image: ProductImageDetail, direction: ProductImageMoveDirection): void {
    const reordered = movedProductImageIds(orderedIds, image.id, direction);
    if (reordered === null) return;
    runMutation("reorder", () => actions.reorderImages(productId, reordered));
  }

  function makePrimary(image: ProductImageDetail): void {
    if (image.isPrimary) return;
    runMutation("primary", () => actions.selectPrimary(productId, image.id));
  }

  function openEditor(image: ProductImageDetail): void {
    clearAltTextFeedback();
    setEditor({ imageId: image.id, draft: image.altText ?? "" });
  }

  function cancelEditor(): void {
    clearAltTextFeedback();
    setEditor(null);
  }

  function saveAltText(imageId: string, draft: string): void {
    const invalid = productImageAltTextError(draft);
    if (invalid !== null) {
      setFeedback({ kind: "alt-text", status: "error", message: invalid });
      return;
    }
    runMutation("alt-text", () => actions.updateAltText(productId, imageId, normalizeProductImageAltText(draft)), () => setEditor(null));
  }

  function confirmDelete(): void {
    if (activeDeleteTarget === null) return;
    const target = activeDeleteTarget;
    // The dialog closes only once the delete is accepted, so a refused confirmation stays open and retryable.
    if (!runMutation("delete", () => actions.deleteImage(productId, target.id))) return;
    setDeleteTarget(null);
  }

  if (images.length === 0) {
    return (
      <EmptyState
        title="No images recorded"
        description={`Laravel returned no image records for ${productName}. An empty collection is not an API failure.`}
      />
    );
  }

  return (
    <Box sx={{ display: "flex", flexDirection: "column", gap: 3 }}>
      {mediaOrigin === null ? <MediaOriginNotice /> : null}
      <FeedbackRegion pending={pending} feedback={visibleFeedback} />
      {images.some((image) => image.isPrimary) ? null : <NoPrimaryNotice />}
      <Box component="ul" sx={{ m: 0, p: 0, listStyle: "none", display: "grid", gap: 3, gridTemplateColumns: { xs: "1fr", sm: "repeat(2, minmax(0, 1fr))", md: "repeat(3, minmax(0, 1fr))" } }}>
        {images.map((image, index) => {
          const itemEditor = activeEditor !== null && activeEditor.imageId === image.id ? activeEditor : null;
          return (
            <Box component="li" key={image.id}>
              <GalleryItem
                image={image}
                position={index + 1}
                total={images.length}
                productName={productName}
                mediaOrigin={mediaOrigin}
                disabled={pending}
                canMoveUp={canMoveProductImage(orderedIds, image.id, "up")}
                canMoveDown={canMoveProductImage(orderedIds, image.id, "down")}
                editor={itemEditor}
                editorError={altTextError}
                handlers={{
                  moveUp: () => move(image, "up"),
                  moveDown: () => move(image, "down"),
                  makePrimary: () => makePrimary(image),
                  editAltText: () => openEditor(image),
                  deleteImage: () => setDeleteTarget(image),
                  changeDraft: (value: string) => setEditor({ imageId: image.id, draft: value }),
                  saveAltText: () => saveAltText(image.id, itemEditor?.draft ?? ""),
                  cancelEdit: cancelEditor,
                }}
              />
            </Box>
          );
        })}
      </Box>
      <ConfirmDialog
        open={activeDeleteTarget !== null}
        title="Delete this image?"
        description={activeDeleteTarget === null ? "" : `Remove ${imageDescription(activeDeleteTarget, deletePosition, productName)} (image ${deletePosition} of ${images.length}) from the product gallery? Laravel deletes the image record and its stored object. This cannot be undone from this screen.`}
        confirmLabel="Delete image"
        onConfirm={confirmDelete}
        onCancel={() => setDeleteTarget(null)}
      />
    </Box>
  );
}

interface GalleryItemHandlers {
  readonly moveUp: () => void;
  readonly moveDown: () => void;
  readonly makePrimary: () => void;
  readonly editAltText: () => void;
  readonly deleteImage: () => void;
  readonly changeDraft: (value: string) => void;
  readonly saveAltText: () => void;
  readonly cancelEdit: () => void;
}

interface GalleryItemProps {
  readonly image: ProductImageDetail;
  readonly position: number;
  readonly total: number;
  readonly productName: string;
  readonly mediaOrigin: CatalogMediaOrigin | null;
  readonly disabled: boolean;
  readonly canMoveUp: boolean;
  readonly canMoveDown: boolean;
  readonly editor: AltTextEditorState | null;
  readonly editorError: string | null;
  readonly handlers: GalleryItemHandlers;
}

/**
 * One gallery entry. Gallery position and the stored sort order Laravel returns are
 * shown separately, because the operator changes the former while Laravel persists
 * the latter; neither is inferred from the other.
 */
function GalleryItem({ image, position, total, productName, mediaOrigin, disabled, canMoveUp, canMoveDown, editor, editorError, handlers }: Readonly<GalleryItemProps>) {
  const description = imageDescription(image, position, productName);

  return (
    <Paper variant="outlined" sx={{ p: 2, height: "100%", display: "flex", flexDirection: "column", gap: 2 }}>
      <Box sx={{ position: "relative", aspectRatio: "var(--media-product-card)", overflow: "hidden", bgcolor: "action.hover", borderRadius: "var(--radius-sm)" }}>
        {isRenderableMediaUrl(image.url, mediaOrigin) ? (
          <ProductImagePreview src={image.url} alt={description} sizes={PREVIEW_SIZES} />
        ) : (
          <ProductImageFallback reason="origin" />
        )}
      </Box>
      <Box sx={{ display: "flex", flexDirection: "column", gap: 1 }}>
        <Typography component="h3" variant="h4">{`Image ${position} of ${total}`}</Typography>
        <Box sx={{ display: "flex", flexWrap: "wrap", gap: 1, alignItems: "center" }}>
          {image.isPrimary ? <StatusIndicator status="info" label="Primary image" /> : null}
          <Typography variant="body2" color="text.secondary">
            Sort order: {image.sortOrder}
          </Typography>
        </Box>
        <Typography variant="body2" color="text.secondary">
          {image.altText === null || image.altText.trim().length === 0 ? "No alt text is recorded for this image." : image.altText}
        </Typography>
      </Box>
      {editor === null ? null : (
        <ProductImageAltTextEditor
          imageOrdinal={position}
          value={editor.draft}
          error={editorError}
          disabled={disabled}
          onChange={handlers.changeDraft}
          onSave={handlers.saveAltText}
          onCancel={handlers.cancelEdit}
        />
      )}
      <Box role="group" aria-label={`Controls for image ${position} of ${total}`} sx={{ display: "flex", flexWrap: "wrap", gap: 1 }}>
        <Button size="small" variant="outlined" disabled={disabled || !canMoveUp} onClick={handlers.moveUp}>
          Move up
        </Button>
        <Button size="small" variant="outlined" disabled={disabled || !canMoveDown} onClick={handlers.moveDown}>
          Move down
        </Button>
        {image.isPrimary ? null : (
          <Button size="small" variant="outlined" disabled={disabled} onClick={handlers.makePrimary}>
            Make primary
          </Button>
        )}
        {editor === null ? (
          <Button size="small" variant="outlined" disabled={disabled} onClick={handlers.editAltText}>
            Edit alt text
          </Button>
        ) : null}
        <Button size="small" variant="outlined" color="error" disabled={disabled} onClick={handlers.deleteImage}>
          Delete
        </Button>
      </Box>
    </Paper>
  );
}

/**
 * A Server Action only rejects on an unexpected transport or serialization
 * failure, because every API error is returned as an outcome. Either way the
 * gallery must not stay in a pending state.
 */
async function settle(operation: () => Promise<ProductImageMutationOutcome>): Promise<ProductImageMutationOutcome> {
  try {
    return await operation();
  } catch {
    return { status: "error", message: UNEXPECTED_MESSAGE };
  }
}

/** Approval or refusal of a completed mutation; pending work is announced separately. */
function FeedbackRegion({ pending, feedback }: Readonly<{ pending: boolean; feedback: MutationFeedback | null }>) {
  if (!pending && feedback === null) return null;

  return (
    <Box sx={{ display: "flex", flexDirection: "column", gap: 2 }}>
      {pending ? (
        <Typography role="status" variant="body2" color="text.secondary">
          Applying the media change…
        </Typography>
      ) : null}
      {feedback === null ? null : (
        <Alert severity={feedbackSeverity(feedback.status)} role={feedback.status === "success" ? "status" : "alert"}>
          {feedback.message}
        </Alert>
      )}
    </Box>
  );
}

function feedbackSeverity(status: ProductImageMutationOutcome["status"]): "success" | "warning" | "error" {
  if (status === "success") return "success";
  return status === "stale" ? "warning" : "error";
}

/** Laravel permits a product with no primary image and never promotes a replacement. */
function NoPrimaryNotice() {
  return (
    <Paper component="div" role="status" variant="outlined" sx={{ p: 2 }}>
      <Typography variant="body2" color="text.secondary">
        No image is selected as the primary image. A product is allowed to have no primary image, and deleting the primary image never promotes another one automatically.
      </Typography>
    </Paper>
  );
}

function MediaOriginNotice() {
  return (
    <Paper component="div" role="status" variant="outlined" sx={{ p: 2 }}>
      <Typography variant="body2" color="text.secondary">
        Image previews are disabled because CATALOG_MEDIA_BASE_URL is missing or invalid. Set it to the HTTPS origin Laravel builds image URLs from to enable previews; the stored records are unaffected.
      </Typography>
    </Paper>
  );
}

/** Uses the stored alt text where present, otherwise a neutral accessible label. */
function imageDescription(image: ProductImageDetail, position: number, productName: string): string {
  const altText = image.altText?.trim();
  return altText !== undefined && altText.length > 0 ? altText : `${productName} — product image ${position}`;
}
