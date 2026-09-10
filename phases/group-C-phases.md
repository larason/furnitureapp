# Group C phases instructions

# Phase 3.6 — Product Images Schema

## Purpose

Implement the database and Laravel model foundation for product images.

The schema must support:

* multiple images per product;
* deterministic gallery ordering;
* one primary product image;
* optional association of an image with a specific product variant;
* customer-facing alt text;
* future CDN/object-storage delivery;
* public catalog serialization;
* variant-specific imagery such as a particular color/fabric configuration;
* staged-room image support without coupling images to product pricing, inventory, or orders.

This phase creates the persistent image relationship only.

Do not implement image upload endpoints, image processing, CDN integration, 3D/AR assets, video, or the public Product API in this phase.

The existing V1 catalog convention defines product images as small embedded child data:

```json
{
  "id": 1,
  "url": "...",
  "alt_text": "...",
  "sort_order": 1,
  "is_primary": true
}
```

and explicitly rejects a separate:

```text
GET /products/{product}/images
```

## endpoint.

# Dependencies

Required:

* Phase 2.1–2.12 completed.
* Phase 3.1 Users Schema completed.
* Phase 3.2 Roles/permissions completed.
* Phase 3.3 Categories Schema completed.
* Phase 3.4 Products Schema completed.
* Phase 3.5 Product Variants Schema completed.
* Existing Laravel migration/model/test conventions operational.

Authoritative inputs:

* `docs/VISION.md`
* `docs/api/api-contract.md`
* `docs/api/api-resources.md`
* `docs/api/api-conventions.md`
* `docs/domain/business-rules.md`
* `docs/decisions.md`
* `AGENTS.md`

Do not reopen frozen API envelope, naming, money, authentication, or authorization decisions.

---

# 3.6.1 Image entity responsibility

Create a dedicated:

```text
product_images
```

table.

Do not store image URLs directly on `products` or `product_variants`.

The relationship is:

```text
Product
   │
   ├── Image
   ├── Image
   ├── Image
   └── Image
          │
          └── optionally associated with Variant
```

The image belongs to the Product.

A nullable `product_variant_id` may identify that the image specifically represents one variant.

This supports both:

```text
Product-wide image
```

and:

```text
Variant-specific image
```

without duplicating the Product record.

---

# 3.6.2 Recommended table structure

Create:

```text
product_images
```

with:

```text
id
product_id
product_variant_id
file_path
alt_text
sort_order
is_primary
created_at
updated_at
```

Recommended migration:

```php
Schema::create('product_images', function (Blueprint $table) {
    $table->id();

    $table->foreignId('product_id')
        ->constrained('products')
        ->cascadeOnDelete();

    $table->foreignId('product_variant_id')
        ->nullable()
        ->constrained('product_variants')
        ->nullOnDelete();

    $table->string('file_path');
    $table->string('alt_text', 500)->nullable();

    $table->unsignedInteger('sort_order')->default(0);
    $table->boolean('is_primary')->default(false);

    $table->timestamps();

    $table->index(['product_id', 'sort_order']);
    $table->index(['product_id', 'is_primary']);
    $table->index(['product_variant_id', 'sort_order']);
});
```

Adapt the exact migration syntax to existing project conventions.

Do not duplicate indexes unnecessarily if the chosen database/index strategy already covers the required access path.

---

# 3.6.3 Product relationship

Add:

```php
public function images(): HasMany
{
    return $this->hasMany(ProductImage::class)
        ->orderBy('sort_order');
}
```

The relationship should return images in deterministic presentation order.

Do not depend on insertion order.

Do not use random ordering.

---

# 3.6.4 Variant relationship

Add to `ProductVariant`:

```php
public function images(): HasMany
{
    return $this->hasMany(ProductImage::class)
        ->orderBy('sort_order');
}
```

This allows the application to retrieve images representing a specific variant.

The same image row belongs to one Product and may optionally point to one Variant belonging to that Product.

---

# 3.6.5 Image model

Create:

```text
App\Models\ProductImage
```

with:

```php
public function product(): BelongsTo
{
    return $this->belongsTo(Product::class);
}

public function productVariant(): BelongsTo
{
    return $this->belongsTo(ProductVariant::class);
}
```

Use nullable relationship typing for `productVariant`.

Do not introduce image-processing behavior into the model.

---

