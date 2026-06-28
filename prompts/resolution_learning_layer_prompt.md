# Distributor Matching — Resolution Learning Layer (handoff)

Self-contained spec for a fresh session. Goal: stop hand-adding matcher config
rules every session. Make attribute resolution **learn from Todd's review-queue
edits and comments** so it improves over time. Read the memory files
[[project_distributors_state]] and [[project_distributor_havinaparty]] for the
surrounding pipeline; this doc is the focused brief for the learning layer.

## Why this exists (the problem)
The distributor matcher resolves each attribute (brand / size / colour /
packaging) through a fixed cascade: **exact name → curated alias (hand-authored
`config.attribute_aliases`) → fuzzy "contains"**. All the intelligence is in
hand-written per-distributor config (`attribute_aliases`, `size_number_aliases`,
`size_shape_prefixes`, `extraction.label_map`). Every new vocabulary mismatch
needs a developer to add a config line in a session. It does **not** learn from
Todd's corrections. Todd wants: *"something that gets better over time that I can
improve with my edits and comments — otherwise we're stuck slowly adding rules."*

The key gap: when an admin edits a proposal, the system stores the correction on
that **one** proposal (`proposed_color_id` etc.) and **throws away the
generalization** — even though the raw distributor value is right there in the
proposal's `evidence`. So the same "Red Fashion → Fashion Red" mis-resolves
forever. We already capture the labelled pair; we just discard it.

## Scope split — DO NOT redo the guardrails (already shipped)
This session already fixed the *cluster-integrity* class (these are DONE, live):
- Brand-gated UPC inheritance + confidence = attribute-bearing distributors
  (merge `69c1e17`) — stops cross-brand contamination / fake high-confidence.
- `representativeProductType` prefers a confident type over unknown/non_balloon.
- warehouse_sku rescue tier (barcode-less → existing catalog, brand-scoped).
- Auto-create accuracy gate (multi-source attribute agreement + GS1 + count).

**The learning layer changes RESOLUTION QUALITY ONLY — never the auto-create
safety bar.** The accuracy gate stays exactly as-is. Learning makes proposals
resolve to the right reference rows; the gate still decides what may be created
without human approval.

## The worked examples (the real cases to satisfy)
1. **Colour word-order** — Larocks/havinaparty send "Red Fashion" / "Lilac
   Deluxe"; our catalog is "Fashion Red" / "Deluxe Lilac". Not a synonym — the
   finish+colour word order is reversed. Neither exact nor the title fallback
   catches it. → learned alias and/or a reversed finish+colour recompose.
2. **Source-trust (the comment case)** — for UPC `08853406036683`, Larocks's own
   `Color` field is the COARSE FAMILY "Yellow / Gold" for a product titled
   "Pastel Ivory". "Yellow / Gold" exact-matches our real "Yellow" colour and
   **beats** the correct shade in the title. The right fix is a *comment*:
   "for Larocks, Color is a coarse family — prefer the title shade." A
   deterministic alias table CANNOT represent this; it's the Phase-2 / comment
   case. (Gemar is the same: breadcrumb/Color gives families "Oranges" for Peach,
   "Ivorys & Yellows" for Yellow.)
3. (Related cleanup) The proposal **name** is the longest distributor title, so a
   contaminated/odd cluster gets a wrong name. Generate the name from the
   RESOLVED attributes instead (`6" Round · Standard Purple · Elitex · 50ct`) so
   it's always coherent. Designed, not built.

## Architecture — phased

### Phase 1 — Learn from edits (deterministic, high-ROI, data already exists)
- New table **`distributor_learned_aliases`** (on the `distributors` connection,
  like the other staging/proposal tables): `(distributor_id, attribute,
  raw_value_normalized) → catalog_id` + `brand_id` scope + `created_by` +
  `note` (nullable) + timestamps. `attribute` ∈ brand|size|color|packaging.
- **Capture hook:** in `DistributorProposalReviewService` (`update()`,
  `approve()`, `mapToExisting()`), when an admin sets `proposed_color_id` /
  `proposed_balloon_size_id` / `proposed_brand_id` / `proposed_packaging_id`,
  derive the raw distributor value from the proposal's `evidence` (each member's
  `attributes` holds the raw value, e.g. `Color: ["Yellow / Gold"]` or
  `["Red Fashion"]`) and upsert a learned alias `(distributor, attribute,
  raw_value) → chosen catalog id`, brand-scoped.
- **Apply:** add a new tier to `DistributorAttributeMatcher` BETWEEN exact and
  fuzzy: **learned alias**. Consult the store (keyed by distributor + brand +
  attribute + normalized raw value) before falling to fuzzy. So once Todd maps
  "Red Fashion"→"Fashion Red" once, every future one resolves with no config.
