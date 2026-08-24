# POAgent — Development Notes

**Project Location:** `C:\xampp\htdocs\website\POAgent`
**Spec:** `pre-demo` build, based on *Purchase Order & Delivery Note Matching System — Pre-Demo Development Spec (v3)* (Priority DB → 3 local directories, WhatsApp bot → web interface).
**Last Updated:** August 18, 2026
**Status:** Phase 1 complete — PO creation flow (user → supplier → items/qty → confirm → create), tested end-to-end. DN/OCR/Diff Engine phases not yet started.

---

## 1. What This Is

A pre-demo stand-in for the full Priority-integrated PO/Delivery-Note matching system. Same
architecture principles as the eventual system, but:
- Priority DB → three local directories (`SuppliersDB/`, `POdir/`, `DNdir/`)
- WhatsApp bot → plain PHP web UI (thin client, no business logic)
- Single user session at a time, single laptop, no auth/security hardening (matches spec §8.9 —
  revisit only when deployed to a real server)

All business logic lives in `lib/` (the "brain"); UI screens only call into it and render.

---

## 2. Build Order Progress (spec §9)

| # | Item | Status |
|---|---|---|
| 1 | `SuppliersDB` reader + `SupplierStore` adapter | ✅ Done |
| 2 | `POStore` adapter + atomic PO counter + PO creation flow (Screens 1–2 + PO flow) | ✅ Done |
| 3 | DN upload + storage (no OCR yet) | ⬜ Not started |
| 4 | Wire in Retailomatics OCR + OCRsanity gate | ⬜ Not started |
| 5 | Barcode matching + Review screen | ⬜ Not started |
| 6 | Diff Engine (fused) — VS generation | ⬜ Not started |
| 7 | Status lifecycle transitions (`open`→`prcv`/`closed`) | ⬜ Not started (adapter method exists, unused) |
| 8 | UI: status/history view, VS display | 🟡 Partial — PO history + per-PO detail view exist, no VS yet |
| 9 | End-to-end test incl. multi-delivery + unknown-barcode + exact-match cases | ⬜ Not started (needs DN/VS) |

---

## 3. Directory Structure

```
POAgent/
├── index.php            Screen 1 — user select (user1/user2/user3 → generator_id)
├── select_user.php       Handles Screen 1 submit, stores generator_id in session
├── main_menu.php         Screen 2 — Create PO / Upload DN (DN disabled, "coming soon")
├── po_supplier.php       PO flow step 1 — supplier list
├── po_items.php          PO flow step 2 — item list w/ prices + qty inputs
├── po_confirm.php        PO flow step 3 — confirm screen, re-derives prices server-side
├── po_create.php         PO flow step 4 — writes the PO via POStore, flash-redirects
├── po_success.php        PO flow step 5 — confirmation screen (one-time session flash)
├── po_view.php           PO detail view (from po_list.php) — same rendering as po_success.php
├── po_list.php           Status/history view — filename-glob backed, totals per PO, links to po_view.php
├── lib/
│   ├── ui_common.php      Shared HTML shell (RTL/Hebrew card layout) + session helper +
│   │                      poagent_render_po_detail() (shared by po_success.php/po_view.php)
│   ├── filename_utils.php Sanitize-for-filename + write-to-temp-then-rename helpers
│   ├── SupplierStore.php  DataStore adapter — reads SuppliersDB/*.xlsx catalogs
│   └── POStore.php        DataStore adapter — atomic counter, PO JSON read/write/status
├── tools/
│   └── seed_demo_suppliers.php   Dev-only: (re)writes 3 placeholder supplier catalogs
├── SuppliersDB/          supplier_<SupplierID>.xlsx catalogs (Barcode | ItemName | Price)
├── POdir/                PO JSON records — gitignored (generated data, not source; see §4)
├── POcounter/             Atomic counter file (.po_counter) — deliberately its own dir, also
│                          gitignored, so it can never be wiped as a side effect of clearing POdir/
└── DNdir/                Reserved for DN/VS files — empty until phase 3+, gitignored
```

