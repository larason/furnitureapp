# Domain Glossary — Version 1

## How to Read This Document

This glossary defines the canonical vocabulary for every Version 1 business concept. It uses **business language**, not technical/database language.

Each entry records:

- **Definition** — what the concept means in the business domain.
- **Purpose** — why the concept exists in Version 1.
- **Owner/parent** — the larger business concept that owns it (see `domain-boundaries.md`).
- **Important distinctions** — what this concept must never be confused with.
- **Version 1 relevance** — how (or whether) the concept is used in Version 1.

A concept is marked `DEFERRED` when the approved Phase 1.1 decisions intentionally postpone its detailed definition. Nothing in this document is a database schema or an API endpoint.

---

# Identity Domain

## User

- **Definition:** The system-level identity of a person who can interact with the platform. A User may be a customer, a staff member, or an administrator; role determines what the user is authorized to do.
- **Purpose:** Provides one shared identity concept so that customers, staff, and admins are not modeled as separate, incompatible systems.
- **Owner/parent:** Identity domain.
- **Important distinctions:** User ≠ Customer Session. A User is a persistent identity; a session is a transient authentication state. User ≠ role: a single User concept carries a role rather than each role being a distinct entity.
- **Version 1 relevance:** IN. One User concept supports customer, staff, and admin personas. Detailed role/permission modeling belongs to Phase Group D.

## Customer

- **Definition:** A User whose role makes them a buyer/requester in the commerce flows.
- **Purpose:** Identifies who owns carts, orders, and account-level operations.
- **Owner/parent:** User (a Customer is a User with the customer role).
- **Important distinctions:** Customer ≠ Guest visitor (a guest has no User identity). Customer ≠ "just a shopper": a registered customer can also submit requests and enquiries.
- **Version 1 relevance:** IN. Checkout requires an authenticated Customer account.

## Guest Visitor

- **Definition:** A person browsing public content without an authenticated account.
- **Purpose:** Represents anonymous discovery and anonymous communication before/without registration.
- **Owner/parent:** Identity domain (no User record).
- **Important distinctions:** Guest Visitor ≠ Customer (no account, no orders). Guest browsing is a first-class supported state, not an error. A Guest may maintain a cart, but a guest is not a Customer until they authenticate.
- **Version 1 relevance:** IN. Guests can browse/search/view catalog, submit made-to-order requests, and submit general enquiries with contact information, and may maintain a guest cart. Guests cannot checkout.

## Authenticated Customer

- **Definition:** A registered User with the customer role who has authenticated successfully.
- **Purpose:** Gates account-required operations (checkout, orders, profile, notifications).
- **Owner/parent:** User (customer role).
- **Important distinctions:** Authenticated Customer ≠ Guest Visitor. Authentication is a state of a User, not a separate concept. A guest cart becomes an authenticated cart when the guest logs in.
- **Version 1 relevance:** IN. Required for checkout, payment, order viewing/tracking, profile, notifications. Cart access itself is available to guests; a guest cart is bound to the account upon authentication.

## Staff

- **Definition:** A User whose role performs operational business work (order handling, request/enquiry handling, fulfilment).
- **Purpose:** Enables the business to operate through the admin client rather than the database.
- **Owner/parent:** User (staff role).
- **Important distinctions:** Staff ≠ Admin (higher-privilege role, defined later). Staff capabilities are not fully authorized in this phase.
- **Version 1 relevance:** IN (recognized). Exact permission granularity is deferred to Phase Group D.

## Admin

- **Definition:** A User whose role has broader administrative control (product, category, inventory, customer, staff management, system settings).
- **Purpose:** Enables full platform administration.
- **Owner/parent:** User (admin role).
- **Important distinctions:** Admin ≠ Staff. Admin is the higher-privilege role.
- **Version 1 relevance:** IN (recognized). Exact permission granularity is deferred to Phase Group D.

---

# Catalog Domain

## Category

- **Definition:** A named grouping used to organize furniture products for browsing and discovery.
- **Purpose:** Gives the catalog structure so customers can navigate by furniture type and the admin can organize products.
- **Owner/parent:** Catalog domain (owns its own catalog information).
- **Important distinctions:** Category ≠ Product (a category groups products; it is not a purchasable item). Category hierarchy is not defined in this phase.
- **Version 1 relevance:** IN. Public category browsing; admin category management.