# 3.6.6 Product/variant consistency

If `product_variant_id` is populated:

```text
product_images.product_variant_id
```

must reference a variant belonging to:

```text
product_images.product_id
```

In other words:

```text
Image Product A
+
Variant Product B
```

must never be a valid logical association.

A simple pair of foreign keys does not automatically enforce this cross-table relationship at the database level.

Therefore the application/domain boundary must validate:

```text
variant.product_id === image.product_id
```

before persistence.

Add a focused test for this invariant.

Do not use arbitrary variant IDs from client input to prove ownership.

The backend must resolve the actual Product and Variant relationship.

---

# 3.6.7 Product deletion behavior

When a Product is deleted through an authorized future lifecycle operation:

```text
Product
→ related Product Images
```

may be deleted through the database cascade.

This removes image records that have no remaining Product parent.

Do not interpret this as permission to delete actual files immediately in this phase.

Physical file cleanup requires a separate storage lifecycle decision and is out of scope.

---

# 3.6.8 Variant deletion behavior

If a Product Variant is removed:

```text
product_variant_id
→ null
```

rather than deleting the image row.

Use:

```php
->nullOnDelete()
```

because an image can remain a valid Product-level image even after its variant association disappears.

Do not cascade-delete Product Images when a Variant is removed.

---

# 3.6.9 File path

Use:

```text
file_path
```

to identify the stored image asset.

This should be a storage reference, not an arbitrary user-submitted public URL.

Examples of appropriate internal values:

```text
products/123/gallery/front.jpg
products/123/gallery/side.webp
products/123/variants/501/green-front.webp
```

Do not store:

```text
https://cdn.example.com/...
```

as the database's authoritative internal storage reference unless the project's later storage architecture explicitly chooses that design.

The eventual API resource should resolve the storage reference into the public URL.

Do not expose internal storage keys if the final storage design considers them sensitive.

---

# 3.6.10 Supported image formats

Do not create a free-form `mime_type` requirement merely to duplicate storage metadata.

The later upload boundary must enforce an explicit image allow-list.

At minimum the existing file-security conventions establish safe image handling requirements for JPEG, PNG, and WebP where image uploads are permitted.

The upload/processing phase must verify actual file content rather than trusting the client-supplied MIME type.

Do not implement upload validation in Phase 3.6.

---

# 3.6.11 Image URL generation

Do not persist a `url` column merely for API convenience.

The future Product API should produce:

```json
{
  "id": 1,
  "url": "https://cdn.example.com/...",
  "alt_text": "Forest green lounge chair in a living room",
  "sort_order": 1,
  "is_primary": true
}
```

where `url` is derived from:

```text
storage configuration
+
file_path
+
public delivery mechanism
```

This keeps the database independent from the eventual CDN/storage provider.

---

# 3.6.12 Alt text

Use:

```text
alt_text
```

as optional descriptive accessibility metadata.

It should describe the image's meaningful visual content.

Examples:

```text
Forest green lounge chair with natural oak legs
Three-seater sofa in charcoal fabric
Walnut executive desk viewed from the front
```

Do not use:

```text
image1
photo123
sofa-final
IMG_1024
```

as meaningful accessibility descriptions.

Do not generate alt text from arbitrary filenames.

Do not store HTML in `alt_text`.

Do not treat alt text as SEO keyword stuffing.

---

# 3.6.13 Nullability of alt text

`alt_text` may initially be nullable because image ingestion may occur before editorial metadata is complete.

Do not substitute:

```text
""
```

for unknown alt text.

The project's conventions distinguish `null`, empty strings, false, and absent values and require consistency within the API contract.

The later Product API must define whether unavailable alt text is represented as `null` or omitted.

Do not change that behavior casually once the public V1 representation is frozen.

---

# 3.6.14 Sort order

Use:

```text
sort_order
```

for deterministic gallery ordering.

Example:

```text
1 → front image
2 → side image
3 → rear image
4 → detail image
```

Use non-negative integers.

Do not rely on the database primary key for display order.

Do not use `created_at` as a substitute for gallery order.

---

# 3.6.15 Primary image

Use:

```text
is_primary
```

to identify the product's primary gallery image.

The logical Product-level invariant is:

```text
A product has at most one primary image.
```

The recommended implementation must ensure this invariant at the application/domain layer and test it.

Do not rely solely on:

