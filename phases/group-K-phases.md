# Phase 11.5 — Product Image Management

## 1. Objective

Implement Version 1 Product image upload and public delivery using:

```text
Cloudflare R2
+
Cloudflare custom-domain CDN delivery
```

The business owner manually optimizes Product images before upload.

Laravel must perform:

```text
authentication
authorization
security validation
safe object-key generation
R2 upload
database persistence
public CDN URL projection
failure compensation
```

Laravel must NOT perform:

```text
resizing
compression
format conversion
thumbnail generation
WebP conversion
AVIF conversion
image optimization
Cloudflare Images transformations
```

Cloudflare Images is NOT part of this architecture.

---

# 2. Final Media Architecture

Use:

```text
Admin
  ↓
manually optimized image
  ↓
Laravel API
  ├── authenticate
  ├── authorize
  ├── validate actual file
  └── generate object key
  ↓
Cloudflare R2
  ↓
R2 custom domain
  ↓
Cloudflare CDN/cache
  ↓
Next.js / Flutter
```

Example public delivery:

```text
https://assets.example.com/products/prod_xxx/img_xxx.webp
```

The exact domain will be supplied later by the project owner through `.env`.

---

# 3. Existing Persistence Architecture

Preserve the established:

```text
product_images
```

architecture.

The authoritative storage reference remains:

```text
file_path
```

It stores an internal object-storage key, not a CDN URL.

Example:

```text
products/prod_01ABC/img_01XYZ.webp
```

Do NOT persist:

```text
https://assets.example.com/products/prod_01ABC/img_01XYZ.webp
```

as the authoritative database value.

The existing design deliberately separates Product images from Products and ProductVariants and keeps storage/CDN provider details out of the database.

---

# 4. Existing Product Image Fields

Preserve the current schema:

```text
id
product_id
product_variant_id
file_path
alt_text
sort_order
is_primary
timestamps
```

Do not add speculative metadata columns such as:

```text
mime_type
filesize
width_px
height_px
checksum
blurhash
dominant_color
status
is_visible
is_published
```

The existing Product-image ADR explicitly excluded those columns.

---

# 5. Existing Domain Invariants

Preserve:

```text
ProductImage belongs to exactly one Product
optional ProductVariant association
variant must belong to same Product
stable ordering by sort_order ASC, id ASC
at most one primary image per Product
```

Do not weaken existing application or database primary-image enforcement.

---

# PART A — CAT-009 CONTRACT RECONCILIATION

## 6. Frozen Endpoint

Implement:

```http
POST /api/v1/products/{product}/images
```

Endpoint:

```text
CAT-009
```

Authorization:

```text
products.manage
```

Actors:

```text
STAFF
ADMIN
```

subject to explicit permission.

The catalog contract defines CAT-009 as the single Product image mutation endpoint in V1.

---

# 7. Frozen Request Shape

The current OpenAPI request is:

```text
multipart/form-data
```

with one field:

```text
image
```

binary.

Do not expand the request to:

```text
alt_text
sort_order
is_primary
variant_id
file_path
url
filename
```

in this phase.

Preserve the external CAT-009 request shape.

---

# 8. Confirmed OpenAPI Response Error

Current CAT-009 OpenAPI incorrectly defines:

```text
201.data → Attachment
```

This is inconsistent with the Product Image domain.

Correct it to:

```text
201.data → ProductImage
```

This is a frozen-contract consistency correction.

It is NOT a new API concept.

CAT-009 creates:

```text
ProductImage
```

not:

```text
Request/Enquiry Attachment
```

---

# 9. ProductImage Response

Return:

```json
{
  "data": {
    "id": "img_...",
    "url": "https://assets.example.com/products/prod_.../img_....webp",
    "alt_text": "Walnut Dining Table",
    "sort_order": 1,
    "is_primary": true
  }
}
```

according to the existing ProductImage schema.

Never expose:

```text
file_path
R2 bucket
R2 endpoint
access key
secret
internal filesystem disk
```

---

# 10. Server-Controlled Image Metadata

Because CAT-009 accepts only the file, define the following V1 server rules:

```text
product_variant_id = null

alt_text = current Product name

sort_order = append after existing Product images

is_primary = true when Product currently has no primary image
             false otherwise
```

These values are server-derived.

This repairs legacy Products that have image rows but no primary image. CAT-009
does not replace an existing primary image.

---

# 11. Product-Wide Images Only Through CAT-009

CAT-009 does not accept a Variant identifier.

Therefore:

```text
product_variant_id = null
```

for CAT-009-created images.

Do not guess Variant association from:

```text
filename
Product state
SKU
image content
```

Variant-specific image management requires a future explicit contract if needed.

---

# 12. Alt Text Rule

For V1:

