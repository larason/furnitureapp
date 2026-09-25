# Phase 7.2 - Address Model Review

**Status:** PASS

## Decision

The frozen V1 public address vocabulary is:

```text
recipient_name
phone
address_line
city
```

Laravel now adapts to that contract. `region` is not a V1 alias and is
rejected as an unknown field. `postal_code`, country, district, ward,
coordinates, instructions, and saved-address identifiers are also outside the
CHK-001 address model.

## Address Model Chain

```text
future CheckoutRequest.delivery_address
  -> copy recipient_name and phone to Order scalar snapshots
  -> pass address_line and city to AddressField::normalize()
  -> Order.delivery_address JSON snapshot
  -> later Delivery.delivery_address projection
  -> Order API delivery_address.city
```

The Order JSON snapshot and later Delivery JSON projection both use
`address_line` and `city`. No database migration was required because the
existing JSON columns can store the frozen shape without loss of meaning.

## Laravel Reconciliation

`AddressField` was shared only by `Order` and `Delivery`. It now:

- allows exactly `address_line` and `city`;
- requires both values to be strings;
- trims both values;
- rejects missing, null, empty, whitespace-only, `region`, and other unknown
  fields;
- exposes `normalize()` for the request-to-snapshot boundary.

Checkout must pass only the nested `address_line` and `city` fields to
`AddressField::normalize()`; `recipient_name` and `phone` are separate Order
snapshot fields and are not AddressField keys. Order and Delivery model
validation persist the normalized address. No profile fallback, saved-address
lookup, geocoding, city lookup, or delivery-fee logic was introduced.

For already-persisted snapshots from the previous Laravel contract,
`AddressField::normalizeSnapshot()` provides a narrow read/write compatibility
path: a legacy `region` equal to `city` is collapsed to `city`, and a legacy
null `postal_code` is discarded. New public input still uses strict
`normalize()` and rejects both fields. A conflicting legacy `region`/`city`
pair is rejected rather than silently choosing one value.

`recipient_name` remains bounded by the existing 255-character persistence
boundary. Phone normalization remains the existing contract-level concern and
is not expanded into a new dependency or full phone-number library in this
phase.

## Fulfillment Behavior

`PICKUP` continues to require `delivery_address = null`; no fake address is
created. `DELIVERY` requires the complete four-field request snapshot. CHK-001
remains a stub; no Checkout, Order, Delivery, reservation, payment, or fee
workflow was activated.

## Files Changed

- `backend/laravel/app/Support/AddressField.php`
- `backend/laravel/app/Models/Order.php`
- `backend/laravel/app/Models/Delivery.php`
- `backend/laravel/database/factories/OrderFactory.php`
- `backend/laravel/tests/Feature/DeliverySchemaTest.php`
- `backend/laravel/tests/Feature/OrderSchemaTest.php`
- `backend/laravel/tests/Feature/OrderDeliverySnapshotConcurrencyTest.php`
- `backend/laravel/tests/Feature/SchemaIntegrityTest.php`

## Classification

```text
AddressField region mismatch: RESOLVED
Legacy snapshot compatibility: RESOLVED via `normalizeSnapshot()`
Public API vocabulary: city
Laravel snapshot vocabulary: city
Schema changes: NONE
Dependencies: NONE
Frontend changes: NONE
Checkout activation: DEFERRED
```

## Verification

Focused Phase 7.2 tests:

```text
127 tests passed
279 assertions passed
```

Phase 7.3 may review the PICKUP branch without guessing the address field
names or nullability behavior.