```text
is_primary = true
```

rows being unique because a standard boolean column cannot express "one true row per product" portably.

When a future primary-image mutation is implemented, the operation must update the old primary and new primary atomically.

Do not implement the mutation API in this phase.

---

# 3.6.16 Variant-specific primary images

Do not create multiple independent concepts such as:

```text
is_product_primary
is_variant_primary
```

in this phase.

`is_primary` remains the canonical image-presentation flag.

If variant-specific primary behavior is required later, define it explicitly in the Product/Variant API contract rather than silently creating ambiguous semantics.

At this stage:

```text
Product image collection
→ one product-level primary image
```

and:

```text
Variant image collection
→ ordered images
```

are sufficient.

---

# 3.6.17 Staged-room images

The furniture platform needs room-staging imagery eventually.

For Phase 3.6, staged-room imagery should still be represented as an image record rather than a completely separate image table.

However, do **not** add a generic:

```text
type = image
type = staged_room
```

column unless the current API/domain contract formally defines an image-type vocabulary.

The current frozen product image representation does not require a `type` field.

Therefore keep Phase 3.6 limited to:

```text
product_images
```

with the core image metadata.

Any future distinction between:

```text
product photo
staged-room render
lifestyle image
detail image
```

must be introduced as an explicit schema/API decision rather than an ad hoc string.

---

# 3.6.18 Product media separation

Do not create:

```text
product_media
```

in this phase.

Do not combine:

```text
images
videos
3D assets
AR assets
documents
```

into a single generic table without a defined contract.

The roadmap deliberately separates Product Images from later product asset work.

Keep the current phase focused.

---

# 3.6.19 3D/AR separation

Do not add:

```text
glb_path
usdz_path
gltf_path
model_url
ar_url
```

to `product_images`.

3D and AR assets are separate product assets and should be implemented in their own schema phase when scheduled.

---

# 3.6.20 Image dimensions and technical metadata

Do not add speculative technical columns such as:

```text
width_px
height_px
filesize
mime_type
checksum
blurhash
dominant_color
```

unless there is an explicit current requirement.

Some of these may be valuable later, especially for optimization and duplicate detection, but they should be introduced based on an actual storage/image-processing contract.

Do not turn the first image schema into a general digital-asset-management system.

---

# 3.6.21 Product-image ordering query

The canonical Product image relationship should return:

```text
ORDER BY sort_order ASC
```

with a deterministic tie-breaker.

Use:

```text
sort_order ASC
id ASC
```

when constructing an explicit query that requires deterministic results even when two images accidentally share the same `sort_order`.

Do not make `sort_order` globally unique.

Multiple images belonging to different products can share the same ordering values.

---

# 3.6.22 Variant-image ordering query

Likewise, Variant image queries should use:

```text
sort_order ASC
id ASC
```

rather than insertion order.

This ensures consistent results across MySQL queries, API responses, Next.js rendering, and Flutter rendering.

---

# 3.6.23 Image visibility

Do not add a second image-specific publication state such as:

```text
is_published
is_visible
status
```

in this phase.

For V1, catalog exposure can be determined from:

```text
Product.is_active
Variant.is_active
```

and the later catalog representation rules.

If image-level moderation or publication becomes necessary, it should be introduced through an explicit future contract.

Do not create a hidden image workflow now.

---

# 3.6.24 Public serialization

The eventual Product Detail API should expose image objects using the established structure:

```json
{
  "id": 1,
  "url": "https://cdn.example.com/products/chair-front.webp",
  "alt_text": "Forest green lounge chair with natural oak legs",
  "sort_order": 1,
  "is_primary": true
}
```

Do not return:

```json
[
  "https://cdn.example.com/one.jpg",
  "https://cdn.example.com/two.jpg"
]
```

The API convention requires consistent object-based image representation.

Do not expose:

```text
file_path
internal storage key
filesystem path
bucket name
upload token
private storage metadata
```

to the public API.

Use explicit resource serialization rather than:

```php
$model->toArray()
```

because the project requires field-level serialization before output.

---

# 3.6.25 Public catalog and caching

Product images form part of the public catalog representation.

The eventual public Product API may therefore be publicly cacheable where the Product itself is public.

However:

* private/internal storage identifiers must not be exposed;
* unpublished products/images must not leak through catalog APIs;
* customer-specific information must never be mixed into product image responses.