## Product

- **Definition:** A furniture item represented in the catalog. A Product may be either available for direct purchase (`IN_STOCK`) or requestable for manufacture (`MADE_TO_ORDER`).
- **Purpose:** The central discoverable and sellable object of the catalog.
- **Owner/parent:** Catalog domain.
- **Important distinctions:** Product ≠ Inventory (Product describes what is sold; Inventory describes how many units are available). Product ≠ Order Item (an Order Item is a historical snapshot of a purchased product). Product ≠ Made-to-order Request (a request references a product; it is not the product).
- **Version 1 relevance:** IN. The primary catalog entity.

## Product Type

- **Definition:** The business classification that controls the customer's primary action on a Product.
- **Purpose:** Ensures the frontends behave correctly and the backend authoritatively enforces the right workflow.
- **Owner/parent:** Product.
- **Important distinctions:** Product type ≠ availability quantity. A `MADE_TO_ORDER` product is not "out of stock"; it is deliberately request-only.
- **Version 1 relevance:** IN. Two values only: `IN_STOCK` (Add to Cart / Buy → Checkout) and `MADE_TO_ORDER` (Request This Furniture).

## Product Image

- **Definition:** A visual representation of a Product used in catalog listing and detail pages.
- **Purpose:** Furniture is a visual purchase; images are core to discovery and trust.
- **Owner/parent:** Product.
- **Important distinctions:** Product Image ≠ Attachment (attachments belong to requests/enquiries and are optional user-supplied files). Image storage technology is not chosen in this phase.
- **Version 1 relevance:** IN. Public product images; admin image management.

## Product Variant

- **Definition:** A purchasable variation of a Product (e.g., by color, material, size, or configuration). When variants exist, the variant is the purchasable inventory unit and may carry its own stock and pricing when required.
- **Purpose:** Represents the fact that one catalog product can be sold in several distinct purchasable forms.
- **Owner/parent:** Product (a variant belongs to a Product).
- **Important distinctions:** Product Variant ≠ Product (a variant is a variation of one product, not a standalone product). Product Variant ≠ Inventory (inventory describes the units of a product/variant).
- **Version 1 relevance:** IN. Variants may have their own stock and pricing when required; final representation is decided in the data-model phase.

## Product Availability

- **Definition:** The customer-facing signal of whether a Product/Variant can be purchased now (`IN_STOCK`) or can only be requested (`MADE_TO_ORDER`), together with purchasable-unit availability for in-stock items.
- **Purpose:** Tells the customer whether to buy or to request.
- **Owner/parent:** Product (and its inventory).
- **Important distinctions:** Product availability ≠ Payment state. Availability is a catalog/inventory fact; payment state is a financial fact. Customer-facing availability is controlled by authoritative backend inventory, never by a frontend's cached value.
- **Version 1 relevance:** IN. Public read-only; final availability checked by the backend at purchase time.

## Inventory

- **Definition:** The business concept of how many purchasable units of a product/variant are currently available, distinct from the product description itself.
- **Purpose:** Prevents overselling and drives availability signals.
- **Owner/parent:** Belongs to a purchasable product/variant.
- **Important distinctions:** Product ≠ Inventory. Inventory distinguishes physical quantity, reserved quantity, and available quantity at the domain level (exact formulas are for the data-model phase).
- **Version 1 relevance:** IN. Backend-controlled; overselling prevention is required. Read availability is public; mutation is admin/staff-only.

---

# Commerce Domain

## Cart

- **Definition:** A person's temporary collection of items intended for checkout. A Cart is **not** strictly bound to an authenticated customer: a guest may maintain a cart, and the cart becomes associated with the customer's account upon authentication.
- **Purpose:** Lets anyone accumulate `IN_STOCK` items before converting them into an order.
- **Owner/parent:** Commerce domain. May be guest-owned (no User) or bound to an Authenticated Customer.
- **Important distinctions:** Cart ≠ Order (a cart is tentative; an order is confirmed and enters the workflow). Cart contents do not guarantee inventory reservation. Availability may change between adding and checkout; the backend revalidates before creating an order.
- **Version 1 relevance:** IN. Guests may create and maintain a cart; the cart is associated with the customer's account on authentication. Checkout still requires an authenticated customer account.

