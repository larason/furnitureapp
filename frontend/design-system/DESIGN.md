# Furniture Product Design System

> Category: E-Commerce & Furniture Retail
> Modern furniture commerce. Monochrome architectural UI, bold uppercase display type, full-bleed interior & craftsmanship photography.

## 1. Visual Theme & Atmosphere

Our furniture commerce platform is a digital showroom and architectural gallery — an environment that channels the enduring beauty of furniture craftsmanship into a refined shopping experience. The design operates on a principle of radical simplicity: strip everything back to black, white, and grey so that natural timber grains, tactile upholstery, leather patinas, and architectural interior photography dominate without distraction. The result feels less like a conventional web shop and more like a high-end furniture monograph or architectural exhibition catalog laid out with curated precision. Every element either showcases craftsmanship or facilitates an intuitive purchase or bespoke inquiry.

The Core Design System establishes an uncompromising monochromatic foundation. The interface recedes into charcoal black (`#111111`) typography and pure white (`#FFFFFF`) surfaces, allowing hero photography — sunlit living spaces, crafted solid wood joinery, hand-finished dining tables, and sculptural chairs — to carry the emotional weight. When color appears in the interface, it is strictly functional: red for clearance or validation errors, blue for navigation links and focus states, green for in-stock confirmations. The furniture pieces and raw materials themselves (walnut, oak, brass, bouclé, linen, and steel) provide the rich color story. This restraint creates an intentional visual balance: the richest, warmest furniture photography presented within the cleanest, most disciplined layout.

The typography system provides the architectural scaffolding of the brand identity. Monumental uppercase headlines in condensed geometric display (Futura Condensed) with tight line-height (0.90) command attention across hero room scenes. Below the display headlines, the workhorse Helvetica Now family handles furniture specifications, material details, dimensions, and navigation with Swiss-precision clarity. This split between bold architectural display type and functional informational type mirrors our brand duality: inspiring interior design meets functional, everyday craftsmanship.

### Brand Identity & Offerings

- **Primary Offering (IN_STOCK):** Ready-made furniture available for immediate purchase, rapid fulfillment, and either complimentary showroom self-pickup or paid home delivery.
- **Secondary Offering (MADE_TO_ORDER):** Bespoke and customizable furniture commissioned on request, with tailored dimensions, timber choices, and upholstery options guided by artisan follow-up.
- **General Inquiries:** Direct concierge communication for custom architectural ideas, bulk commercial/office fitouts, and material sampling.

### Audience

- **Discerning Homeowners & Renters:** Seeking enduring, well-crafted living, dining, bedroom, and home office pieces with clear dimensions, transparent material sourcing, and honest pricing.
- **Interior Designers & Architects:** Sourcing ready-to-ship stock for project deadlines or commissioning custom made-to-order pieces for bespoke residential and hospitality spaces.
- **Remote Professionals:** Investing in ergonomic, aesthetically refined solid timber workspaces and modular storage solutions.

### Key Characteristics

- Monochromatic UI (charcoal, white, and neutral greys) that lets furniture photography be the sole color source
- Massive uppercase display typography (96px, line-height 0.90) that punches through hero interior imagery
- Full-bleed photography with no border radius — architectural imagery fills every available edge
- Pill-shaped buttons (30px radius) as the primary interactive element
- 8px spacing grid with architectural discipline — every measurement snaps to the system
- Category-driven shopping architecture with large navigational image cards (Living, Dining, Bedroom, Workspace)
- Shadow-free, border-minimal elevation model — surface differentiation through subtle grey shifts only

## 2. Color Palette & Roles

### Primary

- **Charcoal Black** (`#111111`): The foundation — primary text, button backgrounds, nav text, hero overlays. Deliberately not pure black (`#000000`), creating a fractionally softer reading experience reminiscent of blackened steel or dark stained oak.
- **Pure White** (`#FFFFFF`): Primary page canvas, button text on dark surfaces, card backgrounds, navigation bar background.

### Surface & Background