The project explicitly defines public catalog responses as safe for CDN/Next.js caching and requires unpublished product/category state to be masked rather than exposed.

Do not configure CDN caching in this phase.

---

# 3.6.26 Storage lifecycle

Do not implement physical storage deletion in this phase.

The database record and physical image file are separate resources.

Later storage logic must account for:

```text
database insert succeeds
file upload fails

file upload succeeds
database insert fails

image record deleted
file cleanup pending
```

Do not solve these cases with a complex storage job system now.

Record the need for controlled storage lifecycle handling if the project decision log requires it.

---

# 3.6.27 Image ownership and authorization

Product images belong to the Product catalog, not to customers.

Future image creation/management must require the appropriate product-management authorization.

The existing RBAC model defines `products.manage` as catalog management and deliberately separates it from inventory management.

Do not implement the authorization middleware or product-management endpoints in Phase 3.6.

Do not allow CUSTOMER clients to create arbitrary product images.

---

# 3.6.28 Mass-assignment protection

When image write APIs are introduced later, never use:

```php
$request->all()
```

to hydrate ProductImage.

Use:

```text
validated input
→ explicit allow-list
→ DTO/command
→ authorization
→ domain validation
→ persistence
```

Server-controlled fields include:

```text
id
product_id
storage path
created_at
updated_at
```

and any future derived/public URL.

The existing backend convention explicitly prohibits request-wide mass assignment.

---

# 3.6.29 Security considerations for future uploads

The schema must not be designed around trusting the client.

A future upload boundary must:

* validate actual file content;
* enforce allowed image types;
* enforce maximum size;
* sanitize filenames;
* store files outside executable application paths where appropriate;
* generate server-controlled storage paths;
* prevent path traversal;
* prevent public access to private upload locations;
* produce safe public delivery URLs.

The existing attachment/file security conventions require client MIME to be untrusted and actual file signatures to be checked.

Do not implement upload processing here.

---

# 3.6.30 Recommended image URL architecture

Keep this relationship:

```text
Database
    product_images.file_path
              ↓
Storage service
              ↓
Public URL
              ↓
API ProductImage resource
```

Do not make:

```text
database.file_path
=
public URL
```

the mandatory architecture.

This leaves room for:

* local storage in development;
* object storage in production;
* CDN delivery;
* signed URLs where a future private asset requires them.

Public product images should eventually use stable public delivery URLs without exposing infrastructure details.

---

# 3.6.31 Image model casts

Use appropriate casts for:

```text
is_primary → boolean
sort_order → integer
```

Do not cast `file_path` or `alt_text` into custom structures.

Do not use JSON for ordinary image metadata.

---

# 3.6.32 Product image queries

Provide only model relationship/query behavior needed by this phase.

Expected relationship operations:

```text
Product → images
Variant → images
Image → product
Image → productVariant
```

Do not create a repository abstraction unless the existing project architecture already requires repositories.

Do not create a generic media repository for one image table.

---

# 3.6.33 Cognitive-complexity and maintainability requirement

**Mandatory code-quality recommendation for this phase:**

Whenever the agent touches or creates functions in the Product Image implementation, keep each function's **cognitive complexity at or below the recommended threshold of 15**.

If an existing function involved in this phase exceeds the threshold:

1. refactor it as part of the phase;
2. split nested conditional logic into small cohesive functions;
3. move domain decisions into focused services/value objects/helpers where justified;
4. reduce nesting with guard clauses where that improves clarity;
5. avoid creating long boolean expressions;
6. keep each extracted function narrowly responsible.

Do not "fix" complexity by suppressing analyzer warnings.

Do not merely increase the configured threshold.

The purpose is maintainability and reduced future change cost.

---

# 3.6.34 Return-statement limit

Functions introduced or refactored in this phase should contain **no more than 3 return statements**.

Treat this as a project quality rule.

When a function has more than three returns:

* simplify the control flow;
* extract decision logic;
* use an explicit result/value variable where that improves readability;
* split the function if it has more than one clear responsibility.

Do not make code harder to read merely to reduce the count.

Do not replace several returns with a deeply nested conditional structure.

The target is simpler control flow, not mechanical metric compliance.

Add/refine static-analysis configuration so this rule is visible during development where the project's tooling supports it.

---

# 3.6.35 Duplicate string literals

