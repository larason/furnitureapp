"use client";

import { useAuth } from "@clerk/nextjs";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Divider from "@mui/material/Divider";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import Image from "next/image";
import { useEffect, useRef, useState, type Dispatch, type ReactNode, type SetStateAction, type SubmitEvent } from "react";
import { ApiConfigurationError, ApiError, ApiTransportError, createApiClient } from "@/lib/api/client";
import { MAX_ATTACHMENT_BYTES, serializeFurnitureRequest, validateFurnitureRequest, type FurnitureRequestFieldErrors, type FurnitureRequestFormValues } from "@/lib/furniture-requests/submission";

const browserApiRequest = createApiClient({ baseUrl: process.env.NEXT_PUBLIC_API_BASE_URL });
const ATTACHMENT_TIMEOUT_MS = 120_000;

const INITIAL_VALUES: FurnitureRequestFormValues = {
  name: "",
  phone: "",
  email: "",
  quantity: "",
  length: "",
  width: "",
  height: "",
  material: "",
  color: "",
  notes: "",
  attachment: null,
};

type FieldName = "name" | "phone" | "email" | "quantity" | "length" | "width" | "height" | "material" | "color" | "notes";

export type FurnitureRequestProductContext = Readonly<{
  id: string;
  name: string;
  slug: string;
  image: Readonly<{ url: string; alt: string }> | null;
}>;

type RequestResponse = Readonly<{ reference?: string; request_reference?: string; request_status?: string }>;