- **Snow** (`#FAFAFA`): Lightest surface, near-white subtle differentiation (`--surface-warm`)
- **Light Gray** (`#F5F5F5`): Secondary background, search input fill, furniture image placeholder, loading skeleton (`--surface`)
- **Hover Gray** (`#E5E5E5`): Hover state background, disabled button fill (`--border-soft`)
- **Dark Surface** (`#28282A`): Primary background on dark/inverted showroom sections
- **Deep Charcoal** (`#1F1F21`): Inverse primary background, darkest non-black surface
- **Dark Hover** (`#39393B`): Hover state on dark backgrounds

### Neutrals & Text

- **Primary Text** (`#111111`): Main body text, headings, navigation links (`--fg`)
- **Secondary Text** (`#707072`): Descriptive copy, material specs, dimensions, timestamps, price labels (`--muted`)
- **Disabled Text** (`#9E9EA0`): Inactive elements, out-of-stock / unavailable variants (`--meta`)
- **Disabled Inverse** (`#4B4B4D`): Disabled text on dark showroom backgrounds
- **Border Primary** (`#707072`): Standard border color, matching secondary text
- **Border Secondary** (`#CACACB`): Subtle borders, form input borders, divider lines (`--border`)
- **Border Disabled** (`#CACACB`): Inactive border state
- **Border Active** (`#111111`): Active/focused border, matching primary text

### Semantic & Accent

- **Critical / Sale Red** (`#D30005`): Form errors, clearance / sale badges, urgent inventory alerts (`--danger`)
- **Bright Red** (`#EE0005`): Red-500, slightly lighter red for emphasis
- **Orange Badge** (`#D33918`): Badge text, "Made to Order" callouts, promotional tags
- **Orange Flash** (`#FF5000`): Expressive accent, new collection highlight
- **Success Green** (`#007D48`): Confirmation, "In Stock" indicator, positive order status (`--success`)
- **Success Inverse** (`#1EAA52`): Success on dark backgrounds
- **Link Blue** (`#1151FF`): Text links, dimension guides, material specification links
- **Info Inverse** (`#1190FF`): Links on dark backgrounds
- **Warning Yellow** (`#FEDF35`): Warning backgrounds, low-stock / extended lead-time banners (`--warn`)
- **Focus Ring** (`rgba(39, 93, 197, 1)`): Keyboard focus indicator ring

### Extended Color Spectrum

Each color ramp runs 50–900 for expressive use in editorial campaigns and seasonal showcase pages:

- **Red**: `#FFE5E5` → `#EE0005` → `#530300`
- **Orange**: `#FFE2D6` → `#FF5000` → `#3E1009`
- **Yellow**: `#FEF087` → `#FCA600` → `#99470A`
- **Green**: `#DFFFB9` → `#1EAA52` → `#003C2A`
- **Teal**: `#D4FFFB` → `#008E98` → `#043441`
- **Blue**: `#D6EEFF` → `#1151FF` → `#020664`
- **Purple**: `#E4E1FC` → `#6E0FF6` → `#1C0060`
- **Pink**: `#FFE1F3` → `#ED1AA0` → `#4C012D`

### Gradient System

Avoid interface gradients. When gradients appear, they are strictly photographic — applied to product hero imagery backgrounds (e.g., a walnut credenza or sculptural lounge chair rendered on a warm studio gradient). The interface itself is flat-color only.

## 3. Typography Rules

### Font Family

**Display:** Futura Condensed (condensed geometric sans-serif)
- Fallbacks: Helvetica Now Display Medium, Helvetica, Arial
- Used exclusively for large uppercase display headlines
- Characteristically tight line-height (0.90) and uppercase transform

**Heading:** Helvetica Now Display Medium
- Fallbacks: Helvetica, Arial
- Used for collection headings and furniture product titles at 24–32px

**Body Medium:** Helvetica Now Text Medium (weight 500)
- Fallbacks: Helvetica, Arial
- Used for links, buttons, captions, material tags, emphasized specifications

