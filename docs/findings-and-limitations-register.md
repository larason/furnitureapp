# Findings and Limitations Register

**Created:** 2026-10-15
**Owner decisions recorded:** 2026-10-16 — see the "Findings Register — Owner Decisions and Mitigation Plan" section at the end.
**Scope:** Verified against the repository on 2026-10-15. Spans `backend/laravel`, `frontend/admin`, `frontend/web` and `docs/api`.
**Status of this document:** A living register. Every entry records verified evidence, the concrete impact, and a recommendation with an owning phase. It is a tracking artifact, not a decision record — promote individual entries into `AGENTS.md`/`README.md` once accepted.

Nothing in this register has been staged or committed by the agent. The register itself was produced by read-only inspection. The owner decisions recorded on 2026-10-16 did result in two executed changes — the additive `CAT-003` documentation fix and the removal of the orphaned STAFF row — each recorded with its evidence in the decisions section.

**How to read the evidence:** `file:line` citations point at the verified location; measurements were taken with read-only queries against the development database and are reproducible.

---

## A. Superseded findings (withdrawn — do not act on the old version)

### F6 — "Two disjoint Clerk instances" was **wrong**; there is exactly one shared instance

**Status:** CORRECTED — the previously reported multi-instance blocker does not exist.

**Evidence (verified):**

- `frontend/admin/.env.local` and `frontend/web/.env.local` carry the **identical** `NEXT_PUBLIC_CLERK_PUBLISHABLE_KEY` → same Clerk instance (`regular-ram-13.clerk.accounts.dev`).
- `php artisan config:show clerk.issuer` → `https://regular-ram-13.clerk.accounts.dev` — the backend validates the *same* instance the two frontends use.
- `config/clerk.php` holds a single `secret_key`/`issuer`/`audiences`/`authorized_parties`.
- `app/Authentication/Clerk/OfficialClerkTokenVerifier.php:38,66-70` verifies one secret and compares `iss` for equality — which is correct for a single shared instance.

**Impact:** The proposed workstream "backend multi-instance token verification + instance-scoped `ClerkUserGateway`" is unnecessary. Both frontends' tokens are already verifiable by the existing backend. The real constraint is F5 (staff provisioning), not token verification.

**Recommendation:** **Withdraw the multi-instance workstream.** Do not build instance-scoped verification, do not add a second Clerk configuration surface, and do not change `users.clerk_user_id`. The `nullable()->unique()` column is correct as-is.

---

## B. Blocking capability limitations

### F1 — Category hierarchy exists in the domain but is unreachable through the API

**Status:** OPEN — expected to be resolved by an additive API v2 (see F1 recommendation). **Blocking capability for Phase 19.7, which was closed PARTIAL-accepted on 2026-10-16 (owner decision 6); it does not block Phase 19.8A.**

**Evidence (verified):**

- Schema supports depth: `database/migrations/2026_09_08_100000_create_categories_table.php:13-16` (self-referencing `parent_id`, `nullOnDelete`), plus `display_order` and `is_active`.
- Model fully implements hierarchy, including safe reparenting: `app/Models/Category.php:39-78` (`changeParent()` with `lockForUpdate` + `assertAcyclicUnderLock` cycle detection), `parent()`/`children()` at `:91-100`.
- `changeParent()` has **no production caller** — `grep` finds it only in `tests/Feature/CategoryHierarchyTest.php` and `tests/Feature/CategoryConcurrentReparentTest.php`. It is implemented and tested but never exposed.
- Reads are single-level: `app/Http/Controllers/Api/V1/CategoryController.php:36-43` filters `parent_id = furnitures-root AND is_active = true`.
- Writes cannot create depth: `app/Services/Categories/CreateCategory.php:30-37` hard-codes `parent_id = $root->id`, `is_active = true`, `space_type = HYBRID`.
- The write allow-list is closed: `app/Http/Requests/CategoryFormRequest.php:10` → `['name','slug','description','image']`; any other field is rejected with "This category field is not allowed."
- The frozen contract is flat too: `docs/api/openapi.yaml:508-548` (`Category` has no `parent_id`/`children`/`is_active`/`display_order`) and `:2319-2361` (`CategoryCreateRequest`/`CategoryUpdateRequest` are the same four fields, `additionalProperties: false`).

**Measured (development database):**

```
total categories:                  76
max nesting depth:                  4  (root → L1 → L2 → L3)
direct children of furnitures-root: 6
categories deeper than depth 2:    69
```

