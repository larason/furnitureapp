export interface CategoryOption {
  readonly id: string;
  readonly name: string;
  readonly slug: string;
}

/** Reads the public category collection Laravel returns for the create/edit picker. */
export function parseCategoryOptions(value: unknown): readonly CategoryOption[] {
  if (!Array.isArray(value)) return [];
  return value.flatMap((entry) => {
    if (typeof entry !== "object" || entry === null || Array.isArray(entry)) return [];
    const record = entry as Record<string, unknown>;
    const id = typeof record.id === "string" && record.id.trim().length > 0 ? record.id : null;
    const name = typeof record.name === "string" && record.name.trim().length > 0 ? record.name : null;
    if (id === null || name === null) return [];
    return [{ id, name, slug: typeof record.slug === "string" ? record.slug : "" }];
  });
}