```text
alt_text = Product.name
```

at upload time.

Example:

```text
Product.name = "Solid Walnut Dining Table"

alt_text =
"Solid Walnut Dining Table"
```

Do not generate:

```text
AI descriptions
computer vision captions
filename-derived descriptions
```

---

# 13. Alt Text Is a Snapshot

If Product.name later changes:

do NOT automatically rewrite existing ProductImage.alt_text.

The upload captures the Product's current name.

A future explicit image metadata editing operation can address manual alt-text management if needed.

Do not invent one in V1.

---

# 14. Sort Order Rule

For each Product:

```text
next sort_order =
MAX(existing sort_order) + 1
```

V1 public representation treats image positions as 1-indexed.

For a Product with zero images:

```text
sort_order = 1
```

---

# 15. Primary Image Rule

If Product has no existing images:

```text
new image is_primary = true
```

Otherwise:

```text
new image is_primary = false
```

Therefore every Product with at least one CAT-009-created image naturally obtains a primary image.

---

# 16. No Client Primary Control

CAT-009 must reject or structurally exclude:

```text
is_primary
```

from the multipart input.

Do not permit the caller to bypass the database primary-image invariant.

---

# 17. Concurrent First Uploads

Two simultaneous uploads to an imageless Product must not both become primary.

Use database transaction + locking.

The existing unique primary guard remains the final database defense.

---

# 18. Concurrent Ordering

Two simultaneous Product uploads must not receive the same server-assigned sort position through an unsafe:

```text
read MAX
→ race
→ insert
```

sequence.

Serialize metadata allocation appropriately.

Prefer locking the Product row before inspecting its image set.

Conceptually:

```text
BEGIN

lock Product FOR UPDATE

read current Product images

next_sort =
    max(sort_order) + 1

is_primary =
    images.count == 0

persist ProductImage metadata

COMMIT
```

Coordinate this with R2 object upload/compensation as specified later.

---

# PART B — CLOUDFLARE R2 STORAGE

## 19. Storage Decision

Use Cloudflare R2 exclusively for public Product image bytes in deployed environments.

Do NOT introduce:

```text
Cloudinary
Cloudflare Images
local VPS production uploads
MySQL BLOB storage
AWS S3 production bucket
```

as alternate production behavior in this phase.

---

# 20. Laravel Storage Abstraction

Use Laravel's filesystem abstraction.

Define a dedicated disk:

```text
r2
```

in:

```text
config/filesystems.php
```

Do not scatter raw Cloudflare/S3 client construction across controllers or services.

---

# 21. Storage Boundary

Prefer a focused application abstraction such as:

```text
ProductImageStorage
```

or:

```text
AssetStorage
```

with responsibilities conceptually:

```text
put()
delete()
exists()
publicUrl()
```

The exact class naming should follow existing repository conventions.

---

# 22. Do Not Build an Image Processor

Do NOT create:

```text
ImageProcessor
ImageOptimizer
ImageResizer
ThumbnailGenerator
WebpConverter
AvifConverter
ImageTransformationJob
```

The project owner manually optimizes uploaded assets.

---

# 23. R2 Uses S3-Compatible Storage

Configure Laravel R2 through the S3-compatible filesystem driver.

Inspect existing Composer dependencies.

If the Laravel AWS S3 Flysystem adapter is already installed:

reuse it.

If absent, add only the standard Laravel-compatible dependency required for S3-compatible storage.

Do not add Cloudflare-specific SDKs unless actually required.

---

# 24. R2 Environment Configuration

The agent MAY edit:

```text
backend/laravel/.env
backend/laravel/.env.example
```

as necessary.

The project owner explicitly authorizes `.env` editing for this phase.

---

# 25. Secrets Rule

The agent must NEVER invent or paste real R2 credentials.

The agent must create empty/placeholding entries so the project owner can enter them manually.

Example `.env` configuration:

```text
PRODUCT_IMAGE_DISK=r2

R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_BUCKET=
R2_ENDPOINT=
R2_REGION=auto

R2_PUBLIC_BASE_URL=
```

If Laravel configuration requires a different exact set of keys, use the smallest correct set.

---

# 26. `.env.example`

Add documentation-safe placeholders:

```text
PRODUCT_IMAGE_DISK=r2

R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_BUCKET=
R2_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
R2_REGION=auto
R2_PUBLIC_BASE_URL=https://assets.example.com
```

Never put:

```text
actual account token
actual secret key
actual production bucket credentials
```

in `.env.example`.

---

# 27. `.env`

The agent may add the exact same keys to `.env`.

Leave credential values blank unless they already legitimately exist locally.

The user will populate:

```text
R2_ACCESS_KEY_ID
R2_SECRET_ACCESS_KEY
R2_BUCKET
R2_ENDPOINT
R2_PUBLIC_BASE_URL
```

