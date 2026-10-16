"use client";

import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import { useActionState } from "react";
import { useFormStatus } from "react-dom";
import { uploadProductImageAction } from "@/lib/catalog/product-image-actions";
import {
  INITIAL_PRODUCT_IMAGE_FORM_STATE,
  PRODUCT_IMAGE_ACCEPT_ATTRIBUTE,
  PRODUCT_IMAGE_ADVISORY_LABEL,
  PRODUCT_IMAGE_FIELD,
  PRODUCT_IMAGE_PRODUCT_FIELD,
} from "@/lib/catalog/product-image-form";

const FILE_INPUT_ID = "product-image-file";
const FILE_HINT_ID = "product-image-hint";

/**
 * Upload form for the CAT-009 endpoint. Submission runs through a Server Action,
 * so the Clerk token never reaches the browser. `useFormStatus` disables the
 * submit control while a request is in flight, which is the duplicate-submit guard.
 */
export function ProductImageUploader({ productId }: Readonly<{ productId: string }>) {
  const [state, action] = useActionState(uploadProductImageAction, INITIAL_PRODUCT_IMAGE_FORM_STATE);

  return (
    <Paper variant="outlined" sx={{ p: 3 }}>
      <Typography component="h3" variant="h4" sx={{ mb: 2 }}>
        Upload an image
      </Typography>
      <Box component="form" action={action} sx={{ display: "flex", flexDirection: "column", gap: 2, maxWidth: "var(--content-width-lead)" }}>
        <input type="hidden" name={PRODUCT_IMAGE_PRODUCT_FIELD} value={productId} />
        <Box sx={{ display: "flex", flexDirection: "column", gap: 1 }}>
          <Typography component="label" htmlFor={FILE_INPUT_ID} variant="subtitle2">
            Product image
          </Typography>
          <Box
            component="input"
            id={FILE_INPUT_ID}
            name={PRODUCT_IMAGE_FIELD}
            type="file"
            accept={PRODUCT_IMAGE_ACCEPT_ATTRIBUTE}
            required
            aria-describedby={FILE_HINT_ID}
            aria-invalid={state.fieldError ? true : undefined}
            sx={{ maxWidth: "100%", typography: "body2" }}
          />
          <Typography id={FILE_HINT_ID} variant="body2" color="text.secondary">
            {PRODUCT_IMAGE_ADVISORY_LABEL} The server re-validates every upload.
          </Typography>
        </Box>

        {state.fieldError ? <Alert severity="error">{state.fieldError}</Alert> : null}
        {state.formError ? <Alert severity="error">{state.formError}</Alert> : null}
        {state.status === "success" && state.message ? <Alert severity="success">{state.message}</Alert> : null}

        <Box sx={{ display: "flex" }}>
          <UploadButton />
        </Box>
      </Box>
    </Paper>
  );
}

function UploadButton() {
  const { pending } = useFormStatus();
  return (
    <Button type="submit" variant="contained" disabled={pending}>
      {pending ? "Uploading…" : "Upload image"}
    </Button>
  );
}