Do not repeatedly hard-code the same meaningful string literals.

Use centralized constants/enums/value objects when the same literal has semantic significance.

Examples:

```php
private const DEFAULT_SORT_ORDER = 0;
private const DEFAULT_IS_PRIMARY = false;
```

More importantly, for repeated domain/storage identifiers, use the appropriate centralized constant rather than repeating:

```text
product_images
product_id
product_variant_id
file_path
alt_text
sort_order
is_primary
```

through unrelated code when a project-level constant abstraction is genuinely beneficial.

However, **do not create a giant "StringConstants" class for every ordinary string in the application**.

Use constants where duplication represents a real shared concept.

The goal is to prevent the same semantic literal from drifting across:

* models;
* validators;
* services;
* tests;
* serializers.

Do not solve duplication by replacing clear ordinary strings with meaningless constants everywhere.

---

# 3.6.36 Constant naming

When constants are appropriate, use descriptive names.

Prefer:

```php
private const PRIMARY_FLAG = 'is_primary';
private const DEFAULT_SORT_ORDER = 0;
```

over:

```php
private const X = 'is_primary';
private const VALUE_1 = 0;
```

Do not create constants whose names merely repeat the value without explaining the domain meaning.

---

# 3.6.37 Tests for maintainability rules

Where the project's static-analysis tooling supports cognitive-complexity and return-count rules, ensure Phase 3.6 code passes them.

The implementation must not introduce:

* cognitive complexity above 15 for new/refactored functions;
* more than 3 return statements in a function;
* duplicated meaningful literals that the project's analyzer identifies as a maintainability problem.

If an existing Product/ProductVariant function must be touched to support images and already violates these rules, refactor it rather than building new logic around the complexity.

Keep refactoring scoped to code affected by this phase.

Do not undertake an unrelated repository-wide cleanup.

---

# 3.6.38 Tests

Create focused tests alongside implementation.

## Migration tests

Verify:

* migration succeeds from an empty database;
* rollback succeeds;
* Product is required;
* optional Variant reference works;
* deleting Product removes its image records;
* deleting Variant nulls `product_variant_id`;
* ordering indexes exist;
* boolean/integer fields have correct defaults.

## Relationship tests

Verify:

```text
Product → images
Variant → images
Image → product
Image → variant
```

Verify images are returned in:

```text
sort_order ASC
```

order.

Where equal sort order values are possible, verify deterministic `id ASC` tie-breaking in explicit queries.

## Product/Variant consistency tests

Verify:

```text
Product A + Variant A → valid
Product A + Variant B → rejected
```

when Variant B belongs to Product B.

This is an important integrity boundary.

## Primary image tests

Verify:

* a product can have a primary image;
* the application prevents two primary images for the same product;
* non-primary images remain valid;
* default value is `false`;
* public serialization uses the boolean representation.

## Alt text tests

Verify:

* alt text can be null;
* valid descriptive text persists;
* HTML is not silently introduced as trusted content.

## Storage reference tests

Verify:

* file path is persisted;
* file path is not treated as the public URL;
* storage implementation details are not included in the public image representation tests.

## Serialization tests

Verify the intended public representation contains:

```text
id
url
alt_text
sort_order
is_primary
```

and does not contain:

```text
file_path
internal storage keys
database internals
private metadata
```

The final API resource belongs to a later API phase, so use model/resource tests only if the project already has the necessary resource layer.

---

# 3.6.39 Factory support

A `ProductImageFactory` may be introduced for testing.

It should:

* create a valid Product;
* optionally create a valid Variant belonging to that Product;
* generate a deterministic-looking storage path;
* generate realistic alt text;
* assign a sensible sort order;
* default `is_primary` to false.

Do not upload actual files from the factory.

Do not create real CDN URLs.

Do not create media, video, or AR assets.

Provide an explicit test state for a primary image rather than making every factory record primary.

---

# 3.6.40 Seed data

Do not add production image files in this phase.

Do not seed external image URLs.

Development seed data may create image database records only if the existing application has a defined local asset convention.

Never make development seeds depend on external websites remaining available.

---

# 3.6.41 API scope

Do not implement:

```text
GET /products/{product}/images
POST /products/{product}/images
PATCH /products/{product}/images/{image}
DELETE /products/{product}/images/{image}
```

The established V1 catalog convention explicitly embeds images in Product detail and rejects a dedicated images endpoint.