manually.

---

# 28. Never Print Secrets

Completion reports and tests must not echo:

```text
R2_ACCESS_KEY_ID
R2_SECRET_ACCESS_KEY
API token
credential values
```

Logs must not contain them.

---

# 29. R2 Endpoint

`R2_ENDPOINT` is the S3-compatible API endpoint used by Laravel to write/delete objects.

It is NOT the public storefront asset URL.

Keep these concepts separate:

```text
R2_ENDPOINT
→ authenticated storage API

R2_PUBLIC_BASE_URL
→ public custom CDN domain
```

---

# 30. Custom Domain

`R2_PUBLIC_BASE_URL` represents the Cloudflare custom domain attached to the R2 bucket.

Example:

```text
https://assets.example.com
```

Do not hard-code a production domain in PHP.

---

# 31. Public URL Projection

Given:

```text
file_path =
products/prod_ABC/img_XYZ.webp
```

and:

```text
R2_PUBLIC_BASE_URL =
https://assets.example.com
```

serialize:

```text
https://assets.example.com/products/prod_ABC/img_XYZ.webp
```

---

# 32. Do Not Persist Public URL

Only persist:

```text
file_path
```

The URL is derived at serialization/runtime.

This preserves provider/domain portability.

---

# 33. Public URL Service

Centralize URL construction.

Do not write:

```php
config('...') . '/' . $image->file_path
```

independently in multiple resources.

Use one storage/public URL service.

---

# 34. URL Misconfiguration

In production-like environments:

if:

```text
R2_PUBLIC_BASE_URL
```

is missing while Product images need serialization:

fail clearly/configurationally rather than emitting malformed URLs.

Do not silently return the R2 S3 API endpoint as a storefront image URL.

---

# 35. R2 Bucket Exposure

The public browser/mobile path is:

```text
custom domain
→ Cloudflare CDN
→ R2 object
```

Do not generate signed download URLs for ordinary public Product images.

These are storefront assets.

---

# 36. Object ACLs

Do not depend on S3 per-object ACL semantics to expose images.

The R2 bucket/custom-domain configuration owns public delivery.

Laravel writes the object.

Cloudflare custom-domain configuration exposes/cache-serves it.

---

# PART C — OBJECT KEY STRATEGY

## 37. Server-Generated Object Keys

Never derive the storage path directly from the uploaded client filename.

Use opaque server-controlled image identifiers.

Conceptually:

```text
products/{product-public-id}/{image-public-id}.{extension}
```

Example:

```text
products/prod_01ABC/img_01XYZ.webp
```

---

# 38. No Numeric Internal IDs in Public Paths

Prefer opaque API Product/Image identifiers over database integer IDs.

Do not leak:

```text
/products/24/17.webp
```

if the repository already has public Product/Image identifiers.

---

# 39. Extension

Extension must come from server-detected image type.

Example:

```text
image/jpeg → jpg
image/png  → png
image/webp → webp
```

Never trust the client's filename extension.

---

# 40. Filename Is Not Authority

This:

```text
beautiful-sofa.webp
```

may be supplied by the browser,

but object path must remain server-generated.

Do not allow:

```text
../../
slashes
backslashes
NUL
URL traversal
```

to influence R2 keys.

---

# PART D — FILE VALIDATION

## 41. Manual Optimization Does Not Replace Validation

The business owner manually optimizes the image.

Laravel must still treat every upload as untrusted input.

---

# 42. Allowed Product Image Types

For V1 allow:

```text
image/jpeg
image/png
image/webp
```

Do not accept:

```text
PDF
SVG
GIF
BMP
TIFF
ICO
AVIF
```

in V1 unless an existing frozen Product-image contract explicitly requires them.

---

# 43. Why No SVG

SVG is executable/XML-like content and creates an unnecessary security surface.

Furniture photography does not require it.

---

# 44. Why No AVIF Initially

Do not introduce AVIF handling complexity merely because it exists.

The owner can manually optimize to:

```text
WebP
JPEG
PNG
```

which is sufficient for this small-business V1.

AVIF can be added deliberately later.

---

# 45. MIME Validation

Do not trust:

```text
UploadedFile::getClientMimeType()
```

as authority.

Use server-side content detection.

---

# 46. File Signature Validation

Validate actual signatures.

At minimum:

```text
JPEG
FF D8 FF

PNG
89 50 4E 47 0D 0A 1A 0A

WebP
RIFF .... WEBP
```

Reject MIME/signature mismatch.

Reuse/refactor the proven attachment validation concepts from Group J where sensible, but do not couple public Product images to private Request/Enquiry Attachment persistence.

---

# 47. Image Validity

