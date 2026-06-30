# GLOSSARY.md — Balloonventory Balloon Industry Vocabulary

This file defines balloon-industry terms that an AI agent or developer unfamiliar with the trade might misread or misinterpret. It is the authoritative reference for domain vocabulary used throughout the codebase, documentation, and catalog data.

When a term in this file conflicts with a general-purpose interpretation, this file wins.

---

## Balloon Sizes

Balloon sizes are model designations, not raw measurements, not counts, and not millimeters. Do not treat them as numbers to be computed.

**Modeling balloons** (elongated, used for twisting and sculpting) are named by a three- or six-digit code. The first digit(s) give approximate uninflated diameter in inches; the last two give approximate uninflated length in inches.

| Size | Common name | Notes |
|------|-------------|-------|
| `160` | Pencil balloon | ~1" diameter × 60" long; thinner than a 260; used for detail work |
| `260` | Standard modeling balloon | ~2" diameter × 60" long; the most common twisting balloon |
| `321` | Bee body | ~3" diameter × 21" long; has a small molded tip at one end; used for bee bodies, apple stems, etc. |
| `350` / `360` | Thick modeling balloon | Larger-diameter modeling balloon; less common than 260 |
| `646` / `660` | Large modeling balloon | Wide-body modeling balloon; used for large sculptures |

**Decorator / round balloons** (filled with air or helium for display) are named by diameter in inches when inflated.

| Size | Notes |
|------|-------|
| `5-inch` | Small round; often used as stuffing in organic designs or clusters |
| `9-inch` | Small/medium round |
| `11-inch` | Standard decorator balloon; most common for events |
| `14-inch` | Large round |
| `16-inch` | Large round |
| `36-inch` | Jumbo round; often called a "geo" or "giant round" |

In the Balloonventory database, the `size` table is the brand-specific. "Size Family" — multiple `balloon_size` rows (brand+material-specific instances) roll up to one `size` and is brand-agnostic. See DATA.md → `size` and `balloon_size`.

---

## Balloon Shapes

Shape describes the physical form of the balloon, not its color or finish.

| Shape | Description |
|-------|-------------|
| `Round` | Standard spherical balloon; the default for decorator work |
| `Heart` | Heart-shaped latex balloon; the ears are fat and short, giving it its shape when inflated |
| `Link` | A balloon with a thin tail that is designed to link together into chains; also sold as "Link-O-Loon" or "Quick Link" |
| `Star` | Five-pointed star-shaped balloon, usually made from foil |
| `Circle` | Flat disc-shaped balloon (distinct from Round), usually made from foil |
| `Non-round` | Catch-all for elongated, oval, or irregular latex shapes that don't fit Round or Heart |
| `Shaped` | Pre-formed foil/latex with a specific non-geometric outline (e.g. a star, cloud, or animal silhouette) |
| `SuperShape` | Large, complex foil balloon — usually a branded character or object shape, usually made from foil |
| `Geo Blossom` | Balloon with a hole in its center, flower-like when inflated; a Qualatex trademark |
| `Geo Donut` | Ring/donut-shaped balloon with a center hole; a Qualatex trademark |
| `Bee Body` | See size `321` above; the shape is defined by the balloon's molded form, not just its size |
| `Other` | Catch-all for shapes not covered above |

---

## Balloon Finishes (Textures)

"Finish" and "texture" are used interchangeably in the trade. They refer to the surface treatment of the latex, not the color. The same color (e.g. red) can exist in multiple finishes. In the Balloonventory database, this attribute is stored in the `texture` table.

