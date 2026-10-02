# Phase 10.6 — Attachment Handling

## 1. Objective

Implement the Version 1 private attachment subsystem shared by:

```text
Furniture Requests
General Enquiries
```

Supported surfaces:

```http
POST /api/v1/requests
POST /api/v1/enquiries
```

with preferred inline:

```text
multipart/form-data
field: attachment
```

and prepare the frozen separate-upload operations:

```http
POST /api/v1/requests/{request}/attachments
POST /api/v1/enquiries/{enquiry}/attachments
```

identified as:

```text
REQ-007
ENQ-007
```

The subsystem must provide:

```text
secure validation
private storage
safe metadata persistence
parent-scoped authorization
opaque attachment identity
safe serialization
failure cleanup
anonymous upload-capability protection
```

Do not implement two different attachment architectures.

---

# 2. Current Group J State

Treat the current state as:

```text
10.1 PASS — Furniture Request API
10.2 PASS — Request validation
10.3 PASS — Request lifecycle
10.4 PASS — Product-linked Requests
10.5 PASS — General Enquiries
10.6 CURRENT — Attachment handling
10.7 NOT STARTED — Staff/Admin request management
10.8 NOT STARTED — Request/Enquiry tests
```

Before implementation, verify the actual Phase 10.5 repository result rather than assuming undocumented classes or route state.

---

# 3. Why This Phase Matters

REQ-001 and ENQ-001 are currently intentionally gated because the frozen Version 1 creation contract supports optional attachments.

Phase 10.6 should close that final creation-contract gap.

After successful 10.6, assess whether:

```text
POST /api/v1/requests
POST /api/v1/enquiries
```

can finally be activated safely.

Do not activate automatically until every prerequisite is verified.

---

# 4. Frozen V1 Attachment Contract

V1 supports:

```text
0 or 1 attachment on creation
```

Multiple attachments are deferred.

Maximum size:

```text
5 MiB
```

Accepted content types:

```text
image/jpeg
image/png
image/webp
application/pdf
```

The backend must not trust:

```text
client Content-Type
filename extension
browser MIME
```

alone.

Actual file content/signature must be validated.

---

# 5. Preferred Transport

Preferred V1 creation flow:

```text
multipart/form-data
```

with:

```text
attachment
```

included directly in:

```text
REQ-001
ENQ-001
```

Example conceptual Request:

```text
product_id = prod_...
name = Asha Mwangi
phone = +255...
notes = Please make something similar
attachment = furniture-reference.jpg
```

Example conceptual Enquiry:

```text
name = Asha Mwangi
email = asha@example.com
subject = Question about this product
message = ...
product_id = prod_...
attachment = screenshot.png
```

---

# 6. JSON Creation Must Continue Working

Attachment remains optional.

These existing calls must continue to work:

```http
Content-Type: application/json
```

for:

```text
REQ-001
ENQ-001
```

Do not make multipart mandatory merely because attachment support exists.

---

# 7. Creation Transport Matrix

Support:

```text
JSON without attachment
→ valid

multipart without attachment
→ valid if otherwise contract-compliant

multipart with one attachment
→ valid

JSON with "attachment": "base64..."
→ invalid

multipart with multiple attachment parts
→ invalid
```

Do not introduce base64 attachment transport.

---

# 8. One Shared Attachment Architecture

Do not create:

```text
RequestAttachmentService
EnquiryAttachmentService
```

with duplicated validation/storage logic.

Prefer shared infrastructure with parent-specific authorization.

Possible architecture:

```text
AttachmentValidator
AttachmentStorage
AttachmentMetadata
AttachmentResource
AttachmentUploadCapability
```

with small Request/Enquiry adapters where needed.

---

# 9. Parent Types

The attachment subsystem must support exactly the approved V1 parent domains:

```text
FURNITURE_REQUEST
ENQUIRY
```

Do not generalize immediately to:

```text
Order
Product
User
Payment
Notification
```

unless existing schema already has a safe generic attachment design.

---

# 10. First Task — Inspect Persistence

Before writing migrations, inspect the repository for:

```text
attachments
request_attachments
enquiry_attachments
Attachment model
RequestAttachment model
EnquiryAttachment model
storage metadata
```

Do not assume attachment persistence already exists.

---

# 11. If Attachment Persistence Already Exists

Reuse it when it satisfies:

```text
parent relationship
private storage key
sanitized filename
validated content type
size bytes
opaque identity
timestamps
authorization boundary
```

Do not replace it unnecessarily.

---

# 12. If No Attachment Persistence Exists

This phase is allowed to introduce the smallest deliberate schema required by the frozen V1 attachment contract.

Do not fake attachments by storing them in:

```text
FurnitureRequest.product_details
FurnitureRequest.message
Enquiry.message
staff_internal_notes
JSON metadata blobs unrelated to files
```

A real attachment needs real metadata persistence.

---

# 13. Preferred Persistence Shape

If no existing schema exists, prefer one shared table rather than parallel Request/Enquiry tables.

Conceptually:

```text
attachments
```

with fields equivalent to:

```text
id
parent_type
parent_id
storage_disk
storage_key
filename
content_type
size
created_at
updated_at
```

However, do not blindly introduce a Laravel polymorphic relation if that conflicts with existing repository schema conventions.

Inspect first.

---

# 14. Strong FK Preference

The repository has emphasized referential integrity.

If a generic polymorphic table would lose database FK enforcement, consider whether two nullable parent FKs with an XOR constraint is more appropriate:

```text
furniture_request_id nullable
enquiry_id nullable
```

with invariant:

```text
exactly one parent is non-null
```

and `CASCADE`/approved delete behavior.

Choose based on existing schema conventions.

Do not sacrifice integrity merely for framework convenience.

---

# 15. Schema Decision Must Be Explicit

If a migration is necessary, document:

```text
why attachment persistence was absent
why the chosen parent model is appropriate
delete behavior
uniqueness/cardinality
private storage semantics
```

This is a legitimate Phase 10.6 schema addition, not a silent patch to an old migration.

---

# 16. Never Edit Historical Migration

If adding persistence now:

```text
create a new forward migration
```

Do not modify Phase 3.x historical migrations.

---

# 17. V1 Cardinality

On creation:

```text
maximum one attachment per Request
maximum one attachment per Enquiry
```

Separate upload must also respect the V1 cardinality.

Do not allow:

```text
inline attachment
+
later second attachment
```

to produce two files on the same parent if the frozen V1 contract says one.

---