Where platform support permits, ensure the payload is a structurally valid image rather than only matching a few magic bytes.

This is validation only.

Do not:

```text
resize
decode/re-encode
compress
strip metadata
transform
```

the file.

### Location Metadata Rejection

Before storing a Product image, inspect supported embedded metadata and reject
an image containing GPS/location data. At minimum, JPEG EXIF GPS metadata must
be detected and rejected. Apply the same rule to other accepted formats when
they carry EXIF metadata.

Do not rely on manual optimization to remove location metadata. Do not strip,
re-encode, or otherwise transform accepted bytes: this is a reject-before-store
validation rule. If an image advertises metadata that the configured runtime
cannot safely inspect, reject it rather than publishing an unchecked location
payload.

Add fixtures proving a location-bearing JPEG is rejected, a non-location image
is accepted, and accepted images retain their original bytes.

---

# 48. Maximum File Size

Use a configurable limit:

```text
PRODUCT_IMAGE_MAX_BYTES
```

Default:

```text
5 MiB
```

because assets are manually optimized.

Example:

```text
PRODUCT_IMAGE_MAX_BYTES=5242880
```

Document this as the V1 operational upload limit.

---

# 49. Empty File

Reject:

```text
0-byte
unreadable
partial upload
```

files.

---

# 50. Single File

CAT-009 uploads exactly one image per request.

Reject arrays/multiple-file payloads.

---

# 51. Strict Multipart Shape

Allow only:

```text
image
```

Do not silently ignore multipart fields such as:

```text
alt_text
sort_order
is_primary
variant_id
product_variant_id
url
file_path
```

---

# 52. Validation Side Effects

Invalid image:

```text
must not create ProductImage row
must not write R2 object
```

---

# PART E — AUTHORIZATION AND PRODUCT RESOLUTION

## 53. Authentication

Anonymous:

```text
401
```

Customer:

```text
403
```

---

# 54. Permission

Require:

```text
products.manage
```

Do not authorize merely from role.

---

# 55. Permission Matrix

Test:

```text
Anonymous
→ 401

CUSTOMER
→ 403

STAFF without products.manage
→ 403

STAFF with products.manage
→ allowed

ADMIN missing products.manage
→ denied

ADMIN with products.manage
→ allowed
```

---

# 56. Product Resolver

Use the canonical operational Product resolver introduced/used during Phase 11.3.

CAT-009 must be able to attach images to Products managed operationally.

Do not accidentally use a public-only Product scope that hides:

```text
inactive
unpublished
```

Products from authorized management.

---

# 57. Unknown Product

Return canonical:

```text
404 RESOURCE_NOT_FOUND
```

or the currently established Product operational not-found mapping.

No SQL/internal disclosure.

---

# PART F — UPLOAD + DATABASE CONSISTENCY

## 58. Distributed Atomicity Reality

R2 and MySQL cannot participate in one database transaction.

Use explicit compensation.

Do not pretend a DB transaction can roll back an R2 object.

---

# 59. Preferred Upload Sequence

Use:

```text
1. validate/auth/authz/Product
2. generate ProductImage identifier and object key
3. upload object to R2
4. transactionally allocate metadata + persist ProductImage
5. return ProductImage
```

If database persistence fails after upload:

delete the just-written R2 object.

---

# 60. Why Upload Before DB Commit

Do not commit a ProductImage row before confirming that its required public object exists.

The successful API invariant should be:

```text
committed ProductImage
→ corresponding R2 object exists
```

---

# 61. R2 Upload Failure

If R2 `put()` fails:

```text
no ProductImage row
request fails
```

Do not persist a broken image URL.

---

# 62. DB Failure After R2 Upload

Attempt compensating:

```text
R2 delete(file_path)
```

then fail request.

---

# 63. Cleanup Failure

If:

```text
DB persistence fails
AND
R2 cleanup also fails
```

do NOT pretend cleanup succeeded.

Use the project's existing recoverable-storage-failure pattern where appropriate.

Log:

```text
request/correlation id
safe object key
operation
```

but never credentials.

Provide enough deterministic information for orphan recovery.

---

# 64. No Silent Orphans

The implementation should make an R2 orphan observable/recoverable.

Do not silently swallow failed cleanup.

---

# 65. Transaction Boundary

Within the DB transaction:

```text
lock Product
allocate sort order
determine primary state
persist ProductImage
```

Use bounded deadlock/transient retry conventions already established by the repository where appropriate.

---

# 66. First-Image Concurrency

Under concurrent first uploads:

successful committed state must have:

```text
exactly one primary image
unique/stable image rows
deterministic sort orders
```

---

# PART G — CDN/CACHE BEHAVIOR

## 67. Immutable Object Keys

Never overwrite an existing Product image object in place.

