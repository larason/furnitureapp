# Query Search Policy — Version 1

## 1. Purpose

Defines the project's **search** convention for collection reads under `/api/v1` before per-endpoint searchable-field lists are designed. Ensures the Next.js website, Flutter app, and admin clients use one predictable free-text discovery language that is distinct from structured filtering, read-only, scoped to public fields, and implementable without a dedicated search engine in V1.

**Authoritative inputs:** `AGENTS.md` §9 (SEO) and Catalog principles, `logical-data-model-v1.md`, `api-resource-inventory.md`, `query-parameter-conventions.md`, `query-filtering-policy.md`, `phase-1.11.md` §§9-11.

## 2. Search Parameter

- **Canonical key:** `search`
- **Type:** free-text string, optional.
- **Rule:** Exactly one search key (`search`). Never `q`, `query`, `find`, `lookup`, `keyword`, `term`, `search_text`. One name project-wide.
- **Presence:** Optional on supported collections. Absent → no text search applied (only filters/sort scope if present).

## 3. Matching Philosophy (Implementation-Independent)

No search engine is selected in Phase 1.11 (`Elasticsearch`/`OpenSearch` not assumed). V1 may be satisfied by SQL `LIKE`/`FULLTEXT` or equivalent simple textual matching with appropriate indexes, per `AGENTS.md` §10 (measure before optimizing).

- **Field scope:** Business-relevant **public** fields only (see §4).
- **Match type:** Case-insensitive partial (substring/token prefix) matching over searchable concepts. Exact DB operator is deferred to Group E implementation, but the API contract guarantee is: `search` matches human-meaningful text, not internal storage details.
- **Multiple terms:** Terms within `search` are combined with `AND` by default on token presence (all terms expected to match somewhere in the searchable fields), unless a later contract documents a different relevance behavior. No operator DSL (`AND`/`OR`/`NOT` keywords) in `search` value in V1; `+apple -orange` syntax is not supported.
- **Whitespace normalization:** Leading/trailing whitespace trimmed; consecutive whitespace collapsed to a single space before matching.
- **Case handling:** Case-insensitive matching. `Modern Sofa`, `modern sofa`, `MODERN SOFA` are equivalent.

No relevance scoring or relevance-ordered results are guaranteed in V1. If relevance ordering is later required, the contract must explicitly define the field `?sort=relevance` behavior. V1 search does not implicitly reorder unless combined with an explicit `sort` choice.

## 4. Searchable Concepts (Public Only)

Search spans **public** searchable information. Internal inventory or administrative notes must never be searchable.

### Product collection (public)

Searchable concepts:
- Product `name`
- SKU / business reference where public (identifier displayed to customers)
- `description` where appropriate (SEO/public description)
- Category `name` / `slug` via product's category
- Variant labels / attributes (`variant name`, attributes displayed to customers)

Not searchable:
- Internal inventory notes, `physical_quantity` / `reserved_quantity`, adjustment history
- Internal `is_active` admin notes, staff operational notes
- `payment_provider_secret`, `internal_reference`

### Category collection

- Category `name`, `description`

### Future admin collections that expose `search`

- Admin may search orders by `order reference` (`OD-...`), requests/enquiries by `subject`/`message` — but the per-contract `search` scope must still enumerate searchable fields; no implicit all-column search.

Each collection contract must list its searchable fields. A field not listed is not searched.

## 5. Search vs Filter (Restated)

- `?search=sofa` → broad text discovery over searchable fields.
- `?category=sofas` → exact structured filter (see `query-filtering-policy.md`).

They compose: `?search=modern&category=sofas` means `text matches 'modern' AND category = sofas`. Do not model textual category matching as `search`; use `category` for category scoping.

## 6. Input Considerations

- **Minimum length:** Empty `search` → validation error per `query-parameter-conventions.md` §6. Single-character `?search=a` is accepted but may return broad results; a per-collection minimum (e.g., 2 characters) may be documented in the contract if operationally justified, otherwise no minimum beyond non-empty.
- **Maximum length:** `search` value is limited to **128 characters** (reason: prevents abuse, long payloads, and expensive queries; suitable for a small commerce catalog). Exceeding the limit → validation error. Individual contracts may impose a tighter limit but never a looser one without review.
- **Encoding:** `search` must be URL-encoded (`query-parameter-conventions.md` §17). Clients must not construct unsafe URLs from raw user input with unencoded spaces or special characters.
- **Sanitization:** Search input is treated as literal text, not a query DSL; `%`, `_`, `*` in input are not wildcards — they are literal characters that must not be interpreted as storage-level wildcards without escaping. Implementation must escape pattern characters before applying storage filters.

## 7. Encoding Example

```
User types: modern sofa
Request: GET /api/v1/products?search=modern%20sofa
or:      GET /api/v1/products?search=modern+sofa
```

Both are equivalent after decoding. Unencoded `?search=modern sofa` (raw space) is malformed.

## 8. Read-Only and No Mutation

`GET ...?search=...` is read-only per `http-method-conventions.md` and `query-parameter-conventions.md` §14. Search queries must not create side effects (no read-state changes, no resource creation).

## 9. Caching — Authorization-Aware

Search results are cacheable per method semantics (`GET`). `search` is part of the cache key, but the cache key **must be scoped by authorization context**. No cache implementation is assumed here.

- **Public catalog (anonymous / public):** `GET /api/v1/products?search=...` and `GET /api/v1/categories?search=...` are `PUBLIC_READ`. Shared (CDN/proxy) caching keyed by URL (including `search`, filters, `sort`, `page`/`per_page`) is permissible because the response does not vary by caller. `Vary` and cache-key include only the URL and public content negotiation.
- **Protected collections (customer / staff / admin):** `search` on `GET /api/v1/me/orders?search=...`, `GET /api/v1/me/requests?search=...`, `GET /api/v1/orders?search=...` (staff/admin), `GET /api/v1/requests?search=...` (staff/admin), etc., is **owner- or role-scoped**. A shared cache keyed only by URL would leak one customer's or administrator's results to another caller. For these collections:
  - Do **not** use shared public cache. Use `Cache-Control: private, no-store` or `private` with short TTL, and/or `Vary: Authorization` and include the **authenticated principal / role and ownership scope** in the cache-key (e.g., `user_id`/`GUEST_TOKEN` holder, `role`).
  - Alternatively, disable caching entirely for protected `search` until an authorization-aware cache is designed (Phase Group T / caching design).
- **Implementation note:** Even for public `search`, do not cache error responses or validation errors. Any future CDN rule must explicitly distinguish `PUBLIC_READ` vs `CUSTOMER_OWNED`/`STAFF_OPERATIONAL` per `api-resource-inventory.md` and `query-security-policy.md` before enabling shared caching.

## 10. SEO Relationship

Public product/category queries used by Next.js for SEO must remain deterministic and crawl-friendly. `search` is for interactive discovery; the website may use `/products?search=modern` for user-driven filtering without necessarily making every `search` combination an SEO-indexed page. API query conventions and SEO URL strategy remain independent (`query-parameter-conventions.md`).

## 11. Out of Scope

No search engine selection, no DB index design, no per-endpoint exhaustive searchable-field list, no relevance ranking algorithm, no OpenAPI parameter enumeration, no Laravel query implementation.
