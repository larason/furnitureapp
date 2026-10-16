"use client";

import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Checkbox from "@mui/material/Checkbox";
import FormControlLabel from "@mui/material/FormControlLabel";
import MenuItem from "@mui/material/MenuItem";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import { useActionState, useState } from "react";
import type { CategoryOption } from "@/lib/catalog/category-option";
import { createProductAction, updateProductAction } from "@/lib/catalog/product-actions";
import {
  INITIAL_PRODUCT_FORM_STATE,
  PRODUCT_DESCRIPTION_MAX_LENGTH,
  PRODUCT_FORM_FIELDS,
  PRODUCT_NAME_MAX_LENGTH,
  PRODUCT_SLUG_MAX_LENGTH,
  type ProductFormValues,
} from "@/lib/catalog/product-form";
import { productTypeLabel } from "@/lib/catalog/product-presentation";
import { PRODUCT_TYPE_VALUES } from "@/lib/catalog/product-query";
import { ADMIN_PRODUCT_PATHS } from "@/lib/navigation/admin-route-paths";

interface ProductFormProps {
  readonly mode: "create" | "edit";
  readonly values: ProductFormValues;
  readonly categories: readonly CategoryOption[];
  readonly currentCategory: { readonly id: string; readonly name: string } | null;
  readonly categoriesUnavailable: boolean;
  readonly productId?: string;
}

/**
 * Create/edit form for the approved product request. Laravel stays authoritative:
 * the form sends only contract fields, never reports success before the API
 * confirms it, and keeps every control in local state — React resets uncontrolled
 * fields after a form action, which would discard the operator's edits on failure.
 */
