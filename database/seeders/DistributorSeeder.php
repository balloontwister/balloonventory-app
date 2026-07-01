<?php

namespace Database\Seeders;

use App\Models\Distributor;
use Illuminate\Database\Seeder;

class DistributorSeeder extends Seeder
{
    public function run(): void
    {
        $distributors = [
            [
                'name' => 'BargainBalloons',
                'slug' => 'bargain-balloons',
                'platform_type' => 'shopify',
                'base_url' => 'https://bargainballoons.com',
                // Shopify: bulk products.json gives barcode/vendor/price; the rich
                // attributes live in the page's "Additional Product Details" accordion
                // (a <ul><li><span> spec list), read via the attribute_list recipe.
                // Brand comes from the JSON vendor; shape is absent (default Round).
                'config' => [
                    'collection_handle' => 'all',
                    'has_json_api' => true,
                    'extraction' => [
                        'attribute_list' => ['section_marker' => 'Additional Product Details'],
                        'required_labels' => ['Manufacturer Color', 'Latex Finish', 'Package Count'],
                        'min_rows' => 5,
                        // The store's labels → our canonical attribute keys.
                        'label_map' => [
                            'size' => 'Size (inches)',
                            'color' => 'Manufacturer Color',
                            'texture' => 'Latex Finish',
                            'count' => 'Package Count',
                            'packaging' => 'Packaging Type',
                        ],
                    ],
                    'attribute_aliases' => [
                        'brand' => ['Betallatex' => 'Sempertex'], // Betallic's old latex rebrand (title path)
                        'packaging' => ['Retail Packaged' => 'Retail'],
                    ],
                    // Sempertex markets its code-12 / 30 cm rounds as "11 inch".
                    'size_number_aliases' => ['Sempertex' => ['11' => '12']],
                    // Betallic remnants on the SKU → bare manufacturer item number.
                    'sku_strip_prefixes' => ['BL-'],
                    'sku_strip_suffixes' => ['-B'],
                ],
                'is_active' => true,
                'sort_order' => 0,
            ],
            [
                'name' => 'Larocks',
                'slug' => 'larocks',
                'platform_type' => 'bigcommerce',
                'base_url' => 'https://larocks.com',
                // Extraction recipe: Larocks renders an "Extra Information" table
                // of label/value divs we read for the product's real attributes.
                // required_labels + min_rows let us trust a page — and detect when
                // the template changes (a drop in matched rows trips it).
                'config' => [
                    'extraction' => [
                        'attribute_table' => [
                            'header_class' => 'productView-table-header',
                            'value_class' => 'productView-table-data',
                        ],
                        'required_labels' => ['Brand', 'Industry'],
                        'min_rows' => 4,
                    ],
                    // Distributor vocabulary → our reference rows. Packaging values
                    // here are Larocks' "Package Type" wording.
                    'attribute_aliases' => [
                        'packaging' => [
                            'Nozzle-Up' => 'Nozzle Up',          // from "Q-Pak / Nozzle-Up" (slash-split)
                            'Loose Bag (Regular)' => 'Loose',
                            'Packaged' => 'Retail',
                        ],
                    ],
                    // Per-brand size-number quirks: Sempertex sells its code-12 /
                    // 30 cm balloons as "11 inch", so 11 → 12 (→ R-12/C-12/LOL-12).
                    'size_number_aliases' => [
                        'Sempertex' => ['11' => '12'],
                    ],
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'LA Balloons',
                'slug' => 'la-balloons',
                'platform_type' => 'shopify',
                'base_url' => 'https://laballoons.com',
                // Shopify, but its attributes live in namespaced products.json tags
                // (Color_/Size_/Packaging_) + product_type — read by the tag
                // extractor, NO HTML page needed. The barcode is fetched from the
                // light per-product .json (Shopify strips it from the bulk feed).
                'config' => [
                    'collection_handle' => 'all',
                    'has_json_api' => true,
                    // Tag path skips the HTML page, but LA's page renders a reliable
                    // JSON-LD Offer.availability — opt in to one extra page fetch per
                    // product to capture real stock (verified true on a live page).
                    'stock_from_page' => true,
                    'extraction' => [
                        'tag_attributes' => [
                            'tag_map' => [
                                'Color_' => 'Color',
                                'Size_' => 'Size',
                                'Packaging_' => 'Package Type',
                                'Theme_' => 'Occasion / Theme',
                            ],
                            // product_type → Balloon Material (drives classification).
                            'product_type_map' => ['latex' => 'Latex', 'foil' => 'Foil', 'mylar' => 'Foil'],
                            // "11\" Latex" → "11\"" so the size resolves.
                            'strip_words' => ['Latex', 'Foil', 'Mylar', 'Bubble'],
                            'required_labels' => ['Color', 'Size'],
                            'min_rows' => 2,
                        ],
                    ],
                    'attribute_aliases' => [
                        'packaging' => ['Packaged' => 'Retail'],
                    ],
                    'size_number_aliases' => [
                        'Sempertex' => ['11' => '12'],
                    ],
                    // Brand-suffixed SKUs (Kalisan -KL, Betallic -B, TufTex -M).
                    'sku_strip_suffixes' => ['-KL', '-B', '-M'],
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => "Havin' A Party",
                'slug' => 'havin-a-party',
                'platform_type' => 'bigcommerce',
                'base_url' => 'https://havinaparty.com',
                // BigCommerce, but unlike Larocks it renders NO attribute table —
                // identity comes from the page's BCData + JSON-LD (sku, brand,
                // title, live stock; verified). No barcode/price (price is
                // login-gated wholesale) → UPC is inherited from a sibling
                // distributor that shares the normalized SKU. With no table, the
                // product's type/count come from the TITLE via the title_attributes
                // recipe (so it classifies correctly instead of non_balloon);
                // size/colour from the title code system is a later increment.
                'config' => [
                    // ~1 MB pages behind Cloudflare → slow, jittered crawl.
                    'request_delay_ms' => 1500,
                    'request_jitter_ms' => 1000,
                    // havinaparty has no barcode, but its on-page SKU is the bare
                    // manufacturer item number — match it (brand-scoped) to our
                    // warehouse_sku so a listing with no sibling UPC can still
                    // attach a Reorder link + stock to an existing catalog SKU.
                    'match_by_warehouse_sku' => true,
                    // Pre-fetch slug filter — skip the ~1 MB page fetch for items a
                    // solid-latex slug never looks like: letter-led slugs
                    // (accessories, foil letters/scripts) and high-confidence foil
                    // keywords. Conservative — themed foils without these signals
                    // still get crawled (no latex lost; they park for later).
                    'crawl_filter' => [
                        'require_leading_digit' => true,
                        'skip_keywords' => ['air-fill', 'air-filled', 'foil', 'orbz', 'sphere', 'mylar', 'bubble', 'banner'],
                    ],
                    // No attribute table → attributes come from the TITLE
                    // (`11"S Red Fashion (100 count)`). Drives classification
                    // (material/printed) + count.
                    'extraction' => [
                        'title_attributes' => [
                            // PRIMARY: the JSON-LD breadcrumb's top category is the
                            // authoritative material (Home > Latex Balloons > …).
                            'category_material_map' => [
                                'Latex Balloons' => 'Latex',
                                'Foil Balloons' => 'Foil',
                                'Mylar' => 'Foil',
                                'Bubble' => 'Plastic',
                            ],
                            // Breadcrumb nodes that mark a printed product (note the
                            // site's "Occassion" spelling).
                            'printed_categories' => [
                                'Printed', 'Special Occassion', 'Special Occasion', 'Shop by Prints',
                            ],
                            // Packaging/shape words removed when reading colour from
                            // the title (`160K Mirror Silver Nozzle Up` → Mirror Silver).
                            'color_strip_words' => [
                                'Nozzle Up', 'Pkg', 'Flat', 'Round', 'Air-Fill', 'Banner', 'Set',
                            ],
                            // Latex shape (drives our R-/C-/LOL- size-name prefixes).
                            // Round unless the title names another shape.
                            'default_shape' => 'Round',
                            'shape_keywords' => [
                                'heart' => 'Heart',
                                'link-o-loon' => 'Link',
                                'linkoloon' => 'Link',
                                'linky' => 'Link',
                            ],
                            // FALLBACKS when a page has no breadcrumb. Foil signals
                            // win first — a latex brand can still sell foil letters.
                            'foil_keywords' => [
                                'air-fill', 'air fill', 'air filled', 'air-filled',
                                'foil', 'mylar', 'orbz', 'sphere', 'bubble',
                            ],
                            'latex_brands' => ['Sempertex', 'Kalisan', 'Tuftex', 'Qualatex', 'Betallatex', 'Gemar', 'Brookloon'],
                            'printed_keywords' => [
                                'happy birthday', 'christmas', 'halloween',
                                'thanksgiving', 'welcome', 'mothers day', 'fathers day',
                                'graduation', 'anniversary', 'valentine',
                            ],
                            'required_labels' => ['Balloon Material'],
                            'min_rows' => 1,
                        ],
                    ],
                    // SKUs are bare manufacturer item numbers (53012, 10150025) —
                    // no affixes to strip; normalized_sku == raw_sku.
                    // Sempertex markets its code-12 / 30 cm rounds as "11 inch".
                    'size_number_aliases' => [
                        'Sempertex' => ['11' => '12'],
                    ],
                ],
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Joker Party Supply',
                'slug' => 'joker-party-supply',
                'platform_type' => 'shopify',
                'base_url' => 'https://www.jokerpartysupply.com',
                // Shopify: the bulk collection products.json gives vendor/sku/price/
                // tags but (like BargainBalloons) strips the barcode. Unlike BB, Joker
                // renders its full attribute table inside the product's body_html AND
                // carries the barcode on the variant — so a single per-product .json
                // fetch yields everything, no heavy HTML page. enrich_from_product_json
                // selects that path; attribute_rows reads the plain two-column
                // "Product Information" table out of body_html.
                'config' => [
                    'collection_handle' => 'latex',
                    'has_json_api' => true,
                    'enrich_from_product_json' => true,
                    // Polite throttle: the enrich pass fetches a per-product .json for
                    // every latex product (plus a page fallback for "auto-info" items),
                    // so pace it to avoid Shopify rate-escalation over a full crawl.
                    'request_delay_ms' => 800,
                    'request_jitter_ms' => 400,
                    'extraction' => [
                        'attribute_rows' => ['section_marker' => 'Product Information'],
                        'required_labels' => ['Brand', 'Size', 'Material'],
                        'min_rows' => 4,
                        // The store's labels → our canonical attribute keys. Brand is
                        // in the table (and matches the JSON vendor); shape is absent
                        // (default Round). No separate finish column — the finish is
                        // part of the Color value ("Deluxe Almond White").
                        'label_map' => [
                            'brand' => 'Brand',
                            'size' => 'Size',
                            'color' => 'Color',
                            'count' => 'Quantity',
                        ],
                    ],
                    // Sempertex markets its code-12 / 30 cm rounds as "11 inch".
                    'size_number_aliases' => ['Sempertex' => ['11' => '12']],
                    // "BT-53011" (Betallic remnant) → bare manufacturer item number.
                    'sku_strip_prefixes' => ['BT-'],
                    // Joker's Gemar products carry no attribute table but DO expose an
                    // on-page SKU whose core matches our catalog warehouse_sku. Rescue
                    // barcode-less listings by that SKU. Our Gemar warehouse_skus are
                    // "G"-prefixed (G110005) while the distributor core is bare
                    // (110005), so also try that prefix (brand-scoped, so it's safe).
                    'match_by_warehouse_sku' => true,
                    'warehouse_sku_prefixes' => ['G'],
                ],
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Rainbow Balloons',
                'slug' => 'rainbow_balloons',
                'platform_type' => 'magento',
                'base_url' => 'https://rainbowballoons.com',
                // Magento 2 store (Rootways theme), Cloudflare-fronted. NEW platform:
                // no Shopify products.json feed, and — critically — NO barcode/UPC
                // anywhere on the product page. So Rainbow can neither seed nor
                // corroborate UPC clusters and can never auto-create a SKU. Its value
                // is Reorder links (+ price + stock) for products we ALREADY carry:
                // the JSON-LD `sku` IS the manufacturer item number (== our
                // warehouse_sku), matched brand-scoped + single-match-guarded, exactly
                // like Joker's Gemar rescue. Lands cleanly for Qualatex (full
                // warehouse_sku coverage); TufTex/Anagram/Betallatex depend on our
                // catalog carrying the matching item number.
                'config' => [
                    // Sitemap lives at a non-standard path (declared in robots.txt).
                    'sitemap_url' => 'https://rainbowballoons.com/media/sitemap.xml',
                    // Solid latex only. Product URLs are flat (/{slug}-{id}.html) so the
                    // sitemap can't be path-filtered — harvest product links from these
                    // brand-scoped solid-latex category listings (paginated) instead.
                    // Only brands we hold SKU data for → reconcilable into Reorder
                    // links. Rainbow also carries solid-anagram-latex and
                    // funsational-latex, but we hold 0 SKUs for those brands, so
                    // crawling them stages rows that can never match. Re-add a
                    // category here (one line) once we seed that brand's SKUs.
                    'category_urls' => [
                        'https://rainbowballoons.com/latex/solid-qualatex-latex.html',
                        'https://rainbowballoons.com/latex/solid-betallatex-latex.html',
                        'https://rainbowballoons.com/latex/solid-tuftex-latex.html',
                    ],
                    // ~100 KB pages behind Cloudflare → slow, jittered crawl.
                    'request_delay_ms' => 1500,
                    'request_jitter_ms' => 1000,
                    // No barcode; on-page SKU == manufacturer item number == our
                    // warehouse_sku → attach Reorder links by that key (brand-scoped).
                    'match_by_warehouse_sku' => true,
                    // Rainbow renders a Magento "additional-attributes" table
                    // (<tr><th class="col label">Label</th><td class="col data">Value</td></tr>)
                    // plus JSON-LD (name/sku/brand/price/availability). Needs a NEW
                    // `attribute_th_rows` extractor mode (existing attribute_rows requires
                    // two <td>s and skips the <th> label). Attribute resolution is only
                    // needed if Rainbow ever helps propose SKUs — not for Reorder links.
                    'extraction' => [
                        'attribute_th_rows' => ['table_class' => 'additional-attributes'],
                        'required_labels' => ['Manufacturers', 'Color'],
                        'min_rows' => 3,
                        'label_map' => [
                            'brand' => 'Manufacturers',
                            'color' => 'Color',
                        ],
                    ],
                    // Rainbow's JSON-LD brand is the manufacturer's full name, not our
                    // short brand — map each to our canonical brand (case-insensitive).
                    // Confirmed against the live site. Aliasing BETALLIC INC is safe
                    // because we crawl ONLY the solid-latex categories (Betallic's own
                    // foils aren't crawled).
                    'attribute_aliases' => [
                        'brand' => [
                            'PIONEER BALLOON' => 'Qualatex',   // Pioneer Balloon Co. makes Qualatex
                            'BETALLIC INC' => 'Sempertex',     // Betallic's latex is Sempertex
                            'TUFTEX BALLOONS' => 'TufTex',
                            'Betallatex' => 'Sempertex',       // category-name fallbacks
                            'BETALLATEX' => 'Sempertex',
                        ],
                    ],
                    // Item numbers carry a trailing brand marker: Betallic/Sempertex "B"
                    // (sku "57102B" → warehouse_sku 57102) and some TufTex "T" (sku
                    // "36282T" → mfg_no 36282). Qualatex numbers are bare, so stripping
                    // is a no-op for them. Confirmed against the live site.
                    'sku_strip_suffixes' => ['B', 'T'],
                    // Sempertex markets its code-12 / 30 cm rounds as "11 inch".
                    'size_number_aliases' => ['Sempertex' => ['11' => '12']],
                ],
                // Inactive until the Magento adapter is built — platform_type=magento
                // isn't wired, so Probe/Sync would throw while active.
                'is_active' => false,
                'sort_order' => 5,
            ],
        ];

        foreach ($distributors as $data) {
            Distributor::firstOrCreate(
                ['slug' => $data['slug']],
                $data,
            );
        }
    }
}