export function FurnitureRequestForm({ product }: Readonly<{ product?: FurnitureRequestProductContext }>) {
  const { isLoaded, isSignedIn, getToken } = useAuth();
  const [values, setValues] = useState<FurnitureRequestFormValues>(INITIAL_VALUES);
  const [fieldErrors, setFieldErrors] = useState<FurnitureRequestFieldErrors>({});
  const [submissionMessage, setSubmissionMessage] = useState<string>();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isSubmitted, setIsSubmitted] = useState(false);
  const [useProductContext, setUseProductContext] = useState(Boolean(product));
  const [productUnavailable, setProductUnavailable] = useState(false);
  const acknowledgementRef = useRef<HTMLDivElement>(null);
  const attachmentInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (isSubmitted) acknowledgementRef.current?.focus();
  }, [isSubmitted]);

  const linkedProductId = useProductContext ? product?.id : undefined;

  async function handleSubmit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    if (isSubmitting) return;

    const errors = validateFurnitureRequest(values);
    setFieldErrors(errors);
    setSubmissionMessage(undefined);
    if (Object.keys(errors).length) return;

    setIsSubmitting(true);
    try {
      const headers = new Headers();
      if (isLoaded && isSignedIn) {
        const token = await getToken();
        if (!token) {
          setSubmissionMessage("Your signed-in session is not ready. Please sign in again before submitting, or sign out to submit as a visitor.");
          return;
        }
        headers.set("Authorization", `Bearer ${token}`);
      }

      const response = await browserApiRequest<RequestResponse>({
        path: "/requests",
        method: "POST",
        body: serializeFurnitureRequest(values, linkedProductId),
        headers,
        cache: "no-store",
        timeoutMs: values.attachment ? ATTACHMENT_TIMEOUT_MS : undefined,
      });
      setSubmissionMessage(response?.data.reference ?? response?.data.request_reference);
      setIsSubmitted(true);
    } catch (error) {
      handleSubmissionError(error, setFieldErrors, setSubmissionMessage, () => setProductUnavailable(true));
    } finally {
      setIsSubmitting(false);
    }
  }

  function updateField(field: FieldName, value: string) {
    setValues((current) => ({ ...current, [field]: value }));
    setFieldErrors((current) => ({ ...current, [field]: undefined, contact: field === "phone" || field === "email" ? undefined : current.contact }));
  }

  function updateAttachment(attachment: File | null) {
    setValues((current) => ({ ...current, attachment }));
    setFieldErrors((current) => ({ ...current, attachment: undefined }));
  }

  function removeAttachment() {
    if (attachmentInputRef.current) attachmentInputRef.current.value = "";
    updateAttachment(null);
  }

  if (isSubmitted) {
    return (
      <Stack ref={acknowledgementRef} tabIndex={-1} spacing={4} aria-live="polite" sx={{ maxWidth: "var(--content-width-form)", outline: "none" }}>
        <Typography component="h2" variant="h3">Your request has been received.</Typography>
        <Typography>Our team will review your details and contact you. This request is not an order, quotation, payment, or delivery confirmation.</Typography>
        {submissionMessage ? <Typography variant="body2" color="text.secondary">Reference: {submissionMessage}</Typography> : null}
      </Stack>
    );
  }

  return (
    <Box component="form" method="post" onSubmit={handleSubmit} noValidate sx={{ maxWidth: "var(--content-width-form)" }}>
      <Stack spacing={6}>
        {product ? <ProductContext product={product} active={useProductContext} onRemove={() => setUseProductContext(false)} onRestore={() => { setUseProductContext(true); setProductUnavailable(false); setSubmissionMessage(undefined); }} /> : null}
        {submissionMessage ? <Alert severity="error" role="alert">{submissionMessage}</Alert> : null}
        {productUnavailable && useProductContext ? <Button type="button" variant="outlined" onClick={() => { setUseProductContext(false); setProductUnavailable(false); setSubmissionMessage(undefined); }} sx={{ alignSelf: "flex-start" }}>Continue as a custom request</Button> : null}
        <FormGroup title="Furniture details" description="Tell us what you would like made. Fields marked optional can be left blank.">
          <TextField label="Quantity (optional)" value={values.quantity} onChange={(event) => updateField("quantity", event.target.value)} error={Boolean(fieldErrors.quantity)} helperText={fieldErrors.quantity ?? "Leave blank if you would like to discuss quantity."} inputMode="numeric" slotProps={{ htmlInput: { name: "quantity", min: 1, max: 100, step: 1 } }} />
          <TextField label="Material preference (optional)" value={values.material} onChange={(event) => updateField("material", event.target.value)} error={Boolean(fieldErrors.material)} helperText={fieldErrors.material} slotProps={{ htmlInput: { name: "material", maxLength: 500 } }} />
          <TextField label="Colour preference (optional)" value={values.color} onChange={(event) => updateField("color", event.target.value)} error={Boolean(fieldErrors.color)} helperText={fieldErrors.color} slotProps={{ htmlInput: { name: "color", maxLength: 200 } }} />
          <TextField label="Tell us about your idea (optional)" value={values.notes} onChange={(event) => updateField("notes", event.target.value)} error={Boolean(fieldErrors.notes)} helperText={fieldErrors.notes ?? "Include room details, style, finish, or anything else that matters."} multiline minRows={5} slotProps={{ htmlInput: { name: "notes", maxLength: 5000 } }} />
        </FormGroup>
        <FormGroup title="Measurements & preferences" description="Measurements are optional. If supplied, use centimetres.">
          <Box sx={{ display: "grid", gridTemplateColumns: { xs: "1fr", sm: "repeat(3, minmax(0, 1fr))" }, gap: 3 }}>
            <TextField label="Length (cm)" value={values.length} onChange={(event) => updateField("length", event.target.value)} error={Boolean(fieldErrors.length)} helperText={fieldErrors.length} inputMode="decimal" slotProps={{ htmlInput: { name: "length" } }} />
            <TextField label="Width (cm)" value={values.width} onChange={(event) => updateField("width", event.target.value)} error={Boolean(fieldErrors.width)} helperText={fieldErrors.width} inputMode="decimal" slotProps={{ htmlInput: { name: "width" } }} />
            <TextField label="Height (cm)" value={values.height} onChange={(event) => updateField("height", event.target.value)} error={Boolean(fieldErrors.height)} helperText={fieldErrors.height} inputMode="decimal" slotProps={{ htmlInput: { name: "height" } }} />
          </Box>
        </FormGroup>
        <FormGroup title="Reference attachment" description="Optional: one JPG, PNG, WebP, or PDF file, up to 5 MiB. Attachments are private.">
          <Stack direction="row" spacing={2} useFlexGap sx={{ alignItems: "center", flexWrap: "wrap" }}>
            <Button component="label" variant="outlined" disabled={isSubmitting}>Choose a file<input ref={attachmentInputRef} hidden name="attachment" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" onChange={(event) => updateAttachment(event.target.files?.[0] ?? null)} /></Button>
            {values.attachment ? <Button type="button" variant="text" onClick={removeAttachment} disabled={isSubmitting}>Remove file</Button> : null}
          </Stack>
          {values.attachment ? <Typography variant="body2" aria-live="polite">{attachmentLabel(values.attachment)}</Typography> : null}
          {fieldErrors.attachment ? <Typography role="alert" variant="body2" color="error">{fieldErrors.attachment}</Typography> : null}
        </FormGroup>
        <FormGroup title="Contact details" description="Your name and at least one way to reach you are required.">
          <TextField required label="Your name" value={values.name} onChange={(event) => updateField("name", event.target.value)} error={Boolean(fieldErrors.name)} helperText={fieldErrors.name} autoComplete="name" slotProps={{ htmlInput: { name: "name", maxLength: 120 } }} />
          <TextField label="Phone number" value={values.phone} onChange={(event) => updateField("phone", event.target.value)} error={Boolean(fieldErrors.contact)} helperText={fieldErrors.contact ?? "Provide a phone number, email address, or both."} autoComplete="tel" slotProps={{ htmlInput: { name: "phone", maxLength: 30 } }} />
          <TextField label="Email address" type="email" value={values.email} onChange={(event) => updateField("email", event.target.value)} error={Boolean(fieldErrors.contact)} helperText={fieldErrors.contact} autoComplete="email" slotProps={{ htmlInput: { name: "email", maxLength: 255 } }} />
        </FormGroup>
        <Stack spacing={2}>
          <Button type="submit" variant="contained" disabled={isSubmitting} sx={{ alignSelf: "flex-start", minHeight: 44 }}>
            {isSubmitting ? "Sending request..." : isLoaded ? "Submit furniture request" : "Submit as visitor"}
          </Button>
          {!isLoaded ? <Typography role="status" variant="body2" color="text.secondary">Sign-in status is still loading. You can submit as a visitor, or wait for your customer session to be ready.</Typography> : null}
          <Typography variant="body2" color="text.secondary">Please do not submit again while your request is sending. We will only confirm success after the request is received.</Typography>
        </Stack>
      </Stack>
    </Box>
  );
}