Every upload receives a new object key.

This makes Cloudflare edge caching safe.

---

# 68. Avoid CDN Cache Invalidation

Because CAT-009 is add-only:

```text
new image
→ new URL
```

No CDN purge should be necessary.

Do not add Cloudflare Cache Purge API integration.

---

# 69. Cache Headers

Where Laravel controls object metadata during upload, use public long-lived caching suitable for immutable content.

Conceptually:

```text
Cache-Control:
public, max-age=31536000, immutable
```

provided it is compatible with the R2 adapter and project conventions.

Because object keys are immutable, this is safe.

---

# 70. Content Type

Set object Content-Type from server-detected type:

```text
image/jpeg
image/png
image/webp
```

Do not use the client-declared MIME blindly.

---

# 71. CDN Is Delivery Infrastructure

Do not proxy Product image bytes through Laravel for public storefront reads.

Clients should load:

```text
https://assets.example.com/...
```

directly.

---

# PART H — PUBLIC CATALOG INTEGRATION

## 72. CAT-002

Existing:

```text
GET /api/v1/products/{product}
```

must embed uploaded images.

Ordering:

```text
sort_order ASC
id ASC
```

---

# 73. CAT-001

Product summaries should continue using:

```text
primary_image
```

from the Product's actual primary ProductImage.

---

# 74. First Upload Effect

For an image-less Product:

```text
CAT-009 upload succeeds
```

then:

```text
CAT-001 primary_image
```

should become that uploaded image.

---

# 75. Subsequent Upload Effect

Additional images:

```text
is_primary = false
```

therefore must not unexpectedly change Product card imagery.

---

# 76. Image URL

All CAT-001/CAT-002 Product image URLs must use:

```text
R2_PUBLIC_BASE_URL
```

not:

```text
R2_ENDPOINT
```

---

# 77. Public File Path Secrecy

`file_path` remains absent from Product APIs.

It may internally resemble a public CDN path but remains persistence/storage metadata and is not an API field.

---

# PART I — NO DELETE/EDIT/REORDER API IN V1

## 78. CAT-009 Is Add-Only

The frozen V1 API contains:

```text
POST /products/{product}/images
```

but no Product-image:

```text
PATCH
DELETE
reorder
set-primary
```

endpoint.

Do not invent them.

---

# 79. No Product Image GET Endpoint

Do not add:

```http
GET /api/v1/products/{product}/images
```

The frozen contract explicitly rejected it because CAT-002 embeds images.

---

# 80. No Image DELETE Endpoint

Do not add:

```http
DELETE /api/v1/products/{product}/images/{image}
```

in Phase 11.5.

Record deletion as an operational limitation/future contract requirement.

---

# 81. No Reorder Endpoint

Do not add:

```text
/images/reorder
```

---

# 82. No Set-Primary Endpoint

Do not add:

```text
/images/{image}/primary
```

---

# 83. No Alt-Text Update Endpoint

Do not add an image-metadata PATCH endpoint.

---

# 84. Existing Database Deletion Semantics

Do not redesign ProductImage FK behavior.

Existing:

```text
Product hard delete
→ image DB rows cascade

Variant delete
→ product_variant_id SET NULL
```

remain persistence semantics.

Do NOT automatically infer that database cascade means R2 objects are automatically deleted.

---

# 85. Product Deletion and External Object Cleanup

Because V1 has no Product DELETE API, do not build broad R2 cleanup around Product deletion in this phase.

Document that external-object lifecycle must be handled deliberately if Product hard deletion becomes an operational API later.

---

# PART J — R2 CONFIGURATION IMPLEMENTATION

## 86. `config/filesystems.php`

Add a dedicated disk conceptually:

```php
'r2' => [
    'driver' => 's3',
    'key' => env('R2_ACCESS_KEY_ID'),
    'secret' => env('R2_SECRET_ACCESS_KEY'),
    'region' => env('R2_REGION', 'auto'),
    'bucket' => env('R2_BUCKET'),
    'endpoint' => env('R2_ENDPOINT'),
    'use_path_style_endpoint' => false,
    'throw' => true,
],
```

Adapt exact options to the installed Laravel/Flysystem version.

Do not blindly paste obsolete config.

---

# 87. Application Config

Prefer a dedicated config file such as:

```text
config/product_images.php
```

containing conceptually:

```text
disk
max_bytes
public_base_url
allowed_types
```

Example environment bindings:

```text
PRODUCT_IMAGE_DISK=r2
PRODUCT_IMAGE_MAX_BYTES=5242880
R2_PUBLIC_BASE_URL=
```

Do not call `env()` directly from domain/services.

---

# 88. Config Cache Compatibility

All runtime code must consume:

```text
config(...)
```

not direct `env(...)`.

