# Nx6_PedidosYa

Magento 2 module that exports your catalog and your promotions to **PedidosYa**
(Local Shops / Quick Commerce) as CSV files delivered over **SFTP**.

It implements PedidosYa's file-based bulk-update integration described at
<https://developer.pedidosya.com/es-lat/documentation/introduction> — specifically the
**Double File Format, single vendor** variant: one catalog file and one promotions file per
PedidosYa store, each named `<prefix>_<vendor_id>.csv`.

| | |
|---|---|
| Package | `nx6/pedidosya` |
| Module | `Nx6_PedidosYa` |
| Version | `0.1.0` |
| PHP | `>= 8.3` |
| License | MIT |

---

## Installation

```bash
composer require nx6/pedidosya
bin/magento module:enable Nx6_PedidosYa
bin/magento setup:upgrade
bin/magento setup:di:compile        # production mode only
bin/magento cache:flush
```

The schema patch `DropLegacyStoreIdUniqueIndexes` runs automatically during `setup:upgrade`
and only matters on installs that carried an older, hand-named unique index on `store_id`;
fresh installs are unaffected.

## Quick start

1. **Get your PedidosYa details** — from your Account Manager: the SFTP host, username and
   password. From the Partner Portal: your store's **Vendor ID** (external store ID).
2. **Create a Products Profile** — *PedidosYa › Products Profiles › Add New Profile*:
   * **Store View** = the store to export, **Vendor ID** = the value from step 1,
     **File Prefix** = e.g. `products` (file will be `products_<vendor_id>.csv`).
   * **SFTP Settings** — host / username / password from step 1, **Remote Path** `catalog`.
     Click **Test Connection**.
   * **CSV Field Mapping** — set the *Source* for each column, e.g.
     `sku` → `attribute:sku`, `barcode` → `attribute:barcode` (or your EAN attribute),
     `price` → `attribute:price`, `active` → `attribute:status`,
     `quantity` → the MSI **stock** or **source** feeding this store.
   * **Save**.
3. **Run it once** — the **Run** button on the profile (or **Run Now** in the grid).
   Check the *Last Run Status* on the profile and `var/log/pedidosya.log`.