## Cart Item

- **Definition:** A single line in a cart representing a chosen product/variant and quantity.
- **Purpose:** Composes the cart's contents.
- **Owner/parent:** Cart.
- **Important distinctions:** Cart Item ≠ Order Item (an order item is a committed historical snapshot; a cart item is tentative and revalidated).
- **Version 1 relevance:** IN.

## Order

- **Definition:** The confirmed commerce record representing a purchase attempt that has entered the checkout/order workflow, containing purchased item information and the selected fulfillment method.
- **Purpose:** The core record for commerce, fulfilment, payment, and tracking.
- **Owner/parent:** Commerce domain (customer-owned).
- **Important distinctions:** Order ≠ Made-to-order Request. Order ≠ Enquiry. Order ≠ Cart. Order must preserve historical purchase information (product name, identifier/SKU, unit price, quantity, subtotal) even if the catalog product later changes.
- **Version 1 relevance:** IN. Created only through the purchase flow.

## Order Item

- **Definition:** A line in an Order capturing a purchased product/variant as it was at purchase time (name, identifier, unit price, quantity, subtotal).
- **Purpose:** Preserves order-time facts so history is accurate even if the catalog changes.
- **Owner/parent:** Order.
- **Important distinctions:** Order Item ≠ Product (it is a snapshot, not the live catalog record). Order Item ≠ Cart Item (committed vs tentative).
- **Version 1 relevance:** IN.

## Fulfillment

- **Definition:** The way the customer receives the order.
- **Purpose:** Captures the two Version 1 delivery modes at checkout.
- **Owner/parent:** Order.
- **Important distinctions:** Fulfillment ≠ Order (fulfillment is a property/workflow of an order). Fulfillment ≠ Payment.
- **Version 1 relevance:** IN. Exactly two modes: `PICKUP` and `DELIVERY`.

## Pickup

- **Definition:** Fulfillment where the customer collects the order from the business.
- **Purpose:** A free fulfillment option.
- **Owner/parent:** Order (fulfillment mode).
- **Important distinctions:** Pickup ≠ Delivery (no fee, no delivery address needed).
- **Version 1 relevance:** IN. No delivery fee applied.

## Delivery