function FormGroup({ title, description, children }: Readonly<{ title: string; description: string; children: ReactNode }>) {
  return <Stack spacing={3}><Divider /><Stack spacing={1}><Typography component="h2" variant="h5">{title}</Typography><Typography variant="body2" color="text.secondary">{description}</Typography></Stack><Stack spacing={3}>{children}</Stack></Stack>;
}

function ProductContext({ product, active, onRemove, onRestore }: Readonly<{ product: FurnitureRequestProductContext; active: boolean; onRemove: () => void; onRestore: () => void }>) {
  return <Stack spacing={2} sx={{ p: 4, border: "1px solid var(--border-default)", borderRadius: "var(--radius-sm)", backgroundColor: "var(--surface-paper)" }}><Box sx={{ display: "grid", gridTemplateColumns: product.image ? "var(--space-10) minmax(0, 1fr)" : "1fr", gap: 3, alignItems: "center" }}>{product.image ? <Box sx={{ position: "relative", aspectRatio: "var(--media-product-card)" }}><Image src={product.image.url} alt={product.image.alt} fill sizes="80px" style={{ objectFit: "contain" }} /></Box> : null}<Typography variant="subtitle2">Requesting: {product.name}</Typography></Box>{active ? <Typography variant="body2" color="text.secondary">Your request will be linked to this made-to-order piece.</Typography> : <><Typography variant="body2" color="text.secondary">You chose to submit this as a custom request instead.</Typography><Button type="button" variant="text" onClick={onRestore} sx={{ alignSelf: "flex-start" }}>Link this piece again</Button></>}{active ? <Button type="button" variant="text" onClick={onRemove} sx={{ alignSelf: "flex-start" }}>Submit as a custom request instead</Button> : null}</Stack>;
}

