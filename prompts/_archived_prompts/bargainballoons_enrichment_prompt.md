# Continue: BargainBalloons enrichment — increments 3 & 4

Pick up the BargainBalloons (Shopify) distributor build. **Increments 1 & 2 are done,
deployed, and validated; you're building increment 3 (the Shopify page-enrichment
crawl) and then 4 (the accuracy gate).** Read the `project-distributors-state` memory
and `distributor_operations_prompt.md` first — they hold the full design, the agreed
policies, and the verified BargainBalloons recipe. This prompt is the focused task.

## Where it stands (don't redo these)

- **Increment 1 ✅ (`bb9f680`):** the matcher recomposes colour from a split finish+base
  field — reads a `texture` label (default "Latex Finish"), tries "{finish} {colour}"
  first ("Yellow" + "Fashion" → "Fashion Yellow"), falls back to base. No-op without a
  finish field.
- **Increment 2 ✅ (`052b768`):** `ProductAttributeTableExtractor` has an `attribute_list`
  recipe mode (`<ul><li><span>Label: </span>Value`, optional `section_marker`) for BB's
  "Additional Product Details" accordion. BargainBalloons' full recipe is in
  `DistributorSeeder` (list recipe + label_map + Betallatex→Sempertex / Retail
  Packaged→Retail aliases + Sempertex 11→12 + `BL-`/`-B` SKU strips).
- **Validated end-to-end on the real product** (`…/11-inch-latex-balloons-100-per-bag-fashion-yellow-betallatex-bl-53005`):
  extractor → inject Brand(vendor) + Shape(Round) → matcher resolves **Sempertex / R-12
  / Fashion Yellow / Retail / 100, all `[exact]`** against the prod catalogue.

### Verified facts about BargainBalloons
- It's **Shopify**. `…/products/{handle}.json` gives `vendor` (= brand, "Sempertex"),
  variant `sku`/`barcode`/`price`, `tags` — but `product_type:""`, `body_html:null`,
  `options: Default Title`. **Rich attributes are page-HTML-only.**
- Page attributes = the "Additional Product Details" accordion
  (`div.cc-accordion-item__content > ul > li > span>{Label}: </span>{Value}`). Real
  labels: `Size (inches)`(11.0), `Manufacturer Color`(Yellow), `Latex Finish`(Fashion),
  `Package Count`(100), `Packaging Type`(Retail Packaged), `Print`(Solid Color),
  `Manufacturer Supplied Category Type`(Non-Print). **No Shape label.**
- The exact Fashion-Yellow product is already a FULL Larocks pending proposal (UPC
  `00030625530057`); we carry R-12 Fashion Yellow in 12/20/50 ct **without barcodes**,
  not 100 ct → a new sibling. So BB's value here is reconciliation + high confidence.

## Production runbook
SSH alias `myvps`, app `/home/balloonventory/balloonventory-app`. **Always** PHP 8.4:
`/opt/cpanel/ea-php84/root/usr/bin/php`. Deploy via `bash bin/deploy.sh` (migrates +
builds, **never seeds**). Crawls/ingests are **dry-run by default**; `--execute` writes.
⚠️ **BB's recipe is in the seeder but NOT on the prod DB** (firstOrCreate won't update
the existing row) — set it on prod when wiring the crawl (update the `bargain-balloons`
distributor's `config` via tinker, or reconcile the seeder), and confirm with a Probe.

---

## Increment 3 — Shopify page-enrichment crawl (the build)

**Goal:** for net-new latex BargainBalloons products, fetch the product page, read the
accordion, and stage a fully-attributed product so it clusters/proposes like Larocks.

**Data flow per product** (mirror `DistributorProductIngestor::crawlBigCommercePage`,
but Shopify-flavoured):
1. **Source list from `products.json`** (the `ShopifyAdapter` already paginates it).
   ⚠️ `ShopifyAdapter::extractProductVariants` currently drops `vendor` — **add it**
   (and keep barcode/sku/price/title/handle→url).
2. **Targeting (lean):** only page-fetch products that are **net-new** (UPC not already
   in our catalogue / another distributor — barcode from products.json) **and** likely
   **solid latex** (cheap pre-filter on `tags`/`title`: has "latex", not
   "foil"/"mylar"/"printed"). The real classification still happens from the page, so a
   misfilter just parks. Shared-UPC products need **no page fetch** — the bulk barcode
   already reconciles them at cluster time.
3. **Fetch the page**, run `ProductAttributeTableExtractor::extract($html, $config)`
   (list mode, already built).
4. **Inject** what the page lacks: `Brand` = [vendor from products.json]; **synthesize
   `Balloon Type / Shape`**: SKU prefix (`R`/`H`/`L` + size, e.g. Decomex `R12`/`H07`/
   `L11`) → title keyword (`linking`/`link`→Link, `heart`→Heart) → **default Round** for
   latex.
5. **Classify** via `DistributorProductClassifier::classify($extraction)` and **upsert**
   into `distributor_products` with sku/barcode/price (json) + `raw_data.attributes` +
   `product_type` — honouring the existing no-clobber guard (`hasStagedAttributes`) and
   the freshness columns (`last_seen_at`/`removed_at` — reuse the incremental machinery;
   Shopify can diff on products.json `updated_at`).

**Where to put it:** either extend `catalog:ingest-distributor` with an `--enrich`
option, or add `catalog:enrich-distributor`. Keep the bulk products.json ingest as the
cheap reconciliation pass; enrichment is the targeted second pass. Decide and note it.

**Tests:** `Http::fake` the products.json + a product page (use the real accordion shape
from the extractor test). Assert a staged product gets brand(vendor)+shape(synth)+
attributes + `product_type=solid_latex`, and that the net-new/latex filter skips
shared-UPC and non-latex.

**Verify on prod (after deploy + set BB config):** run a dry-run, then `--execute` on a
small `--limit`, then re-cluster — confirm a BB-staged product resolves and (for the
Fashion Yellow UPC) the Larocks proposal flips to **high confidence**.

## Increment 4 — auto-create accuracy gate

Implements the agreed policy (see `distributor_operations_prompt.md` → "Accuracy &
auto-create policy"). Before auto-creating a NEW SKU:
- **Multi-source attribute agreement:** when a cluster has ≥2 members with attribute
  tables (now possible: Larocks + BB), compare their resolved brand/size/colour
  (each member's attributes are already in the proposal `evidence`). **Agree → eligible
  to auto-create; disagree → leave for review** (surface both sources). Today the
  resolver picks the first member; add the comparison.
- **GS1-prefix → brand check:** validate the resolved brand against the UPC's
  manufacturer prefix (e.g. `030625` = Sempertex); mismatch flags a bad resolution.
- Keep: **don't bulk-`promote` single-source** clusters — review via the queue.

## Guardrails
- Stay incremental: one increment per branch → tests green → `pint --dirty` → deploy →
  verify on prod, exactly as increments 1–2 were done. Run distributor-scoped tests
  (`--filter='Distributor|CatalogCluster|Promote|ProposalResolver|Extractor'`), not the
  full suite, until the end.
- Don't bulk-`promote --execute`. SKUs are created by review/approval until the accuracy
  gate is in and a cluster has a verified second attribute source.
- Don't touch unrelated working-tree changes; stage commits by filename.
