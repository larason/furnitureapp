# Query Parameter Decisions — Phase 1.11

## Purpose
Records all final decisions from Phase 1.11 Query Parameter Conventions per `phase-1.11.md` §57H. Each entry includes Decision ID, Decision, Reason, Alternatives considered, Affected resources, and Future phase.

---

## 1.11-DEC-01 — Canonical naming is lowercase snake_case

- **Decision:** All query parameters use `lowercase snake_case` (`page`, `per_page`, `sort_direction`, `min_price`, `max_price`, `product_type`, `created_from`). One consistent style project-wide.
- **Reason:** URL query ergonomics per `phase-1.11.md` §5; matches Laravel/Next.js/Flutter HTTP tooling predictably and avoids `sortBy` vs `sort_by` drift.
- **Alternatives:** `kebab-case` (`sort-direction`) for queries, `camelCase` (`sortDirection`). Rejected — less common for query strings and inconsistent with the `min_*`/`max_*` idiom.
- **Affected resources:** All collections.
- **Future phase:** All resource contracts (1.12+, Group E), OpenAPI parameters.

## 1.11-DEC-02 — Parameter names are case-sensitive; lowercase required

- **Decision:** Parameter names are case-sensitive and must be lowercase documented spelling; `?Category=sofas` and `?CATEGORY=sofas` are invalid.
- **Reason:** Predictability and prevents accidental aliasing (see phase §7).
- **Alternatives:** Case-insensitive aliases. Rejected — complicates validation and allow-lists.
- **Affected resources:** All collections.
- **Future phase:** Validation layer, client SDKs.

## 1.11-DEC-03 — Unknown query parameters are rejected (validation error)

- **Decision:** For known collections, unknown query parameters are rejected as validation errors rather than silently ignored.
- **Reason:** Surfaces client/API contract mismatch during development (phase §8); prevents `?password=` becoming a DB filter and hidden breakage on typos (`?min_pirce=`).
- **Alternatives:** Silently ignore unknown params for forward compatibility. Rejected for known collections — would hide contract mismatches; forward-compatible new params are handled via explicit additive versioning, not silent ignores.
- **Affected resources:** All collections; `products`, `categories`, `orders` (customer/admin), `requests`, `enquiries`.
- **Future phase:** Error contract phase, per-endpoint allow-lists (Group E+).

## 1.11-DEC-04 — Empty values are validation errors (uniform)

- **Decision:** An empty value for any typed query parameter (`?search=`, `?category=`, `?min_price=`, `?product_type=`, `?sort=`) is a validation error. No V1 parameter treats empty as absent.
- **Reason:** Consistent semantics across endpoints per phase §34; avoids divergent `empty == absent` edge cases and forces clients to omit rather than send blank values.
- **Alternatives:** Treat empty as absent (no filter). Rejected as V1 uniform policy — introduces ambiguity for typed params; may be reconsidered per specific param only if a contract proves need.
- **Affected resources:** All typed filters, `search`, `sort`.
- **Future phase:** Validation contracts.

## 1.11-DEC-05 — Null has no special meaning in V1 query semantics

- **Decision:** `?field=null` means literal string `null`, not SQL NULL. No V1 collection defines nullable query semantics (field absent vs empty vs literal `null` are distinct).
- **Reason:** Phase §35 — avoids conflating absence with null filtering without explicit contract.
- **Alternatives:** Treat `?field=null` as NULL filter. Rejected — no business need and complicates semantics.
- **Affected resources:** All.
- **Future phase:** If a contract ever requires nullable filtering, it must explicitly define it.

## 1.11-DEC-06 — Search uses single key `search` (free-text discovery)

- **Decision:** Canonical free-text key is `search`. One name project-wide; no aliases `q`, `query`, `find`, `lookup`.
- **Reason:** Phase §§9-11; search vs filter distinction must be stable.
- **Alternatives:** Multiple search names. Rejected — splits the language.
- **Affected resources:** `products` (primary), `categories`, admin `orders`/`requests`/`enquiries` where relevant.
- **Future phase:** Group E (catalog search), `query-search-policy.md`.