**Body:** Helvetica Now Text (weight 400)
- Fallbacks: Helvetica, Arial
- Used for standard body copy, furniture descriptions, dimensions, care instructions

**Arabic:** Neue Frutiger Arabic — locale-specific alternative

### Hierarchy

| Role | Size | Weight | Line Height | Letter Spacing | Notes |
|------|------|--------|-------------|----------------|-------|
| Display | 96px | 500 | 0.90 | — | Futura Condensed, uppercase, hero headlines |
| Heading 1 | 32px | 500 | 1.20 | — | Helvetica Now Display Medium, collection & category titles |
| Heading 2 | 24px | 500 | 1.20 | — | Helvetica Now Display Medium, section titles & product titles |
| Heading 3 | 16px | 500 | 1.50 | — | Helvetica Now Text Medium, product card titles |
| Body | 16px | 400 | 1.75 | — | Helvetica Now Text, furniture descriptions & specifications |
| Body Medium | 16px | 500 | 1.75 | — | Helvetica Now Text Medium, emphasized copy & feature callouts |
| Link | 16px | 500 | 1.75 | — | Helvetica Now Text Medium, navigation links |
| Link Small | 14px | 500 | 1.86 | — | Helvetica Now Text Medium, footer & utility links |
| Button | 16px | 500 | 1.50 | — | Helvetica Now Text Medium, primary & secondary CTA text |
| Button Small | 14px | 500 | 1.50 | — | Helvetica Now Text Medium, compact filter & inquiry buttons |
| Caption | 14px | 500 | 1.50 | — | Helvetica Now Text Medium, price labels & stock status |
| Small | 12px | 500 | 1.50 | — | Helvetica Now Text Medium, dimensions & lead-time notices |
| Tiny | 12px | 400 | 1.50 | — | Helvetica Now Text, legal text & copyright |

### Principles

The typography establishes deliberate architectural authority. The display layer — condensed Futura at 96px with a disciplined 0.90 line-height — is engineered to feel like an architectural exhibition title: massive, condensed, uppercase, and structurally grounded. It transforms headlines into bold design statements. Below the display layer, Helvetica Now provides Swiss-precision legibility with a generous 1.75 line-height for comfortable reading of dimensions, wood finishes, joinery techniques, and assembly notes. Weight 500 (Medium) dominates throughout interactive elements, giving product titles and calls-to-action a confident, authoritative presence without unnecessary bold clutter.

## 4. Component Stylings

### Buttons

**Primary (Add to Cart / Buy In-Stock / Submit Request)**
- Background: Charcoal Black (`#111111`)
- Text: White (`#FFFFFF`), 16px/500, Helvetica Now Text Medium
- Border: none
- Border radius: fully rounded pill (30px)
- Padding: ~12px 24px
- Hover: background shifts to Grey-500 (`#707072`), text remains white
- Active: scale(0) ripple effect with opacity 0.5
- Focus: 2px box-shadow ring in `rgba(39, 93, 197, 1)`
- Transition: background 200ms ease

**Primary on Dark (Hero Room Vignettes)**
- Background: White (`#FFFFFF`)
- Text: Charcoal Black (`#111111`)
- Hover: background shifts to Grey-300 (`#CACACB`)

**Secondary / Outlined (Request Custom Quote / View Dimensions / Filter)**
- Background: transparent
- Text: Charcoal Black (`#111111`)
- Border: 1.5px solid `#CACACB` (grey-300)
- Border radius: 30px
- Hover: border darkens to `#707072`, background to grey-200 (`#E5E5E5`)

**Disabled (Out of Stock / Inactive State)**
- Background: Grey-200 (`#E5E5E5`)
- Text: Grey-400 (`#9E9EA0`)
- Cursor: not-allowed

**Icon Button (Wishlist / Cart / Search / Drawer Close)**
- Background: Grey-100 (`#F5F5F5`)
- Shape: 30px radius (or 50% circular)
- Padding: 6px
- Hover: Grey-500 background