# 18. Database Cardinality Enforcement

Where practical, enforce one-per-parent structurally.

Examples depending on schema design:

```text
UNIQUE(furniture_request_id)
UNIQUE(enquiry_id)
```

for non-null parent columns.

Do not rely only on controller checks if DB enforcement is straightforward.

---

# 19. Delete Policy

An Attachment is subordinate private data belonging to the parent.

If parent is hard-deleted under an approved path, attachment metadata should normally be deleted with it.

Use:

```text
CASCADE
```

where consistent with repository retention policy.

Do not guess—inspect current Request/Enquiry retention rules.

---

# 20. File Storage and DB Delete Are Different

Deleting attachment metadata does not automatically delete an object from the filesystem/object store.

If deletion workflows exist, account for physical file cleanup.

Do not implement a broad retention/deletion system beyond current scope.

---

# 21. Private Laravel Disk

Use Laravel:

```php
Storage::disk(...)
```

behind a dedicated configured private disk.

Example configuration concept:

```text
ATTACHMENTS_DISK
```

Do not hard-code:

```text
s3
r2
local
cloudinary
```

inside services.

---

# 22. Default Test/Local Disk

Tests should use:

```php
Storage::fake(...)
```

or equivalent private test disk.

Local development may use a private local disk.

Production provider can later be:

```text
S3-compatible
R2
private object storage
```

without changing domain services.

---

# 23. Storage Must Not Be Public

Do not write to:

```text
public/
storage/app/public
public disk with permanent URL
```

for private customer attachments.

The default visibility must be:

```text
private
```

---

# 24. Never Expose Storage Key

Internal:

```text
storage_key
```

must never appear in:

```text
REQ-001 response
ENQ-001 response
customer views
staff views
logs
error details
```

---

# 25. Storage Key Generation

Generate server-side unpredictable keys.

Do not derive storage key directly from:

```text
original filename
email
customer name
request reference
enquiry reference
```

Use random/opaque storage identifiers.

Possible structure:

```text
attachments/requests/<random>
attachments/enquiries/<random>
```

but never rely on path secrecy as authorization.

---

# 26. Original Filename Is Untrusted

Treat uploaded filename as untrusted display metadata.

Never use it directly as filesystem path.

---

# 27. Filename Sanitization

Produce a safe display filename.

Requirements:

```text
strip directory components
remove control characters
remove NUL
normalize unsafe separators
bound length
avoid traversal components
preserve sensible extension only when compatible
```

Examples that must not become storage paths:

```text
../../secret.pdf
..\..\secret.pdf
foo/bar.jpg
NUL-containing values
```

---

# 28. Do Not Trust Extension

This:

```text
invoice.jpg
```

may actually contain a PDF or arbitrary bytes.

Validation authority comes from detected content.

---

# 29. Size Limit

Maximum:

```text
5 MiB
```

Interpret precisely as:

```text
5 * 1024 * 1024 bytes
```

unless existing contract code defines otherwise.

Avoid ambiguous decimal 5,000,000 unless frozen code already establishes that interpretation.

Document exact byte boundary.

---

# 30. Size Boundary Tests

Test:

```text
5 MiB - 1 → accepted if type valid
5 MiB     → accepted
5 MiB + 1 → rejected
```

---

# 31. Oversize Error

Use:

```text
ATTACHMENT_TOO_LARGE
```

with the frozen HTTP status.

The contract currently identifies the attachment errors as:

```text
INVALID_ATTACHMENT
ATTACHMENT_TOO_LARGE
UNSUPPORTED_ATTACHMENT_TYPE
```

Verify exact registry/status mappings before implementation.

---

# 32. Body Limit Interaction

The repository also has a global request body ceiling.

Because multipart overhead adds bytes, ensure the global ingress/app limit is large enough to permit a valid 5 MiB file plus multipart fields.

Do not accidentally make:

```text
attachment max = 5 MiB
global body max = 5 MiB
```

because valid uploads would fail due to multipart overhead.

---

# 33. Existing Global Ceiling

Earlier security hardening established a broader request ceiling.

Verify it remains compatible.

Do not weaken the global security ceiling unnecessarily.

---

# 34. Allowed Content Types

Exactly:

```text
image/jpeg
image/png
image/webp
application/pdf
```

No:

```text
image/gif
image/svg+xml
text/plain
application/zip
application/msword
application/vnd.*
video/*
audio/*
```

---

# 35. Why SVG Is Rejected

Do not add SVG merely because it is an image.

SVG can contain active/script-like content and is not in the frozen allow-list.

---

# 36. MIME Detection

Use server-side detection based on file contents.

Prefer PHP/Laravel facilities backed by:

```text
finfo
```

or equivalent reliable server-side detector.

Do not trust:

```php
$uploadedFile->getClientMimeType()
```

as security authority.

---

# 37. Signature Validation

In addition to MIME detection, validate actual file signature/magic.

At minimum:

```text
JPEG
PNG
WebP
PDF
```

must match their expected content signatures.

---

# 38. JPEG Signature

Validate using actual file content / trusted image decoder or signature detection.

Do not only check:

```text
.jpg extension
```

---

# 39. PNG Signature

Validate PNG magic bytes.

---

# 40. WebP Signature

Validate RIFF/WEBP structure sufficiently to distinguish it from arbitrary RIFF content.

---

# 41. PDF Signature

Validate that the content is a PDF using server-side type/signature detection.

Do not implement a fake rule that merely checks filename `.pdf`.

---

# 42. MIME/Signature Agreement

If:

```text
client MIME = image/jpeg
server detected = application/pdf
```

the server-detected content is authoritative.

Do not silently store with the client-supplied type.

---

# 43. Stored Content Type

Persist the validated server-detected MIME:

```text
content_type
```

not the client-declared MIME.

---

# 44. Unsupported Type

Use:

```text
UNSUPPORTED_ATTACHMENT_TYPE
```

for well-formed uploads whose actual content type is outside the allow-list.

---

# 45. Invalid/Corrupted File

Use:

```text
INVALID_ATTACHMENT
```

for:

```text
unreadable temp file
signature mismatch
truncated/corrupt structure where detected
upload transport error
```

according to existing error conventions.

---

# 46. Upload Transport Errors

Handle PHP upload errors explicitly.

Do not treat:

```text
UPLOAD_ERR_PARTIAL
UPLOAD_ERR_NO_TMP_DIR
UPLOAD_ERR_CANT_WRITE
```

as valid files.

Return safe API errors.

Do not expose raw PHP temp paths.