**Impact:** The admin `/categories` screen can manage **6 of 76** categories. Phase 19.7 is therefore PARTIAL, not PASS. Product filing (F2) and crawl discovery (F3) inherit the same ceiling.

**Recommendation:**

1. Keep the current scope for the request-only launch. The gap is a catalog-information-architecture problem, not a transactional one — orders, payments, inventory and fulfilment are all deferred, so nothing in the launch depends on depth.
2. When addressing it, add **`/api/v2/categories`** with `parent_id`, `children` and depth, reusing the existing `changeParent()` for reparenting rather than writing new cycle logic. Leave `/api/v1/categories` frozen and top-level-only.
3. **Do not redefine `/api/v1/categories` to return the whole tree.** Four existing consumers read it as "the category list": storefront navigation/filter (`frontend/web/lib/category/options.ts:11,18`), the sitemap (F3), and the admin picker. Widening v1 would change all four silently with no code change to trigger it — the sitemap alone would jump from 6 to 76 URLs. It would also start emitting a `/categories/furnitures-root` entry that **404s**, because the detail endpoint deliberately rejects the structural root (`CategoryController.php:56`: `$resolved->parent_id === null` → `404`). The listing and the detail contract would contradict each other.
4. Because `docs/api/*` is frozen, this needs the post-freeze contract-change process. It is a genuine breaking-change decision, not something to slip into a frontend phase.

**Owner:** new API v2 phase (contract work), then Phase 19.7 follow-up (UI).

---

### F5 — Every staff lifecycle endpoint returns `501`; the admin dashboard has exactly one usable account

**Status:** OPEN — Phase 19.13 owns the UI, and the **backend** is still unimplemented. **Owner decision (2026-10-16): one bootstrapped ADMIN is sufficient for launch (recorded below), and the orphaned unlinked demo rows have been removed.**

**Evidence (verified):**

- `app/Http/Controllers/Api/V1/AdminController.php:34-52` → `staffStore`, `staffApprove`, `staffSuspend`, `staffReactivate` all `return $this->notImplemented();`.
- `app/Support/NotImplementedResponse.php` → HTTP `501` `{"status":"not_implemented"}`.
- Routes and permissions *are* registered and gated (`routes/api.php:224-231`, `staff.manage`/`staff.approve`), so the surface exists but cannot succeed.
- The only working role-granting path is the operator command: `app/Console/Commands/BootstrapInitialAdminCommand.php` (`admin:bootstrap {clerk-user-id}`) → `app/Authentication/BootstrapInitialAdmin.php:68` (`syncRoles([ADMIN])`). It refuses a second admin: `:98-102` throws "already configured for a different Clerk identity".
- `syncRoles` appears **only** there; JIT provisioning elsewhere assigns `CUSTOMER` (F7).

**Measured (development database, users by role and linkage):**

```
user 1 | NOT-LINKED   | state=null   | roles=CUSTOMER   <- orphaned demo account; REMOVED 2026-10-16
user 2 | NOT-LINKED   | state=ACTIVE | roles=STAFF      <- orphaned demo account; REMOVED 2026-10-16
user 4 | clerk-linked | state=ACTIVE | roles=ADMIN      <- the one usable account
user 6 | clerk-linked | state=null   | roles=CUSTOMER
```

**Current state (2026-10-16):** only `user 4` (ADMIN) and `user 6` (CUSTOMER) remain — both Clerk-linked. The two unlinked demo accounts were removed with the owner's approval; see the decisions section.

**Impact:** Exactly one operator account can reach the dashboard, and there is **no supported way to add a second**. The cleaned-up unlinked rows had no Clerk identity, so they could never sign in — they were orphaned local records that would silently fail any attempt to use them.

**Recommendation:**

1. Treat "exactly one Admin" as the launch constraint and document it as an operational fact, not a bug. — **Confirmed by the owner (2026-10-16).**
2. Do not hand-link Clerk IDs or hand-assign roles to work around it. If a second operator is genuinely required before 19.13, that is a scope decision to raise explicitly, not an ad-hoc database edit. — **Standing rule.**
3. Decide what to do with the orphaned unlinked rows — either link them deliberately through a documented procedure or remove them. Leaving them is a trap for the next operator. — **Resolved: removed (2026-10-16), both the `STAFF` and `CUSTOMER` rows, see the decisions section below.**

**Owner:** Phase 19.13 (staff management) for the API + UI; operator decision for the orphan rows (taken).

---

## C. Contract and documentation discrepancies