## 1.11-DEC-07 — Search is case-insensitive partial matching over public fields only

- **Decision:** `search` matches case-insensitively and partially (substring/token prefix) over documented public searchable concepts (Product `name`, public SKU, `description`, category affinity, variant labels). Internal inventory/adjustment fields are never searchable. Matching details stay engine-agnostic; no `Elasticsearch` assumption.
- **Reason:** Business-relevant discovery per phase §§9-10; protects internal data and avoids premature search infrastructure.
- **Alternatives:** Case-sensitive exact search, or search over all columns. Rejected — poor UX and leaks internals.
- **Affected resources:** `Product`, `Category`, variants; future admin collections with documented scopes.
- **Future phase:** Group E search validation, indexing.

## 1.11-DEC-08 — Search does not mutate state

- **Decision:** `GET ...?search=` is read-only. No query may cause purchases, order creation, or read-state changes.
- **Reason:** Phase §§29,4; queries are retrieval only.
- **Alternatives:** Search-triggered side effects. Rejected — violates GET safety (`http-method-conventions.md`).
- **Affected resources:** All searchable collections.
- **Future phase:** Implementation.

## 1.11-DEC-09 — Exact filters use resource-specific keys with domain language

- **Decision:** Structured filters use exact resource-specific keys (`category`, `product_type`, `availability`). Generic filtering concept does not introduce a universal `filter[...]` DSL.
- **Reason:** Phase §§6,47 — small commerce API benefits from explicit keys (`?category=sofas&min_price=...`) rather than a universal `filter[field][operator]=value` DSL.
- **Alternatives:** Generic `filter[...]` syntax. Rejected — overkill for V1 catalog size and adds authorization complexity.
- **Affected resources:** `Product`, `Order` (admin), `Request`/`Enquiry`.
- **Future phase:** Resource contracts.

## 1.11-DEC-10 — Category filter accepts slug (not identifier)

- **Decision:** `?category=sofas` accepts the canonical category **slug** (lowercase, unique global per `logical-data-model-v1.md`). Not an internal ID.
- **Reason:** Phase §12; slug is the public identity already used for SEO and navigation.
- **Alternatives:** Numeric `category_id`. Rejected — not the public identifier.
- **Affected resources:** `Product` collection.
- **Future phase:** Product contract (Group E).

## 1.11-DEC-11 — Product type and availability use exact CLOSED enum filters

- **Decision:** `?product_type=IN_STOCK|MADE_TO_ORDER` (CLOSED) and `?availability=available|unavailable` (coarse derived). Unknown values are validation errors, not wildcards.
- **Reason:** Phases §§13-14,43; public availability signal vs internal inventory separation (`INTERNAL_ONLY`).
- **Alternatives:** Accepting `in_stock`, `InStock`, or wildcard on unknown. Rejected — violates CLOSED policy and case rule.
- **Affected resources:** `Product` collection.
- **Future phase:** Catalog and enum policies.

## 1.11-DEC-12 — Range convention uses explicit `min_*`/`max_*` and `*_from`/`*_to`

- **Decision:** Ranges use paired bounds: `min_price`/`max_price` (price in TZS minor-unit integers, e.g., `50000000` = 500,000.00 TZS) and `created_from`/`created_to` (dates). Inclusive semantics (`>=` lower, `<=` upper, compared in minor units for prices). No bracket syntax (`price[gte]`) or dash range (`price=500-1500`) and no decimal price form (`500000.00`).
- **Reason:** Phases §§15-17,30-31; explicit pairs are simple and predictable for Laravel/Flutter/Next.js tooling; single minor-unit wire format eliminates ambiguous interpretation of `500000` (see 1.11-DEC-13).
- **Alternatives:** Bracket operators, dash range, decimal major-unit form. Rejected — adds parsing complexity and reintroduces dual-unit ambiguity; V1 commits to minor-unit integers per `PRICE-004`.
- **Affected resources:** Price-filterable collections (`Product`, `Order` via admin), date-filterable collections (`Order`, `Request`, `Enquiry`).
- **Future phase:** Delivery fee rules, order management filters.