### Cards & Containers

- Background: White (`#FFFFFF`) — no visible card border in standard product grids
- Border radius: 0px for furniture product image cards (clean, architectural edge-to-edge imagery), 20px for interactive configurator containers and inquiry dialogs
- Shadow: none — completely flat elevation model
- Hover: no lift or float effect on cards; subtle text underline or secondary image transition on hover
- Product cards: isolated furniture piece or room vignette on top (no radius), product name, wood finish/material, and price below with 12px gap
- Category cards: full-bleed interior photography with text overlay on dark gradient scrim
- Transition: opacity 200ms ease for image swap on hover (e.g., front angle to room vignette or material detail)

### Inputs & Forms (Search, Checkout, Custom Request Forms)

- Background: Grey-100 (`#F5F5F5`)
- Border: 1px solid `#CACACB` when visible, or borderless on header search
- Border radius: 24px (search inputs), 8px (form inputs: name, address, request specifications)
- Font: Helvetica Now Text, 16px
- Focus: border shifts to `#111111` (border-active), 2px focus ring in `rgba(39, 93, 197, 1)`
- Error: border `#D30005` (critical validation error)
- Placeholder: Grey-500 (`#707072`)
- Transition: border-color 200ms ease

### Navigation

- Background: White (`#FFFFFF`), sticky
- Height: ~60px desktop
- Left: Furniture Brand Monogram / Wordmark (minimalist SVG)
- Center: Category links (New Arrivals, Living Room, Dining, Bedroom, Workspace, Made-to-Order) in 16px/500 Helvetica Now Text Medium
- Right: Search (24px radius input), Saved / Wishlist, Cart / Inquiries count
- Hover: text color shifts to Grey-500 (`#707072`)
- Mobile: hamburger menu opening a full-screen drawer
- Top banner: fulfillment and announcement strip with dark background (`#111111`) and white text

### Image Treatment

- Hero images: full-bleed, no border radius, edge-to-edge showcasing styled living spaces and craftsmanship
- Product grid: square (1:1) or 4:3 aspect ratio, no border radius (sharp architectural cut)
- Category cards: 16:9 or 4:3, full-bleed with category title overlay
- Image placeholder: Grey-100 (`#F5F5F5`) solid background
- Lazy loading: native loading="lazy", skeleton placeholder uses `#F5F5F5` background
- Product hover: secondary image swap (isolated piece → room setting or detail joinery shot)

### Promotional & Announcement Banners

- Full-width dark (`#111111`) background with white text
- Tight padding (8–12px vertical)
- Centered text, 12px/500 Helvetica Now Text Medium
- Used for fulfillment announcements (e.g., "Complimentary showroom pickup | In-stock items ship within 48 hours"), custom commission windows, and showroom events

## 5. Layout Principles

### Spacing System

Base unit: 4px (primary grid is 8px multiples)

| Token | Value | Use |
|-------|-------|-----|
| space-1 | 4px | Tight icon gaps, inline badge spacing |
| space-2 | 8px | Base unit, button icon gaps, form label spacing |
| space-3 | 12px | Card internal padding, image-to-metadata gaps |
| space-4 | 16px | Standard padding, nav horizontal spacing |
| space-5 | 20px | Product card grid gaps |
| space-6 | 24px | Section internal padding, major grid gaps |
| space-7 | 32px | Section breaks, module spacing |
| space-8 | 48px | Major section padding, collection breaks |
| space-9 | 64px | Hero section padding, featured showroom bands |
| space-10 | 80px | Large section spacing on desktop displays |

### Grid & Container

- Max container width: 1920px
- Standard content width: ~1440px with horizontal padding
- Product grid: 3-column on desktop, 2-column on tablet, 1-column on mobile
- Category grid: 3-column with full-bleed interior images
- Grid gap: 4–12px between product cards (intentionally tight and architectural)
- Horizontal padding: 48px desktop, 24px tablet, 16px mobile

### Whitespace Philosophy