### F4 — `GET /categories` is the only paginated collection in the frozen OpenAPI that omits `page`/`per_page`

**Status:** CLOSED (2026-10-16) — the approved additive documentation change is implemented; the gap was documentation-only.

**Evidence (verified):**

- Reusable components exist: `docs/api/openapi.yaml:69-87` defines `Page` and `PerPage` (per_page `maximum: 100`, `default: 20`).
- Eight collection operations reference them; `CAT-003` (`/categories`, `openapi.yaml:2985-2992`) declares **no `parameters:` block at all**.
- The controller nonetheless validates and accepts both: `CategoryController.php:30-35` (`page` ≥1, `per_page` 1–100).
- Phase 19.6 already consumes `?per_page=100` from this endpoint, and Phase 19.7 consumes `page`/`per_page`.

**Impact:** The documented contract understates the real one. A client reading only `openapi.yaml` would not know pagination is available; the parameters are nonetheless optional, so no client is broken today.

**Recommendation:** Add the two `$ref`s to `CAT-003` under the post-freeze documentation-change process. This is **additive and non-breaking** (the parameters are optional, already accepted, and already in use), but it touches a frozen file and so needs the sanctioned sign-off rather than a drive-by edit. **Done (2026-10-16):** approved by the owner and applied — `CAT-003` now declares `Page` and `PerPage`, matching `CAT-001`. Three added lines; no path, schema, response, or runtime behaviour changed.

---

### F-C (corrected) — The `products.view` discrepancy is already resolved

**Status:** CLOSED — no action.

**Evidence (verified):** `grep "products.view" phases/group-S-phases.md` returns nothing; the file now records `products.manage` as the verified category-write permission (`group-S-phases.md:72,113,128`). `app/Support/PermissionName.php:12-13` confirms both `PRODUCTS_VIEW` and `PRODUCTS_MANAGE` exist, so the earlier note that `products.view` *is* real remains accurate — it is simply no longer misattributed in the phase document.

---

## D. Operational constraints

### F7 — JIT provisioning always assigns `CUSTOMER`, which interacts badly with the unique email constraint

**Status:** OPEN — an ordering constraint to document.

**Evidence (verified):**

- `app/Authentication/LocalUserProvisioner.php:58` → `assignRole(RoleName::CUSTOMER->value)` on every JIT-provisioned identity.
- `database/migrations/*_create_users_table.php:17` → `email` is `unique()`.
- `LocalUserProvisioner.php:45-47` refuses to adopt an existing unlinked user with the same email (throws a conflict); the `User::create` at `:49-53` would otherwise hit the unique constraint.
- Admin admission requires `STAFF`/`ADMIN` with `account_state = ACTIVE` (`app/Http/Middleware/StaffOrAdminAccess.php:19`).

**Impact:** A staff member who signs in *before* being granted a role is provisioned as a `CUSTOMER` and correctly denied — but that row now occupies their email permanently. Combined with F5 (no working staff API), the ordering is unforgiving: the role must be granted before or at first sign-in, and a premature sign-in cannot simply be retried.

**Recommendation:** Document the ordering explicitly in the operator runbook: grant the role first, have the person sign in second. Do not add a "fix it up later" path until 19.13 provides one.

**Owner:** Phase 19.13 + operations documentation.

---

### F2 — Products are filed deeper than the admin product form can select

**Status:** OPEN — resolves with F1.

**Evidence (verified):**

- `frontend/admin/AGENTS.md` (Product module) and the Phase 19.6 record: category options come from the public `GET /categories` only, because no operational category endpoint exists.
- Measured distribution of products by category depth: **depth 2 → 1 product; depth 4 → 3 products**.

**Impact:** Three of four seeded products sit in leaf categories the admin picker cannot offer. Existing products are protected by the 19.6 rule that injects the product's own server-returned category as an extra option, so an edit never silently reassigns — but any **new** product can only be filed at top level.

**Recommendation:** No change now. Revisit together with F1; when an additive v2 listing exists, migrate the product form's category options to it and retire the first-100/top-level limitation recorded in the Phase 19.6 record.

---

### F3 — The sitemap lists 6 category URLs, not 76

**Status:** OPEN — soft SEO loss, acceptable at launch scale.

**Evidence (verified):**

- `frontend/web/lib/seo/crawl.ts:33,37` builds `/categories/{slug}` entries from `listCategorySlugs`.
- `crawl.ts:80-81` → `getCategoryFilterOptions` → `frontend/web/lib/category/options.ts:11,18`, which pages through the public `GET /categories`. That endpoint returns top-level only, so the result is 6 slugs.

