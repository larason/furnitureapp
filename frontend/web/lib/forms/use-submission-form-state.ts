"use client";

import { useEffect, useRef, useState, type Dispatch, type SetStateAction } from "react";

type FormValues<TField extends string> = Record<TField, string> & { attachment: Blob | null };
type FieldErrors<TField extends string> = Partial<Record<TField | "contact" | "attachment", string>>;

export function useSubmissionFormState<TField extends string, TValues extends FormValues<TField>>(initialValues: TValues) {
  const [values, setValues] = useState<TValues>(initialValues);
  const [fieldErrors, setFieldErrors] = useState<FieldErrors<TField>>({});
  const [submissionMessage, setSubmissionMessage] = useState<string>();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isSubmitted, setIsSubmitted] = useState(false);
  const acknowledgementRef = useRef<HTMLDivElement>(null);
  const attachmentInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (isSubmitted) acknowledgementRef.current?.focus();
  }, [isSubmitted]);

  function updateField(field: TField, value: string) {
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

  return { values, setValues, fieldErrors, setFieldErrors: setFieldErrors as Dispatch<SetStateAction<FieldErrors<TField>>>, submissionMessage, setSubmissionMessage, isSubmitting, setIsSubmitting, isSubmitted, setIsSubmitted, acknowledgementRef, attachmentInputRef, updateField, updateAttachment, removeAttachment };
}