This must work under:

```bash
php artisan config:cache
```

---

# 89. Local/Test Storage

Tests must not require actual Cloudflare credentials.

Use:

```php
Storage::fake(...)
```

or an injected fake storage implementation.

Do not call real R2 in the normal PHPUnit suite.

---

# 90. Optional R2 Smoke Test

If the project owner has populated actual credentials locally, an explicit/manual storage smoke test may verify:

```text
write
exists
delete
```

against the configured test/development R2 bucket.

Do not make this required for canonical unit/feature tests.

Do not delete unknown existing R2 objects.

Use a uniquely generated test key.

Clean it up afterward.

---

# 91. No Production Destructive Test

Never run a bucket purge.

Never enumerate-and-delete bucket contents.

Never modify unrelated R2 objects.

---

# PART K — IMPLEMENTATION STRUCTURE

## 92. Controller

Keep CAT-009 controller thin:

```text
authenticated actor
authorization
FormRequest
Product resolver
service
ProductImageResource
```

---

# 93. Request

Use a focused request such as:

```text
CreateProductImageRequest
```

Responsibilities:

```text
multipart structure
exact image field
size
server MIME/signature validation integration
unknown-field rejection
```

---

# 94. Service

Prefer:

```text
CreateProductImage
```

or:

```text
ProductImageService::create()
```

Responsibilities:

```text
generate image identifier
generate object key
store R2 object
lock Product
derive metadata
persist ProductImage
compensate storage failure
```

---

# 95. Storage Adapter

Prefer:

```text
ProductImageStorage
```

wrapping Laravel Storage.

Controller/service should not directly know:

```text
Cloudflare credentials
bucket endpoint syntax
CDN hostname construction internals
```

---

# 96. Resource

Use:

```text
ProductImageResource
```

with exactly:

```text
id
url
alt_text
sort_order
is_primary
```

---

# 97. Identifier

Reuse the established ProductImage opaque identifier format:

```text
img_...
```

Do not expose numeric DB IDs.

---

# 98. No `$request->all()`

Use validated/explicit input.

CAT-009 has only:

```text
image
```

---

# PART L — SECURITY TESTS

## 99. File Validation Tests

Cover:

```text
valid JPEG
valid PNG
valid WebP
zero bytes
oversize
fake JPEG extension
fake PNG extension
fake WebP extension
MIME/signature mismatch
PDF
SVG
GIF
multiple files
non-file text image field
unknown multipart fields
```

---

# 100. Path Safety Tests

Prove uploaded filename cannot influence:

```text
directory traversal
object prefix
Product identifier
Image identifier
```

---

# 101. Authorization Tests

Cover full matrix:

```text
Anonymous
Customer
Staff without permission
Staff products.manage
Admin without permission
Admin products.manage
```

---

# 102. Operational Product Tests

Verify upload works to authorized:

```text
published Product
unpublished Product
inactive Product
```

where Phase 11.3 operational management semantics permit them.

---

# PART M — STORAGE FAILURE TESTS

## 103. Successful Upload

Assert:

```text
R2 fake contains object
ProductImage row exists
file_path matches object
response URL uses CDN base
```

---

# 104. Storage Failure

When storage write throws:

```text
no ProductImage
no partial DB state
canonical server/external error
```

Do not leak R2 error internals.

---

# 105. DB Failure After Storage

Force ProductImage persistence failure.

Assert:

```text
uploaded object deleted
no ProductImage committed
```

---

# 106. Cleanup Failure

Force:

```text
DB failure
+
R2 delete failure
```

Assert:

```text
failure observable
safe recovery information retained/logged
secret values not logged
```

Reuse established cleanup-recovery patterns where practical.

---

# PART N — IMAGE DOMAIN TESTS

## 107. First Image

For imageless Product:

```text
sort_order = 1
is_primary = true
alt_text = Product.name
product_variant_id = null
```

---

# 108. Second Image

Expected:

```text
sort_order = 2
is_primary = false
```

---

# 109. Existing Gaps in Sort Order

If existing images have:

```text
1
3
7
```

new image:

```text
8
```

Use:

```text
MAX + 1
```

not:

```text
COUNT + 1
```

---

# 110. Existing Primary

If Product already has a primary:

new upload cannot replace it.

---

# 111. Product Name Change

Existing image alt text remains unchanged.

New subsequent upload uses the Product's current name.

---

# 112. Concurrency Test

Use the established disposable MariaDB concurrency infrastructure to prove:

```text
two simultaneous uploads
→ distinct sort_order
→ max one primary
→ no DB invariant break
```

This is important because SQLite cannot prove actual InnoDB `FOR UPDATE` behavior.

Use:

```text
furnitureapp_test_disposable
```

only.

---