**Impact:** Roughly 70 category pages exist, load correctly and remain reachable through internal links from product pages, but are absent from the sitemap. This is a crawl-priority loss, not an availability loss.

**Recommendation:** No action for the request-only launch. Fixing it depends on F1; the correct fix is to point the sitemap at a hierarchy-aware listing rather than to widen the frozen v1 endpoint.

---

## E. Verification gaps (not code defects)

### F11 — The authenticated render has never been executed in any phase

**Status:** OPEN — requires a human with the operator credential.

**Evidence (verified):** A usable `ADMIN` identity exists (`user 4`: clerk-linked, `account_state = ACTIVE`) and the app's admission boundary is built for it. The credential for that identity was never available to the agent sessions, and obtaining or creating one is outside the permitted scope. Consequently the authenticated render of `/products*` and `/categories*` (list, detail, create, edit, real `422`s, real pagination) has never been observed live; it is covered only by mocked automated suites.

**Impact:** Everything proven about these screens is proven at the code/unit level. No end-to-end evidence exists that the admin dashboard works against the running API with a real session.

**Recommendation:** Have the operator sign in with the bootstrapped Admin and walk the four category routes plus the four product routes once. Record the result here. Until then, Phase 19.6/19.7 records must keep reporting this as an unverified limitation rather than a pass.

**Owner:** operator action; verification, not implementation.

---

## F. Deferred and hygiene items

### F10 — User-facing roadmap jargon is deferred to the end of Group S

**Status:** DEFERRED by owner decision (agreed).

**Evidence (verified counts):**

- `placeholder`/`plannedFor`: **53 occurrences across 14 files** — 26 in source (`app/`, `components/`, `lib/`), 27 in tests.
- User-visible `Phase 19.x` strings in source: **8 occurrences across 3 files** (`components/layout/admin-overview-body.tsx`, `components/catalog/product-detail-view.tsx`, `lib/navigation/admin-navigation.ts`).

**Dependency to remember:** `frontend/admin/AGENTS.md` currently *requires* placeholders to name their owning roadmap phase, and `tests/overview.test.tsx` asserts it. Any cleanup must amend both together, or the guard and the copy will contradict each other.

**Recommendation:** Keep deferred until Group S completes, as agreed. When it happens, plan it as one coherent pass with the AGENTS/test change included — not as scattered copy edits.

---

### F8 — Product variant creation returns `501`

**Status:** ACCEPTED — correctly surfaced by the UI.

**Evidence (verified):** `routes/api.php:150` registers `POST /products/{product}/variants`; `app/Http/Controllers/Api/V1/ProductController.php:155-158` → `notImplemented()` → `501`. The admin renders variants read-only and offers no enabled create control.

**Recommendation:** No action. Keep the UI read-only and keep asserting it in tests (already done in `tests/products.test.tsx`).

---

### F9 — Media management is deferred to Phase 19.8

**Status:** PARTIALLY RESOLVED (2026-10-16) — Phase 19.8A delivered upload + preview only.

**Evidence (verified):** The admin product detail reports only an image count; the category form and detail treat `image` as a URL field (`CategoryCreateRequest.image` is a `string|null` URL in `openapi.yaml:2336-2340`) with no upload control. Phase 19.8 ("Product Media Management - cloudflare R2") owns the real capability.

**Update (2026-10-16):** Phase 19.8A implemented product image **upload and preview** (`POST /api/v1/products/{product}/images`, CAT-009) on the product detail screen, with the preview origin allow-listed from `CATALOG_MEDIA_BASE_URL`. Deletion, ordering, alt-text editing and setting the primary image remain **unimplemented and unoffered**, because no backend endpoint exists for any of them. Category media remains a plain `image` URL string with no upload.

**Recommendation:** The residual work belongs to Phase 19.8B/19.8C as the owner defines them, and needs a backend media mutation contract first. Do not add controls for unimplemented media mutations.

---

### F12 — Fallow advisory metrics were not re-verified in this session

**Status:** UNVERIFIED — previously reported, needs re-running.

**Evidence:** `.fallowrc.json` is present and contains two deliberate `ignoreFindings` entries and two Laravel Vite `entry` seeds. The Fallow tool is not available in the current environment, so the previously reported dead-code result (29 → 0) and the advisory metrics (duplication ≈4.08%, ≈101 functions, `coverage_model: "static_estimated"`) could **not** be reproduced and are therefore recorded as unverified rather than restated as fact.

