/**
 * Non-production navigation fixture for Phase 13.7 shell geometry only.
 * Slugs mirror the authoritative Laravel CategorySeeder taxonomy; Group N
 * replaces this fixture with authoritative catalog navigation data.
 */
export type CategoryNavigationItem = {
  name: string;
  slug: string;
};

export const CATEGORY_NAVIGATION_FIXTURE: readonly CategoryNavigationItem[] = [
  { name: "Living Room", slug: "living-room" },
  { name: "Bedroom", slug: "bedroom" },
  { name: "Dining Room & Kitchen", slug: "dining-room-kitchen" },
  { name: "Home Office", slug: "home-office-corporate-workspaces" },
  { name: "Outdoor", slug: "outdoor-patio" },
  { name: "Entryway", slug: "entryway-accent" },
];