- Resolution is computed **LIVE at queue render** (`DistributorProposalReviewService::guessFor()`
  runs the matcher against stored `evidence`), so a new learned alias flips all
  matching partials→full immediately, no re-cluster. ⚠️ BUT the stamped columns
  used for queue facets/sorting (`resolution_state`, `resolved_brand_name`) are
  written at cluster time — decide whether to also re-stamp on alias capture, or
  accept that facet counts lag until the next re-cluster.

### Phase 2 — Comments + semantic tail (where it stops needing exact repeats)
- **Comment field** on proposals (and/or on learned aliases) — capture Todd's
  free-text reasoning. Cheap to add; banks guidance for the LLM step.
- **LLM matcher (Claude API)** for the low-confidence / partial residue: give it
  the distributor's raw attributes + the **constrained** list of real catalog
  candidates (it PICKS, never invents) + accumulated learned examples + Todd's
  comments → it proposes a mapping with a one-line rationale. Human confirms in
  the queue; the confirmation becomes a learned alias. This is the piece that
  "understands my comments" and the source-trust case (#2). Use the latest Claude
  model; see the `claude-api` skill for ids/usage.
- (Optional) **Embedding similarity** to replace brittle "fuzzy contains" for
  word-order/synonym variants without a rule each.

## Open decisions to resolve FIRST (ask Todd)
1. **Learned-alias scope** — per-distributor + brand (default, safest) vs global.
   Recommend per-distributor+brand with an explicit "promote to global" action.
2. **Comment field now?** — recommend yes; cheap, starts banking reasoning even
   before the LLM step.
3. **Phase 2 path** — Phase 1 deterministic first, then LLM (Claude) for the
   residue + comments. Embeddings optional.
4. **Re-stamp `resolution_state` on alias capture** vs only on re-cluster.
5. **Confidence from match quality** — currently identity-only; consider folding
   resolution quality in (separate from the auto-create gate).

## Code map (verified this session, 2026-06-27)
- `app/Services/Distributors/DistributorAttributeMatcher.php` — `match($attributes,
  $config)`; tiers in `resolve()` / `matchAliased()` (exact → alias → fuzzy);
  `matchBrand` / `matchSize` / `matchColor`. Colour priority today: manual >
  exact-structured > title (`CatalogAttributeResolver::colorInText`) >
  fuzzy-structured. **Add the learned-alias tier here.**
- `app/Services/Distributors/DistributorProposalReviewService.php` — `update()`,
  `approve()`, `mapToExisting()` (the **capture hooks**); `guessFor()` runs the
  matcher live at render; cross-connection rule: NO Eloquent relations across the
  `distributors` connection — batch-load reference rows from primary + stitch.
- `app/Services/Distributors/ProposalResolver.php` — canonical resolution stamped
  onto proposals at cluster time.
- `app/Services/DistributorClusterEngine.php` — `persistProposal()` stamps
  `resolution`/`resolution_state`.
- Proposal columns: `proposed_brand_id`, `proposed_balloon_size_id`,
  `proposed_color_id`, `proposed_packaging_id`, `proposed_count`,
  `proposed_name`, `proposed_warehouse_sku`, `evidence` (json, holds raw member
  attributes), `reviewed_by/at`, `resolved_brand_id/name`, `resolution_state`,
  `resolution` (json). **No comment/note column yet.**

## Conventions (so the new session doesn't rediscover them)
- PHP 8.4 CLI on prod: `/opt/cpanel/ea-php84/root/usr/bin/php`. Deploy via
  `ssh myvps "cd /home/balloonventory/balloonventory-app && bash bin/deploy.sh"`
  (does NOT run seeders/migrate-seed; migrations yes).
- Per-distributor config: `DistributorSeeder` uses `firstOrCreate` → **won't
  update existing prod rows**; set new config on prod by hand via tinker (merge,
  preserve existing keys).
- Tests: PHPUnit, `php artisan test --compact [--filter=…]`. Style:
  `vendor/bin/pint --dirty --format agent`. Every change programmatically tested.
- `distributors` DB connection is relocatable (staging/proposals/learned-aliases);
  reference data (brands/colors/sizes/packaging) is on the primary connection.
- Resolution is live at queue render → matcher/alias changes show in the queue
  WITHOUT a re-cluster; re-cluster only re-stamps facet columns + re-attaches URLs
  (idempotent, preserves human-touched proposals).

## Suggested build order
1. Resolve the 5 open decisions with Todd.
2. Phase 1: migration (`distributor_learned_aliases`) → capture hook in the review
   service → matcher learned-alias tier → tests (capture on edit; alias resolves
   next time; brand-scoping; live flip at render). Deploy. Have Todd review a few
   in the queue to confirm corrections stick.
3. Phase 2 (after Phase 1 proves out): comment field + LLM matcher for the
   residue/source-trust cases; optional embeddings; canonical proposal-name
   generation.