| Finish | Description |
|--------|-------------|
| `Standard` | Regular latex; slight natural sheen; semi-opaque |
| `Crystal` | Semi-transparent latex; the color is translucent, not opaque; also called "Jewel tone" by Qualatex |
| `Matte` | Low-sheen, velvet-like surface; no reflective shine |
| `Pearl` | Soft iridescent sheen; slightly opaque; powder-like luminosity |
| `Metallic` | Medium-gloss metallic sheen; fully opaque; has a foil-like look without being actual foil |
| `Chrome` | Mirror-like, high-gloss metallic finish; fully opaque; the most reflective latex finish available |
| `Satin` | Smooth finish with a softer gloss than Chrome; grouped with Chrome in the `texture_family` field |
| `Neon` | Bright fluorescent color; may fluoresce under UV/blacklight |
| `Glow-in-the-dark` | Phosphorescent; charges under light and glows in the dark |
| `Confetti` | Clear latex balloon pre-filled with paper or foil confetti; the "finish" is the clear shell; a product type as much as a finish |
| `Agate` | Marbled, stone-like surface pattern; multiple colors swirled into the latex |
| `Mosaic` | Multi-color geometric pattern on the latex surface |

> **Note:** The Balloonventory `texture` table currently seeds: Crystal, Standard, Matte, Glow-in-the-dark, Metallic, Pearl, Neon, Chrome, Satin. Confetti, Agate, and Mosaic are real industry finishes not yet seeded; add them via migration before using them in catalog data.

---

## Balloon Materials

The substrate the balloon is made from. Stored in the `material` table.

| Material | Description |
|----------|-------------|
| `Latex` | Natural rubber latex; the standard material for both modeling and decorator balloons |
| `Foil` | Metallic polyester film (also called Mylar, though Mylar is a brand name); used for shaped and printed balloons; does not biodegrade |
| `Chloroprene` | Synthetic rubber; used in Qualatex's bubble balloons (e.g. Deco Bubble); more durable and stretchy than standard latex |
| `Plastic` | Rigid or semi-rigid plastic; used for some specialty balloons and balloon weights |
| `Stretchy` | Highly elastic specialty latex; can be stretched significantly beyond standard latex limits |

---

## Brands & Abbreviations

Brand abbreviations appear in BrandTag UI components and throughout catalog data. These are the canonical system abbreviations from `DATA.md`. Do not substitute common internet abbreviations (e.g. "QLX") for these values.

### Seeded brands (v1 catalog)

| Abbreviation | Full name | Notes |
|-------------|-----------|-------|
| `QTX` | Qualatex | Pioneer Balloon Company's flagship brand; made in USA/Canada; wide color range; |
| `STX` | Sempertex | Colombian manufacturer; known for consistent quality and an extensive color palette including unique shades not available elsewhere |
| `TTX` | TufTex | Budget-friendly brand; popular for high-volume decorator work where unit cost matters more than premium finish |
| `BTL` | Betallic | Produces foil and latex balloons; known for SuperShape foils and metallic latex |
| `KLS` | Kalisan | Turkish manufacturer; growing presence in North America; competitive color range |
| `DCX` | Decomex | Specialty foil and printed balloons |
| `FUN` | Funsational | Value-tier latex balloons |

> The abbreviations above are drawn from `DATA.md`. If an abbreviation for a brand is not yet defined in DATA.md, do not invent one — add it to DATA.md first.

---

## Industry Terms

General balloon trade vocabulary an agent may not know.

**Balloon Artist** — a person who creates balloon sculptures. This is an all-encompassing term that includes entertainers, decorators, profeesionals, and hobbyists.

**Twister** — a person who creates balloon sculptures by twisting and knotting modeling balloons. Usually into balloon animals, balloon hats, balloon characters, and more.

**Decorator** — a balloon professional who focuses on large-scale event decor (arches, garlands, columns) rather than on-demand twisted sculptures.

**Déco-twister** — a practitioner who combines large-scale decor and hand-twisted sculpture work.

**Bag** — the unit of inventory for balloons. Manufacturers sell balloons in bags, not by the individual balloon. A "bag" of 11-inch Qualatex rounds typically contains 100 balloons. Balloonventory tracks `stock_level.quantity` in bags (decimal to allow partial bags).

