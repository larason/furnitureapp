"use client";

import { useAuth } from "@clerk/nextjs";
import Alert from "@mui/material/Alert";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Divider from "@mui/material/Divider";
import FormControl from "@mui/material/FormControl";
import InputLabel from "@mui/material/InputLabel";
import MenuItem from "@mui/material/MenuItem";
import Select from "@mui/material/Select";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import { useEffect, useRef, useState, type Dispatch, type ReactNode, type SetStateAction, type SubmitEvent } from "react";
import { ApiConfigurationError, ApiError, ApiTransportError, createApiClient } from "@/lib/api/client";
import { MAX_ENQUIRY_ATTACHMENT_BYTES, serializeEnquiry, validateEnquiry, type EnquiryFieldErrors, type EnquiryFormValues } from "@/lib/enquiries/submission";

const browserApiRequest = createApiClient({ baseUrl: process.env.NEXT_PUBLIC_API_BASE_URL });
const ATTACHMENT_TIMEOUT_MS = 120_000;
const INITIAL_VALUES: EnquiryFormValues = { name: "", phone: "", email: "", subject: "", message: "", category: "", attachment: null };

type FieldName = Exclude<keyof EnquiryFormValues, "attachment">;
type EnquiryResponse = Readonly<{ enquiry_status?: string }>;

export function EnquiryForm() {
  const { isLoaded, isSignedIn, getToken } = useAuth();
  const [values, setValues] = useState<EnquiryFormValues>(INITIAL_VALUES);
  const [fieldErrors, setFieldErrors] = useState<EnquiryFieldErrors>({});
  const [submissionMessage, setSubmissionMessage] = useState<string>();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isSubmitted, setIsSubmitted] = useState(false);
  const acknowledgementRef = useRef<HTMLDivElement>(null);
  const attachmentInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (isSubmitted) acknowledgementRef.current?.focus();
  }, [isSubmitted]);

  async function handleSubmit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    if (isSubmitting) return;

    const errors = validateEnquiry(values);
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

      const response = await browserApiRequest<EnquiryResponse>({
        path: "/enquiries",
        method: "POST",
        body: serializeEnquiry(values),
        headers,
        cache: "no-store",
        timeoutMs: values.attachment ? ATTACHMENT_TIMEOUT_MS : undefined,
      });
      setSubmissionMessage(response?.data.enquiry_status);
      setIsSubmitted(true);
    } catch (error) {
      handleSubmissionError(error, setFieldErrors, setSubmissionMessage);
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
    return <Stack ref={acknowledgementRef} tabIndex={-1} spacing={4} aria-live="polite" sx={{ maxWidth: "var(--content-width-form)", outline: "none" }}><Typography component="h2" variant="h3">Your enquiry has been received.</Typography><Typography>Thank you for contacting SL Furnitures. We will review your message and use the contact details you provided.</Typography>{submissionMessage ? <Typography variant="body2" color="text.secondary">Status: {submissionMessage}</Typography> : null}</Stack>;
  }

  return (
    <Box component="form" method="post" onSubmit={handleSubmit} noValidate sx={{ maxWidth: "var(--content-width-form)" }}>
      <Stack spacing={6}>
        {submissionMessage ? <Alert severity="error" role="alert">{submissionMessage}</Alert> : null}
        <FormGroup title="Your enquiry" description="Tell us how we can help. Fields marked optional can be left blank.">
          <TextField required label="Subject" value={values.subject} onChange={(event) => updateField("subject", event.target.value)} error={Boolean(fieldErrors.subject)} helperText={fieldErrors.subject ?? "Use 5 to 200 characters."} slotProps={{ htmlInput: { name: "subject", minLength: 5, maxLength: 200 } }} />
          <TextField required label="Message" value={values.message} onChange={(event) => updateField("message", event.target.value)} error={Boolean(fieldErrors.message)} helperText={fieldErrors.message ?? "Use 10 to 5,000 characters."} multiline minRows={6} slotProps={{ htmlInput: { name: "message", minLength: 10, maxLength: 5000 } }} />
          <FormControl fullWidth error={Boolean(fieldErrors.category)}><InputLabel id="enquiry-category-label">Category (optional)</InputLabel><Select labelId="enquiry-category-label" label="Category (optional)" name="category" value={values.category} onChange={(event) => updateField("category", String(event.target.value))}><MenuItem value="">General enquiry</MenuItem><MenuItem value="GENERAL">General</MenuItem><MenuItem value="PRODUCT">Product</MenuItem><MenuItem value="DELIVERY">Delivery</MenuItem><MenuItem value="OTHER">Other</MenuItem></Select>{fieldErrors.category ? <Typography role="alert" variant="body2" color="error">{fieldErrors.category}</Typography> : null}</FormControl>
        </FormGroup>
        <FormGroup title="Reference attachment" description="Optional: one JPG, PNG, WebP, or PDF file, up to 5 MiB. Attachments are private.">
          <Stack direction="row" spacing={2} useFlexGap sx={{ alignItems: "center", flexWrap: "wrap" }}><Button type="button" variant="outlined" onClick={() => attachmentInputRef.current?.click()} disabled={isSubmitting}>Choose a file</Button><input ref={attachmentInputRef} hidden id="enquiry-attachment" aria-label="Choose a reference attachment" name="attachment" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" onChange={(event) => updateAttachment(event.target.files?.[0] ?? null)} />{values.attachment ? <Button type="button" variant="text" onClick={removeAttachment} disabled={isSubmitting}>Remove file</Button> : null}</Stack>
          {values.attachment ? <Typography variant="body2" aria-live="polite">{attachmentLabel(values.attachment)}</Typography> : null}
          {fieldErrors.attachment ? <Typography role="alert" variant="body2" color="error">{fieldErrors.attachment}</Typography> : null}
        </FormGroup>
        <FormGroup title="Contact details" description="Your name and at least one way to reach you are required.">
          <TextField required label="Your name" value={values.name} onChange={(event) => updateField("name", event.target.value)} error={Boolean(fieldErrors.name)} helperText={fieldErrors.name} autoComplete="name" slotProps={{ htmlInput: { name: "name", maxLength: 120 } }} />
          <TextField label="Phone number" value={values.phone} onChange={(event) => updateField("phone", event.target.value)} error={Boolean(fieldErrors.contact)} helperText={fieldErrors.contact ?? "Provide a phone number, email address, or both."} autoComplete="tel" slotProps={{ htmlInput: { name: "phone", maxLength: 30 } }} />
          <TextField label="Email address" type="email" value={values.email} onChange={(event) => updateField("email", event.target.value)} error={Boolean(fieldErrors.contact)} helperText={fieldErrors.contact} autoComplete="email" slotProps={{ htmlInput: { name: "email", maxLength: 255 } }} />
        </FormGroup>
        <Stack spacing={2}><Button type="submit" variant="contained" disabled={isSubmitting} sx={{ alignSelf: "flex-start", minHeight: 44 }}>{isSubmitting ? "Sending enquiry..." : isLoaded ? "Send enquiry" : "Send as visitor"}</Button>{!isLoaded ? <Typography role="status" variant="body2" color="text.secondary">Sign-in status is still loading. You can submit as a visitor, or wait for your customer session to be ready.</Typography> : null}<Typography variant="body2" color="text.secondary">Please do not submit again while your enquiry is sending. We will only confirm success after it is received.</Typography></Stack>
      </Stack>
    </Box>
  );
}