## 1.11-DEC-13 — Numeric validations are explicit — single price unit/format

- **Decision:** `page`/`per_page` are integers ≥1; `min_price`/`max_price` are **integers ≥0 representing TZS minor units (cents, 1 TZS = 100)** with **no decimal point** (digits only). Wire format is single canonical minor-unit integer: `500000` means 500,000 cents = 5,000.00 TZS; `50000000` = 500,000.00 TZS. Decimal strings (`500000.00`), commas, or currency symbols are validation errors. Non-numeric or out-of-range → validation error; `min_price > max_price` and `created_from > created_to` → validation error (no silent swap, comparison in minor units).
- **Reason:** Phase §§16-17,54 and Data Integrity fix: original policy described both minor-unit integers and two-decimal major-unit decimals without defining how `500000` is interpreted, allowing client/server mismatch. Choosing one unit/format (minor-unit integers) satisfies `AGENTS.md` §13 `PRICE-004` safe money representation and makes every price filter unambiguous.
- **Alternatives:** Coerce invalid numeric, swap contradictory bounds, or retain dual decimal/minor-unit description. Rejected — hides client bugs and preserves ambiguity; dual format would allow `500000` to be interpreted as either 5,000.00 TZS or 500,000.00 TZS.
- **Affected resources:** All collections with pagination/price/date filters; especially `Product` catalog (`?min_price=...&max_price=...` examples updated to `50000000`/`150000000`).
- **Future phase:** Pagination (1.12) to define `per_page` max; validation error envelope.

## 1.11-DEC-14 — Boolean canonical is `true`/`false` lowercase

- **Decision:** Boolean query parameters are exactly `true`/`false` lowercase (e.g., `?is_active=true`). Mixed `1`/`yes`/`TRUE` rejected.
- **Reason:** Phase §18; one representation eliminates drift.
- **Alternatives:** `1`/`0`, `yes`/`no`. Rejected — multiple encodings complicate validation.
- **Affected resources:** Any future boolean filter (admin `is_active` etc.).
- **Future phase:** Admin contracts where boolean filtering is needed.

## 1.11-DEC-15 — Multi-value uses comma-separated CSV (OR within attribute; AND across)

- **Decision:** Multiple values for one parameter use CSV inside one occurrence: `?category=sofas,beds` (means `OR` within attribute). Repeated keys `?category=sofas&category=beds` or `category[]` are rejected. Across different parameters `AND` applies.
- **Reason:** Phases §§19-20; CSV is simplest that Laravel/Next.js/Flutter/standard HTTP handle predictably.
- **Alternatives:** Array bracket syntax, repeated-key syntax. Rejected — requires array parsing and complicates canonicalization.
- **Affected resources:** `Product` (`category`), any collection with OR-within-enum later (`product_type` where documented).
- **Future phase:** Resource contracts must document whether CSV multi-value is supported per param.

## 1.11-DEC-16 — Sorting uses `sort` + `sort_direction` (single-sort, explicit allow-list)

- **Decision:** Canonical sorting is `?sort={field}&sort_direction=asc|desc` with defaults documented per contract. Single-sort only in V1; unknown field, invalid direction, or repeated `sort` is a validation error. Compact `-field` prefix form is not supported. Each resource contract must declare an allow-list; arbitrary DB columns (including internal stock/secret columns) are never sortable.
- **Reason:** Phases §§21-24; one convention eliminates `sort_by`/`orderby` mixes, direction remains explicit, and allow-list prevents exposure of internals.
- **Alternatives:** `sort_by` + `order`, `sort=-price` prefix, unbounded multi-sort. Rejected — creates inconsistent API and wider attack surface.
- **Affected resources:** All sortable collections (`Product`, `Category`, `Order`, `Request`, `Enquiry` etc. when documented).
- **Future phase:** Group E sorting, pagination interaction.

