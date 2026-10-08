import assert from "node:assert/strict";
import test from "node:test";
import {
  serializeFurnitureRequest,
  validateFurnitureRequest,
  type FurnitureRequestFormValues,
} from "./submission";

const validValues: FurnitureRequestFormValues = {
  name: "Asha Mrema",
  phone: "+255 700 000 000",
  email: "",
  quantity: "",
  length: "120.5",
  width: "60",
  height: "",
  material: "Oak",
  color: "Natural",
  notes: "Please keep the grain visible.",
  attachment: null,
};

test("serializes an attachment-free request as typed JSON without empty optional fields", () => {
  const result = serializeFurnitureRequest(validValues, "prod_oak-table");

  assert.deepEqual(result, {
    product_id: "prod_oak-table",
    name: "Asha Mrema",
    phone: "+255 700 000 000",
    dimensions: { length: 120.5, width: 60, unit: "cm" },
    material: "Oak",
    color: "Natural",
    notes: "Please keep the grain visible.",
  });
});

test("serializes an inline attachment using Laravel bracket dimension fields", () => {
  const attachment = new Blob(["reference"], { type: "application/pdf" });
  const result = serializeFurnitureRequest({ ...validValues, attachment }, undefined);

  assert.ok(result instanceof FormData);
  assert.equal(result.get("name"), "Asha Mrema");
  assert.equal(result.get("dimensions[length]"), "120.5");
  assert.equal(result.get("dimensions[width]"), "60");
  assert.equal(result.get("dimensions[unit]"), "cm");
  assert.equal(result.get("attachment") instanceof Blob, true);
  assert.equal(result.has("product_id"), false);
});

test("requires a name and at least one contact method", () => {
  const errors = validateFurnitureRequest({ ...validValues, name: " ", phone: "", email: "" });

  assert.equal(errors.name, "Enter your name.");
  assert.equal(errors.contact, "Enter a phone number or email address.");
});

test("rejects client-invalid quantity, dimensions, and attachments before submission", () => {
  const errors = validateFurnitureRequest({
    ...validValues,
    quantity: "1.5",
    length: "0",
    attachment: new Blob(["x"], { type: "text/plain" }),
  });

  assert.equal(errors.quantity, "Quantity must be a whole number from 1 to 100.");
  assert.equal(errors.length, "Enter a measurement greater than 0 and no more than 10,000 cm.");
  assert.equal(errors.attachment, "Choose a JPG, PNG, WebP, or PDF file.");
});