**Recommendation:** Re-run Fallow before treating the dead-code result as a gate on later phases. Treat the duplication/complexity figures as advisory only — they are not, and should not become, a merge blocker.

---

### F13 — `confirm-dialog.tsx` is a deliberately unreferenced primitive

**Status:** OPEN — intentional, tracked.

**Evidence (verified):** `.fallowrc.json` `ignoreFindings` explicitly exempts `frontend/admin/components/ui/confirm-dialog.tsx` with the comment that it is "a Phase 19.2 UI primitive awaiting the phase that consumes it."

**Impact:** A component exists with no consumer. It is exempted from dead-code reporting, so it will not surface again on its own.

**Recommendation:** Either consume it in the first phase that needs a confirmation dialog (F5/Phase 19.13 is the likely candidate) or remove it. Do not let the exemption outlive the reason for it.

---

### F14 — Primary navigation is a hardcoded fixture, so operator-created categories never reach the header, drawer or footer

**Status:** OPEN — deferred to the Group S closure work package. Plan: `docs/plan-catalog-backed-navigation.md`. **Owner decision (2026-10-16): the navigation renders the categories the authoritative public API supplies (all active top-level) — not a curated subset and not a fixed six; reconciled at the end of Group S.**

**Evidence (verified):**

- `frontend/web/components/layout/category-navigation.fixture.ts:10-17` — six items, unconditionally, under the comment *"Slugs mirror Laravel's CategorySeeder taxonomy until catalog-backed global navigation is introduced."*
- Consumed by exactly three surfaces: `components/layout/primary-category-navigation.tsx:30` (header), `components/layout/mobile-navigation.tsx:97` (`"use client"` drawer), `components/layout/site-footer.tsx:48`.
- Composed by `components/layout/site-shell.tsx:54,63` and `components/layout/site-header.tsx:26`; `SiteShell` accepts `children` only, so no catalog data reaches the shell and `app/layout.tsx` stays sync with no `dynamic` export.
- Every other taxonomy consumer already reads the API: `lib/category/options.ts:6-21` (filters), `lib/homepage/catalog.ts:22-24` (homepage), `lib/seo/crawl.ts:33,37,80-81` (sitemap), `lib/category/catalog.ts` (detail). The fixture is now the **only** taxonomy consumer that does not.
- `frontend/web/AGENTS.md` still sanctions it as *"a fixture until Group N supplies authoritative catalog data"*, while the same file otherwise requires that category link slugs *"always come from Laravel, never from local slugification"*.
- No test renders any of the three surfaces (`grep` for `Primary navigation`, `Mobile navigation`, `PrimaryCategoryNavigation`, `SiteFooter`, `SiteShell` across `**/*.test.ts*` returns nothing), so the hardcoded list is unguarded in both directions.

**Impact:**

1. An operator-created category appears in the filter dropdown, search and the sitemap, but never in the three primary discovery surfaces — the storefront contradicts itself.
2. A rename or re-slug in the admin leaves the fixture pointing at the old slug → `/categories/{stale-slug}` → `404`. Nothing detects the drift: no build check, no test, no runtime check.
3. The header advertises a fixed six regardless of the real catalog size, with no indication the list is not live.

**Recommendation:** Retire the fixture and source all three surfaces from the public `GET /categories`, passing categories down from the server shell as props into the client drawer. Detailed, evidence-backed plan with the decisions that need an owner: `docs/plan-catalog-backed-navigation.md`. Note the two non-obvious consequences the plan documents: the shell-level read must never throw (it wraps every route), and the desktop row uses `flexWrap: "nowrap"` so it needs an explicit cap before it can render a live list.

**Owner:** Group S closure work package (with F10).

---

### F15 — Homepage room tiles are API-backed but silently capped at four categories

**Status:** OPEN — deferred to the Group S closure work package. Plan: `docs/plan-catalog-backed-navigation.md` §4.5.

**Evidence (verified):**

- `frontend/web/lib/homepage/catalog.ts:7,22-24` — reads the public `GET /categories` and returns `categories.data.slice(0, 4)`; rendered by `components/catalog/catalog-discovery.tsx:22-41` under "Start with your space".
- This is **not** a hardcoded fixture: `HOMEPAGE_DATA_SOURCE` defaults to `api` (`:7`) and is not set in any committed environment file.
- A newly created category is appended last: `backend/laravel/app/Services/Categories/CreateCategory.php:36` sets `display_order = max(children) + 1`.