## 1.11-DEC-17 — Every sortable collection has an explicit default ordering

- **Decision:** API consumers must not depend on undocumented ordering; every contract documents the default ordering applied when no `sort` is supplied.
- **Reason:** Phase §23.
- **Alternatives:** Rely on DB natural order. Rejected — non-deterministic, breaks caching/next-page predictability.
- **Affected resources:** All sortable collections.
- **Future phase:** Per-resource contracts document defaults.

## 1.11-DEC-18 — Pagination reserves `page`/`per_page` (full contract deferred)

- **Decision:** V1 reserves `page` and `per_page` as generic pagination params (see `query-parameter-conventions.md` §18). Details — numbering, page-size max, metadata, navigation, cursor consideration — are deferred to Phase 1.12.
- **Reason:** `phase-1.11.md` §25 (Pagination) and `endpoint-naming-conventions.md` §10 (Search/filter/pagination: Not resources — Do not create `/products/page/2`); reserves the namespace without finalizing the pagination contract. Note: identifiers/slugs are `endpoint-naming-conventions.md` §4, not §10.
- **Alternatives:** Path-based `.../page/2` or alternative names `limit`/`offset`. Rejected per `endpoint-naming-conventions.md` §10 (Search/filter/pagination: Not resources).
- **Affected resources:** All collections.
- **Future phase:** Phase 1.12.

## 1.11-DEC-19 — GET filtering belongs in query string, not body; identity in path

- **Decision:** `GET` collection filtering belongs exclusively in the query string. Do not use JSON body for filtering; identity belongs in path (`/products/{product}`) not query (`/products?id=123`).
- **Reason:** Phases §§26-28; respects HTTP semantics and `endpoint-naming-conventions.md`.
- **Alternatives:** Body filtering on `GET` or identity via query. Rejected — violates REST semantics and caching.
- **Affected resources:** All.
- **Future phase:** OpenAPI GET parameter bindings.

## 1.11-DEC-20 — Query parameters must not encode business actions or mutations

- **Decision:** All `GET` collection queries remain read-only; hidden business actions (`?action=cancel`, `?ship=true`, `?cancel=true`) on `GET` are prohibited (validation errors). Controlled operations use `POST` to action subresources (see §14 of `http-method-conventions.md`).
- **Reason:** Phases §§28-29; prevents hidden commands.
- **Alternatives:** Action-as-query. Rejected — breaks safety and audit.
- **Affected resources:** `Order` actions, `Cart` checkout, `Payment`.
- **Future phase:** Action endpoints (Groups G-I).

## 1.11-DEC-21 — Date filtering uses `created_from`/`created_to` ISO 8601 UTC inclusive

- **Decision:** Date filters use `created_from`/`created_to` (and later `updated_from`/`updated_to` if required) with ISO 8601 UTC (`YYYY-MM-DDTHH:MM:SSZ`), inclusive boundaries. Timezone-ambiguous bare timestamps are rejected. Server uses UTC, not server-location time.
- **Reason:** Phases §§30-32; unambiguous timezone representation and one consistent pattern.
- **Alternatives:** `from_created_at`/`to_created_at`, bare date strings without timezone. Rejected — two patterns or ambiguity.
- **Affected resources:** `Order`, `Request`, `Enquiry`, `Payment` where date filtering is admin-operational.
- **Future phase:** Per-resource date filtering contracts.

## 1.11-DEC-22 — Encoding and parameter ordering are standardized

- **Decision:** All query values must be RFC 3986 URL-encoded; parameter order does not change semantics (canonical ordering is lexicographic where signatures/caching later require normalization).
- **Reason:** Phases §§33,37-38; avoids unsafe URL construction from raw input and aids caching/testing/debugging.
- **Alternatives:** Allow raw spaces/specials. Rejected — malformed requests and security issues.
- **Affected resources:** Especially `search` (free text) and any text filter.
- **Future phase:** Caching/signature work if ever introduced.

## 1.11-DEC-23 — Repeated parameters are rejected in V1

