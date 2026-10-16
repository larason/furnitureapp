"use client";

import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import TextField from "@mui/material/TextField";
import { PRODUCT_IMAGE_ALT_TEXT_MAX_LENGTH } from "@/lib/catalog/product-image-mutations";

interface ProductImageAltTextEditorProps {
  readonly imageOrdinal: number;
  readonly value: string;
  readonly error: string | null;
  readonly disabled: boolean;
  readonly onChange: (value: string) => void;
  readonly onSave: () => void;
  readonly onCancel: () => void;
}

/**
 * Alt-text editor for one image (CAT-016). The draft is owned by the gallery, so a
 * failed save keeps the operator's text and a successful one closes the editor
 * from the server-refreshed gallery. The field sends only `alt_text`; no
 * description is ever generated for the operator.
 */
export function ProductImageAltTextEditor({ imageOrdinal, value, error, disabled, onChange, onSave, onCancel }: Readonly<ProductImageAltTextEditorProps>) {
  const fieldId = `product-image-alt-text-${imageOrdinal}`;

  return (
    <Box
      component="form"
      onSubmit={(event) => {
        event.preventDefault();
        onSave();
      }}
      sx={{ display: "flex", flexDirection: "column", gap: 2 }}
    >
      <TextField
        id={fieldId}
        label={`Alt text for image ${imageOrdinal}`}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        disabled={disabled}
        error={error !== null}
        helperText={error ?? `${value.length} of ${PRODUCT_IMAGE_ALT_TEXT_MAX_LENGTH} characters used. Leaving it empty clears the alt text.`}
        multiline
        minRows={2}
        slotProps={{ htmlInput: { maxLength: PRODUCT_IMAGE_ALT_TEXT_MAX_LENGTH } }}
      />
      <Box sx={{ display: "flex", gap: 1 }}>
        <Button type="submit" size="small" variant="contained" disabled={disabled}>
          Save alt text
        </Button>
        <Button type="button" size="small" variant="outlined" disabled={disabled} onClick={onCancel}>
          Cancel
        </Button>
      </Box>
    </Box>
  );
}