**Impact:** The homepage tiles are live, but the rule that selects them — "the first four by `display_order`" — is undocumented, and it means a newly created category can never appear on the homepage while four categories exist. A category created in the admin is therefore visible in filters and the sitemap, invisible in the header/drawer/footer (F14), and invisible on the homepage. There is no "browse all categories" affordance on that section to compensate.

**Recommendation:** Decide the intent at Group S closure: keep a curated top-N only if the selection rule is made explicit and documented, or render the live list with a browse-all link. Do not leave "first four by insertion order, silently" as the de-facto editorial policy.

**Owner:** Group S closure work package (with F10/F14).

---

### F16 — Product reads report an unreadable response as a service outage

**Status:** CLOSED (2026-10-16) — fixed. The same defect the Phase 19.7 review found in the category module, missed in the product module.

**Evidence (verified):**

- `frontend/admin/lib/catalog/products-api.ts` `describeProductApiFailure` mapped only `ApiError` status codes, so a `ProductResourceError` (a response the parsers reject) fell through to `"unavailable"`.
- The module already declares `"invalid"` in `ProductApiFailure` and `STATUS_FAILURES` (409/422), so the state existed but was unreachable for a parse failure.
- `frontend/admin/components/catalog/product-failure-state.tsx:15` renders a different state for `"invalid"` (the unreadable-response default) than for `"unavailable"` (`ServiceUnavailableState`), so the misclassification changed what the operator saw and was told to do.
- The category analogue was fixed under Phase 19.7 (`lib/catalog/categories-api.ts`, `CategoryResourceError` → `"invalid"`); the product module was not covered by that change.

**Impact:** An unreadable product response asserted that the **product service was down** and implied a retry would help, when the actual condition was unreadable data. That is the wrong recovery guidance and a false outage signal.

**Recommendation:** Classify a parse failure as `"invalid"` before the status fallback, exactly as the category module does.

**Resolved (2026-10-16):** `describeProductApiFailure` now returns `"invalid"` for `ProductResourceError` ahead of the `ApiError` fallback. Regression test added — `frontend/admin/tests/products.test.tsx` "an unreadable response is classified as invalid data, not a service outage". Gates re-run green: lint, typecheck, 150 tests, build.

**Owner:** `frontend/admin` product module — closed.

---

### Group S closure work package — F10 + F14 + F15

These three are bundled for the end of Group S because their mitigation is the same kind of work (removing
deferral scaffolding from customer/operator-facing surfaces) and because they are the only findings whose
resolution is purely a product decision plus a small, self-contained frontend change:

| Item | Application | Nature |
| --- | --- | --- |
| F10 | `frontend/admin` | Remove `placeholder`/`plannedFor` (53 occurrences / 14 files) and user-visible `Phase 19.x` copy (8 strings / 3 files) |
| F14 | `frontend/web` | Retire the navigation fixture; read the catalog |
| F15 | `frontend/web` | Make the homepage tile selection rule deliberate |

F10 and F14/F15 touch **disjoint files in different applications**, so they can be done as separate commits
and verified in a single closure pass. Do not merge them into one change set on the grounds of convenience.

Plan for the web half: `docs/plan-catalog-backed-navigation.md`. F10's guard coupling (it must amend
`frontend/admin/AGENTS.md` and `tests/overview.test.tsx` together) is recorded in the F10 entry above.

---

## Recommendation summary