---

## 4. Key Implementation Decisions

- **DataStore adapters** (`SupplierStore`, `POStore`) are the only code that knows about the
  filesystem layout, per spec §7 — UI screens never touch `SuppliersDB/`/`POdir/` directly.
- **Prices are re-derived server-side, always.** Every step that receives a barcode+qty from a
  form (`po_confirm.php`, `po_create.php`) looks the price back up in the supplier catalog by
  barcode — client-submitted price/name values are never trusted, even though they're only
  echoed back as hidden fields.
- **Filename-glob is the index** (spec §8.1) — `POStore::listPOs()` just globs `POdir/`, no
  separate index file.
- **Atomic PO counter** (spec §8.2) — `POStore::nextPoId()` uses `flock()` on `.po_counter`
  around read-increment-write.
- **Counter lives in its own gitignored directory, `POcounter/`, separate from `POdir/`** (Aug
  18, 2026) — both `POdir/` (generated PO records) and `POcounter/` (the counter) are gitignored,
  each with its own `*` / `!.gitignore` (matches the convention already used by `downloads/`,
  `uploads/`, `ocrDir/`, etc. elsewhere in this repo). Splitting them into separate directories
  means clearing/regenerating `POdir/` (e.g. resetting demo data) can never take the counter down
  with it. `nextPoId()` does **not** try to reconstruct a missing counter from existing PO files
  in `POdir/` — if `.po_counter` is lost, it silently restarts at `PO00001`, colliding with any
  higher-numbered POs already on disk (reproduced deliberately — see Fix 4 below). Recovery is
  manual: write the desired last-used number as plain text into `POcounter/.po_counter`.
- **Write-to-temp-then-rename** (spec §8.6) — `poagent_write_json_atomic()` in
  `filename_utils.php`, used by every PO write.
- **PO status rename is a single code path** (spec §8.7) — `POStore::setStatus()` exists for
  later phases; nothing else is allowed to rename a PO file.
- **POST/redirect/GET on PO creation** — `po_create.php` writes the PO then redirects to
  `po_success.php`, which reads a one-time `$_SESSION['poagent_last_po']` flash and clears it.
  Refreshing the success page never creates a duplicate PO (verified).
- **Deviation from spec's own example:** the spec's pattern is `PO#####` (5 digits) but its
  worked example shows `PO0004` (4 digits) — implementation follows the 5-hash pattern literally
  (`PO00001`, `PO00002`, …). Flag if 4-digit is actually wanted.

---

## 5. Placeholder Supplier Data

`tools/seed_demo_suppliers.php` (re-runnable, overwrites) seeds 3 supplier catalogs reusing
supplier IDs already present in the root `suppliers.json` (so IDs stay consistent across the
codebase) with **fictional** items/prices, ≤10 per supplier, per explicit request:

| Supplier ID | Hebrew name | Items |
|---|---|---|
| `Osem` | אסם | 10 |
| `Tnuva` | תנובה | 8 |
| `Dansell` | דנסל | 6 |

Real supplier catalogs can replace these files directly — same filename convention
(`supplier_<SupplierID>.xlsx`) and column layout (`Barcode \| ItemName \| Price`), no code changes
needed.

---

## 6. Fixes Applied Since Initial Build

### Fix 1 — quantities lost on "back to items" from confirm screen
**Symptom:** clicking "חזרה לעריכת כמויות" on `po_confirm.php` reloaded `po_items.php` with every
quantity reset to 0, discarding the user's original picks.
**Fix:** the back link is now a POST form (`po_confirm.php`) carrying `supplier_id` + the
confirmed `qty[barcode]` values as hidden fields. `po_items.php` now accepts `supplier_id` from
either GET (fresh pick, all qty=0) or POST (return trip), and pre-fills each quantity `<input>`
from the posted values when present.
**Verified:** curl round-trip — entered 2×/3× on two items → confirm → simulated back-click →
items screen returned with those exact values, everything else at 0.

