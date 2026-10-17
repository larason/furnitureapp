/**
 * Idempotency-key support shared by the transport and the callers that own a
 * key's lifecycle. Laravel requires the documented `Idempotency-Key` header to be
 * a UUID, so the same shape is enforced here before a malformed header can be
 * sent. The value is a request token, never an authorization or secret.
 */
const UUID_PATTERN = /^[\da-f]{8}-[\da-f]{4}-[\da-f]{4}-[\da-f]{4}-[\da-f]{12}$/i;

export function isIdempotencyKey(value: unknown): value is string {
  return typeof value === "string" && UUID_PATTERN.test(value);
}

/** A fresh random key for one logical operation. Never derived from time or Math.random. */
export function newIdempotencyKey(): string {
  return crypto.randomUUID();
}