| ID | Finding | Recommendation | Owner | Blocking launch? |
| --- | --- | --- | --- | --- |
| F6 | "Two Clerk instances" — **withdrawn** | Do not build multi-instance verification | — | No (moot) |
| F1 | Category hierarchy not in the API | Additive **v2** listing; never widen v1. **Phase 19.7 closed PARTIAL-accepted (2026-10-16)** on this gap | new API v2 phase | No |
| F5 | Staff endpoints `501`; one usable account | **DECIDED (2026-10-16):** single-operator launch confirmed; orphan demo rows removed; backend remains 19.13 | 19.13 | No (decision taken) |
| F4 | `CAT-003` omits `page`/`per_page` | **DONE (2026-10-16)** — additive doc fix applied | — | No |
| F7 | JIT always assigns CUSTOMER | Document "grant role first, sign in second" | 19.13 + ops | No |
| F2 | Products deeper than the picker | Resolves with F1 | with F1 | No |
| F3 | Sitemap has 6 of 76 categories | Resolves with F1 | with F1 | No |
| F11 | Authenticated render never executed | Operator walks 8 routes once (accepted 2026-10-16) | operator | No |
| F10 | Roadmap jargon deferred | One coherent pass after Group S | end of Group S | No |
| F8 | Variants `501` | Keep read-only | — | No |
| F9 | Media deferred to 19.8 | **Upload + preview DONE (2026-10-16, 19.8A)**; deletion/ordering/alt-text/primary still need a backend contract | 19.8B/19.8C | No |
| F12 | Fallow metrics unverified | Re-run Fallow before gating on it | next quality phase | No |
| F13 | Unreferenced `confirm-dialog.tsx` | Consume or remove | 19.13 | No |
| F14 | Nav header/drawer/footer hardcode the taxonomy | Retire the fixture; read the catalog (`docs/plan-catalog-backed-navigation.md`); **set = API-supplied (owner, 2026-10-16)** | Group S closure | No |
| F15 | Homepage tiles silently capped at 4 | Make the selection rule deliberate (`docs/plan-catalog-backed-navigation.md` §4.5) | Group S closure | No |
| F16 | Unreadable product response shown as an outage | **DONE (2026-10-16)** — parse failure now classified `"invalid"` | `frontend/admin` | No |

**Group S closure bundle:** F10 + F14 + F15 (see the subsection above) — one closure pass, separate commits.

**No finding blocks the request-only launch.** F5 was the only launch-blocking candidate, and only if more than one operator were needed before Phase 19.13; the owner confirmed a single bootstrapped ADMIN is sufficient (2026-10-16). Everything else is a delay with a named owner and a reversal plan.

# Findings Register — Owner Decisions and Mitigation Plan

**Requested:** 2026-10-15. **Decided and executed:** 2026-10-16.

## Decisions recorded (owner, 2026-10-16)

1. **Single-operator launch model (F5/F7).** *Requested:* confirm whether one bootstrapped ADMIN is sufficient for the initial launch.
   **Owner decision:** Confirmed — one bootstrapped `ADMIN` is sufficient for launch. Recorded as an operational fact, not a defect; a second operator is not a pre-launch dependency.

2. **Category hierarchy at launch (F1/F2/F3).** *Requested:* confirm whether the catalog can launch with six selectable top-level categories.
   **Owner decision:** Launch with the number of categories **supplied by the authoritative Laravel backend API** — not a hardcoded six. Deeper categories stay reachable through the existing product/category routes. To be reconciled at the end of Phase Group S. This settles which set the global navigation renders (`docs/plan-catalog-backed-navigation.md` §9.1); it does **not** authorise widening the frozen `/api/v1/categories`.

3. **Orphaned demo records (F5).** *Requested:* investigate the records' provenance and dependencies, then retain, link, or remove them.
   **Owner decision:** Remove the orphaned, unlinked records; leave the users that carry a Clerk identity. Remove the notifications and carts that block the delete.
   **Executed (2026-10-16), `user 2` — `staff@example.com`, `clerk_user_id = NULL`, role `STAFF`:** deleted under a two-part guard (`app()->environment() !== 'production'` **and** database name `furnitureapp`), inside a single transaction. Removed in child-first order: 5 `notification_email_deliveries`, 8 `notifications`, 1 `staff_profiles` (FK `CASCADE`), 1 `model_has_roles` pivot. The schema's own `SET NULL` rule cleared 6 `order_status_history.actor_id` references. No `carts` existed for this row. Verified afterwards: 0 rows for the id in every dependent table.
   **Executed (2026-10-16), `user 1` — `customer@example.com`, `clerk_user_id = NULL`, role `CUSTOMER`** (owner approved removing the account together with its four demo orders): same guard, single transaction. Order dependencies resolved child-first — `enquiries.order_id` (`RESTRICT`, nullable) was set to `NULL` rather than deleting the enquiry; 2 `payments` and 1 `deliveries` are `RESTRICT` and were deleted; `orders` then cascaded 5 `order_items` (→ `order_item_inventory_allocations`) and 16 `order_status_history` rows. 3 `cart_items` and 2 `carts` deleted; 1 `notification` deleted; 1 `model_has_roles` pivot deleted; the user row then cascaded 1 `customer_profiles`.
   **Outcome (verified 2026-10-16):** `users` = 2 (`user 4` ADMIN, `user 6` CUSTOMER — both Clerk-linked). `orders`, `order_items`, `order_status_history`, `payments`, `deliveries` are all empty. A post-delete integrity sweep found **0** orphaned rows across every foreign key touched (`payments`, `deliveries`, `order_items`, `order_status_history` → `orders`; `cart_items` → `carts`; `notification_email_deliveries` → `notifications`; `notifications`, `enquiries`, `furniture_requests` → `users`).

