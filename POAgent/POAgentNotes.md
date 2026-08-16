# POAgent — Development Notes

**Project Location:** `C:\xampp\htdocs\website\POAgent`
**Spec:** `pre-demo` build, based on *Purchase Order & Delivery Note Matching System — Pre-Demo Development Spec (v3)* (Priority DB → 3 local directories, WhatsApp bot → web interface).
**Last Updated:** August 13, 2026
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
| 8 | UI: status/history view, VS display | 🟡 Partial — PO history view exists, no VS yet |
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
├── po_list.php           Status/history view — filename-glob backed, totals per PO
├── lib/
│   ├── ui_common.php      Shared HTML shell (RTL/Hebrew card layout) + session helper
│   ├── filename_utils.php Sanitize-for-filename + write-to-temp-then-rename helpers
│   ├── SupplierStore.php  DataStore adapter — reads SuppliersDB/*.xlsx catalogs
│   └── POStore.php        DataStore adapter — atomic counter, PO JSON read/write/status
├── tools/
│   └── seed_demo_suppliers.php   Dev-only: (re)writes 3 placeholder supplier catalogs
├── SuppliersDB/          supplier_<SupplierID>.xlsx catalogs (Barcode | ItemName | Price)
├── POdir/                PO JSON records + atomic counter file (.po_counter)
└── DNdir/                Reserved for DN/VS files — empty until phase 3+
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