- **Decision:** Repeated identical keys (`?sort=price&sort=name` or `?category=sofas&category=beds`) are rejected; multi-value uses CSV where supported; single-sort only.
- **Reason:** Phase §36; one representation avoids silent choice of one value.
- **Alternatives:** Accept repeated keys and choose last. Rejected — ambiguous.
- **Affected resources:** All.
- **Future phase:** No change unless CSV multi-value is explicitly disclaimed.

## 1.11-DEC-24 — Query parameters cannot override customer ownership or expose sensitive fields

- **Decision:** Customer-owned reads (`GET /api/v1/me/orders`) derive owner from authenticated principal; no `?user_id=` param may override it. Internal fields, sensitive fields, and other-customer resources are never selectable via query. Allow-lists enforce this. Admin filters remain authorization-controlled.
- **Reason:** Phases §§39-43,48; ownership via authorization, not query; prevents enumeration and privilege escalation.
- **Alternatives:** Allowing `?user_id=` to traverse other users. Rejected — critical security invariant (IDENT-006, AUTHZ-003).
- **Affected resources:** `Me` collections, `Order`, `Request`, `Enquiry`, `Cart`, `Inventory`, `Payment`.
- **Future phase:** Group D authorization policies, envelope hardening.

## 1.11-DEC-25 — Generic query parameters are global; resource-specific use domain vocabulary

- **Decision:** Reusable generic params are `page`, `per_page`, `sort`, `sort_direction`, `search`, `created_from`, `created_to`, `include` considerations (but V1 deferred), `fields` (deferred). Resource-specific params use domain words: `category`, `product_type`, `availability`, `min_price`, `max_price`, `fulfillment_type`, `order_status`, `request_status`, `enquiry_status`, `payment_status`.
- **Reason:** `phase-1.11.md` §6 (Query Parameter Vocabulary — generic vs resource-specific) ; global predictability plus domain accuracy. Note: §43 defines enum query parameters (product_type / fulfillment_type / order_status etc.), not the generic/resource-specific distinction.
- **Alternatives:** All-params-namespaced generically. Rejected — hides domain signals.
- **Affected resources:** All.
- **Future phase:** Resource contracts.

## 1.11-DEC-26 — All V1 enums in queries are CLOSED; unknown value is validation error — canonical per-resource keys

- **Decision:** All V1 enum query params (`product_type`, `fulfillment_type`, `order_status`, `request_status`, `enquiry_status`, `payment_status`, `role`) are **CLOSED** with **canonical per-resource keys**: `order_status` for orders, `request_status` for requests, `enquiry_status` for enquiries, `payment_status` for payments (not `payment_state`). Generic `?status=` without prefix is a validation error on all V1 collections; unknown value is a validation error, never a wildcard. Adds require `v2` unless the enum is explicitly reclassified `OPEN` after compatibility review. Availability filter is `availability=available|unavailable` only — not `IN_STOCK` etc. (those belong to `product_type` or response display).
- **Reason:** Phases §§43-44, `api-versioning-strategy.md` §4.4 and `api-breaking-change-policy.md` §5; clients branch exhaustively and tolerate no unknown values. Because unknown parameters are rejected, `order_status` vs `status` are not interchangeable — canonical prefixed keys eliminate ambiguity and prevent silent fallback; also resolves `payment_state` vs `payment_status` drift.
- **Alternatives:** `OPEN/EXTENSIBLE` interim where unknowns become wildcards or display generically without review, or generic `?status=` shared across resources, or `payment_state` alias. Superseded per `phase-1.10.md` §61 and rejected to keep allow-lists strict.
- **Affected resources:** `Product`, `Order`, `Request`, `Enquiry`, `Payment`, `User`.
- **Future phase:** Any enum expansion must follow `api-versioning-strategy.md` §9 (new major version `v2`). Per-collection contracts must list only their canonical `*_status` key.

## 1.11-DEC-27 — Canonical enum values are `UPPER_SNAKE_CASE` with one spelling

