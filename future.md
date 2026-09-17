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

8. USE font awesome icons [fontawesome](https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css)

9. mitigate the recorded Group C data risks when their owner phase arrives (see `docs/decisions.md` ADR/BACKEND-020/022, phase 3.19 §23):
* `products.product_type` / `is_published` absent from schema though the frozen catalog contract references them — add when catalog availability lands (Group E, phase 5.7); made-to-order eligibility stays Group J domain validation.
* MySQL enums match case-insensitively (e.g. lowercase `relation_type` persists at DB level; app enum casts remain authoritative) — Group K admin category/product writes must validate CLOSED values before attach; do not rely on DB rejection alone.
* `carts.user_id` FK is RESTRICT on MySQL but SET NULL on sqlite (preservation holds on both, proven) — unify delete action when the user-account retention policy is decided (Group D profile ops / Group K customer management).
* 9 suite failures occur only on MySQL-backed runs (raw `PRAGMA`, pcntl-fork connection loss, raw `DROP CHECK` in test helpers) — sqlite remains canonical CI; port those harness assumptions only if MySQL-backed CI is introduced (Group U).

10. A good furniture store
Easy Navigation: A great website is simple to use. Categories are clear, and menus are easy to follow. This is the foundation for cool furniture stores online.

High-Quality Images and Details: Shoppers cannot touch or feel the furniture online. That is why sharp images, multiple views, and detailed descriptions are a must. They help buyers make confident decisions.

Smooth Checkout Process: No one likes a complicated checkout. The best websites keep it fast, simple, and secure, with different payment options to fit customer needs.

Mobile-First friendly Design: Most people shop on their phones. A website that looks and works well on any device provides a simple experience for users.

Customer Reviews and Trust Signals: Real reviews, ratings, and clear return policies build trust. They make buyers feel safe when spending on big items and buying from online modern furniture stores.

Personalization and Inspiration: The best platforms do not just sell furniture. They guide customers with room ideas, style suggestions, and product recommendations, making shopping easier and more fun.

Best support for assistive technologies.

In short, a great furniture eCommerce website blends design, function, and trust. By investing in professional eCommerce web design services, brands can overcome the challenges of traditional retail and create an online shopping experience that is smooth, intuitive, and enjoyable.