# PART O — PUBLIC CATALOG REGRESSION

## 113. Product Detail

Verify:

```text
CAT-002.images[]
```

contains uploaded ProductImage.

---

# 114. Product Summary

Verify:

```text
CAT-001.primary_image
```

uses uploaded primary.

---

# 115. CDN URL Test

With:

```text
R2_PUBLIC_BASE_URL=https://assets.example.test
```

and:

```text
file_path=products/prod_123/img_456.webp
```

expect:

```text
https://assets.example.test/products/prod_123/img_456.webp
```

---

# 116. Endpoint Regression

Assert no accidental:

```text
GET /products/{product}/images
PATCH /products/{product}/images/{image}
DELETE /products/{product}/images/{image}
```

routes exist.

---

# PART P — OPENAPI RECONCILIATION

## 117. CAT-009 201 Response

Change:

```text
Attachment
```

to:

```text
ProductImage
```

---

# 118. Request Schema

Preserve:

```text
multipart/form-data
image: binary
```

---

# 119. Strict Multipart Contract

If OpenAPI currently lacks:

```text
required: [image]
additionalProperties: false
```

reconcile it to match the intended already-required CAT-009 upload semantics if runtime is implementing those rules.

Classify this as a consistency hardening correction.

Do not add new client fields.

---

# 120. ProductImage Representation

Ensure OpenAPI remains aligned with:

```text
id
url
alt_text
sort_order
is_primary
```

---

# PART Q — DOCUMENTATION

## 121. ADR

Add a new accepted ADR conceptually:

```text
Phase 11.5 Product Image Storage and R2 Delivery
```

Use the next repository-consistent ADR identifier.

---

# 122. ADR Must Record

At minimum:

```text
Cloudflare R2 stores Product image bytes.

Cloudflare custom-domain CDN serves public image URLs.

Cloudflare Images is not used.

Laravel does not resize, optimize, compress, or convert images.

Images are manually optimized before upload.

MySQL stores only ProductImage metadata/internal file_path.

file_path is provider-neutral.

public URL is derived from R2_PUBLIC_BASE_URL.

CAT-009 remains single-image multipart upload.

CAT-009 creates product-wide images only.

alt_text is server-derived from Product.name.

sort_order appends with MAX + 1.

first image becomes primary.

subsequent images are non-primary.

CAT-009 OpenAPI Attachment response was a stale reference and is corrected to ProductImage.

No Product-image DELETE/reorder/set-primary/edit endpoint exists in V1.
```

---

# 123. Update Phase Documentation

Update:

```text
phases/group-K-phases.md
docs/decisions.md
```

and relevant API docs/OpenAPI.

---

# PART R — QUALITY AND VERIFICATION

## 124. Focused Tests

Run:

```text
ProductImage schema
Product image CAT-009
Product image validation
Product image authorization
Product image storage
Product image compensation
Product image public URL
Product image primary invariant
Product image ordering
Product image concurrency
Product catalog summary/detail
Operational Product regression
RBAC
OpenAPI
```

---

# 125. Full Suite

Run:

```bash
php artisan test
```

Expected:

```text
all existing tests green
```

apart from already-known intentional skips.

---

# 126. Static Analysis

Run:

```bash
vendor/bin/phpstan analyse
```

---

# 127. Formatting

Run:

```bash
vendor/bin/pint --test
```

---

# 128. Dependency Audit

Run:

```bash
composer audit
```

---

# 129. Diff Validation

Run:

```bash
git diff --check
```

---

# 130. Route Verification

Run:

```bash
php artisan route:list --path=api --except-vendor
```

Confirm:

```text
CAT-009 active
```

and no invented Product-image management routes.

---

# 131. Config Verification

Run appropriate config tests, including:

```bash
php artisan config:clear
php artisan config:cache
```

if consistent with repository workflow.

Confirm R2 config remains available through cached config.

Do not expose secrets in command output.

---

# 132. MariaDB Concurrency Gate

Run Product-image allocation concurrency tests on:

```text
furnitureapp_test_disposable
```

using the established forked-worker approach.

Prove:

```text
unique deterministic append positions
max one primary
no deadlock leak
no DB corruption
```

---

# PART S — COMPLETION REPORT

## 133. Phase Status

Return:

```text
Phase 11.5 Status
PASS
```

or:

```text
Phase 11.5 Status
BLOCKED
```

---

# 134. Storage Report

Report:

```text
Storage provider:
Cloudflare R2

Laravel disk:
<actual disk>

Public delivery:
Cloudflare custom-domain CDN

Cloudflare Images:
NOT USED

Server-side optimization:
NONE

Server-side resizing:
NONE

Server-side conversion:
NONE
```

---

# 135. Environment Report

Report only key names:

```text
PRODUCT_IMAGE_DISK
PRODUCT_IMAGE_MAX_BYTES
R2_ACCESS_KEY_ID
R2_SECRET_ACCESS_KEY
R2_BUCKET
R2_ENDPOINT
R2_REGION
R2_PUBLIC_BASE_URL
```

Never report credential values.

---

# 136. Credential Status

Report:

```text
.env placeholders prepared: YES/NO
.env.example documented: YES/NO
Real credentials committed: NO
```

---

# 137. CAT-009 Report

Report:

```text
route:
authorization:
request content type:
accepted field:
allowed MIME types:
max size:
response resource:
```

---

# 138. Server-Derived Metadata Report

Report:

```text
product_variant_id:
null

alt_text:
Product.name snapshot

sort_order:
MAX + 1, first = 1

is_primary:
true only for first Product image
```

---

# 139. Object-Key Report

Report exact key convention.

Example:

```text
products/{product-public-id}/{image-public-id}.{detected-extension}
```

Confirm:

```text
client filename controls key: NO
numeric DB IDs exposed: NO
```

---

# 140. CDN URL Report

Report:

```text
DB stores CDN URL:
NO

DB stores file_path:
YES

Public URL derived from:
R2_PUBLIC_BASE_URL + file_path
```

---

# 141. OpenAPI Reconciliation Report

Report:

```text
CAT-009 old response:
Attachment

CAT-009 new response:
ProductImage

Request shape expanded:
NO
```

---

# 142. Side-Effect Report

Confirm:

```text
Product pricing mutated: NO
Variants mutated: NO
Inventory mutated: NO
Cart mutated: NO
Request mutated: NO
Enquiry mutated: NO
```

---

# 143. Route Surface Report

Confirm:

```text
POST Product image: YES

GET Product images endpoint: NO
PATCH Product image: NO
DELETE Product image: NO
reorder endpoint: NO
set-primary endpoint: NO
```

---

# 144. Failure-Safety Report

Report results for:

```text
R2 upload failure
DB failure after R2 upload
R2 compensation delete
cleanup-delete failure handling
```

---

# 145. Validation Report

Report:

```text
JPEG:
PNG:
WebP:
oversize:
zero-byte:
fake extension:
signature mismatch:
PDF:
SVG:
unknown multipart field:
```

---

# 146. Verification Report

Report:

```text
focused tests:
MariaDB concurrency:
full PHPUnit:
PHPStan:
Pint:
Composer audit:
git diff --check:
route:list:
config cache:
```

---

# 147. Definition of Done

Phase 11.5 is complete only when:

- CAT-009 is implemented;
- CAT-009 requires `products.manage`;
- CAT-009 accepts exactly one `image`;
- response is ProductImage, not Attachment;
- R2 is configured through Laravel Storage;
- `.env` has empty/manual credential slots;
- `.env.example` documents safe placeholders;
- no credential is committed;
- R2 S3 endpoint and public CDN domain are separate;
- image bytes are stored in R2;
- public delivery uses the custom domain;
- Cloudflare Images is not used;
- Laravel performs no optimization;
- Laravel performs no resize;
- Laravel performs no conversion;
- only JPEG/PNG/WebP are accepted;
- actual content/signature is validated;
- client filename cannot control object path;
- object key is server generated;
- DB stores only internal `file_path`;
- public `url` derives from `R2_PUBLIC_BASE_URL`;
- first image becomes primary;
- subsequent images remain non-primary;
- sort order appends deterministically;
- Product name becomes alt-text snapshot;
- concurrent uploads preserve primary/order invariants;
- R2 failure leaves no ProductImage row;
- DB failure compensates R2 upload;
- cleanup failure is observable/recoverable;
- CAT-001 primary image works;
- CAT-002 gallery works;
- no GET image collection endpoint is added;
- no Product image PATCH is added;
- no Product image DELETE is added;
- no reorder endpoint is added;
- no set-primary endpoint is added;
- full regression is green.

---

# 148. STOP Condition

STOP when the repository can prove:

```text
manually optimized Product image
        ↓
CAT-009
        ↓
auth + products.manage
        ↓
server-side file security validation
        ↓
safe immutable object key
        ↓
Cloudflare R2
        ↓
ProductImage.file_path
        ↓
R2_PUBLIC_BASE_URL
        ↓
Cloudflare custom-domain CDN URL
        ↓
CAT-001 / CAT-002
```

with:

```text
Cloudflare Images = NOT USED
Laravel processing = NOT USED
MySQL image BLOB = NOT USED
VPS image storage = NOT USED
```

and report:

```text
Phase 11.5 PASS

Phase 11.6 — Inventory management READY
```

Do not begin Phase 11.6 automatically.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles all Git operations.
