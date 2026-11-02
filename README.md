# Subaru Parts Inventory (WordPress plugin)

A WordPress plugin for keeping an inventory of **new and used Subaru parts**. It has a full back office, physical labels with QR codes and barcodes, phone scanning, and a public catalogue (block, shortcode and widget).

The plugin is in [`subaru-parts-inventory/`](subaru-parts-inventory/).

## Features

| Area | What you get |
|---|---|
| **Categories = boxes** | The Subaru catalog groups for the 2017 WRX 2.0L 6MT come preloaded: Engine Main, Engine Auxiliaries, Manual Transmission, Differential & Propeller Shaft, Suspension Axle & Brake, Body Electrical, Body, Interior & Seats, Heater & A/C, Accessories, Maintenance, plus a "To Sort" box. That is 12 groups and about 145 sub-categories. Each category has a **storage box code** (e.g. `BOX-EA`) and a location. Sub-categories use their parent's box until you give them their own, so you can split a crowded box later. |
| **Condition** | Four plain grades: **New**, **Used**, **Bad** (needs repair or cleaning), **Unusable** (for parts or scrap only). Older grades are converted automatically on update. There is a checklist (tested, dirty, cracks, corrosion, broken tabs, leaking…), public condition notes, mileage on the part, and the donor vehicle/VIN (kept private). "Removed from" defaults to your VIN, which you can change in Settings. |
| **Condition log** | A dated history of inspection notes with the grade at that time. Grade changes are logged automatically. |
| **Labels** | Every part gets a permanent **label ID** (`SP-00042`). A label shows the **OEM part number** (large), the part name, the condition grade, the label ID and the box code. It also has a **QR code** (scan to open the part) and, on labels at least 34 mm tall, a **Code 128 barcode** of the part number. Formats: Brother QL (DK-11209/11201/11202), DYMO (11352, 99012), thermal 2.25×1.25"/4×2"/4×6", Avery L7160/L7163/L7165 (A4), Avery 5160/5163 (Letter), and A6/A4 box labels. On sheets you can skip labels that are already used. Labels are marked as printed after you print them, and the dashboard reminds you which parts still need one. |
| **Box labels** | A large label per box with the box code, the catalog group, a list of the sub-categories inside, and a QR code that lists everything in the box. |
| **Scan / quick update** | When you're logged in, scanning a label with your phone camera opens a mobile screen where you can adjust quantity ±1, mark the part sold/installed/reserved, or add a condition note. USB barcode scanners also work there, and so does in-browser camera scanning on Chrome/Android. For visitors, the same QR code opens the public page. |
| **Back office** | A dashboard (totals, new/used, value vs purchase cost, per-box counts, to-dos). The parts list has filters for category, condition, status and label printed. Search matches part numbers with or without dashes, superseded numbers, label IDs and names. Bulk actions: print labels, set status, show/hide. You also get a duplicate-part button and a warning when you enter a part number you already have. |
| **Public catalogue** | Use the **"Subaru Parts Catalogue" block** or `[subaru_parts]`. It has live search and filters for category, new/used/grade and sorting, a grid or table layout, and links you can share. Single part pages show a gallery, specs and condition notes. Sold, installed, scrapped and hidden parts never appear. Prices, quantity and storage location can each be shown or hidden. |
| **Widget** | "Subaru Parts Inventory" sidebar widget: search box, stock counters, categories, latest parts. |
| **Import / export** | CSV export, which round-trips every field. CSV import creates parts or updates them (matched by label ID), creates missing categories from paths like `Engine Auxiliaries > Turbocharger`, and accepts `;` or `,` separators. A template CSV is included. |
| **REST API** | `GET /wp-json/subaru-parts/v1/parts?search=&category=&condition=used`, `/parts/<id>`, `/categories`. Logged-in managers also get `/lookup?code=` and `POST /parts/<id>/quick`. |
| **Roles** | Admins get full access. A "Parts Manager" role is added for a helper who should only manage parts. |

## Official Subaru catalog (bundled)

The plugin ships the complete parts.subaru.com catalog for the 2017 WRX 2.0L 6MT Limited (`data/subaru-2017-wrx-library.json.gz`): 3,495 OEM part numbers on 445 diagrams in 12 categories, plus 69 genuine accessories. It installs itself on the first admin page load after activating or updating the plugin.