- **Definition:** Fulfilment where the business delivers the order to the customer.
- **Purpose:** A paid fulfillment option managed by the business.
- **Owner/parent:** Order (fulfillment mode).
- **Important distinctions:** Delivery ≠ Order (delivery is a mode/workflow of an order). Delivery ≠ Payment. Delivery ≠ Delivery Fee Rule (the fee rule is pricing configuration; the applied fee is part of the order's monetary data).
- **Version 1 relevance:** IN. Requires a delivery address and a delivery fee derived from the delivery location/region.

## Delivery Fee

- **Definition:** The amount charged for `DELIVERY` fulfillment, determined from the business's staff-managed delivery pricing for the customer's delivery location.
- **Purpose:** Covers the business's delivery cost.
- **Owner/parent:** Delivery (fulfillment).
- **Important distinctions:** Delivery Fee ≠ fixed catalog price. The fee is variable and staff-controlled; the customer cannot arbitrarily set the final fee. It is an order monetary component that must be included in backend-authoritative totals.
- **Version 1 relevance:** IN. Staff-controlled and differentiated by delivery region/location (e.g., Dar es Salaam — Kinondoni: free, Ubungo: TZS 5,000). At checkout, once the customer provides their delivery location and address, the applicable fee is resolved from the staff-managed rules and displayed to the customer before payment.

## Delivery Fee Rule

- **Definition:** A staff-managed pricing rule that assigns a delivery fee to a delivery region/location (e.g., Dar es Salaam — Kinondoni: free, Ubungo: TZS 5,000). Staff enter this pricing as part of product/delivery configuration.
- **Purpose:** Gives the business control over variable delivery pricing without letting the customer influence the final fee.
- **Owner/parent:** Business delivery configuration (staff-managed); applied to Delivery and Order.
- **Important distinctions:** Delivery Fee Rule ≠ a single fixed fee. Delivery Fee Rule ≠ the applied order-level fee (the applied fee becomes part of the order's authoritative monetary data). Delivery Fee Rule ≠ Delivery Address (a rule is pricing; an address is the precise destination).
- **Version 1 relevance:** IN. Staff-managed; used to resolve the fee for an order based on the customer's delivery location/address at checkout.

## Delivery Region / Delivery Location

- **Definition:** The geographic tiering used to price delivery (e.g., region "Dar es Salaam" containing locations such as Kinondoni and Ubungo).
- **Purpose:** Provides the key used to look up the applicable Delivery Fee Rule for a customer's order.
- **Owner/parent:** Delivery Fee Rule configuration (staff-managed).
- **Important distinctions:** Delivery Region/Location ≠ Delivery Address (the address is the precise order destination; the region/location is the price-tier key).
- **Version 1 relevance:** IN for fee determination. The exact geographic depth/representation is a later-phase implementation decision.

## Payment

- **Definition:** The financial transaction associated with an Order.
- **Purpose:** Records how the order is paid and its financial state.
- **Owner/parent:** Order (one order may relate to one or more payment transactions).
- **Important distinctions:** Payment ≠ Order Status (payment state is distinct from order state). Payment ≠ Product availability. Payment state must be verified by the backend (provider callback/webhook), never by frontend claims.
- **Version 1 relevance:** IN as a boundary. Provider selection and provider-specific behavior are deferred to Phase Group G.

---

# Order Operations Domain

## Order Status

- **Definition:** The lifecycle state of an Order.
- **Purpose:** Drives fulfilment, customer tracking, and staff operations.
- **Owner/parent:** Order.
- **Important distinctions:** Order Status ≠ Payment Status (distinct state machines). Clients never invent or force status changes; the backend validates transitions.
- **Version 1 relevance:** IN. Lifecycle states: `PENDING_PAYMENT`, `PAID`, `ACCEPTED`, `PROCESSING`, `READY_FOR_PICKUP`, `SHIPPED`, `DELIVERED`, `COMPLETED`, `CANCELLED`. Exact legal transitions are formalized in the later order-state phase.

## Order Status History

- **Definition:** A chronological record of significant order status changes.
- **Purpose:** Provides the customer tracking timeline and operational accountability.
- **Owner/parent:** Order.
- **Important distinctions:** Order Status History ≠ Order Status (history is a set of past transitions; status is the current state).
- **Version 1 relevance:** IN. Every significant transition is recorded.

## Cancellation

- **Definition:** The order operation by which an order is ended without completion.
- **Purpose:** Handles orders that cannot or should not proceed.
- **Owner/parent:** Order (operation on an order).
- **Important distinctions:** Cancellation ≠ automatic refund. Not every cancellation means a refund; refund behavior belongs to later payment/order rules.
- **Version 1 relevance:** IN, time-bounded for customers (see Cancellation Window). Eligibility is evaluated by the backend.

## Cancellation Window

- **Definition:** The approved 20-minute period after order creation during which the customer may cancel the order.
- **Purpose:** Bounds customer-initiated cancellation.
- **Owner/parent:** Cancellation (rule on orders).
- **Important distinctions:** Cancellation Window ≠ refund policy (not invented here). Interaction with payment/processing state is formalized later.
- **Version 1 relevance:** IN. Customer cancellation allowed only within 20 minutes of order creation; exact transition/refund behavior deferred to later order/payment contract phases.

---

# Customer Communication Domain

## Made-to-order Request

- **Definition:** A business lead/request from a customer asking the business to manufacture a furniture item; it is a request, not a purchase.
- **Purpose:** Captures demand for `MADE_TO_ORDER` furniture without creating an order.
- **Owner/parent:** Customer communication domain. May reference a Product; may reference a User; may exist without a User (anonymous).
- **Important distinctions:** Made-to-order Request ≠ Order (never automatically an order, payment, or stock reservation). Made-to-order Request ≠ Enquiry (a request asks to manufacture a specific furniture item; an enquiry is general). A request may include quantity, dimensions, preferred material/color, notes, and optional attachment.
- **Version 1 relevance:** IN. Submittable anonymously with contact information; admin/staff follow-up.

## General Enquiry

- **Definition:** A general customer communication that does not necessarily ask the business to manufacture a particular furniture item.
- **Purpose:** Captures broader customer demand and questions as leads for the sales/admin team.
- **Owner/parent:** Customer communication domain. May reference a User; may exist without a User.
- **Important distinctions:** Enquiry ≠ Made-to-order Request (different business intents; do not merge them). Enquiry ≠ Order. An enquiry must support contact information and a message, with optional attachment.
- **Version 1 relevance:** IN. Submittable anonymously with contact information; admin/staff follow-up.

## Attachment

- **Definition:** An optional file associated with a Made-to-order Request or a General Enquiry.
- **Purpose:** Lets customers share sketches, photos, or documents that help the business understand the request/enquiry.
- **Owner/parent:** Belongs to a Made-to-order Request or a General Enquiry.
- **Important distinctions:** Attachment ≠ Product Image (images are curated catalog content; attachments are user-supplied files on communication records).
- **Version 1 relevance:** IN conceptually. Storage/upload implementation and cardinality are deferred to a later phase.

---

# Supporting Concepts

## Address

- **Definition:** The set of location/contact details used for order fulfilment and payment.
- **Purpose:** Captures where and how an order is delivered and billed.
- **Owner/parent:** Part of the relevant transaction/order data (no persistent customer address book in Version 1).
- **Important distinctions:** Address ≠ Address Book (the book is deferred). Address is primarily transaction/order data in Version 1.
- **Version 1 relevance:** IN as transaction data; the persistent saved-address concept is DEFERRED.

## Billing Address

- **Definition:** The address associated with payment/transaction information as required by the payment flow.
- **Purpose:** Supports the payment flow's address requirements.
- **Owner/parent:** Order/payment transaction data.
- **Important distinctions:** Billing Address ≠ Delivery Address (a delivery order needs a delivery address; billing address is tied to payment).
- **Version 1 relevance:** IN at checkout (collected with payment information).

## Delivery Address

- **Definition:** The destination for a `DELIVERY` order.
- **Purpose:** Tells the business where to deliver.
- **Owner/parent:** Order (delivery fulfillment).
- **Important distinctions:** Delivery Address ≠ Billing Address. Only required for `DELIVERY` fulfillment.
- **Version 1 relevance:** IN. Captured per checkout/order for delivery orders.

## Contact Information

- **Definition:** The customer's reachable details (email and/or phone) used for follow-up.
- **Purpose:** Enables the business to follow up on anonymous requests/enquiries and to communicate with customers.
- **Owner/parent:** Identity/communication domain.
- **Important distinctions:** Contact Information ≠ a full account. Anonymous requests/enquiries collect contact information without creating a User.
- **Version 1 relevance:** IN. Required for anonymous requests and enquiries.

## Currency

- **Definition:** The monetary unit in which all Version 1 prices, fees, and totals are expressed.
- **Purpose:** Establishes one consistent money context (TZS per the project vision) so financial calculations are unambiguous.
- **Owner/parent:** Commerce domain.
- **Important distinctions:** Currency ≠ a pricing rule. Money must still be represented safely (no unreliable floating-point financial math); the exact representation is a data-model decision.
- **Version 1 relevance:** IN, single currency assumed for Version 1.

## Order Number / Reference

- **Definition:** The human-readable customer-facing reference for an order, using the `OD-` prefix.
- **Purpose:** Gives customers and staff a friendly identifier for orders.
- **Owner/parent:** Order.
- **Important distinctions:** Order Number ≠ Order id. The suffix format, length, and generation strategy are deliberately not decided here.
- **Version 1 relevance:** IN. Format `OD-*****`; sequence generation and collision-safe implementation belong to the later order-contract phase.

---

# Supporting / Cross-Cutting (Deferred)

## Saved Address Book

- **Definition:** A persistent customer-maintained collection of reusable addresses.
- **Purpose:** (None in Version 1.)
- **Owner/parent:** DEFERRED.
- **Important distinctions:** Deferred per Phase 1.1 decision 11. Addresses are captured per checkout/order.
- **Version 1 relevance:** DEFERRED. Not modeled in Version 1.

## Notification

- **Definition:** A communication/alert associated with an application event, such as order-state changes.
- **Purpose:** Keeps customers and staff informed of relevant events.
- **Owner/parent:** Supporting domain.
- **Important distinctions:** Notification ≠ core commerce record (notifications reference events but do not drive the order state machine). Real email/push delivery is deferred to Phase Group R.
- **Version 1 relevance:** IN minimally (in-app notification records/events); external delivery DEFERRED to Phase Group R.