The whitespace strategy is disciplined and architectural — framing furniture pieces like exhibits in a contemporary design gallery. Product grids use compact gaps (4–12px) to present an abundant, cohesive catalog of timber, textile, and metal designs. Generous section breaks (48–80px) clearly separate curated rooms, in-stock selections, and custom commissioning workflows. The overall effect is a store that feels comprehensive and tactile while remaining effortless to navigate.

### Border Radius Scale

| Value | Context |
|-------|---------|
| 0px | Product images, hero interior photography (sharp architectural edges) |
| 8px | Form inputs, specification modals, inquiry textareas |
| 18px | Small interactive tags, material swatches |
| 20px | Containers, filter dialogs, card wrappers with UI controls |
| 24px | Search inputs, medium pills |
| 30px | Buttons, status pills, category filter pills (full pill) |
| 50% | Circular icon buttons, color/finish swatches, avatar placeholders |

## 6. Depth & Elevation

| Level | Treatment | Use |
|-------|-----------|-----|
| Flat | No shadow, no border | Default state for all cards and page sections |
| Divider | `0px -1px 0px 0px #E5E5E5 inset` | Subtle inset line between catalog rows and footer |
| Focus | `0 0 0 2px rgba(39, 93, 197, 1)` | Keyboard focus ring |
| Overlay | Dark scrim over room photography | Text-on-image legibility across hero bands |

The elevation philosophy is intentionally flat. There are no heavy card drop shadows, no hover lifts, and no floating cards. Depth is communicated exclusively through tonal contrast — dark showroom sections recede, light canvases advance, and grey shifts indicate hover and active states. This flatness reflects honest architectural design: clean lines, authentic materials, and direct communication. The only shadow in the entire system is a 1px inset divider line and the WCAG-compliant focus ring.

### Decorative Depth

- **Hero photography overlays**: Dark gradient scrims over full-bleed room photography for text legibility
- **Product studio backgrounds**: Clean neutral backdrops behind hero furniture pieces (e.g., solid oak dining table with soft natural shadowing captured photographically)
- **Banner bars**: Solid dark (`#111111`) announcement strips at page top

## 7. Do's and Don'ts

### Do

- Use Charcoal Black (`#111111`) for all primary text — never pure `#000000`
- Keep buttons pill-shaped (30px radius) and limited to primary/secondary variants
- Use full-bleed, edge-to-edge photography for hero room sections — no border radius on images
- Let furniture materials (wood grain, leather, bouclé, steel) provide all color vibrancy; keep UI monochromatic
- Use uppercase condensed display typography ONLY for hero display headlines (96px+)
- Maintain tight product grid gaps (4–12px) for an architectural, catalog-gallery feel
- Use Grey-100 (`#F5F5F5`) for all input and placeholder backgrounds
- Reserve color exclusively for semantic meaning (red=error/clearance, green=in-stock, blue=spec link)
- Use weight 500 (Medium) for all interactive text elements and buttons

### Don't

- Don't add shadows to product cards — the elevation model is strictly flat
- Don't use border radius on furniture product imagery — only UI elements receive rounded corners
- Don't introduce brand colors beyond the grey scale for UI elements
- Don't use display typography below 24px — it is exclusively a display face
- Don't add hover lift or float effects — furniture cards do not animate or translate on hover
- Don't use regular weight (400) for buttons or links — always use weight 500
- Don't place saturated colored backgrounds behind UI elements — color is reserved for product photography
- Don't use more than two levels of text hierarchy per product card (title + price/material spec)
- Don't add decorative dividers — the 1px inset is the only divider pattern
- Don't soften contrast — maintain crisp charcoal-on-white clarity across all views

## 8. Responsive Behavior

### Breakpoints