- **Categories = parts.subaru.com.** The 12 top-level categories (Engine and Cooling, Manual Transmission, Suspension and Brakes, Electrical, Body, …) contain service-manual sections, and those contain the diagrams (see *Boxes* below). Parts from earlier versions are moved over automatically, and unused old categories are removed.
- **Where a part goes:** a part listed on several diagrams is filed under the real diagram rather than the Maintenance shortcut list, preferring diagrams that apply to MY2017. Diagrams for other model years are marked "other MY".
- **In the app:** the *Subaru catalog* section lets you browse diagrams, see what you already have, and tap **+ Add**. Typing a name in *Add a part* searches the catalog. Part cards show the official name, notes, US list price, catalog picture and every diagram with its callout number.
- **Images:** upload the `subaru_2017_wrx_images_partN.zip` files under *Import / Export → Catalog images*, or copy the `images/` folder to `wp-content/uploads/sps-library/images/`. Until then, images load from the parts.subaru.com image server; this can be switched off in Settings.
- **Updating:** import a newer library JSON with the same structure on the same page.

## Boxes: categories and service-manual sub-boxes

Inside each parts.subaru.com category, the diagrams are grouped by Subaru **service-manual section**: FU, EC, IN, ME, EX, CO, LU, SP, IG, SC, EN · CS, 6MT, CL · FS, RS, WT, TPM, DI, DS, VDC, BR, PB, PS · AC, AB, SB, LI, WW, ET, GW, BS, IDI, SE, SL, SR, EI, EB, WI, GN. Diagnostics-only "(diag)" sections are not used.

- **Sub-box mode** (default): box codes are `EN-FU`, `EN-IN`, `EN-ME`, `SB-BR`, `EL-LI`, … Accessories and your own categories get one sub-box per sub-category (`AX-AM` Audio Media, `AX-STI` STI Brand, …).
- **Category mode:** one box per category (`EN`, `SB`, `EL`, …).
- **Clean up & rebuild boxes** (parts app → *Storage boxes* → ⚙ *Box setup*, or wp-admin → *Subaru Parts → Boxes & images*) does the following, then reloads the page:
  - removes every box code;
  - merges duplicate categories;
  - deletes empty categories that are not from the Subaru catalog;
  - files every part with a catalog number into its diagram;
  - clears box overrides that point at codes which no longer exist;
  - optionally marks all parts as "Not boxed yet";
  - assigns a fresh, unique scheme.
- Reprint box labels after rebuilding.

## Catalog images

- **Download catalog images to this site** (parts app → *Tools*, or wp-admin → *Subaru Parts → Boxes & images*) copies all ~3,000 part pictures and diagrams from the parts.subaru.com image server to `wp-content/uploads/sps-library/images/`, 12 at a time. No big upload is needed, so it avoids hosting upload limits and plugins that load whole uploads into memory. You can stop and continue later. Images the server doesn't have are reported; network errors just pause the download.
- **Add missing pictures:** in the Subaru catalog, use **📷 Add picture** on any part, or **📷 Add / replace diagram image**. On a part card, **Use my photo as catalog picture** copies one of your own photos. Your pictures are stored in `images/custom/` and survive catalog re-imports.
- **Thumbnails:** every category, sub-box and diagram shows a diagram thumbnail in the catalog browser and on box cards. In a diagram, **Use as thumbnail for …** picks a different one.

## Part-number library (auto names & boxes)

When you type or scan a part number, the name and category/box fill in automatically:

1. **Your library:** import a CSV under *Subaru Parts → Import / Export → Part name library*. Columns are `part_number`, `name` and optionally `category`. Commas, semicolons and tabs all work, and a header row is optional.
2. **Remembered names:** every name you give a part is saved. The next time that number, or another number with the same 5-digit Subaru base (e.g. 22433AA750 → 22433AA790), is entered, it fills in automatically. Your own names always win over imported ones.
3. **Built-in number groups:** Subaru numbers start with a 5-digit group (14411 = turbocharger, 22433 = ignition coil, 26296 = front brake pads…). Unknown numbers still get a suggested box from these groups. This is shown in amber as "guessed – check it".

This also works for CSV imports and parts added with only a part number.

## Boxing workflow

New parts start as **Not boxed yet**. Add the parts and print their labels. Then open **Scanner → Put away into box**, scan the box label, and scan each part as you drop it in. Each scanned part becomes **In stock (boxed)**. If a part belongs in a different box you get a warning, plus a "Store it here anyway" button that records the override. Each not-boxed part's card also has a one-tap **Mark boxed in BOX-xx** button. The header shows how many parts are still not boxed.

## The parts page (front-end app)

`[subaru_parts]` (or the **Subaru Parts Catalogue** block) renders a single-page app styled like the Gintesa *Subaru Tracker* page. It uses a dark surface, a neon `#E8FF3A` accent, Inter and JetBrains Mono, a sticky section nav, a hero with a stats strip, and collapsible sections.

