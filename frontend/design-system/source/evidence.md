# Furniture Design System — Source Evidence

## Source Scope

This Design System 2.0 backfill is derived from the curated OpenDesign bundled fixture.
It does not claim a fresh crawl of the original upstream brand repository or website.

## Included Fixture Files

- design-system/DESIGN.md
- design-system/tokens.css
- design-system/components.html

## Token Contract

Phase 12.2 established `tokens.css` as the canonical shared-token authority. `source/tokens.source.json` and `source/token-contract.report.json` record the reconciliation and required contract families; they are audit records, not competing token authorities.
`design-tokens.json` and `tailwind-v4.css` are synchronized derived representations and must agree with `tokens.css` in the same change.