function FormGroup({ title, description, children }: Readonly<{ title: string; description: string; children: ReactNode }>) {
  return <Stack spacing={3}><Divider /><Stack spacing={1}><Typography component="h2" variant="h5">{title}</Typography><Typography variant="body2" color="text.secondary">{description}</Typography></Stack><Stack spacing={3}>{children}</Stack></Stack>;
}

function attachmentLabel(attachment: Blob): string {
  const name = "name" in attachment && typeof attachment.name === "string" ? attachment.name : "Selected file";
  return `${name} (${Math.ceil(attachment.size / 1024)} KiB of ${MAX_ENQUIRY_ATTACHMENT_BYTES / 1024 / 1024} MiB maximum)`;
}

function handleSubmissionError(error: unknown, setFieldErrors: Dispatch<SetStateAction<EnquiryFieldErrors>>, setSubmissionMessage: Dispatch<SetStateAction<string | undefined>>): void {
  if (error instanceof ApiError && error.status === 422) {
    setFieldErrors(apiErrorsToFields(error));
    setSubmissionMessage("Please correct the highlighted details and try again.");
  } else if (error instanceof ApiError && error.status === 401) setSubmissionMessage("Your signed-in session could not be confirmed. Please sign in again before submitting.");
  else if (error instanceof ApiError && error.status === 403) setSubmissionMessage("This account cannot submit an enquiry. Please use a customer account or submit as a visitor.");
  else if (error instanceof ApiError && error.status === 413) setSubmissionMessage("The attachment is too large. Choose a file no larger than 5 MiB.");
  else if (error instanceof ApiError && error.status === 429) setSubmissionMessage(error.retryAfterSeconds ? `Please wait ${error.retryAfterSeconds} seconds before submitting another enquiry.` : "Too many enquiries were sent. Please wait before trying again.");
  else if (error instanceof ApiConfigurationError) setSubmissionMessage("Enquiries are not configured for this environment.");
  else if (error instanceof ApiTransportError) setSubmissionMessage("We could not confirm whether your enquiry was received. Please do not submit again automatically; contact us before retrying.");
  else setSubmissionMessage("We could not submit your enquiry right now. Please try again later.");
}

function apiErrorsToFields(error: ApiError): EnquiryFieldErrors {
  const errors: EnquiryFieldErrors = {};
  for (const item of error.errors) {
    if (item.field === "phone" || item.field === "email") errors.contact = item.message;
    else if (item.field === "name" || item.field === "subject" || item.field === "message" || item.field === "category" || item.field === "attachment") errors[item.field] = item.message;
  }
  return errors;
}