| Name | Width | Key Changes |
|------|-------|-------------|
| Mobile | <640px | Single column, hamburger nav, display text scales down, tight 16px horizontal padding |
| Small Tablet | 640-768px | 2-column product grid begins, nav remains collapsed |
| Tablet | 768-960px | 2-column grids, category cards scale, horizontal padding 24px |
| Small Desktop | 960-1024px | Nav expands to full horizontal, 3-column product grid |
| Desktop | 1024-1440px | Full layout, expanded nav, 3-column grids, 48px horizontal padding |
| Large Desktop | >1440px | Max-width container centered (1440px), hero room images break out full-bleed |

### Touch Targets

- Minimum touch target: 44x44px (WCAG AAA)
- Mobile nav icons: 48x48px touch area
- Product cards: full surface is tappable
- Filter pills: minimum 36px height with 12px padding

### Collapsing Strategy

- **Navigation**: Full category links → hamburger menu below 960px; search, wishlist, and cart icons remain visible
- **Product grids**: 3-col → 2-col at 960px → 1-col at 640px
- **Hero sections**: Display text scales from 96px → 64px → 48px; interior hero images remain full-bleed at all sizes
- **Category cards**: 3-col → 2-col → 1-col with maintained full-bleed interior imagery
- **Section padding**: 80px → 48px → 32px → 24px as viewport narrows
- **Promotional banner**: text wraps or truncates, maintains dark background

### Image Behavior

- Responsive images with modern srcset and Next.js Image optimization
- Product images: srcset with multiple resolutions (w_320, w_640, w_960, w_1920)
- Hero images: full-bleed across all breakpoints, aspect ratio shifts (16:9 desktop → 4:3 mobile)
- Lazy loading: native loading="lazy", Grey-100 (`#F5F5F5`) placeholder skeleton during load
- Art direction: hero crops change between desktop panoramic room scenes and mobile vertical compositions

## 9. Agent Prompt Guide

### Quick Color Reference

- Primary CTA: Charcoal Black (`#111111`)
- Background: Pure White (`#FFFFFF`)
- Secondary surface: Light Gray (`#F5F5F5`)
- Heading text: Charcoal Black (`#111111`)
- Body text / hover: Secondary Text (`#707072`)
- Border: Border Secondary (`#CACACB`)
- Error / Sale: Critical Red (`#D30005`)
- Link: Link Blue (`#1151FF`)

### Example Component Prompts

- "Create a furniture collection hero section with full-bleed edge-to-edge room photography, no border radius, a dark gradient overlay for text, a massive uppercase 96px/500 headline 'ARCHITECTURAL LIVING' with 0.90 line-height, and a Charcoal Black (#111111) pill button (30px radius) 'EXPLORE IN-STOCK'."
- "Design a 3-column furniture product card grid with square images (no border radius), 4px gap between cards, product name 'Solid Oak Dining Table' in 16px/500 Charcoal Black (#111111), price in 14px/500, and material/finish metadata in Grey-500 (#707072)."
- "Build a sticky white navigation bar with a left-aligned minimalist furniture wordmark logo, centered category links (Living Room, Dining, Bedroom, Workspace, Made-to-Order) in 16px/500 (#111111) with hover color #707072, and right-aligned search (24px radius, #F5F5F5 background), wishlist, and cart icons."
- "Create a promotional announcement banner strip with #111111 background, white 12px/500 centered text ('Complimentary Showroom Pickup | In-Stock Furniture Ships Within 48 Hours'), and 8px vertical padding — full width, no border radius."
- "Design a secondary outlined button with transparent background, 1.5px #CACACB border, 30px pill radius, 16px/500 #111111 text 'REQUEST BESPOKE QUOTE', hover border darkening to #707072."

### Iteration Guide

When refining existing screens generated with this design system:
1. Focus on ONE component at a time
2. Reference specific color names and hex codes from this document
3. Remember: furniture photography and natural materials (timber, fabric, leather) provide the color — UI stays monochromatic
4. Use the grey scale for state changes: #F5F5F5 → #E5E5E5 → #CACACB → #707072
5. If something feels too colorful in the UI, it probably is — keep UI greyscale
6. Display type should ALWAYS be uppercase and never below 24px
7. Body type should almost always be weight 500 for interactive elements