The eventual image management mechanism will be part of the appropriate catalog/admin implementation.

---

# 3.6.42 API representation boundary

The database uses:

```text
file_path
sort_order
is_primary
```

The public API uses:

```text
url
alt_text
sort_order
is_primary
```

Do not make database naming dictate API naming.

The API continues to use:

```text
snake_case
```

and the established response envelope when implemented.

---

# 3.6.43 Performance considerations

Create indexes that support:

```text
product → ordered images
product → primary image
variant → ordered images
```

Do not optimize prematurely with:

* image caches;
* database denormalization;
* precomputed primary-image columns on Product;
* serialized image arrays;
* custom image read models.

The catalog API phase can optimize query loading once real access patterns are implemented.

---

# 3.6.44 Code quality

Expected components:

```text
ProductImage migration
ProductImage model
Product relationship
ProductVariant relationship
focused tests
optional factory
```

Avoid:

* generic media repositories;
* upload services;
* image-processing pipelines;
* thumbnail generators;
* CDN integrations;
* image moderation;
* 3D/AR services;
* video handling;
* recommendation logic.

Keep comments to the absolute minimum.

New/refactored functions must satisfy:

```text
Cognitive complexity <= 15
Return statements <= 3 per function
No unjustified duplicated meaningful string literals
```

Do not suppress warnings to make the metrics pass.

---

# 3.6.45 Documentation / durable decisions

Update `docs/decisions.md` only if a durable architectural decision needs recording, such as:

* product images stored independently from Product;
* image storage uses internal `file_path`, with public URL derived later;
* variant association is optional;
* Product image deletion and Variant deletion have different relationship semantics;
* public image representation excludes internal storage identifiers.

Do not create a permanent Phase-3.6 markdown file merely to duplicate this instruction.

---

# Explicitly out of scope

Do not implement:

* image upload APIs;
* image replacement APIs;
* image deletion APIs;
* image editing;
* image resizing;
* thumbnails;
* WebP conversion;
* image optimization pipeline;
* CDN integration;
* object-storage integration;
* signed URLs;
* image moderation;
* video;
* staged-room taxonomy;
* 3D models;
* AR assets;
* Product Asset schema;
* Inventory;
* Product search;
* Product filtering;
* Product API;
* Variant API;
* staff product UI;
* Admin product UI;
* Next.js image gallery;
* Flutter image gallery;
* recommendation engine.

---

# Definition of done

Phase 3.6 is complete only when:

1. `product_images` exists.
2. Every image belongs to exactly one Product.
3. An image may optionally reference a Product Variant.
4. Variant association is validated against the same Product.
5. Image storage uses an internal `file_path`, not an authoritative public URL.
6. `alt_text` is available.
7. `sort_order` provides deterministic image ordering.
8. `is_primary` identifies the Product's primary image.
9. The application enforces at most one primary image per Product.
10. Product deletion removes dependent image records.
11. Variant deletion nulls the image's Variant reference rather than deleting the image.
12. Product and Variant image relationships exist in Eloquent.
13. Images are not stored directly on Product or Product Variant.
14. Video, 3D, AR, and generalized media are not mixed into this schema.
15. No separate images API endpoint is created.
16. Public serialization is prepared for `id`, `url`, `alt_text`, `sort_order`, and `is_primary`.
17. Internal storage paths are not exposed in public serialization.
18. Migrations run successfully from an empty database and roll back successfully.
19. Tests cover relationships, ordering, Product/Variant consistency, primary-image integrity, nullability, and deletion behavior.
20. Static analysis and formatting pass.
21. New/refactored functions have cognitive complexity no greater than 15.
22. New/refactored functions contain no more than 3 return statements.
23. Meaningful duplicated string literals are replaced with appropriate constants/enums where duplication exists.
24. No analyzer warnings are suppressed merely to satisfy the maintainability rules.
25. No later-phase image processing, storage, API, or frontend functionality has been implemented.
26. Code comments remain minimal.

---

# STOP condition

Stop after the Product Image schema, migration, Eloquent relationships, integrity rules, tests, and necessary maintainability refactoring are complete.

Do not continue into Phase 3.7 Inventory Schema.

Do not implement upload/storage processing, CDN handling, image APIs, video, 3D/AR assets, or frontend galleries.

Do not commit, stage, or push changes.