| Section | Visitors | Logged-in parts managers |
|---|---|---|
| **Scanner** | – | The phone or laptop camera reads part and box label QR codes. It uses the native barcode reader where the browser has one, and a bundled QR decoder (jsQR) everywhere else, including iPhone. You can also type a code or use a USB/Bluetooth scanner. A scanned part opens a card where you can change the quantity ±1, set the status, add a condition note, edit, print the label or trash the part. Scanning a box label lists everything in that box. An unknown code offers **Add as new part**. |
| **Add a part** | – | One form for everything: part number with a duplicate warning (a scan button reads the barcode on the Subaru box), name, catalog category with the box it goes into, condition grade and checklist chips, notes, quantity, status, mileage, donor car and fitment. A collapsible panel holds the extra numbers, storage, prices and description. You can take or attach photos; they are shrunk before upload. After saving you get the label ID, the target box and a **Print label** button, and the form stays ready for the next part in the same category. **Edit** on any part reopens this form with its data. |
| **Inventory** | Searchable list (New/Used/Core chips, category, sort) with an expandable detail for each part | The same list plus a status filter (including sold/installed parts), quick actions, condition log and trash |
| **Storage boxes** | Box cards with part counts (tap to filter the list) | Plus editing the box code and location, and a box-label link |
| **Labels to print** | – | Queue of parts that have no label yet. Pick a label format and click *Print selected*. |
| **Activity** | – | Recent condition-log entries across all parts |
| **Tools** | – | Counts by status, and links to the admin dashboard, bulk list, categories, import/export and settings |

Attributes: `[subaru_parts title="Parts storage" category="engine-auxiliaries" condition="used" per_page="30"]`. Use `layout="grid"` for the earlier simple catalogue. Colours are CSS variables on `#sps-app` (`--neon`, `--bg`, `--surface`, …), so you can override them in *Customizer → Additional CSS*.

The camera only works on HTTPS pages and asks for camera permission the first time.

## Installation

1. Zip the `subaru-parts-inventory` folder (or copy it to `wp-content/plugins/`).
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, then activate it.
3. Open **Subaru Parts → Settings** and set the label ID prefix, default label format, currency, km/mi and catalogue options.
4. Open **Subaru Parts → Categories & Boxes** to adjust the box codes and locations so they match your physical boxes. Then go to **Print Labels → Box labels**.
5. Add parts under **Subaru Parts → Add Part**, or import a CSV. Then use **Print missing labels**.
6. To publish the catalogue, add the *Subaru Parts Catalogue* block to a page and select that page in Settings.

```bash
cd Subaru-parts-storage && zip -r subaru-parts-inventory.zip subaru-parts-inventory
```

Requirements: WordPress 6.0+ and PHP 7.4+. The plugin has no build step and no external services. The QR library (`qrcode-generator`, MIT) is bundled, and barcodes are generated in PHP.

### Printing tips
- **Brother QL / DYMO:** in the print dialog choose the label printer and the matching paper size, margins *None*, scale *100 %*.
- **Avery sheets:** margins *None*, scale *100 %* / *Actual size*. Use *Skip used labels* to finish a partly used sheet.
- QR codes hold your site URL, so print labels from the site address you will scan from (not `localhost`).

## Why it is designed this way

The design follows patterns from established open-source inventory systems such as **InvenTree** and **Part-DB**, and from what auto recyclers and parts sellers use:

- **A label ID separate from the part number.** OEM numbers repeat: you may have three used `22433AA750` coils in different condition. The unique label ID is what the QR code points to, so each physical item can be tracked. The OEM number is still printed largest and encoded in the barcode.
- **Location from the category.** Each category maps to a box. You file a part by picking its Subaru catalog category, and the box follows automatically. You only set an override when a part does not fit in that box.
- **A grading scale like the ones recyclers use** (A/B/C/D plus core), with a checklist so notes are consistent and searchable. A dated condition log is kept separately.
- **"Mark labelled" tracking and a "print missing labels" queue** so no part ends up on the shelf without a label.
- **Plain WordPress building blocks:** a custom post type, taxonomies and post meta. Backups, revisions, the media library, roles and the REST API all work as usual.

### About the category list
I could not open parts.subaru.com from the build environment because the site was blocked. The default tree therefore follows the standard Subaru OEM (EPC) catalog grouping for the 2017 WRX (VA, FA20DIT, 6MT), which is the structure parts.subaru.com is organised by. Some sub-category names may differ a little from the website. Every category can be renamed, moved or added under **Categories & Boxes**. **Settings → Restore default categories** brings back any default you deleted without touching your edits.
