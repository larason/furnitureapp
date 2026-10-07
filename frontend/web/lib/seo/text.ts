export const MAX_DESCRIPTION_LENGTH = 300;

export function boundText(value: string, maxLength: number): string {
  return value.length > maxLength ? `${value.slice(0, maxLength - 1).trimEnd()}\u2026` : value;
}

export function normalizeDescription(value: string | null | undefined): string | undefined {
  if (!value) {
    return undefined;
  }
  const normalized = value.replace(/\s+/g, " ").trim();
  return normalized ? boundText(normalized, MAX_DESCRIPTION_LENGTH) : undefined;
}
