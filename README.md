# Subaru Parts Inventory (WordPress plugin)

A WordPress plugin for keeping an inventory of **new and used Subaru parts**. It has a full back office, physical labels with QR codes and barcodes, phone scanning, and a public catalogue (block, shortcode and widget).

The plugin is in [`subaru-parts-inventory/`](subaru-parts-inventory/).

## Features

| Area | What you get |
|---|---|
| **Categories = boxes** | The Subaru catalog groups for the 2017 WRX 2.0L 6MT come preloaded: Engine Main, Engine Auxiliaries, Manual Transmission, Differential & Propeller Shaft, Suspension Axle & Brake, Body Electrical, Body, Interior & Seats, Heater & A/C, Accessories, Maintenance, plus a "To Sort" box. That is 12 groups and about 145 sub-categories. Each category has a **storage box code** (e.g. `BOX-EA`) and a location. Sub-categories use their parent's box until you give them their own, so you can split a crowded box later. |
| **Condition** | Grades: `NEW`, `NEW-OB`, `USED-A/B/C/D`, `CORE`. There is a 16-item checklist (tested, cracks, corrosion, broken tabs, leaking…), public condition notes, mileage on the part, and the donor vehicle/VIN (kept private). |
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