4. **OpenAPI pagination documentation (F4).** *Requested:* approve a narrow documentation change adding the existing `page` and `per_page` parameter references to CAT-003.
   **Owner decision:** Approved.
   **Executed (2026-10-16):** `CAT-003` (`GET /categories`) now declares `$ref: '#/components/parameters/Page'` and `$ref: '#/components/parameters/PerPage'`, matching `CAT-001`. Three added lines; runtime behaviour, paths, schemas, and the rest of the frozen document are untouched. The document re-parses cleanly.

5. **Authenticated smoke test (F11).** *Requested:* the owner will test the implemented product and category routes with the authorised admin account and safe test data.
   **Owner decision:** Accepted. F11 stays OPEN until the result is written back here; the Phase 19.6/19.7 records continue to report live behaviour as unverified until then.

6. **Phase 19.7 closure (F1/F4/F11).** *Requested:* confirm whether Phase 19.7 can be closed given its PARTIAL verdict.
   **Owner decision:** Close **Phase 19.7 as PARTIAL — accepted (2026-10-16)**; do **not** relabel it PASS, because the verdict rests on a missing backend capability, not on the delivered work. The blocking gap is **F1** — there is no operational/admin category listing endpoint, so `/categories` can only manage the active top-level categories returned by the public `GET /api/v1/categories`. Resolving it requires an approved `/api/v2/categories` contract phase and must never be done by widening the frozen `/api/v1/categories`. **F4** is CLOSED (the additive `CAT-003` documentation fix was applied); **F11** (authenticated live render) remains OPEN. F5 is an accepted operational fact, not a blocker. Closure unblocks Phase 19.8A.
   **Record:** `phases/phase-19.7-completion.md` → Status → "Closure (2026-10-16)".

### Carried over from the decisions above

- **Seeded demand rows removed; genuinely anonymous submissions preserved.** Deleting `user 1`/`user 2` triggers the schema's `SET NULL` on `enquiries.user_id`/`furniture_requests.user_id`, and the public API accepts no-login submissions, so `user_id` (and even `carts.user_id`) is nullable by design. The leftovers were therefore a **mix**, not one disposable batch.
  - **Removed (2026-10-16):** the seeded subset only — enquiries `1–4` and furniture requests `1–3` (faker identities, all created `2026-10-13 15:15:54`). Deleted under the standard guard with a signature assertion on `created_at` + `user_id IS NULL`, inside one transaction: `enquiries` 6 → 2, `furniture_requests` 7 → 4.
  - **Preserved (verified):** furniture requests `4` (anonymous email submission, contact redacted), `5` (`David`), `6` (`donny`, `user 4`), `7` (`Operational notification email test`); enquiries `5` (`David`, `user 4`), `6` (`Phase 18.6 smoke test`). `user_id IS NULL` was **not** used as the delete predicate.
  - The `carts`/`cart_items`/`notifications` records belong to the two Clerk-linked users or are a single anonymous guest cart — legitimate, untouched.
  Recorded in `docs/plan-remove-hardcoded-demo-fixtures.md` §3 Phase 2 item 4.
- **Demo-data removal is a separate, later step.** The owner intends to remove demo data so that Laravel is the sole authority of truth once Phase Group S completes — sequenced in `docs/plan-remove-hardcoded-demo-fixtures.md`. That work is distinct from F10 (user-facing roadmap jargon) and from the `HOMEPAGE_DATA_SOURCE=fixtures` preview switch.

## Accepted limitations pending their owning phases

- F8: Variant creation remains unavailable while CAT-010 returns `501`.
- F9: Product image upload + preview shipped in Phase 19.8A; deletion, ordering, alt-text editing and primary selection remain deferred until a backend media mutation contract exists.
- F10: Roadmap-jargon cleanup remains deferred until the end of Group S.
- F12: Fallow metrics remain unverified until the tool can be run.
- F13: The unused confirmation dialog will be reused or removed in its owning phase.
- F6: The alleged multi-Clerk-instance issue remains withdrawn; no multi-instance implementation is authorized.

## Change-control rules

- Do not silently widen the frozen v1 category API.
- Do not invent permissions, endpoints, request fields or state transitions.
- Do not hand-link Clerk identities or hand-assign staff roles as an operational workaround.
- Keep the findings register updated with evidence, owner, status and verification results.