### Fix 2 — no order totals in the history view
**Symptom:** `po_list.php?all=1` listed POs but not their value.
**Fix:** added `POStore::totalAgorot($po)` (sum of `qty × unit_price_agorot` across a PO's line
items) and a "סה"כ" column in `po_list.php`.
**Verified:** rendered list shows correct per-PO totals (e.g. PO00001 → 36.30 ₪, matching its
confirm-screen total).

### Fix 3 — item picker didn't scale past ~20 SKUs (Aug 18, 2026)
**Symptom:** `po_items.php` rendered one `<tr>` per catalog item with a qty box next to each —
fine for the 6-10 item demo catalogs, unusable once a real supplier has hundreds of SKUs.
**Fix:** rebuilt `po_items.php` around a search/typeahead box: the full catalog is embedded once
as JSON and filtered client-side in vanilla JS (no per-keystroke round trip). Typing filters by
name or barcode (prefix matches ranked above substring matches, capped at 40 results); focusing
the box with nothing typed shows the catalog from the top so the user can scroll/browse instead
of searching. Clicking (or arrow-keys + Enter on) a result adds it to a running "picked items"
table below, where qty defaults to 1 and is edited/removed per-row. An exact barcode match on
Enter always adds directly, regardless of what's highlighted — covers a barcode-scanner feeding
the search box. On submit, JS generates one hidden `qty[<barcode>]` input per picked item, i.e.
**the POST shape to `po_confirm.php` is unchanged** from the old per-row table — `po_confirm.php`
still re-derives prices/names server-side by barcode and needed no changes. The "back to edit"
round trip from `po_confirm.php` (Fix 1) still works: posted `qty[]` values are matched back
against the catalog server-side into an `INITIAL_SELECTED` JSON blob that the JS renders as the
starting picked-items table.
**Verified:** `php -l` on both changed files; curl smoke test — loaded picker page for supplier
`Osem` (catalog JSON + picker markup present), POSTed `qty[barcode]=n` for two real barcodes
straight to `po_confirm.php` (unchanged — correct lines/total rendered), then replayed the same
POST to `po_items.php` (the "back" shape) and confirmed `INITIAL_SELECTED` carried the exact
qty=2/qty=3 back.
**Not yet done:** no visual/browser check of the JS itself (dropdown rendering, keyboard nav,
add/remove/qty-edit interactions) — curl can't drive JS. Worth an manual click-through in a
browser before the next demo, and worth revisiting once a supplier catalog actually reaches
hundreds of rows (current fake catalogs are 6-10 items, so the "scroll to browse hundreds" path
is unexercised at real scale).