**HiFloat** — a brand-name latex sealant applied inside a balloon before inflation to significantly extend helium float time. Sometimes used as a verb ("did you HiFloat those?"). Not a balloon type or finish.

**Helium-filled vs. air-filled** — balloons can be inflated with helium (floats) or air (does not float, used for ground displays and structures). Float time decreases with larger balloons, high temperatures, and humidity.

**Float time** — how long a helium-filled balloon remains buoyant; varies by size, material, and whether HiFloat was used.

**Uninflated / inflated** — size codes (260, 11-inch, etc.) always refer to the balloon's uninflated dimensions unless explicitly stated otherwise.

**Neck / nozzle** — the tie end of a latex balloon; the small tube-like opening used to inflate and seal the balloon.

**Organic style** — a design aesthetic where balloon clusters are arranged in irregular, free-form shapes mimicking natural growth (like flowers or foliage) rather than uniform geometric patterns. An "organic garland" uses this style; a "traditional arch" does not.

**Garland** — a long flexible strand of balloon clusters, usually hung from ceilings, walls, or stairways.

**Arch** — a freestanding or frame-supported balloon structure in an arch shape, used as an entrance feature or photo backdrop.

**Column** — a vertical tower of balloon clusters, typically 4–6 feet tall, used as event markers or flanking an arch.

**Bouquet** — a grouped arrangement of helium-filled balloons tied together and anchored with a weight.

**Organic column** — a column built in the organic style rather than a uniform geometric stack.

**Confetti fill** — adding paper or foil confetti inside a clear balloon before inflation. The finished product is a "confetti balloon." Distinct from confetti finish (see Finishes above).

**Mylar** — common industry shorthand for foil balloons, derived from the DuPont brand name for polyester film. Technically imprecise but universally understood in the trade.

**SuperShape** — Qualatex's product category for large, complex foil balloons (characters, objects, etc.). Often used generically for any large shaped foil balloon regardless of brand.

**Geo** — short for "Geo Blossom" or "Geo Donut" (Qualatex trademarks), or loosely applied to any large round decorator balloon ("36-inch geo").

**Link-O-Loon / Quick Link** — brand names for link-shaped balloons designed to be inflated and linked together into chains for arches and garlands without a frame.

**Bee body (321)** — see Sizes section. The term "bee body" refers specifically to the 321 balloon shape, regardless of brand.

**Price code** — an internal pricing tier code assigned to SKUs that share the same wholesale price. Not a publicly visible attribute; used for local price lookups in Settings → Pricing.

---

## Balloonventory System Terms

Terms specific to how this application models the balloon business. See `DATA.md` for full schema definitions.

**SKU** — Stock Keeping Unit. In Balloonventory, a SKU is a specific balloon product defined by brand + size + shape + finish + color + material. "An 11-inch Qualatex Crystal Red Round" is one SKU.

**Shared catalog** — SKUs where `sku.owned_by_business_id IS NULL`; visible to all businesses. Maintained by SuperAdmin.

**Private SKU** — a SKU owned by a single business (`sku.owned_by_business_id` is set); visible only to that business.

**Check In** — recording that balloon inventory has been received or returned; increases `stock_level.quantity`.

**Check Out** — recording that balloons have been used or removed; decreases `stock_level.quantity`.

**Job** — a planned event or work assignment with a date and a list of SKUs needed. Used for preparation and Check Out planning.

**List** — a reusable, undated collection of SKUs. The built-in "Favorites" list marks the SKUs a business regularly stocks.

**Business** — the tenant unit in the multi-tenant architecture. Each balloon business that uses Balloonventory is one Business.

**Override** — a `business_sku_override` record that lets a business customize a shared catalog SKU (rename it, set a reorder threshold, add notes) without modifying the shared catalog entry.

**Pending UPC scan** — a barcode scan that didn't resolve to any known SKU; queued for a manager to resolve by assigning it to an existing SKU or creating a new one.
