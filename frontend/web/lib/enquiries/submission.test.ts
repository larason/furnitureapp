import assert from "node:assert/strict";
import test from "node:test";
import { MAX_ENQUIRY_ATTACHMENT_BYTES, serializeEnquiry, validateEnquiry, type EnquiryFormValues } from "./submission";

const validValues: EnquiryFormValues = {
  name: "Asha Mwangi",
  phone: "+255700000001",
  email: "",
  subject: "Delivery to Dodoma",
  message: "Could you tell me whether you deliver furniture to Dodoma?",
  category: "DELIVERY",
  attachment: null,
};

test("requires anonymous enquiry contact, subject, and message", () => {
  const errors = validateEnquiry({ ...validValues, name: "", phone: "", subject: "", message: "" });

  assert.equal(errors.name, "Enter your name.");
  assert.equal(errors.contact, "Enter a phone number or email address.");
  assert.equal(errors.subject, "Enter a subject with 5 to 200 characters.");
  assert.equal(errors.message, "Enter a message with 10 to 5,000 characters.");
});

test("accepts optional category and attachment-free JSON submissions", () => {
  assert.deepEqual(validateEnquiry(validValues), {});
  assert.deepEqual(serializeEnquiry({ ...validValues, category: "" }), {
    name: "Asha Mwangi",
    phone: "+255700000001",
    subject: "Delivery to Dodoma",
    message: "Could you tell me whether you deliver furniture to Dodoma?",
  });
});

test("serializes one supported attachment as multipart form data", () => {
  const attachment = new File(["reference"], "room-plan.pdf", { type: "application/pdf" });
  const payload = serializeEnquiry({ ...validValues, attachment });

  assert.ok(payload instanceof FormData);
  assert.equal(payload.get("category"), "DELIVERY");
  assert.equal((payload.get("attachment") as File).name, "room-plan.pdf");
});

test("rejects unsupported and oversized attachments before submission", () => {
  const unsupported = new File(["plain text"], "notes.txt", { type: "text/plain" });
  const oversized = new File([new Uint8Array(MAX_ENQUIRY_ATTACHMENT_BYTES + 1)], "room-plan.pdf", { type: "application/pdf" });

  assert.equal(validateEnquiry({ ...validValues, attachment: unsupported }).attachment, "Choose a JPG, PNG, WebP, or PDF file.");
  assert.equal(validateEnquiry({ ...validValues, attachment: oversized }).attachment, "Choose a file no larger than 5 MiB.");
});