function attachmentLabel(attachment: Blob): string {
  const name = "name" in attachment && typeof attachment.name === "string" ? attachment.name : "Selected file";
  return `${name} (${Math.ceil(attachment.size / 1024)} KiB of ${MAX_ATTACHMENT_BYTES / 1024 / 1024} MiB maximum)`;
}

function handleSubmissionError(error: unknown, setFieldErrors: Dispatch<SetStateAction<FurnitureRequestFieldErrors>>, setSubmissionMessage: Dispatch<SetStateAction<string | undefined>>, markProductUnavailable: () => void): void {
  if (error instanceof ApiError && error.status === 422) {
    if (hasProductContextError(error)) {
      setFieldErrors({});
      setSubmissionMessage("This product is no longer available for requests. You can continue as a custom furniture request instead.");
      markProductUnavailable();
      return;
    }
    setFieldErrors(apiErrorsToFields(error));
    setSubmissionMessage("Please correct the highlighted details and try again.");
    return;
  }
  if (error instanceof ApiError && error.status === 409) {
    setSubmissionMessage("This product is no longer available for requests. You can continue as a custom furniture request instead.");
    markProductUnavailable();
    return;
  }
  if (error instanceof ApiError && error.status === 401) setSubmissionMessage("Your signed-in session could not be confirmed. Please sign in again before submitting.");
  else if (error instanceof ApiError && error.status === 403) setSubmissionMessage("This account cannot submit a furniture request. Please use a customer account or submit as a visitor.");
  else if (error instanceof ApiError && error.status === 404) {
    setSubmissionMessage("The selected furniture is no longer available. You can continue as a custom furniture request instead.");
    markProductUnavailable();
  }
  else if (error instanceof ApiError && error.status === 413) setSubmissionMessage("The attachment is too large. Choose a file no larger than 5 MiB.");
  else if (error instanceof ApiError && error.status === 429) setSubmissionMessage(error.retryAfterSeconds ? `Please wait ${error.retryAfterSeconds} seconds before submitting another request.` : "Too many requests were sent. Please wait before trying again.");
  else if (error instanceof ApiError) setSubmissionMessage("We could not submit your request right now. Please try again later.");
  else if (error instanceof ApiConfigurationError) setSubmissionMessage("Furniture requests are not configured for this environment.");
  else if (error instanceof ApiTransportError) setSubmissionMessage("We could not confirm whether your request was received. Please do not submit again automatically; contact us before retrying.");
  else setSubmissionMessage("We could not submit your request right now. Please try again later.");
}

function hasProductContextError(error: ApiError): boolean {
  return error.errors.some((item) => item.field === "product_id");
}

function apiErrorsToFields(error: ApiError): FurnitureRequestFieldErrors {
  const errors: FurnitureRequestFieldErrors = {};
  for (const item of error.errors) {
    const field = item.field?.replace("dimensions.", "") ?? "";
    if (field === "phone" || field === "email") errors.contact = item.message;
    else if (field in INITIAL_VALUES || field === "attachment") errors[field as keyof FurnitureRequestFieldErrors] = item.message;
  }
  return errors;
}
