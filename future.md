# Future plans
* make the designs as simple and small as possible since its still a small business wit small catalogue of products, No bloated images and layout, keep it consistency, follow the theme and colour inspiration from [https://www.urbanladder.com/](https://www.urbanladder.com/) BUT not the design system since it must use explicitly material ui for react/nextjs and material 3 for flutter, NO custom designs just pure frameworks adaptation.
* implement material ui for react web application and material 3 for flutter app. do not create custom buttons, cards, forms, navbars other than that provided by the design frameworks(material ui for react and material 3 for flutter).
* During the Laravel backend implementation phase, ensure form requests and DTO mappers enforce FormRequest::validated() strictly rather than passing $request->all() to models, directly honoring the additionalProperties: false rules established in the schemas. Require the rate limiter middleware to produce the standard Retry-After header matching the documented contract in api-conventions.md:32

# tech stack
Next.js + MUI + Laravel API + MySQL + Flutter + videojs for videos
Website: Next.js + MUI
App: Flutter + Material 3
Backend: Laravel API
Database: MySQL
Admin: Next.js + MUI
Authentication: Shared account across app/site
Product images: Object storage/CDN
Payments: Payment gateway integrated through Laravel
Orders: Simple status workflow + history
Delivery: Pickup or delivery
Made-to-order: Request/quotation workflow
General enquiries: Separate enquiry system


1.28  User/Profile API Contract
1.29  Staff/Admin Operational API Contract
1.30  Cross-Domain API Contract Review
1.31  Canonical API Examples
1.32  OpenAPI Contract Specification
1.33  API Contract Security Review
1.34  API Contract Consistency & Completeness Review
1.35  Version 1 API Contract Freeze