---

# 47. Empty File

Reject a zero-byte attachment as:

```text
INVALID_ATTACHMENT
```

unless the frozen contract says otherwise.

A zero-byte JPEG/PDF is not valid.

---

# 48. File Name vs Content Type

Sanitized display filename extension should be coherent with validated content where feasible.

Do not rename content merely to make a dangerous upload appear valid.

---

# 49. Malware Scanning

The global contract says malware/security scanning should be considered where appropriate.

Do not invent an external antivirus dependency in Phase 10.6 unless one is already approved.

Record:

```text
signature/type validation implemented
malware scanner integration deferred
```

if no scanning provider exists.

Do not falsely claim malware scanning.

---

# 50. No Image Re-encoding Requirement

Do not:

```text
resize
compress
strip EXIF
convert format
generate thumbnails
```

unless explicitly required.

This phase is secure intake/storage, not media processing.

---

# 51. Metadata Privacy

Potential EXIF inside an uploaded image remains part of the private file.

Do not expose it via API.

If future privacy requirements call for EXIF stripping, handle separately.

---

# 52. Attachment Model

If persistence is introduced, create one explicit model such as:

```php
App\Models\Attachment
```

or the repository-consistent equivalent.

Avoid mass serialization.

---

# 53. Opaque Attachment Identifier

Public ID:

```text
att_...
```

Reuse the existing opaque-ID convention.

Never expose numeric DB ID.

---

# 54. Attachment Identifier

Introduce/reuse:

```php
AttachmentIdentifier
```

following:

```text
ProductIdentifier
OrderIdentifier
FurnitureRequestIdentifier
EnquiryIdentifier
```

patterns.

---

# 55. Attachment Resource

Create one shared:

```php
AttachmentResource
```

or equivalent.

Customer-safe metadata:

```text
id
filename
content_type
size
url
```

according to frozen resource definitions.

---

# 56. `url`

The resource contract allows:

```text
url: string|null
```

but requires private access.

If secure temporary-download URLs are not implemented in this phase:

```text
url = null
```

is preferable to exposing a public storage URL.

Do not fake a permanent URL.

---

# 57. No Direct `Storage::url()`

Do not expose:

```php
Storage::url($storageKey)
```

if that yields a durable/public URL.

---

# 58. Download URLs

If the selected Laravel disk safely supports temporary signed URLs and the project already has an authorization-safe download design, you may use them.

Otherwise keep:

```text
url = null
```

and defer file retrieval endpoint/signing.

Do not expand scope merely to avoid null.

---

# 59. Parent Resource Integration

Replace Phase 10.1/10.5 placeholder:

```json
"attachments": []
```

with actual attachment metadata when present.

For no attachment:

```json
"attachments": []
```

remains.

---

# 60. Never Embed File Bytes

Do not return:

```text
base64
binary body
data URI
```

inside Request/Enquiry JSON resources.

---

# 61. Inline Request Creation

Extend:

```text
REQ-001
```

to accept multipart form data containing existing fields plus:

```text
attachment
```

---

# 62. Inline Enquiry Creation

Likewise extend:

```text
ENQ-001
```

to multipart with:

```text
attachment
```

---

# 63. Multipart Scalar Semantics

Important: multipart form fields arrive as strings.

The existing strict JSON Request validation intentionally distinguishes:

```text
integer 2
```

from:

```text
string "2"
```

For multipart, there is no native JSON scalar typing.

Do not accidentally make the multipart contract impossible.

---

# 64. Explicit Multipart Normalization

Define transport-aware parsing.

For fields whose schema has a non-string type:

Furniture Request:

```text
quantity
dimensions
product_id nullable semantics
```

must be decoded according to the frozen multipart contract.

Do not reuse raw multipart PHP strings blindly.

---

# 65. Do Not Weaken JSON Strictness

JSON:

```json
{"quantity":"2"}
```

must remain invalid.

Multipart:

```text
quantity=2
```

may be intentionally parsed into integer 2 because multipart has string transport semantics.

Transport-specific decoding is acceptable.

Document it.

---

# 66. Structured `dimensions`

Define the canonical multipart representation.

Inspect OpenAPI first.

Possible forms:

```text
dimensions[length]
dimensions[width]
dimensions[height]
dimensions[unit]
```

or a JSON-encoded multipart field if frozen OpenAPI says so.

Do not invent both aliases.

Use exactly the documented encoding.

---

# 67. Enquiry Multipart Fields

Subject/message/category/product_id/order_id/contact are naturally string fields.

Apply the same validation/domain semantics after transport decoding.

---

# 68. Multipart Unknown Fields

Unknown fields must still be rejected.

Multipart must not become a bypass for the strict JSON allow-list.

---

# 69. Multiple Attachment Parts

Reject:

```text
attachment[]
multiple attachment values
```

V1 creation supports one.

---

# 70. Inline Atomic Business Requirement

If an inline attachment fails validation/storage:

```text
Request/Enquiry creation must fail
```

and no valid-looking parent may remain committed without its requested file.

Conversely, if parent persistence fails:

```text
attachment must not remain as an intended live orphan
```

---

# 71. Database vs Filesystem Atomicity

Filesystem/object storage cannot participate in the SQL transaction.

Design explicit compensation.

Do not claim true distributed atomicity.

---

# 72. Recommended Inline Persistence Strategy

A practical low-concurrency flow:

```text
1. validate all scalar fields
2. validate file fully
3. resolve Product/Order domain rules
4. generate random private storage key
5. enter creation transaction
6. create parent
7. store file
8. create attachment metadata
9. commit
10. on any DB/service failure after file write → delete stored file
```

If storage write fails:

```text
throw
→ DB transaction rolls back
```

If later DB work fails:

```text
rollback DB
→ compensating Storage::delete(key)
```

---

# 73. Cleanup Must Be Best-Effort Safe

Compensating file deletion failure must be logged safely for operational cleanup.

Do not expose storage paths in client response.

---

# 74. Do Not Swallow Creation Failure

If client submitted an attachment and it could not be safely stored:

```text
do not create a success response with attachment silently missing
```

Fail the entire creation request.

---

# 75. Database Commit / Storage Timing

Keep the design simple and explicit.

Do not build:

```text
distributed transaction manager
two-phase commit
saga framework
```

for one ≤5 MiB private upload.

---

# 76. Storage Failure Error

Use a safe internal failure:

```text
INTERNAL_SERVER_ERROR
```

or approved attachment-storage error if one already exists.

Do not reveal:

```text
bucket
disk path
credentials
SDK exception body
```

---

# 77. Separate Upload Purpose

REQ-007 / ENQ-007 support an after-creation attachment flow.

This exists especially for:

```text
anonymous parent creation
retrying attachment separately
```

but must not turn the opaque parent ID into authorization.

---

# 78. Separate Upload Routes

Frozen routes:

```http
POST /api/v1/requests/{request}/attachments
POST /api/v1/enquiries/{enquiry}/attachments
```

Content:

```text
multipart/form-data
attachment
```

---

# 79. Separate Upload Requires Capability

For anonymous parent:

```text
parent ID alone is insufficient
```

Require:

```text
server-issued scoped upload capability/token
```

---

# 80. Capability Properties

The token must be:

```text
cryptographically unpredictable
scoped to one parent
scoped to attachment upload action
single-use
time-limited
server-verifiable
not derived from parent ID
```

---

# 81. Capability Is Not Ownership

Do not treat the upload token as:

```text
login
general Request retrieval credential
general Enquiry retrieval credential
staff credential
```

It grants only the specific approved upload operation.

---

# 82. Capability Scope

A Request upload token must not work on:

```text
another Request
an Enquiry
```

An Enquiry token must not work on:

```text
another Enquiry
a Request
```

---

# 83. Token Lifetime

Use a bounded short lifetime.

Do not invent a random duration without checking frozen docs/decisions.

If no exact TTL is frozen:

choose a conservative documented value and record it in the ADR/config.

Prefer configuration constant rather than magic number.

---

# 84. Single Use

After successful separate upload:

```text
token becomes unusable
```

A retry using the same consumed token must not create another attachment.

---

# 85. Failed Upload and Token Consumption

Do not consume the token merely because:

```text
file validation failed
storage temporarily failed
```

unless contract explicitly requires that.

Preferred:

```text
consume atomically only on successful attachment persistence
```

so the user may correct/retry.

---

# 86. Token Persistence

If true single-use enforcement requires persisted state, introduce the minimum secure mechanism.

Options may include:

```text
hashed capability digest on parent
dedicated upload_capabilities table
```

Do not store bearer tokens in plaintext.

---

# 87. Token Hashing

Store only a secure digest/HMAC of the capability if persistence is required.

Follow the guest Cart token precedent:

```text
raw capability → client only
digest → database
```

---

# 88. Do Not Log Raw Capability

Never log:

```text
attachment upload token
```

---

# 89. Creation Response Capability

The frozen contract says the separate-upload capability is returned from successful creation.

If the client submitted no attachment and separate upload remains permitted:

the creation response may need the scoped token.

Verify exact OpenAPI/resource shape before modifying response.

---

# 90. Contract Gap Check

If the frozen prose requires an upload token but the frozen OpenAPI/resource does not define where that token appears:

do not silently invent a response field.

Classify as a contract consistency gap.

Reconcile using the established post-freeze process.

This is an important Phase 10.6 verification point.

---

# 91. Do Not Leak Capability in Normal Resource Forever

An upload capability is transient security material.

It should not become a permanent:

```text
RequestResource
EnquiryResource
```

field returned on every GET.

If approved, expose it only in the relevant creation response metadata/field.

---

# 92. Authenticated Customer Separate Upload

For authenticated parent:

require:

```text
authenticated CUSTOMER
owns parent
```

and, if the frozen contract requires the same capability, enforce it.

Do not let authentication bypass a required scoped token without verifying the contract.

---

# 93. Frozen Wording

The frozen docs describe separate upload as:

```text
scoped token + parent ownership
```

Therefore default implementation should enforce both where applicable.

For anonymous:

```text
token supplies scoped capability
```

since no account ownership can exist.

---

# 94. Staff/Admin Separate Upload

Do not automatically grant staff arbitrary customer attachment upload merely because staff can view the parent.

The frozen attachment rules say authorization inherits parent semantics, but staff operational mutation boundaries should be checked against Phase 10.7.

Do not broaden staff mutation power accidentally.

---

# 95. Customer A vs Customer B

Customer A must never upload to Customer B's:

```text
Request
Enquiry
```

even with a guessed opaque parent ID.

Use 404 masking where ownership rules require.

---

# 96. Anonymous Parent Retrieval

Do not create public GET attachment APIs for anonymous users.

Anonymous upload capability does not imply retrieval.

---

# 97. Attachment Download

If not already frozen and implemented:

do not add:

```text
GET /attachments/{attachment}
```

merely because storage exists.

Phase 10.6 can safely return:

```text
url = null
```

until private download delivery is designed.

---

# 98. Inline Upload Does Not Need Separate Capability

When the attachment is included atomically in the original:

```text
REQ-001
ENQ-001
```

no after-creation upload capability is needed for that file.

Do not issue unnecessary reusable tokens after the one allowed attachment already exists.

---

# 99. One Attachment Already Present

Separate upload to a parent that already has its V1 attachment:

reject deterministically.

Use an existing canonical conflict/invalid-value error if defined.

Do not overwrite the original attachment silently.

---

# 100. No Replacement Semantics

V1 does not define:

```text
replace attachment
delete attachment
edit attachment
```

Do not invent these.

---

# 101. No Multi-File Semantics

Do not allow repeated separate uploads.

One parent → max one attachment.

---

# 102. Attachment Validation Service

Introduce one focused component such as:

```php
App\Services\Attachments\AttachmentValidator
```

or repository-consistent equivalent.

Input:

```text
UploadedFile
```

Output concept:

```text
ValidatedAttachment
```

containing only trusted derived metadata:

```text
temporary file reference
sanitized filename
validated content type
size
```

---

# 103. Immutable Validated Attachment

Prefer a readonly value object:

```php
ValidatedAttachment
```

so downstream storage never rereads untrusted client MIME/name.

---

# 104. Attachment Storage Service

Introduce:

```php
AttachmentStorage
```

or equivalent.

Responsibilities:

```text
generate key
store privately
delete on compensation
optionally create temporary URL if approved
```

No authorization inside storage layer.

---

# 105. Attachment Creation Service

Introduce a focused application service for parent attachment persistence.

Possible:

```php
CreateParentAttachment
```

or separate thin adapters:

```text
CreateFurnitureRequestAttachment
CreateEnquiryAttachment
```

that share validator/storage internals.

Avoid duplicate storage/security logic.

---

# 106. Parent-Type Abstraction

If using a common parent adapter, keep it explicit.

Do not make arbitrary Eloquent models attachable.

