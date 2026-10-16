"use client";

import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import { useActionState, useState } from "react";
import { createCategoryAction, updateCategoryAction } from "@/lib/catalog/category-actions";
import {
  CATEGORY_DESCRIPTION_MAX_LENGTH,
  CATEGORY_FORM_FIELDS,
  CATEGORY_IMAGE_MAX_LENGTH,
  CATEGORY_NAME_MAX_LENGTH,
  CATEGORY_SLUG_MAX_LENGTH,
  INITIAL_CATEGORY_FORM_STATE,
  type CategoryFormValues,
} from "@/lib/catalog/category-form";
import { ADMIN_CATEGORY_PATHS } from "@/lib/navigation/admin-route-paths";

interface CategoryFormProps {
  readonly mode: "create" | "edit";
  readonly values: CategoryFormValues;
  readonly categoryId?: string;
}

/**
 * Create/edit form for the approved category request. Laravel stays
 * authoritative: the form sends only contract fields, never reports success
 * before the API confirms it, and keeps every control in local state — React
 * resets uncontrolled fields after a form action, which would discard the
 * operator's edits on failure.
 */
export function CategoryForm({ mode, values, categoryId }: Readonly<CategoryFormProps>) {
  const [state, formAction, pending] = useActionState(mode === "create" ? createCategoryAction : updateCategoryAction, INITIAL_CATEGORY_FORM_STATE);
  const [draft, setDraft] = useState(values);
  const fieldErrors = state.fieldErrors ?? {};
  const setField = (patch: Partial<CategoryFormValues>) => setDraft((current) => ({ ...current, ...patch }));

  return (
    <Box component="form" action={formAction} sx={{ display: "flex", flexDirection: "column", gap: 3, maxWidth: "var(--content-width-lead)" }}>
      {state.status === "error" && state.formError ? <Alert severity="error">{state.formError}</Alert> : null}
      {mode === "create" ? null : <input type="hidden" name="category_id" value={categoryId ?? ""} />}
      <input type="hidden" name="original_values" value={JSON.stringify(values)} />

      <TextField
        name={CATEGORY_FORM_FIELDS.name}
        label="Name"
        required
        value={draft.name}
        onChange={(event) => setField({ name: event.target.value })}
        error={Boolean(fieldErrors[CATEGORY_FORM_FIELDS.name])}
        helperText={fieldErrors[CATEGORY_FORM_FIELDS.name] ?? `Up to ${CATEGORY_NAME_MAX_LENGTH} characters.`}
        slotProps={{ htmlInput: { maxLength: CATEGORY_NAME_MAX_LENGTH } }}
      />
      <TextField
        name={CATEGORY_FORM_FIELDS.slug}
        label="URL slug"
        required
        value={draft.slug}
        onChange={(event) => setField({ slug: event.target.value })}
        error={Boolean(fieldErrors[CATEGORY_FORM_FIELDS.slug])}
        helperText={fieldErrors[CATEGORY_FORM_FIELDS.slug] ?? `Lowercase letters, numbers and single hyphens, up to ${CATEGORY_SLUG_MAX_LENGTH} characters.`}
        slotProps={{ htmlInput: { maxLength: CATEGORY_SLUG_MAX_LENGTH } }}
      />
      <TextField
        name={CATEGORY_FORM_FIELDS.image}
        label="Image URL"
        value={draft.image}
        onChange={(event) => setField({ image: event.target.value })}
        error={Boolean(fieldErrors[CATEGORY_FORM_FIELDS.image])}
        helperText={fieldErrors[CATEGORY_FORM_FIELDS.image] ?? `Optional. A full http(s) address, up to ${CATEGORY_IMAGE_MAX_LENGTH} characters. Leaving it empty clears the stored image on save.`}
        slotProps={{ htmlInput: { maxLength: CATEGORY_IMAGE_MAX_LENGTH, autoComplete: "off" } }}
      />
      <TextField
        name={CATEGORY_FORM_FIELDS.description}
        label="Description"
        multiline
        minRows={4}
        value={draft.description}
        onChange={(event) => setField({ description: event.target.value })}
        error={Boolean(fieldErrors[CATEGORY_FORM_FIELDS.description])}
        helperText={fieldErrors[CATEGORY_FORM_FIELDS.description] ?? `Optional, up to ${CATEGORY_DESCRIPTION_MAX_LENGTH} characters. Leaving it empty clears the stored description on save.`}
        slotProps={{ htmlInput: { maxLength: CATEGORY_DESCRIPTION_MAX_LENGTH } }}
      />

      <Box sx={{ display: "flex", flexDirection: "row", gap: 2, flexWrap: "wrap" }}>
        <Button type="submit" variant="contained" disabled={pending} aria-busy={pending}>
          {submitLabel(pending, mode)}
        </Button>
        <Button href={cancelHref(mode, categoryId)}>Cancel</Button>
      </Box>
      <Typography color="text.secondary">
        Only the fields above are sent. Category hierarchy and active/inactive state are not part of the approved category request, so this form cannot change them.
      </Typography>
    </Box>
  );
}

function submitLabel(pending: boolean, mode: "create" | "edit"): string {
  if (pending) return "Saving…";
  return mode === "create" ? "Create category" : "Save changes";
}

function cancelHref(mode: "create" | "edit", categoryId: string | undefined): string {
  return mode === "edit" && categoryId ? ADMIN_CATEGORY_PATHS.detail(categoryId) : ADMIN_CATEGORY_PATHS.list;
}