### Feature — PO detail view from history (Aug 18, 2026)
**Gap:** `po_list.php` only ever showed the summary row per PO (id/supplier/user/date/status/
item-count/total) — the only way to see a PO's actual line items again (like the confirmation
screen shown right after creation) was gone once you navigated away from `po_success.php`.
**Fix:** extracted the id/status/supplier/generator/date/core-name header + items table (with
per-line and grand totals) out of `po_success.php` into a shared renderer,
`poagent_render_po_detail()` in `lib/ui_common.php`. Added `po_view.php?core_name=<core_name>`,
a read-only detail screen that calls `POStore::loadByCoreName()` (existing adapter method,
previously unused) and renders through the same shared function — so a PO looks identical
whether you just created it or you're looking it up later. `po_list.php` now has a "🔍 צפייה"
link per row pointing at it. `po_success.php` shrank to a thin wrapper around the shared
renderer and, as a side effect, now also shows a grand total (it didn't before).
**Security note:** `core_name` arrives via GET and feeds a `glob()` call inside
`loadByCoreName()`. `po_view.php` whitelists it to `^[A-Za-z0-9_-]+$` before calling in — blocks
`/`, `..`, and glob wildcards (`*`, `?`, `[`) — since core names are always plain
`<user>_<supplier>_<ddmmyy-His>_PO#####` segments built server-side.
**Verified:** `php -l` on all 4 touched/added files; curl smoke test — loaded the history list,
pulled a real `core_name` (PO00005 / Tnuva / user1, matching the screenshot that prompted this),
opened `po_view.php` with it and confirmed the id, all 3 line items, and the grand total render;
confirmed a path-traversal-shaped `core_name` (`../../etc/passwd`) gets redirected to
`po_list.php` instead of reaching `glob()`.

### Fix 4 — POdir/counter gitignored, counter moved to its own directory (Aug 18, 2026)
**Trigger:** while investigating "what happens if `.po_counter` gets deleted?" — reproduced it
(backed up the real counter, deleted it, called `nextPoId()` twice, saw it return `PO00001` then
`PO00002` again, restored the backup after). No crash: `fopen($file, 'c+')` silently recreates a
missing counter file and treats empty content as `0`. Since `POdir/` already had real
`PO00001`-`PO00005` on disk, a fresh PO created after such a loss would silently collide with an
existing PO number (different `core_name`/timestamp, so no file gets overwritten, but the
human-facing `PO#####` reference stops being unique).
**Fix:** (1) moved the counter out of `POdir/` into a new sibling directory, `POcounter/`, so
`POdir/` can be treated as fully disposable/regenerable without risking the counter — updated
`POStore.php`'s `POAGENT_COUNTER_DIR`/`POAGENT_PO_COUNTER_FILE` constants and added a
`mkdir()`-if-missing for the new directory alongside the existing one for `POdir/`. (2) Added
`.gitignore` (`*` / `!.gitignore`, same convention as `downloads/`, `uploads/`, etc.) to both
`POdir/` and `POcounter/`, and ran `git rm -r --cached` on the previously-tracked `POdir/`
entries (3 old PO json records + the old `.po_counter` path) so the ignore rule actually takes
over — working-tree files were untouched, this only stopped git from tracking them going
forward, and nothing was committed. Both directories are now fully local/untracked and evolve
together per machine, which also sidesteps the collision risk above in the common case: a fresh
clone starts both `POdir/` and the counter empty together, rather than getting a stale counter
next to someone else's committed PO history. Explicitly **not** implemented: reconstructing the
counter from existing `POdir/` files on startup — deletion still means a silent restart at
`PO00001`; recovery is manual (write the desired last number into `POcounter/.po_counter`), by
this ask's explicit choice.
**Verified:** `php -l` on `POStore.php`; confirmed all 5 real PO json files + the counter
survived the directory move/untrack with byte-identical content; ran a full PO creation through
the real HTTP flow (login → `po_create.php` → `po_success.php`) and confirmed it correctly
allocated `PO00006` (counter was at 5) from the new `POcounter/.po_counter` location, then
deleted that one test PO record afterward (counter intentionally left at 6, not rolled back —
rolling counters backward is exactly the collision risk being guarded against, so `PO00006` is
now a harmless gap, not reused).

---

## 7. Testing Notes

- No automated test suite yet — verification so far is manual smoke-testing via `curl` with a
  cookie jar (simulating the session across the multi-step flow) plus `php -l` syntax checks on
  every changed file, run after each change.
- Local dev server: XAMPP Apache, served at `http://localhost/website/POAgent/`. Apache is not
  installed as a Windows service in this environment — start via `C:\xampp\apache_start.bat` (or
  `apache\bin\httpd.exe -D FOREGROUND`) if it isn't already running.
- Real usage during development left PO00001–PO00003 in `POdir/` (Osem/Dansell/Tnuva across
  user1/2/3) — harmless leftover test data, safe to delete before a clean demo run, or leave as
  sample history.

---

## 8. Known Open Items / Next Steps

- DN upload/storage, OCR wiring (+ OCRsanity gate), barcode-match Review screen, Diff Engine (VS
  generation), and status-lifecycle transitions are all unbuilt — see §2 table.
- `po_list.php` currently shows only PO status/totals; VS display per delivery (spec §4.1 "Status/
  history view") depends on the Diff Engine landing first.
- No automated tests exist; consider adding a lightweight PHP test script once DN/VS logic lands,
  since manual curl smoke-tests won't scale well to the barcode-matching/variance paths.