export function ProductForm({ mode, values, categories, currentCategory, categoriesUnavailable, productId }: Readonly<ProductFormProps>) {
  const [state, formAction, pending] = useActionState(mode === "create" ? createProductAction : updateProductAction, INITIAL_PRODUCT_FORM_STATE);
  const [draft, setDraft] = useState(values);
  const fieldErrors = state.fieldErrors ?? {};
  const categoryOptions = withCurrentCategory(categories, currentCategory);
  const categoryBlocked = mode === "create" && categoryOptions.length === 0;
  const setField = (patch: Partial<ProductFormValues>) => setDraft((current) => ({ ...current, ...patch }));

  return (
    <Box component="form" action={formAction} sx={{ display: "flex", flexDirection: "column", gap: 3, maxWidth: "var(--content-width-lead)" }}>
      {state.status === "error" && state.formError ? <Alert severity="error">{state.formError}</Alert> : null}
      {categoryBlocked ? <Alert severity="warning">No category options are available, so a product cannot be created yet.</Alert> : null}
      {mode === "create" ? null : <input type="hidden" name="product_id" value={productId ?? ""} />}
      <input type="hidden" name="original_values" value={JSON.stringify(values)} />

      <TextField
        name={PRODUCT_FORM_FIELDS.name}
        label="Name"
        required
        value={draft.name}
        onChange={(event) => setField({ name: event.target.value })}
        error={Boolean(fieldErrors[PRODUCT_FORM_FIELDS.name])}
        helperText={fieldErrors[PRODUCT_FORM_FIELDS.name] ?? `Up to ${PRODUCT_NAME_MAX_LENGTH} characters.`}
        slotProps={{ htmlInput: { maxLength: PRODUCT_NAME_MAX_LENGTH } }}
      />
      <TextField
        name={PRODUCT_FORM_FIELDS.slug}
        label="URL slug"
        required
        value={draft.slug}
        onChange={(event) => setField({ slug: event.target.value })}
        error={Boolean(fieldErrors[PRODUCT_FORM_FIELDS.slug])}
        helperText={fieldErrors[PRODUCT_FORM_FIELDS.slug] ?? `Lowercase letters, numbers and single hyphens, up to ${PRODUCT_SLUG_MAX_LENGTH} characters.`}
        slotProps={{ htmlInput: { maxLength: PRODUCT_SLUG_MAX_LENGTH } }}
      />
      <TextField
        select
        name={PRODUCT_FORM_FIELDS.productType}
        label="Product type"
        required
        value={draft.productType}
        onChange={(event) => setField({ productType: event.target.value })}
        error={Boolean(fieldErrors[PRODUCT_FORM_FIELDS.productType])}
        helperText={fieldErrors[PRODUCT_FORM_FIELDS.productType] ?? "Made-to-order is a valid product type."}
      >
        <MenuItem value="" disabled>Select a product type</MenuItem>
        {PRODUCT_TYPE_VALUES.map((value) => <MenuItem key={value} value={value}>{productTypeLabel(value)}</MenuItem>)}
      </TextField>
      <TextField
        name={PRODUCT_FORM_FIELDS.priceAmount}
        label="Price (TZS)"
        required
        value={draft.priceAmount}
        onChange={(event) => setField({ priceAmount: event.target.value })}
        error={Boolean(fieldErrors[PRODUCT_FORM_FIELDS.priceAmount])}
        helperText={fieldErrors[PRODUCT_FORM_FIELDS.priceAmount] ?? "Amount in TZS, for example 1500.00."}
        slotProps={{ htmlInput: { inputMode: "decimal", autoComplete: "off" } }}
      />
      <TextField
        select
        name={PRODUCT_FORM_FIELDS.categoryId}
        label="Category"
        required
        value={draft.categoryId}
        onChange={(event) => setField({ categoryId: event.target.value })}
        error={Boolean(fieldErrors[PRODUCT_FORM_FIELDS.categoryId])}
        helperText={fieldErrors[PRODUCT_FORM_FIELDS.categoryId] ?? (categoriesUnavailable ? "Category options could not be loaded; the current selection is unchanged." : undefined)}
      >
        <MenuItem value="" disabled>Select a category</MenuItem>
        {categoryOptions.map((option) => <MenuItem key={option.id} value={option.id}>{option.name}</MenuItem>)}
      </TextField>
      <TextField
        name={PRODUCT_FORM_FIELDS.description}
        label="Description"
        multiline
        minRows={4}
        value={draft.description}
        onChange={(event) => setField({ description: event.target.value })}
        error={Boolean(fieldErrors[PRODUCT_FORM_FIELDS.description])}
        helperText={fieldErrors[PRODUCT_FORM_FIELDS.description] ?? `Up to ${PRODUCT_DESCRIPTION_MAX_LENGTH} characters.`}
        slotProps={{ htmlInput: { maxLength: PRODUCT_DESCRIPTION_MAX_LENGTH } }}
      />

      <Box sx={{ display: "flex", flexDirection: "column", gap: 1 }}>
        <FormControlLabel control={<Checkbox name={PRODUCT_FORM_FIELDS.isActive} checked={draft.isActive} onChange={(event) => setField({ isActive: event.target.checked })} />} label="Active" />
        <FormControlLabel control={<Checkbox name={PRODUCT_FORM_FIELDS.isPublished} checked={draft.isPublished} onChange={(event) => setField({ isPublished: event.target.checked })} />} label="Published" />
        <Typography variant="body2" color="text.secondary">Publication is validated by the backend; a request-only release may reject publishing in-stock products.</Typography>
      </Box>

      <Box sx={{ display: "flex", flexDirection: "row", gap: 2, flexWrap: "wrap" }}>
        <Button type="submit" variant="contained" disabled={pending || categoryBlocked} aria-busy={pending}>
          {submitLabel(pending, mode)}
        </Button>
        <Button href={cancelHref(mode, productId)}>Cancel</Button>
      </Box>
    </Box>
  );
}

function submitLabel(pending: boolean, mode: "create" | "edit"): string {
  if (pending) return "Saving…";
  return mode === "create" ? "Create product" : "Save changes";
}

function cancelHref(mode: "create" | "edit", productId: string | undefined): string {
  return mode === "edit" && productId ? ADMIN_PRODUCT_PATHS.detail(productId) : ADMIN_PRODUCT_PATHS.list;
}

function withCurrentCategory(categories: readonly CategoryOption[], current: { readonly id: string; readonly name: string } | null): readonly CategoryOption[] {
  if (current === null || categories.some((option) => option.id === current.id)) return categories;
  return [{ id: current.id, name: `${current.name} (current)`, slug: "" }, ...categories];
}