Approved parent set remains closed.

---

# 107. Security Validation Before Storage

Do not store first and validate later.

File must pass:

```text
upload integrity
size
detected type
signature
filename normalization
```

before becoming a live attachment.

---

# 108. Temporary PHP Upload

Reading the PHP temporary upload for validation is fine.

Do not move into durable storage before validation.

---

# 109. Streaming

Avoid reading the whole 5 MiB file into memory unnecessarily.

Use streams/server-side file inspection where practical.

Do not base64 encode internally.

---

# 110. Hashing

A content hash may be useful operational metadata, but it is not required by the frozen contract.

Do not introduce deduplication semantics from a hash.

If storing a hash helps integrity, document it as internal only.

---

# 111. No File Deduplication

Two users uploading identical PDFs/images may create separate attachment records.

Do not use content hash as hard uniqueness.

---

# 112. No User-Supplied Storage Filename

Never let client choose:

```text
storage_key
path
disk
bucket
```

Reject those fields.

---

# 113. Multipart Server-Controlled Field Rejection

Multipart must retain all Phase 10.2/10.5 strict protections.

A multipart Request must still reject:

```text
user_id
request_status
staff_internal_notes
message alias
style
product_details
order_id
price
```

as applicable.

Multipart Enquiry must similarly reject server-controlled and Request-specific fields.

---

# 114. File Field Is the Only New Creation Field

For this phase, the only new transport field on REQ-001/ENQ-001 is:

```text
attachment
```

Do not expand the business body.

---

# 115. Request Attachment Validation

REQ-001 + attachment must still enforce all completed:

```text
10.2 validation
10.4 MADE_TO_ORDER Product eligibility
```

Attachment support must not bypass them.

---

# 116. Enquiry Attachment Validation

ENQ-001 + attachment must still enforce all completed:

```text
10.5 contact
subject/message
category
Product association
Order ownership
```

---

# 117. Validation Ordering

Conceptually:

```text
Transport
→ Auth/actor
→ scalar schema
→ file transport validation
→ file security validation
→ domain validation
→ parent + attachment persistence
```

Do not run expensive durable storage when scalar input is already invalid.

---

# 118. MIME Detection Is Potentially Expensive

But at 5 MiB maximum, full security inspection is acceptable.

Still avoid redundant file reads.

---

# 119. Error Code Registry

Verify these exist consistently in:

```text
ApiErrorCode
OpenAPI global error enum
api-contract
api-conventions
```

Expected:

```text
INVALID_ATTACHMENT
ATTACHMENT_TOO_LARGE
UNSUPPORTED_ATTACHMENT_TYPE
```

If already frozen but absent in registry/OpenAPI:

reconcile as a contract consistency correction.

Do not invent renamed codes.

---

# 120. Error Status Codes

Use the exact frozen statuses.

The current contract indicates:

```text
invalid/type issues → 422
too large → 413
```

Verify before implementation.

Do not map everything to 422 automatically.

---

# 121. Field Path

Attachment errors should identify:

```text
attachment
```

where the canonical error format supports field.

---

# 122. No Raw Exception Leakage

Never return:

```text
finfo error
filesystem path
bucket
AWS error body
driver exception
PHP temp path
```

---

# 123. Logging

Safe logs may contain:

```text
request_id
error code
validated content type where appropriate
size
parent type
```

Do not log:

```text
raw file
private file contents
upload token
Authorization bearer
full customer submission
storage credentials
```

---

# 124. Storage Failure Logs

Storage exception text may contain provider/internal information.

Sanitize according to existing security logging policy.

---

# 125. Private Response

REQ-001/ENQ-001 responses with attachment metadata remain:

```text
Cache-Control: private, no-store
Vary: Authorization
```

where applicable.

---

# 126. No CDN Public Cache

Never mark attachment-bearing parent responses:

```text
public
s-maxage
```

---

# 127. Attachment Resource Exposure

Authorized metadata:

```text
id
filename
content_type
size
url|null
```

No:

```text
storage_key
disk
bucket
etag
internal path
capability digest
database parent FK
```

---

# 128. Anonymous Creation Response

Anonymous creation may receive attachment metadata for the file it just submitted.

This does not grant later retrieval.

---

# 129. Attachment Relationship

Parent resource:

```text
attachments: []
```

or:

```text
attachments: [
  {
    id: "att_...",
    filename: "...",
    content_type: "...",
    size: ...,
    url: null
  }
]
```

Max length:

```text
0..1
```

in V1.

---

# 130. Resource Ordering

With max one file this is trivial.

Do not create attachment ordering infrastructure.

---

# 131. Historical Attachment Metadata

Once stored, preserve:

```text
validated filename
validated content type
size
parent association
```

as historical intake metadata.

Do not rewrite because original local file name later changes.

---

# 132. Parent Lifecycle Independence

Changing Request status:

```text
SUBMITTED → IN_REVIEW → CLOSED
```

must not delete/change attachment.

---

# 133. Enquiry Lifecycle Independence

Closing Enquiry must not delete/change attachment.

---

# 134. Product Changes

Product changes do not affect attachment.

---

# 135. Order Changes

Order changes do not affect Enquiry attachment.

---

# 136. No Commerce Side Effects

Attachment upload must not:

```text
create Order
create Payment
reserve inventory
change stock
change Cart
create quote
```

---

# 137. No Notifications

Do not create:

```text
NEW_MADE_TO_ORDER_REQUEST
NEW_ENQUIRY
```

notifications here.

Group R owns notifications.

---

# 138. No Email

None.

---

# 139. No Image AI/Transformation

No.

---

# 140. No Antivirus Service Dependency Unless Approved

Expected external dependency change:

```text
NONE
```

unless actual reliable content-signature verification cannot be implemented using existing PHP/Laravel capabilities.

If dependency becomes necessary:

stop and justify before adding one.

---

# 141. Inline Creation Atomicity Tests

Mandatory Request scenarios:

```text
valid parent + valid file → both exist
invalid parent + valid file → neither persists
valid parent + invalid file → neither persists
storage failure → parent rolls back
DB failure after file write → stored file cleaned
```

---

# 142. Inline Enquiry Atomicity Tests

Same matrix.

---

# 143. Request File Type Tests

At minimum:

```text
real JPEG → accepted
real PNG → accepted
real WebP → accepted
real PDF → accepted
```

---

# 144. Unsupported Type Tests

Examples:

```text
GIF
SVG
TXT
ZIP
```

→:

