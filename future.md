# Future plans

1. make the designs as simple and small as possible since its still a small business with small catalogue of products, No bloated images and layout, keep it consistency, follow the theme and colour inspiration from [https://www.urbanladder.com/](https://www.urbanladder.com/) BUT not the design system since it must use explicitly material ui for react/nextjs and material 3 for flutter, NO custom designs just pure frameworks adaptation.

2. implement material ui for react web application and material 3 for flutter app. do not create custom buttons, cards, forms, navbars other than that provided by the design frameworks(material ui for react and material 3 for flutter).

3. adding a share button on the products images

4. Set up promotional and heavy advertisment campains including
* Video campaigns in instagram, meta, Youtube
* Promotional codes for a discount for returning customers briging a friend or colleague
* banners and popups for "New customer get a discount!"
* two videos one for broader audience and another for university students
* 30s max 2D explainer videos real human and furnitures

5. using **CLERK auth** for authentication handling while we handle roles, policies/permissions

6. use urbanladder advertisment style
* a person sees a furniture now he/she imagines the possibilities of owning that furniture, a worker come and ask the customer if he/she wants it the customer says yeah this is the one!

7. migrate remaining factory-local reference helpers to the shared generator (Group D/E): `OrderFactory::generateReference()` (`OD-` + 5), `PaymentFactory::generateReference()` (`PAY-` + 8) and `FurnitureRequestFactory::generateReference()` (`REQ-` + 10) still use `fake()->bothify` with in-process dedup. Replace each `bothify` expression with `App\Support\ReferenceGenerator::generate(<Model>::REFERENCE_PREFIX, <length>)`, keeping the existing `do/while usedReferences` wrappers untouched (`EnquiryFactory` already migrated and is the pattern to follow). At the same time, point the API service layer (checkout → Order, payment initiation, request intake) at `ReferenceGenerator` directly for server-issued references so no faker involvement remains in production paths. Verify with the existing per-domain reference-format/immutability tests; no new tests strictly required.

8. use lovable or ai studio to speed up development for the admin dashboard UI and features- by connecting to github with the repo.

9. fix this. Staff-lifecycle endpoints (ADM-001..006) are still notImplemented() (501); orders/payments/delivery are formally deferred (11.7/11.11/11.12), and checkout is permanently gated. Production scope is request-first, so the website's primary flows are catalog → made-to-order request → enquiry, and the admin app can't yet manage staff/orders.