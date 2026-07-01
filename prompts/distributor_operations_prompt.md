# Distributor operations runbook (session prompt)

Reusable prompt for a session helping Todd operate the Balloonventory **distributor
catalog pipeline**: onboard a distributor + tune its matching, crawl/refresh its
products, and manage the resolver/review queue. (A future Admin Dashboard UI will
replace much of this; until then it's driven from a session + artisan.)

Read the `project-distributors-state` memory first — it's the authoritative current
state. **Production access:** SSH alias `myvps`, app at
`/home/balloonventory/balloonventory-app`, **always use PHP 8.4**:
`/opt/cpanel/ea-php84/root/usr/bin/php` (plain `php` is 8.2 and breaks the lock file).
The deploy script (`bash bin/deploy.sh`) runs migrate + build but **never seeds** —
run seeders manually. Crawls/clusters are **dry-run by default**; pass `--execute`
to write. For long crawls run detached: `nohup … < /dev/null > log 2>&1 &`.

---

## Lessons from Joker Party Supply (2026-06-30) — apply to the next distributor
Joker was a Shopify store; onboarding it added reusable capabilities + gotchas. Details in the `project-distributor-joker` memory.

**Shopify now has THREE enrich sources — pick by where the store keeps its attributes:**
- **page HTML accordion** (`extraction.attribute_list`, `section_marker`) — BargainBalloons.
- **products.json tags** (`extraction.tag_attributes`) — LA Balloons (no page fetch; barcode from per-product `.json`).
- **per-product `.json` `body_html` table** (`enrich_from_product_json: true` + `extraction.attribute_rows`) — Joker. One light JSON fetch yields the barcode (variant) AND the spec table (body_html), no heavy HTML page.
- (BigCommerce still = page crawl: Larocks two-cell table / havinaparty title+breadcrumb.)

**New extractor mode `attribute_rows`** — a plain two-column `<tr><td>Label</td><td>Value</td></tr>` table (no CSS classes), `section_marker`-anchored, skips the `<th>` header. Alongside `attribute_table` / `attribute_list`.

**`auto-info` / body_html-vs-page gotcha:** some Shopify products have a narrative `body_html` (no table) while the SAME table is still rendered on the PAGE (from metafields). `enrichShopifyFromProductJson` falls back to the page when body_html has no table. Expect a slice (~8% at Joker) to need it. ⚠️ The **Probe button does NOT cover the product-JSON path** — validate a new product-JSON recipe with `catalog:ingest-distributor {slug} --enrich --execute --limit=N` then inspect staged rows.

**Table-less-but-barcoded brands still work:** a brand a store renders with no attribute table classifies as `unknown`, but if it has a UPC it still clusters, corroborates, and attaches a Reorder link (type only gates *self-proposing* new SKUs). Don't panic at a high `unknown` count — check whether those brands are (a) ones we don't carry (correct park) or (b) carried-but-table-less (recoverable, below).

**`warehouse_sku_prefixes` (new) bridges a prefixed catalog code:** when a store exposes a bare SKU core (`110005`) but our catalog `warehouse_sku` for that brand is prefixed (`G110005` for Gemar), set `match_by_warehouse_sku: true` + `warehouse_sku_prefixes: ['G']`. The rescue tier tries `prefix+core`, brand-scoped + single-match-guarded, and attaches barcode-less listings as Reorder links (never creates SKUs). Recovered ~156 Joker Gemar this way. **Update (Rainbow, 2026-07-01): the rescue index now unions `warehouse_sku` + `mfg_no`** — some brands store the item number in `mfg_no` (TufTex), so a bare on-page SKU matches whichever column our catalog uses.

**Throttle:** Shopify default 500ms **rate-escalates under sustained/repeated bursts** (429s). Use `request_delay_ms: 800` + `request_jitter_ms: 400` for a full crawl. Failed fetches are harmless — they return null before upsert (data intact) and re-run is idempotent (skips already-enriched-fresh). Scope the crawl with `collection_handle` (e.g. `latex`) to the slice you support.

**Full post-import command sequence (in order):**
1. `catalog:ingest-distributor {slug} --enrich` (dry) → `--execute` (detached for a full catalog).
2. `catalog:cluster-distributors` (dry) → `--execute`. (This already stamps each open proposal's consensus warehouse SKU.)
3. `catalog:promote-distributor-proposals` (dry) → `--execute`. Adding a new corroborating source is exactly when the accuracy gate auto-creates more (Joker unlocked 17). **Spot-check the created SKUs' counts** — `default_count_per_bag` vs the evidence titles (proposed_count from Quantity can occasionally be wrong).
4. `catalog:audit-promoted-warehouse-skus` (read-only) — **run this after adding a new distributor**: new evidence can shift the consensus warehouse SKU on *already-promoted* catalog SKUs, which re-clustering does NOT touch. `--execute` to correct (skips manually-edited).
5. `catalog:recompute-proposal-warehouse-skus` — redundant *immediately* after a full re-cluster (cluster already recomputed open proposals), but the go-to when fixing proposals without a re-cluster.

**New-brand intake capture:** when a distributor carries brands we don't hold, dump them from staging (authoritative — real SKUs + UPCs) to `intake/{brand}/{brand}_latex_from_{slug}.json` for later seeding. `intake/` is gitignored (local-only). Did this for Belbal + Balloonia from Joker.

---

## Lessons from Rainbow Balloons (2026-07-01) — the Magento / BARCODE-LESS archetype
Rainbow is our first **Magento 2** store AND first **barcode-less** distributor — a genuinely new archetype. Everything above assumes the store exposes a UPC; Rainbow exposes **none**. Full detail in the `project-distributor-rainbow` memory. Handle a barcode-less store very differently.

**Third platform: Magento** (`platform_type: 'magento'`, `MagentoAdapter`). No bulk feed, and the sitemap is a flat, **brand-blind** list of `.html` product URLs — so you CANNOT path-filter it to the slice you support. Instead the adapter **harvests product links from configured category pages** (`config.category_urls`) following `?p=N` pagination. `catalog:crawl-distributor {slug}` accepts magento (same command as BigCommerce); per-page parse = **JSON-LD Product** (`MagentoProductPageParser`: name/sku/brand/price) + `JsonLdAvailabilityParser` for stock.

**NO barcode ⇒ Reorder-links-only, NOT the UPC pipeline.** A barcode-less store can never cluster, corroborate, self-propose, or auto-create (all UPC-gated). Its ONLY value is **attaching Reorder links (+price+stock) to SKUs we ALREADY carry**, via the `match_by_warehouse_sku` rescue. So the flow is crawl → `cluster-distributors --execute` (cluster is what attaches the rescue links) → **NEVER `promote`** (no-op). No proposals appear in the review queue from this store.

**Join key = the manufacturer item number, in EITHER `warehouse_sku` OR `mfg_no`.** The rescue index now unions BOTH columns (Sempertex/Qualatex/Kalisan/Gemar use `warehouse_sku`; **TufTex uses `mfg_no`**). ⚠️ When measuring a barcode-less store, check catalog coverage in BOTH columns — reading only `warehouse_sku` under-counts (it hid TufTex's 465 mfg_no on the first pass and made TufTex look un-reconcilable).

**MEASURE RECONCILIATION POTENTIAL BEFORE BUILDING.** Read-only spot check FIRST: pull the store's item numbers per brand, count how many hit our `warehouse_sku`/`mfg_no` (brand-scoped). ~0 coverage for a brand ⇒ no links ⇒ don't crawl it. Rainbow: Sempertex 12% (we lack its 24" premium finishes), TufTex 86%, Qualatex 30% — all correct, all known before the full crawl.

**Only crawl brands we hold SKU data for.** A brand with 0 catalog SKUs (Anagram, Funsational at Rainbow) stages rows that can never match — dead weight. Scope `category_urls` to reconcilable brands; re-add a category (one line) once that brand is seeded.

**Cross-brand item-number collisions are real — the match MUST stay brand-scoped.** Bare item numbers aren't namespaced across manufacturers (Sempertex #57100 collides with a Funsational #57100). The rescue's brand scope + single-match guard handle it. ⚠️ Corollary: NEVER validate a match by grepping the sitemap for a bare number brand-blind — you'll get false hits. Confirm the product's brand.

**JSON-LD brand is the manufacturer's FULL legal name; item numbers carry a per-brand letter suffix.** Rainbow's brand strings were `PIONEER BALLOON`(→Qualatex), `BETALLIC INC`(→Sempertex), `TUFTEX BALLOONS`(→TufTex) — alias each in `attribute_aliases.brand` (matched case-insensitively). SKUs carry a Betallic `B` (57102B→57102) and some TufTex `T` (36282T→36282) — put BOTH in `sku_strip_suffixes`. Don't assume the short brand name or a single suffix; read the live JSON-LD.

**Lean build for barcode-less.** Since it can never propose, SKIP the attribute-table extractor (size/color parsing) — you only need sku + brand + price + stock from JSON-LD. Rainbow's Magento `attribute_th_rows` table extractor was deliberately NOT built.

**⚠️ Crawl health-guard gotcha for ANY table-less/lean path.** `catalog:crawl-distributor`'s drift guard aborts the run + marks the distributor `broken` when `extractionOk/pagesParsed < 0.2`. A lean path has no attribute table to grade, so it MUST mark `extraction.ok = true` on a successful parse (as `crawlMagentoPage` does) — otherwise the crawl self-aborts at ~10 pages with a false `broken` health flag. (Cost the first Rainbow run.)

**Onboarding sequence for a barcode-less store:** measure potential → build/config (scope to reconcilable brands) → **dry-run crawl** (confirm harvest count) → **small `--execute` batch** + a **live end-to-end smoke test** of the parse chain (⚠️ the Probe button does NOT cover Magento) → **full crawl detached** → `cluster-distributors --execute` (attaches links; NO promote) → **spot-check matches** (our SKU name vs the distributor title) → **flip `is_active=true` LAST** so it goes live already populated. Validating against the LIVE site caught two config bugs mocked tests couldn't (the full brand strings + the TufTex `T` suffix) — do it every time.

---

## Pipeline in one picture

```
sitemap/products.json ──► STAGING (distributor_products)
   crawl (BigCommerce)        │  raw_sku, normalized_sku, upc, title, price,
   ingest (Shopify)           │  stock, product_type, raw_data.attributes, fetched_at
                              ▼
   catalog:cluster-distributors  ──► group by canonical UPC, classify type,
                              │       resolve attributes (matcher), stamp resolution
                              ▼
   distributor_catalog_proposals (review queue)   +   distributor_sku_urls (Reorder links)
                              ▼
   approve in queue  /  catalog:promote-distributor-proposals  ──► catalog Sku
```

Key model facts:
- Staging + proposals live on the relocatable **`distributors`** DB connection.
- Clustering is **UPC-gated**: a cluster's identity is a canonical GTIN-14, so it
  forms only when ≥1 distributor exposes a barcode.
- **Barcode-less stores bypass clustering entirely** (e.g. Rainbow/Magento — no UPC
  anywhere): the `match_by_warehouse_sku` rescue attaches Reorder links to SKUs we
  already carry, matched by item number (`warehouse_sku` ∪ `mfg_no`), brand-scoped +
  single-match-guarded. No proposals, no promote. See the Rainbow lessons above.
- Only **`solid_latex`** is proposed today; other types park in staging (no re-crawl
  needed to enable later).
- **Confidence** = `high` only when **≥2 distributors** share the UPC **and** a count
  parsed; else `low`. `promote --execute` auto-creates SKUs **only for `high`** (or
  human-approved) proposals — so with one distributor everything is `low` and SKUs
  are created only by approving in the queue.

---

## 1) Onboard a new distributor + set up matching

1. **Identify the platform AND whether it exposes a barcode.** BigCommerce (sitemap +
   product-page scrape, e.g. Larocks, havinaparty), Shopify (`products.json`, e.g.
   BargainBalloons), or **Magento** (category-page harvest + JSON-LD, e.g. Rainbow —
   see the Rainbow lessons). Set `platform_type`. ⚠️ **Check up front whether the store
   exposes a UPC anywhere.** A barcode-less store (Rainbow) skips the whole
   UPC/cluster/propose/promote pipeline and is a **Reorder-links-only** build — follow
   the Rainbow section instead of the steps below, and **measure reconciliation
   potential first** (catalog `warehouse_sku`+`mfg_no` coverage for its brands).
2. **Create the distributor** (admin UI `/admin/distributors`, or `DistributorSeeder`
   for repeatability): `name`, `slug`, `platform_type`, `base_url`, `config` (below),
   `is_active`.
3. **Write the `config`** (JSON). All matching is config-driven — no per-store code:
   - **`extraction`** (BigCommerce page tables): `attribute_table.header_class` /
     `value_class` (CSS classes of the label/value cells), `required_labels` (drift
     signal), `min_rows`, and **`label_map`** (canonical key → that store's label:
     `brand`,`size`,`color`,`count`,`packaging`,`shape`; defaults are Larocks wording
     `Brand`/`Size`/`Color`/`Quantity`/`Package Type`/`Balloon Type / Shape`).
   - **`attribute_aliases`**: `brand`/`color`/`packaging` value → our reference name
     (e.g. `"Loose Bag (Regular)" => "Loose"`). An alias that doesn't resolve is a
     config error (surfaced as no-match), not a fuzzy guess.
   - **`size_shape_prefixes`** (default `round→R, heart→C, link/link-o-loon→LOL`):
     maps the structured shape to the prefix some brands put on a size name
     (`R-24`, `C-14`, `LOL-12`).
   - **`size_number_aliases`** (per-brand): a brand's marketing quirk, e.g.
     `{"Sempertex": {"11": "12"}}` (Sempertex sells its code-12/30 cm rounds as
     "11 inch" → our R-12/C-12/LOL-12).
   - Throttle knobs: `request_delay_ms` (default 500), `request_jitter_ms`,
     `max_retries`, `max_pages`. Shopify: `collection_handle`, `has_json_api`.
4. **Probe before crawling.** On the distributor's Show page use **"Test fetch
   (Probe)"** (or `DistributorProbe`) against one product URL — it runs the full
   recipe→extractor→classifier→matcher READ-ONLY and shows recipe-matched + resolved
   attrs. Iterate the recipe/label_map/aliases until it resolves cleanly.
5. **Seed reference data** so the matcher can resolve: the brand must exist
   (`BrandSeeder`) and its **colors/sizes/textures** seeded (see
   `workflow-brand-color-imports` / `workflow-brand-sku-imports`). Missing reference
   rows show up in the queue's **"Missing reference data"** gaps panel after a
   cluster run — add them, then re-cluster.
6. **Record** the recipe/label_map/aliases in the distributor's config AND a short
   per-distributor memory, and tick the checklist here.

---

## 2) Manage crawling + updating products

- **BigCommerce crawl:** `catalog:crawl-distributor {slug} --execute --limit N`.
  Incremental by design: captures the sitemap `<lastmod>` and **skips products
  already fetched at/after it** (only new + changed pages are pulled; 24h fallback
  when a store omits lastmod). After a **complete** sitemap fetch it **reconciles
  removals** — marks listed products `last_seen_at`, sets `removed_at` on ones that
  dropped out (a truncated/blocked sitemap skips this). `--force` re-fetches all
  (e.g. to backfill a new field). Resumable; run detached for big catalogs.
  Health/drift is graded each run (`health_status`); re-check a degraded recipe with
  Probe.
- **Shopify ingest:** `catalog:ingest-distributor {slug} --execute` reads
  `products.json` (sku + **barcode** + price). Fast, no crawl, no Cloudflare risk.
  ⚠️ It stages a barcode/title/price only — **no `product_type`, no structured
  attribute table** — so Shopify products don't classify or self-propose yet (they
  still upgrade a shared-UPC cluster's confidence). "Shopify-side classification" is
  unbuilt future work.
- **Magento crawl:** `catalog:crawl-distributor {slug} --execute --limit N` (same
  command as BigCommerce). Harvests product links from `config.category_urls`
  (`?p=N` pagination), fetches each page, parses JSON-LD. Barcode-less → stages
  sku/brand/price/stock only; the cluster step attaches Reorder links via the
  item-number rescue. ⚠️ Set a high `--limit` (default 100) to cover the catalog in
  one pass; run detached. See the Rainbow lessons for the health-guard gotcha.
- **After any crawl/ingest:** `catalog:cluster-distributors --execute` to (re)build
  proposals + attach Reorder URLs + re-stamp resolution. Idempotent; preserves
  human-reviewed proposals (refreshes their evidence/resolution only). Dry-run first
  to eyeball counts.
- **Verify:** check `DistributorProduct::count()` / `active()` / `whereNotNull('removed_at')`,
  and the cluster summary (clusters / already-in-catalog / proposals / deferred-by-
  type / unclustered).

---

## 3) Manage the resolver + review queue

How a proposal resolves (all field-by-field, exact → curated alias → fuzzy):
- **Brand/Color/Packaging:** matched on the structured value (slash-combined values
  like `"Gray / Silver"` are split and each part tried).
- **Size:** core-key equality first (`"24 inch"` ↔ `24in`), then the **shape-prefix**
  tier (`Round`+24 → `R-24`), with the per-brand **number alias** applied first.
- **Color title fallback:** when the structured Color is only a coarse family, the
  real shade is read from the **product title** (priority: exact-structured → title →
  fuzzy-structured); shown with a "name" badge.
- **Resolution is stamped at cluster time** (`resolved_brand_name`,
  `resolution_state` = full|partial|no_brand, `resolution` detail JSON) so the queue
  sorts/groups/counts and the gaps panel read stored data, not a live matcher pass.

Tuning levers (then **re-cluster** to re-stamp):
- Add **reference data** (brands/sizes/colors/textures) for whatever the gaps panel
  lists — most "partial"/"no brand" proposals are a missing catalog row, not a code
  bug. Britetex/Elitex/Sempertex were proven this way.
- Add an **alias** (`attribute_aliases`) when a distributor's wording differs from
  ours; a **`size_number_aliases`** entry for a brand size-naming quirk; a
  **`size_shape_prefixes`** entry for an unusual shape→prefix scheme; fix a
  **`label_map`** when a store labels a field differently.

Review queue (`/admin/distributors/proposals`):
- Sorted **fully-resolved first → by brand → by product number**; brand **facet
  chips** (counts, click to filter, "No brand") and a **full/partial/no-brand** split.
- **Edit** modal pins brand/size/color/packaging/count; **Approve** materialises a
  SKU (honoring edits); **Map to existing** backfills the distributor's barcode onto
  a SKU we already carry (no duplicate). Approving builds identical-sibling links
  across pack counts.
- `catalog:promote-distributor-proposals --execute` bulk-auto-creates SKUs **only for
  `high`-confidence** pending proposals — so it's a no-op until a 2nd distributor
  shares UPCs. **Don't run it blind** — see the *Accuracy & auto-create policy* below:
  `high` confirms identity, not attributes, so until a cluster has a second attribute
  source the right move is to approve via the queue, not bulk-promote.

---

## Accuracy & auto-create policy (REQUIRED for every UPC-bearing distributor)

> **Barcode-less stores (Rainbow/Magento) are exempt** — they never cluster or
> auto-create; they only attach Reorder links to SKUs we already carry. This whole
> policy applies only to distributors that expose a UPC.

"High confidence" today confirms **identity**, not attributes: it means ≥2
distributors share the UPC **and** a count parsed. The resolved brand/size/colour
still come from a **single** source — the first cluster member with an attribute
table. So auto-creating a *new* SKU on high-confidence-identity alone can faithfully
copy that one source's mis-attribution. The bar for **adding (auto-creating) a new
catalog product** is therefore:

1. **Identity gate — BUILT.** UPC present + ≥2 distributors agree → `high`;
   `promote --execute` already requires it.
2. **Attribute-accuracy gate — POLICY, NOT yet built.** Resolved attributes must be
   corroborated, not single-sourced, before auto-create:
   - **Multi-source agreement** — when ≥2 cluster members expose attribute tables
     (e.g. once a Shopify store is page-enriched, below), compare their resolved
     brand/size/colour: **agree → eligible to auto-create; disagree → route to
     review** showing both sources. The cluster already stores *every* member's
     attributes in `evidence` — today the resolver just picks the first rather than
     comparing, so the data is there to build this on.
   - **GS1-prefix → brand check** — validate the resolved brand against the UPC's
     manufacturer prefix; a mismatch flags a bad resolution.
   - **Title corroboration** — the title should agree with the structured
     size/count (already done for colour via the title fallback).
3. **Until a cluster has a 2nd attribute source, do NOT bulk-`promote`.** Treat
   `high` as "identity confirmed, attributes still to review" and approve via the
   queue (the brand/approvability grouping makes that fast). Single-source attributes
   are review material, not auto-create material.

Same bar for every distributor — a second *attribute* source is what upgrades a
cluster from identity-confirmed to attribute-cross-validated.

## Per-platform ingest strategy (lean — set up this way for BargainBalloons + future Shopify)

Don't crawl a whole catalog. Split the two goals:
- **Reconcile / high-confidence (shared UPC):** needs only the **barcode**, which the
  bulk **`products.json`** gives for every product — no page crawl. Cluster merges any
  shared-UPC product with our catalog / another distributor; attributes come from the
  side that has them.
- **New items (distributor-exclusive brands, e.g. Decomex at BargainBalloons):** these
  are the only ones that need the store's own attribute table. **Page-enrich ONLY**
  the products that (a) aren't already in our catalog / another distributor by UPC and
  (b) pass a cheap **solid-latex pre-filter** from `products.json` `product_type` /
  `tags` / `title` ("latex", not "foil"/"mylar"/"printed"). Run the recipe-driven
  `ProductAttributeTableExtractor` on those pages with the store's recipe. The real
  type classification still comes from the table, so a misfiltered page just parks.
- **Foil / print / misc — skip the page crawl** until we actually support matching
  those types. Barcode-only staging is enough to park them.

So a Shopify distributor onboards as: bulk `products.json` ingest → cluster (instant
reconciliation) → targeted page-enrichment of the net-new latex slice only. This is
also what gives a cluster its **second attribute source**, satisfying the
auto-create accuracy gate above.

## Worked example — BargainBalloons recipe (PLANNED; not built/configured yet)

The agreed config + rules for onboarding BargainBalloons (Shopify), and the template
for similar US-importer stores:

- **Ingest:** bulk `products.json` first (barcode/sku/price → reconciliation +
  Reorder links, no crawl); then page-enrich ONLY net-new latex (per the lean
  strategy above).
- **Brand:** the structured Brand field reads `Sempertex` — use it directly. Titles/
  URLs/SKUs still say "Betallatex" (Betallic's old US latex rebrand, now consolidated
  to Sempertex). So alias **`Betallatex → Sempertex`** for the title path. ⚠️ Do NOT
  alias bare **`Betallic`** — Betallic is a real brand for its **own foils** (in the DB
  for upcoming foil work); only the *-latex* name maps to Sempertex.
- **SKU affixes:** strip prefix **`BL-`** and suffix **`-B`** (Betallic remnants) →
  core = manufacturer item # = **middle of the UPC** (`030625`-**`53005`**-`7`) = the
  cross-distributor join key (`size_strip_prefixes`/`suffixes`).
- **Shape (BB omits it):** synthesize a Shape attribute so the matcher's shape→size
  logic runs unchanged. Priority: **SKU prefix** (`R`/`H`/`L` + size, e.g. Decomex
  `R12`/`H07`/`L11`) → **title keyword** (`linking`/`link`→Link, `heart`→Heart) →
  **default Round** for latex.
- **Size:** structured Size (`"11 inches"`) + the synthesized shape → `R-12` (Sempertex
  11→12 number-alias applies here too). Decomex SKU prefix also carries the size.
- **Color:** structured Color (e.g. `Fashion Yellow`), with the usual title fallback.
- **Finish → Texture:** resolve **exact texture first** (`Finish: Reflex` →
  `Reflex (S)`), else fall back to the **texture-family** (`Standard` → family
  `standard` = Fashion/Deluxe). Texture lives on the colour in our model, so Finish
  mainly **disambiguates/completes the colour** (`Yellow` + `Reflex` → `Reflex Yellow`).
- **Count:** structured Quantity.
- **Reconciliation shape:** shared UPC merges with the Larocks cluster → high
  confidence; net-new pack sizes create + link as identical siblings (e.g. our R-12
  Fashion Yellow exists in 12/20/50 ct without barcodes — a BB/Larocks 100 ct is a new
  sibling; matching counts could later backfill barcodes via map-to-existing).
- **VERIFIED against the real page (2026-06-25), `…/products/{handle}.json` + page HTML:**
  - `products.json` gives `vendor: "Sempertex"` (✓ brand), `sku: BL-53005`,
    `barcode: 030625530057`, price, useful `tags` — but `product_type: ""`,
    `body_html: null`, `options: Default Title`. So the rich attributes are NOT in
    the JSON — they're in the **page HTML**.
  - The attribute block is the **"Additional Product Details" accordion**:
    `div.cc-accordion-item__content > ul > li > span>{Label}: </span>{Value}` — a
    **list/label-prefix** shape, NOT Larocks' two-cell `header_class`/`value_class`
    table. So `ProductAttributeTableExtractor` needs a **second recipe mode** (`<ul><li>`
    with a `<span>` label) to parse it.
  - Real labels → canonical: `vendor`(JSON)→brand; `Size (inches)`(11.0)→size;
    `Manufacturer Color`(Yellow)→colour BASE; `Latex Finish`(Fashion)→texture/finish
    (→ recompose "Fashion Yellow"); `Package Count`(100)→count; `Packaging Type`
    (Retail Packaged)→packaging→Retail; `Print`(Solid Color)/`Manufacturer Supplied
    Category Type`(Non-Print)→classify solid_latex. No Shape label → default Round.
  - So colour really is split (base + finish), exactly the recompose case — handled
    by the matcher change (`feat/matcher-finish-combined-color`).

## Status note (updated 2026-07-01)
Much of this runbook was written 2026-06-25, before later distributors shipped.
Corrections to the rest of the doc:
- **SIX distributors are now live**, not the one/two implied above: Larocks,
  BargainBalloons, LA Balloons, Havin' A Party (table-less BigCommerce — JSON-LD
  breadcrumb + title recipe), Joker Party Supply (Shopify product-JSON table), and
  **Rainbow Balloons (Magento, barcode-less — Reorder-links-only)**. See the
  `project-distributor-havinaparty` / `-joker` / `-rainbow` memories.
- The **BargainBalloons recipe** in the "Worked example" section below is no longer
  "PLANNED" — it's **built, configured, and live** (Shopify accordion `attribute_list`
  extractor mode, Betallatex→Sempertex alias, BL-/-B affix strip, finish+colour
  recompose). Read it as a worked reference, not a TODO.
- **Texture/Finish matching is BUILT** — `DistributorAttributeMatcher` recomposes a
  split finish+colour (`Latex Finish: Fashion` + `Yellow` → `Fashion Yellow`) via the
  `texture` label; exact brand-texture first.
- **Attribute-accuracy gate is BUILT** — `Gs1BrandRegistry` + `DistributorCatalogPromoter::canPromote()`
  (multi-source attribute agreement + GS1-prefix→brand check), enforced by
  `promote --execute`; human-approved proposals bypass the gate.

## Standing gaps / future (so a session doesn't rediscover them)
- **Alias-learning from admin edits is IN PROGRESS** (Todd's working-tree branch):
  `DistributorLearnedAlias` model + `DistributorLearnedAliasStore` wired into the
  matcher/review service so admin corrections become reusable distributor→catalog
  aliases. Not finalized/committed at time of writing.
- **App SKU search affix-strip (separate from distributor matching):** when a SKU
  search returns no match, retry after stripping known affixes (`BL-`, `-B`) so a
  Betallic-coded lookup finds the Sempertex SKU.
- **2nd attribute source** is the real unlock for trustworthy auto-create — for
  Shopify stores that's the targeted page-enrichment (not just `products.json`).
  Shopify products still need **classification** (from their page table or tags)
  before they self-propose — that classification step remains unbuilt for Shopify.
- **New e-commerce platform = new adapter.** Shopify + BigCommerce + **Magento**
  adapters exist; a store on one of those is just a config recipe. A genuinely new
  platform (WooCommerce, etc.) still needs a new adapter + page parser.
- **`firstOrCreate` prod gotcha:** the seeder won't update an existing distributor
  row, so every config tweak must be set on prod by hand via tinker.
- **Crawls are run manually** (`nohup … catalog:crawl-distributor`); no scheduled
  refresh cadence is wired yet.
- `distributor_sku_urls` aren't retired when a staged product is removed (removal
  detection covers staging only).
- The whole thing is session-driven today; the Admin Dashboard UI for onboarding +
  crawl control + review is the eventual home (see `admin_ui_build_prompt.md`).