```text
UNSUPPORTED_ATTACHMENT_TYPE
```

---

# 145. Spoofed Extension Tests

Examples:

```text
PDF bytes named photo.jpg
text bytes named photo.png
```

Server detection must win.

---

# 146. Spoofed Client MIME Tests

Examples:

```text
text bytes
client MIME image/jpeg
```

must fail.

---

# 147. Signature Mismatch Test

A file with a claimed/extension type but invalid magic must fail safely.

---

# 148. Corrupt/Partial Upload Test

Must fail:

```text
INVALID_ATTACHMENT
```

and create no parent/metadata/file.

---

# 149. Zero Byte Test

Reject.

---

# 150. Filename Traversal Tests

Test:

```text
../../avatar.jpg
..\..\avatar.jpg
foo/bar.jpg
control chars
```

The stored/display name must be safe.

---

# 151. Filename Unicode

Reasonable Unicode display filename may be preserved safely.

Do not corrupt legitimate customer filenames unnecessarily.

---

# 152. Long Filename

Bound/sanitize according to the chosen metadata column and filesystem-safe policy.

Do not let giant filenames cause persistence failure after file storage.

Validate before durable storage.

---

# 153. Multiple Files Test

Reject two attachments on creation.

---

# 154. Existing Attachment Separate Upload Test

Parent already has one:

```text
REQ-007/ENQ-007
→ deterministic conflict
```

No replacement.

---

# 155. Capability Token Tests

Mandatory if separate upload implemented:

```text
valid token correct parent → allowed
wrong parent → rejected
wrong parent type → rejected
expired → rejected
consumed → rejected
random token → rejected
missing token anonymous → rejected
```

---

# 156. Raw Token Persistence Test

Assert raw capability is not stored.

---

# 157. Token Logging Test

Assert raw capability does not appear in logs where feasible.

---

# 158. Failed Validation Token Retry

Invalid file attempt should not consume token if chosen contract semantics follow the recommended successful-consumption rule.

Then valid retry succeeds.

---

# 159. Concurrent Separate Upload

Two concurrent uploads using same single-use token must not both succeed.

At most:

```text
one attachment
one token consumption
```

---

# 160. Concurrency Mechanism

Use transaction + locked capability/parent metadata where required.

Do not rely on:

```text
check token used=false
then later mark used=true
```

without locking/atomic update.

---

# 161. MariaDB Concurrency

If the capability uses database state:

add a MariaDB integration race test for:

```text
same capability
two concurrent uploads
```

provided existing disposable concurrency harness makes it practical.

---

# 162. DB Unique Guard

The one-attachment-per-parent DB constraint should provide an additional final guard.

---

# 163. File Cleanup Under Losing Race

If two concurrent workers both write files before one loses the DB race:

the losing worker must delete its stored file.

No permanent orphan.

---

# 164. Authenticated Ownership Tests

Customer A cannot separately upload to Customer B's parent.

Use masked not-found behavior.

---

# 165. Staff/Admin Tests

Only implement/test staff upload authority if frozen operational permissions clearly permit it in current Phase 10.6 scope.

Do not broaden 10.7 prematurely.

---

# 166. Anonymous No-Retrieval Regression

Even with upload token:

```text
GET parent
GET attachment
```

must not automatically become public.

---

# 167. Route Activation Assessment — Requests

After inline multipart works, verify:

```text
10.1 creation PASS
10.2 validation PASS
10.3 lifecycle PASS
10.4 product eligibility PASS
10.6 attachments PASS
```

If all externally required REQ-001 creation behavior is complete:

assess activation of:

```text
POST /api/v1/requests
```

---

# 168. Request Route Activation

If no other REQ-001 blocker remains:

this phase may remove/enable:

```text
requests.enabled
```

gate according to the project's explicit route-feature strategy.

Do not leave the route gated merely from inertia.

But do not activate if:

```text
upload capability response contract unresolved
multipart encoding unresolved
security validation incomplete
```

---

# 169. Route Activation Assessment — Enquiries

Likewise assess:

```text
POST /api/v1/enquiries
```

after Phase 10.5 + attachment completion.

Do not activate if an Enquiry-specific creation-contract gap remains.

---

# 170. No Environment-Driven Secret Activation

The Request gate was deliberately not environment-driven.

Do not casually turn these APIs on/off with ad-hoc `.env` flags.

Use the approved architecture.

---

# 171. Separate Upload Route Activation

REQ-007/ENQ-007 may remain gated if the scoped capability contract cannot yet be reconciled safely.

However, the frozen contract explicitly includes them.

Report their precise readiness separately from inline creation.

Do not claim full attachment PASS if separate upload is required and missing.

---

# 172. Important Contract Consistency Check

The prose says:

```text
separate upload token returned on REQ-001/ENQ-001 creation
```

Verify whether:

```text
openapi.yaml
api-resources.md
```

define the creation-response token field.

If not, this is a concrete frozen-contract representation gap.

Do not guess a JSON field name.

---

# 173. If Token Response Gap Exists

Classify:

```text
BLOCKER / contract consistency gap
```

for REQ-007/ENQ-007 activation.

Possible Phase 10.6 outcome:

```text
inline attachment support PASS
separate-upload capability BLOCKED
public creation route activation decision based on whether separate upload is required for contract completeness
```

Do not conceal this.

---

# 174. Do Not Invent `upload_token`

Unless already supported by frozen docs/OpenAPI or formally reconciled.

---

# 175. Attachment Error Enum Reconciliation

Same principle.

If frozen attachment codes are absent from OpenAPI/backend enum:

add only the already-frozen codes and document as a consistency correction.

---

# 176. OpenAPI Multipart Review

Verify both:

```text
REQ-001
ENQ-001
```

currently define multipart/form-data correctly.

Ensure the schema represents:

```text
attachment:
  type: string
  format: binary
```

or equivalent frozen representation.

Do not invent an incompatible multipart shape.

---

# 177. OpenAPI Separate Upload Review

Verify:

```text
REQ-007
ENQ-007
```

paths, security/capability representation, file field, response.

Report any gap.

---

# 178. Resource Contract Review

Verify Attachment resource field set remains:

```text
id
filename
content_type
size
url
```

Do not add storage internals.

---

# 179. Migration Tests

If schema added:

extend:

```text
MigrationRebuildTest
SchemaIntegrityTest
```

with:

```text
table presence
FKs
delete policy
parent XOR if applicable
one-per-parent uniqueness
```

---

# 180. SQLite

Canonical suite must prove:

```text
validation
metadata persistence
resource serialization
rollback/cleanup behavior
ownership rules
```

---

# 181. MariaDB

If schema/concurrency behavior depends on:

```text
unique nullable FKs
CHECK/XOR
row locking
single-use token race
```

run the relevant integration tests against disposable MariaDB.

Do not rely only on SQLite for lock proof.

---

# 182. Storage Fake Tests

Use:

```php
Storage::fake(...)
```

for normal unit/feature tests.

Assert:

```text
expected object exists after success
object absent after failure
```

---

# 183. Cleanup Tests

Inject failure:

```text
after storage
before attachment metadata commit
```

and assert:

```text
no parent if atomic inline creation expected
no attachment row
no stored orphan
```

---

# 184. Do Not Over-Test Framework Internals

Focus on domain/security behavior.

Do not assert implementation-specific temp filenames.

---

# 185. Request Regression

Run all:

```text
FurnitureRequest*
RequestStatus*
RequestableProductResolver*
```

Attachment support must not weaken strict validation.

---

# 186. Enquiry Regression

Run all Phase 10.5 Enquiry tests.

Multipart support must preserve contact/order/product authorization.

---

# 187. Security Regression

Run:

```text
body size
anonymous submission throttle
optional auth
invalid bearer
safe logging
security headers
```

where route/middleware changes touch them.

---

# 188. Request Body Ceiling Regression

Ensure valid 5 MiB file + form overhead can pass the application limit.

Also ensure clearly oversized bodies fail early.

---

# 189. Content-Type Boundary

Test:

```text
multipart/form-data → supported
application/json → supported
application/x-www-form-urlencoded → rejected if not frozen
```

Do not widen transport accidentally.

---

# 190. Dependency Audit

No new dependency expected.

If file signature handling uses built-in:

```text
finfo
getimagesize
stream reads
```

document that.

---

# 191. Storage Provider

Report:

```text
Laravel filesystem private disk abstraction
```

not a provider-specific integration.

Do not add Cloudinary merely because the CampusBite project used it.

---

# 192. Production Configuration

Document required:

```text
private attachment disk configured
credentials available outside repo
no public ACL
```

Do not commit credentials.

---

# 193. Fail-Closed Production Config

If production attachment disk is missing/misconfigured:

application should fail safely.

Do not fall back to public disk silently.

---

# 194. Storage Health

Do not build a complex health-check endpoint just for attachments.

Existing deployment checks/documentation are sufficient unless architecture already has startup validation.

---

# 195. Documentation ADR

Add the next available backend ADR.

Likely concept:

```text
Private Request/Enquiry Attachment Architecture
```

Inspect current latest ADR after BACKEND-046/Phase 10.5.

Do not guess numbering.

---

# 196. ADR Must Record

At minimum:

```text
shared Request/Enquiry subsystem
0 or 1 file V1
5 MiB exact byte limit
allowed MIME types
server-detected content authority
signature validation
filename sanitization
private Laravel disk
storage key hidden
Attachment resource shape
url=null or temporary-only policy
inline multipart atomicity/compensation
schema choice if added
parent authorization inheritance
scoped separate-upload capability design
single-use/time-limited semantics
token digest storage if applicable
route activation decision
contract inconsistencies discovered
malware scanning status
```

---

# 197. Update Group J Tracking

If fully successful:

```text
10.1 PASS
10.2 PASS
10.3 PASS
10.4 PASS
10.5 PASS
10.6 PASS
10.7 READY
10.8 NOT STARTED
```

If separate upload contract remains blocked:

state that precisely rather than marking unconditional PASS.

---

# 198. Verification Commands

Run focused attachment tests first.

Then:

```bash
php artisan test --filter=Attachment
php artisan test --filter=FurnitureRequest
php artisan test --filter=Enquiry
php artisan test

vendor/bin/phpstan analyse
vendor/bin/pint --test
composer audit
git diff --check
php artisan route:list
```

Validate:

```text
docs/api/openapi.yaml
```

parses.

If migration added:

```bash
php artisan migrate:fresh --env=testing
```

through the project's normal test harness.

Do not run destructive commands against non-disposable databases.

---

# 199. MariaDB Verification

If new schema/constraint/capability concurrency exists, use:

```text
furnitureapp_test_disposable
```

only.

Report engine/version and exact integration tests.

---

# 200. Completion Report — Phase Status

Return:

## Phase 10.6 status

```text
PASS
```

or:

```text
BLOCKED
```

If partial:

```text
PASS — inline attachments
BLOCKED — separate-upload capability contract
```

only if that distinction accurately reflects the frozen contract.

---

# 201. Completion Report — Persistence

Report:

```text
existing attachment schema reused
```

or:

```text
new migration added
```

and exact table/model.

---

# 202. Completion Report — Parent Model

Report how attachment parent integrity is implemented.

Example:

```text
Request FK
Enquiry FK
XOR parent constraint
max-one-per-parent unique guards
```

or actual architecture.

---

# 203. Completion Report — Storage

Report:

```text
disk configuration key
private visibility
key generation
provider abstraction
```

Do not report secrets.

---

# 204. Completion Report — Validation

Report exact:

```text
max bytes
allowed types
server MIME detection
signature validation
zero-byte behavior
filename sanitization
```

---

# 205. Completion Report — Resource

Report:

```text
id
filename
content_type
size
url
```

and confirm hidden:

```text
storage_key
disk
capability digest
numeric id
```

---

# 206. Completion Report — Inline Request

Report:

```text
REQ-001 JSON path
REQ-001 multipart path
attachment optional
atomic creation behavior
```

---

# 207. Completion Report — Inline Enquiry

Same.

---

# 208. Completion Report — Separate Upload

Report exact state for:

```text
REQ-007
ENQ-007
```

---

# 209. Completion Report — Capability

Report:

```text
generation
scope
TTL
single-use behavior
digest persistence
consumption timing
```

if implemented.

---

# 210. Completion Report — Authorization

Report:

```text
anonymous inline
CUSTOMER inline
customer-own separate upload
cross-customer rejection
anonymous token behavior
staff/admin behavior
```

---

# 211. Completion Report — Atomicity

Report failure points tested:

```text
invalid scalar
invalid file
storage failure
DB failure after storage
concurrent upload race
```

and whether orphans remain.

---

# 212. Completion Report — Route State

Report:

```text
POST /api/v1/requests
POST /api/v1/enquiries
POST /api/v1/requests/{request}/attachments
POST /api/v1/enquiries/{enquiry}/attachments
```

