/**
 * Presentation helpers for the category resource. The backend returns only
 * display values, so this module never derives state or hierarchy.
 */

const timestampFormatter = new Intl.DateTimeFormat("en-GB", {
  dateStyle: "medium",
  timeStyle: "short",
  timeZone: "UTC",
});

/**
 * Returns the address only when it is a full http(s) URL. Any other value is
 * treated as absent, so an unsafe scheme is never turned into a link.
 */
export function safeImageUrl(value: string | null | undefined): string | null {
  if (typeof value !== "string" || value.trim().length === 0) return null;
  try {
    const url = new URL(value);
    return url.protocol === "http:" || url.protocol === "https:" ? value : null;
  } catch {
    return null;
  }
}

/** Formats the API ISO-8601 timestamp; an unreadable value is treated as absent. */
export function formatCategoryTimestamp(value: string | null): string | null {
  if (value === null) return null;
  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? null : timestampFormatter.format(parsed);
}