- **Decision:** Query enum values are single-canonical `UPPER_SNAKE_CASE` (e.g., `IN_STOCK`, `MADE_TO_ORDER`, `READY_FOR_PICKUP`). Aliases `in_stock`, `InStock`, `in-stock` are rejected unless a contract explicitly documents them (none does).
- **Reason:** Phase §44; single representation eliminates drift across `Next.js`/`Flutter`/`Admin`.
- **Alternatives:** Accept multiple casings. Rejected — inconsistent.
- **Affected resources:** All enum filters.
- **Future phase:** Remains unless a contract explicitly documents an alias and the compatibility impact is approved.

## 1.11-DEC-28 — `include` and `fields` are DEFERRED (not supported in V1)

- **Decision:** V1 does not support `?include=category,variants` (relationship expansion) nor `?fields=id,name,price` (sparse fieldsets). Future inclusion requires measured need and explicit design; V1 uses predetermined embeds and dedicated subresources (`products/{product}/variants`, etc.) per `api-resource-relationships.md`.
- **Reason:** Phases §§45-46; small commerce scope does not justify a dynamic graph/field language yet; predictable responses are simpler to authorize, cache, and version.
- **Alternatives:** Generic `include`/`fields` in V1. Rejected — premature complexity and authorization surface.
- **Affected resources:** `Product` + variants/images, `Order` + items/tracking/attachments, `Request`/`Enquiry` + attachments.
- **Future phase:** Any adoption requires evidence → approval → compatibility review (potentially `v2`).

## 1.11-DEC-29 — No generic query DSL is introduced

- **Decision:** V1 does not introduce a universal syntax such as `filter[field][operator]=value` for every condition. Prefer explicit named filters.
- **Reason:** Phase §47; small-scale commerce need is covered by explicit `?category=...&min_price=...&max_price=...&product_type=...` and does not require DSL complexity.
- **Alternatives:** Advanced filter DSL. Rejected — over-engineering for V1.
- **Affected resources:** All.
- **Future phase:** Revisit only if product requirements demonstrate need.

## 1.11-DEC-30 — Allow-lists and complexity limits govern query security

- **Decision:** Every resource declares a supported-parameter allow-list; unknown params are not mapped to DB columns. Reasonable complexity limits are recognized (search max 128 chars, per_page max deferred to 1.12, max filters/CSV values), with exact limits to be set later; a generic unlimited filter DSL is not introduced.
- **Reason:** Phases §§48-50.
- **Alternatives:** Unbounded allow-all filtering. Rejected — exposes internal columns and enables expensive queries.
- **Affected resources:** All.
- **Future phase:** Pagination (1.12) for `per_page` max; per-resource limits.

## 1.11-DEC-31 — SEO/public-catalog queries remain independent; conventions are platform-neutral

- **Decision:** API query conventions are platform-neutral (Next.js, Flutter, Admin read the same language; no Flutter-specific syntax). API query semantics remain independent from SEO URL strategy; public website uses query params for interactive filtering without making every filtered URL an SEO-indexed page; caching determinism holds (`/products?category=sofas` is a deterministic read).
- **Reason:** Phases §§51-53.
- **Alternatives:** Flutter-specific or SEO-coupled query syntax. Rejected — fragments the contract.
- **Affected resources:** `Product`, `Category` browsing.
- **Future phase:** Next.js SEO phase handles crawl/indexing separately; pagination/SEO coordination.

## 1.11-DEC-32 — Precedence and defaults are explicit

- **Decision:** Contradictory values are validation errors (not corrected), default values for `page`/`per_page`/`sort_direction` are documented per later contract (defaults are not left implicit), and every future endpoint documents the per-param table (Name/Type/Required/Allowed/Default/Description/Validation/Security/Example) per `phase-1.11.md` §56.
- **Reason:** Phases §§54-56; avoids silent swaps and implicit defaults that break pagination/sort assumptions.
- **Alternatives:** Implicit defaults or silent correction. Rejected — hides contract details.
- **Affected resources:** All.
- **Future phase:** Endpoint contracts and pagination phase fill in exact defaults.