as:

```text
ACTIVE
STUB/GATED
```

individually.

---

# 213. Completion Report — Attachment URLs

State:

```text
permanent public URL = NO
temporary URL = YES/NO
resource url = value/null
```

---

# 214. Completion Report — Malware Scanning

State honestly:

```text
implemented
```

or:

```text
not implemented / deferred
```

Do not call signature validation malware scanning.

---

# 215. Completion Report — Commerce Side Effects

Confirm:

```text
Orders = NONE
Payments = NONE
Inventory = NONE
Cart = NONE
Price/quote = NONE
```

---

# 216. Completion Report — Schema

Report exact.

Unlike prior Group J phases, Phase 10.6 may legitimately need:

```text
NEW MIGRATION
```

if attachment metadata was not previously modeled.

Do not force `NONE` if real persistence is required.

---

# 217. Completion Report — Dependencies

Expected:

```text
NONE
```

---

# 218. Completion Report — Frontend

Expected:

```text
NONE
```

---

# 219. Completion Report — OpenAPI

Report:

```text
UNCHANGED
```

or exact frozen-contract reconciliation for:

```text
attachment errors
multipart encoding
upload capability response
```

---

# 220. Completion Report — Tests

Report:

```text
file validation tests
signature spoof tests
size boundaries
filename safety
inline Request upload
inline Enquiry upload
rollback/orphan cleanup
resource privacy
ownership
capability token
single-use race
schema tests
Request regressions
Enquiry regressions
full suite
MariaDB if applicable
```

---

# 221. Completion Report — Quality

Report:

```text
PHPUnit
PHPStan
Pint
composer audit
git diff --check
route:list
OpenAPI parse
```

---

# 222. Completion Report — Group J

Return:

```text
10.1 PASS
10.2 PASS
10.3 PASS
10.4 PASS
10.5 PASS
10.6 PASS/BLOCKED
10.7 READY/BLOCKED
10.8 NOT STARTED
```

---

# 223. Definition of Done

Phase 10.6 is complete when:

- Request and Enquiry attachments use one security/storage architecture;
- attachment is optional;
- no more than one attachment is allowed per parent in V1;
- JSON creation without attachment still works;
- inline multipart creation works;
- multipart cannot bypass scalar validation;
- file maximum is enforced at 5 MiB;
- JPEG is accepted only when actual content is valid JPEG;
- PNG is accepted only when actual content is valid PNG;
- WebP is accepted only when actual content is valid WebP;
- PDF is accepted only when actual content is valid PDF;
- GIF/SVG/TXT/ZIP and other unsupported types are rejected;
- client MIME is not trusted;
- extension is not trusted;
- server-detected content type is persisted;
- invalid/corrupt/zero-byte files are rejected;
- filename traversal is neutralized;
- storage filenames/keys are server-generated;
- storage is private;
- permanent public URLs are not exposed;
- storage keys are not serialized;
- attachment has opaque `att_...` ID;
- Attachment resource exposes only approved metadata;
- parent resources expose `attachments` metadata;
- inline parent + attachment creation has explicit failure compensation;
- failed creation does not leave intended live orphan files;
- failed file storage does not leave a parent falsely reported as successful;
- Product/Order/contact validation still occurs;
- Request attachment does not alter Request commerce semantics;
- Enquiry attachment does not alter Enquiry semantics;
- Request/Enquiry lifecycle changes do not destroy attachments;
- attachment creates no Order;
- attachment creates no Payment;
- attachment changes no inventory;
- attachment creates no quote;
- separate upload does not use parent ID alone as authorization;
- anonymous separate upload requires a scoped capability;
- capability is random, scoped, time-limited, and single-use;
- raw capability is not stored or logged;
- token consumption is concurrency-safe;
- customer cannot upload to another customer's parent;
- anonymous capability does not grant retrieval;
- second V1 attachment cannot be added;
- no replacement/delete semantics are invented;
- attachment error codes match frozen contract;
- any OpenAPI inconsistency is explicitly reconciled;
- migration is forward-only if needed;
- no hard-coded storage provider is introduced;
- no credentials are committed;
- no frontend work occurs;
- full regression suite passes;
- PHPStan reports zero errors;
- Pint passes;
- Composer audit is clean;
- diff check passes.

---

# 224. Out of Scope

Do not implement:

```text
multiple attachments
attachment replacement
attachment deletion
public attachment browsing
permanent download URLs
image resizing
thumbnail generation
image compression
EXIF processing
OCR
file-to-PDF conversion
Cloudinary-specific integration
S3-specific domain coupling
malware service integration unless separately approved
Product media upload
Order attachments
chat/message threads
notifications
email
frontend
ClickPesa
Payments
Order workflow
```

---

# 225. STOP Condition

STOP when the backend can safely perform:

```text
REQ-001 / ENQ-001
        ↓
JSON or multipart transport
        ↓
existing scalar/domain validation
        ↓
optional attachment
        ↓
5 MiB/type/signature/name validation
        ↓
private storage
        ↓
attachment metadata
        ↓
parent-scoped private resource
```

and, where the frozen separate-upload contract is fully representable:

```text
REQ-007 / ENQ-007
        ↓
parent authorization
+
scoped single-use upload capability
        ↓
one validated private attachment
```

with:

```text
no permanent public URL
no storage-key leak
no cross-parent access
no commerce side effects
```

Do not continue automatically to Phase 10.7.

DO NOT COMMIT, STAGE OR PUSH.

The project owner handles all Git operations.
---

# Group J Tracking

```text
10.1 PASS — Furniture Request API foundation (ADR/BACKEND-043)
10.2 PASS — Request validation (ADR/BACKEND-044)
10.3 PASS — Request status lifecycle (ADR/BACKEND-045)
10.4 PASS — Product-linked requests (ADR/BACKEND-046)
10.5 PASS — General enquiries (ADR/BACKEND-047)
10.6 PASS — Inline private attachments (ADR/BACKEND-048)
        BLOCKED — separate-upload capability (REQ-007/ENQ-007): frozen prose requires a creation-response
        scoped upload token, but no token field is defined in openapi.yaml or api-resources.md
10.7 NOT STARTED — Staff/admin request management
10.8 NOT STARTED — Request/enquiry tests
```

Route state remains **STUB/GATED** for `POST /api/v1/requests`, `POST /api/v1/enquiries`,
`POST /api/v1/requests/{request}/attachments`, and `POST /api/v1/enquiries/{enquiry}/attachments`.