4. **Schedule it** — add a system cron line per profile (see
   [Scheduling](#scheduling)):

   ```cron
   0 * * * *  cd /var/www/magento && bin/magento pedidosya:export:run products <vendor-id>
   ```

5. **Promotions** (optional) — create a **Promo Profile** the same way, Remote Path
   `promotions`, map `campaign_name` / `reason` / `start_date` / `end_date` /
   `promotion_type` and either map `discounted_price` or set a **Markdown Percentage**.

---

## What it does

* Adds two admin entities — **Products Profiles** and **Promo Profiles**. Each profile
  represents one PedidosYa vendor/store and carries:
  * its target **Store View** and **Vendor ID**,
  * its **own SFTP credentials** (host, port, user, password, remote path, timeout),
  * a **CSV field mapping** (which Magento attribute / stock feeds each PedidosYa column),
  * export options (markup, price cap, SKU filters, …).
* On each run it streams the store's products page by page, builds the CSV according to the
  profile mapping, optionally archives a copy locally, and uploads the file(s) to the
  profile's SFTP server.
* Runs can be triggered from the admin ("Run Now") or from the CLI (for scheduling via cron).

### Dependencies

`magento/module-catalog`, `magento/module-store`, `magento/module-inventory-api`,
`magento/module-inventory-sales-api`, `magento/module-config`, `magento/module-cron`,
and `phpseclib/phpseclib ~3.0` (SFTP transport).

---

## Admin UI

### PedidosYa menu

Top-level **PedidosYa** admin menu:

* **PedidosYa › Products Profiles** — `pedidosya/products_profile`
* **PedidosYa › Promo Profiles** — `pedidosya/promo_profile`

ACL resources: `Nx6_PedidosYa::products_profile`, `Nx6_PedidosYa::promo_profile`,
`Nx6_PedidosYa::config`.

### Store configuration

**Stores › Configuration › Farma › PedidosYa**

| Field | Path | Purpose |
|---|---|---|
| Archive Export Files | `nx6_pedidosya/archive/enabled` | When *Yes*, every generated file is also saved to `var/export/peya/<name>_<timestamp>.tar.gz` in addition to the SFTP upload. Archiving never fails a run. |
| Blacklisted Product Names (Auto-Disable) | `nx6_pedidosya/export/blacklisted_product_names` | One name per line. Any product whose name contains one of these **as a whole word** is exported with `Active = 0` in the **Products** CSV, regardless of its Magento status or stock. Intended for products that must never be sold on the marketplace (e.g. controlled medication). |

---

## Profile fields

### General (both profile types)

| Field | Notes |
|---|---|
| Profile Name | Internal label only, not sent to PedidosYa. |
| Active | Inactive profiles are skipped by the CLI runner. |
| Store View | Source store for product data and scope for attribute values. |
| Vendor ID | PedidosYa **external store ID** from the Partner Portal. Used in the file name. |
| File Prefix | File name is `<prefix>_<vendor_id>.csv`. Defaults: `products` / `promo`. |

### SFTP Settings (per profile)

Host, Port (default `22`), Username, Password (stored encrypted), Remote Path
(default `catalog` / `promotions`), Connection Timeout (default `10` s).

* Credentials are **per profile** — nothing is shared between profiles or with core config.
* The password is never sent back to the browser; leaving it blank on save keeps the stored one.
* **Test Connection** button: connects, authenticates, and creates the remote directory
  (recursively) if it does not exist yet — without uploading anything.
* On a real run the remote directory is likewise auto-created if missing.

PedidosYa's own SFTP endpoint (from their docs) is
`vendor-automation-sftp-live-us.prod.aws.qcommerce.live:22`; request the username/password
from your PedidosYa Account Manager. Catalog files go under the *Catalog* directory,
promotion files under the *Promotion* directory.

### CSV Field Mapping

One row per CSV column. Each column has:

* **Source** — where the value comes from:
  * `-- Not Mapped --` — always use the Default,
  * `attribute:<code>` — a product attribute (media/gallery attributes excluded),
  * for the **Quantity** column only, additionally `stock:<id>` (an MSI stock, incl. Default
    Stock — resolved as *salable* quantity) or `source:<code>` (a single MSI source location).
* **Default** — literal fallback used whenever the source resolves to empty/null. For
  `start_date` / `end_date` it is a datepicker; for `promotion_type` / `promotion_sub_type`
  it is a dropdown of PedidosYa's allowed values.

---

## Column sets

### Products Profile → catalog CSV

Column order: `sku, barcode, price, active, quantity, vendors, exclude`

| Column | PedidosYa meaning | Module post-processing |
|---|---|---|
| `sku` | Product identifier | — |
| `barcode` | Product barcode | Numeric codes shorter than 14 digits are left-padded with zeros to GTIN-14 width. |
| `price` | Selling price (> 0) | **Markup %** applied if configured. |
| `active` | `1`/`0` availability | Normalised to strictly `1`/`0`. Forced to `0` when: exported price > **Max Price** (if enabled), salable quantity ≤ 0, or the product name is blacklisted. |
| `quantity` | Stock, compared against PedidosYa's sales buffer to auto (de)activate | Negative values floored to `0`. |
| `vendors` | Target stores (`all` or a list) | Passed through from mapping. |
| `exclude` | Stores to exclude when `vendors = all` | Passed through from mapping. |

**Products export options**

| Option | Effect |
|---|---|
| Only Enabled | Restrict the export to enabled products. |
| Markup Percentage | `10` turns a `100.00` price into `110.00` (e.g. to cover a marketplace fee). Non-numeric resolved values are left alone. |
| SKUs to Exclude | One per line / comma-separated. Always omitted from the file. |
| Flag Above Max Price as Inactive + Max Price | Overpriced products stay in the file but with `Active = 0`, so PedidosYa still knows they exist. |

The catalog file is **not** row-split (PedidosYa allows up to 1.5M rows per catalog file in
the double-file format).

### Promo Profile → promotions CSV

Column order: `barcode, sku, campaign_name, reason, start_date, end_date, promotion_type,
promotion_sub_type, discounted_price, max_no_of_orders, discount_usage_limit,
bundle_details, bundle_discount, campaign_status`

| Column | PedidosYa meaning |
|---|---|
| `barcode` / `sku` | Product identifier (at least one). |
| `campaign_name` | Campaign identifier (required by PedidosYa). |
| `reason` | Campaign reason (required by PedidosYa). |
| `start_date` / `end_date` | `YYYY-MM-DD HH:MM:SS` (or `YYYY-MM-DD`). The datepicker default is converted server-side to `Y-m-d H:i:s`. |
| `promotion_type` | `strikethrough` or `same_item_bundle`. |
| `promotion_sub_type` | `free_item`, `absolute_value_off` (per the shipped sample; `percentage_value_off` also exists in PedidosYa docs). Blank for plain strikethrough. |
| `discounted_price` | Promo price for strikethrough. |
| `max_no_of_orders` | Order cap for the campaign. |
| `discount_usage_limit` | Times the promo applies within a single order. |
| `bundle_details` | Buy/get spec for same-item bundles, e.g. `B2G1`. |
| `bundle_discount` | Discount value for `absolute_value_off` / `percentage_value_off` bundles. |
| `campaign_status` | `1` active / `0` inactive. |

**Promo export options**

| Option | Effect |
|---|---|
| Use All Enabled Products | Every enabled product joins the promo. |
| SKUs to Include | Otherwise, only these SKUs. If this is empty and "Use All Enabled" is off, nothing is exported. |
| Markdown Percentage | Computes `discounted_price` straight from the product's base price, bypassing the `discounted_price` mapping. `10` turns `100.00` into `90.00`. |

**Row limit / batching:** PedidosYa rejects a promotions file over 20,000 rows including the
header, so the module caps each file at 19,999 data rows and splits into
`<prefix>1_<vendor_id>.csv`, `<prefix>2_<vendor_id>.csv`, … when needed. Promo rows whose
`discounted_price` resolves empty are skipped.

---

## Running an export

Both paths use the same code (`Model\Export\ExportRunner`): generate → archive (if enabled)
→ SFTP upload → record `last_run_at` / `last_run_status` on the profile.

### From the admin

* **Products/Promo Profiles grid** → row action **Run Now** (confirm dialog, POST).
* **Profile edit page** → **Run** button (next to Save).

### From the CLI

```bash
bin/magento pedidosya:export:run <type> <vendor-id>
```

* `<type>` — `products` or `promo`
* `<vendor-id>` — the Vendor ID of an **active** profile of that type

One invocation runs exactly one profile. Examples:

```bash
bin/magento pedidosya:export:run products 290056
bin/magento pedidosya:export:run promo 290056
```

### Scheduling

The module ships no `crontab.xml`; schedule the CLI command from the system crontab, one
line per profile, so each store syncs on its own cadence. Example — catalog hourly, promos
every 6 hours:

```cron
0   * * * *  cd /var/www/magento && bin/magento pedidosya:export:run products 290056 >> var/log/pedidosya_cron.log 2>&1
0 */6 * * *  cd /var/www/magento && bin/magento pedidosya:export:run promo    290056 >> var/log/pedidosya_cron.log 2>&1
```

### Where things land

* Working files: `var/pedidosya/export/`
* Local archives (if enabled): `var/export/peya/`
* Log: `var/log/pedidosya.log`
* Remote: `<sftp_remote_path>/<prefix>_<vendor_id>.csv` on the profile's SFTP server

---

## Usage scenarios

Mapped to the use cases in PedidosYa's SFTP docs
([catalog](https://developer.pedidosya.com/es-lat/documentation/catalog-sftp-use-cases),
[promotions](https://developer.pedidosya.com/es-lat/documentation/promotions-sftp-use-cases)).

### Catalog

**1. Keep price and stock in sync (the everyday case)**
Products Profile → map `price` to `attribute:price` (or your sale-price attribute), `quantity`
to the MSI stock/source that feeds this store, `active` to `attribute:status`. Schedule
`pedidosya:export:run products <vendor>` hourly. PedidosYa auto-activates a product when
`quantity` exceeds its sales buffer and deactivates it when it drops to/below it.

**2. Auto-hide out-of-stock products**
No configuration needed — any row whose salable quantity is ≤ 0 is exported with
`Active = 0` even if the mapped Active source says otherwise.

**3. Price-only or stock-only pushes**
PedidosYa treats an omitted column as "unchanged". Map only the columns you want to move and
leave the rest `-- Not Mapped --` with no default.

**4. Products that must never be sold on the marketplace**
Add their names to **Blacklisted Product Names** in store config (whole-word match), or list
specific SKUs under **SKUs to Exclude** to drop them from the file entirely.

**5. Marketplace-fee markup**
Set **Markup Percentage** so the exported `price` is higher than the in-store price.

**6. Don't sell above a price ceiling**
Enable **Flag Above Max Price as Inactive** and set **Max Price**; overpriced items are still
listed but as `Active = 0`.

**7. One Magento install, several PedidosYa stores**
One Products Profile per Vendor ID, each with its own Store View, SFTP credentials and
mapping. Use the `vendors` / `exclude` columns if the target account uses multi-vendor files.

### Promotions

**8. Strikethrough discount campaign**
Promo Profile → `promotion_type` default `strikethrough`, `campaign_name` / `reason`
defaults, `start_date` / `end_date` defaults. Provide the promo price either by mapping
`discounted_price` to a sale-price attribute or by setting **Markdown Percentage**. List the
promoted SKUs under **SKUs to Include**.

**9. Store-wide sale**
Same as above but tick **Use All Enabled Products** and use **Markdown Percentage** so every
product gets a computed `discounted_price`.

**10. "Buy 2, get 1 free" / same-item bundle**
`promotion_type` = `same_item_bundle`, `promotion_sub_type` = `free_item`,
`bundle_details` default `B2G1`. For a value-off bundle use `absolute_value_off` and map/set
`bundle_discount`.

**11. Schedule a campaign ahead of time**
Set `start_date` / `end_date` in the future and `campaign_status` = `1`; upload now, PedidosYa
activates it on the start date. Push `campaign_status` = `0` (or let the campaign expire) to
end it.

**12. Large promotion catalogs**
No action required — files over 19,999 data rows are automatically split into
`<prefix>1_<vendor>.csv`, `<prefix>2_<vendor>.csv`, … each within PedidosYa's 20,000-row limit.

---

## Extension points

`Model\Export\Generator` exposes two `public`, non-`final` hook methods for client-specific
modules to plug onto via a plugin:

* `buildRow()` — adjust a resolved row before it is written (e.g. a client-specific fallback
  when a mapped column comes back empty).
* `expandRows()` — fan one built row out into several CSV rows (e.g. one row per barcode when
  the Barcode column maps to a multi-value attribute). Row-count and batch-split limits are
  applied per returned row.

---

## Notes & limitations

* `promotion_sub_type` values are taken from the shipped sample file and are **not**
  independently verified against PedidosYa's authoritative spec. If a real export is rejected
  on that column, confirm the allowed values with PedidosYa integration support.
* `last_run_status` is a `varchar(255)`; a very long multi-file summary is truncated with `…`
  rather than failing the save.
* The SFTP client opens one connection per upload (each profile may target a different
  server) and authenticates with username + password only (no key auth).
