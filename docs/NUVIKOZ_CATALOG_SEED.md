# Nuvikoz starter catalog

Adds 30 young nursery plants, 7 categories, plant-care profiles, original labelled SVG illustrations and zero-quantity inventory records. Prices are provisional INR list prices (₹99–₹599), intended for owner review. No crossed-out discount prices, fake reviews, customer accounts, orders or sales counts are created. Nursery recommendations are explicitly editorial text in each description.

The seeder is deliberately separate from DatabaseSeeder and the old sample SQL. **Do not import nursery_sample_data.sql into production**: it includes demonstration users and transactional data.

## Hostinger: update and preview

Take a current database backup in hPanel before a production import. Run commands individually from SSH (copy commands only, not terminal prompts):

```bash
cd /home/u317570912/domains/api.nuvikoz.com/source/apps/nursery-api
GIT_SSH_COMMAND='ssh -i /home/u317570912/.ssh/nursery_api_deploy -o IdentitiesOnly=yes' git pull --ff-only
composer dump-autoload --no-dev --optimize
php artisan catalog:seed-nuvikoz --dry-run
```

Preview runs the actual inserts in a transaction and rolls them back. It may consume auto-increment numbers on MySQL; gaps are harmless. Expect 30 new plants on the first preview. A SKU/slug collision or inactive/deleted matching category aborts the whole import; resolve that conflict before continuing. Do not run simultaneous import processes. The dedicated command uses a cache lock on this deployment.

## Publish illustrations and import

This Hostinger installation serves public_html separately from Laravel's source/public. Copy only the catalog assets; **do not overwrite the customised public_html/index.php**:

```bash
mkdir -p /home/u317570912/domains/api.nuvikoz.com/public_html/catalog/starter
cp -a public/catalog/starter/. /home/u317570912/domains/api.nuvikoz.com/public_html/catalog/starter/
curl -I --max-time 20 https://api.nuvikoz.com/catalog/starter/golden-money-plant.svg
php artisan catalog:seed-nuvikoz --force
curl -sS --max-time 20 'https://api.nuvikoz.com/api/v1/products?per_page=3'
```

The image request should return 200 and an SVG content type. If it does not, correct the public directory before importing. APP_URL must be https://api.nuvikoz.com; it supplies absolute image URLs for API consumers. Existing Next.js SafeImage can load .svg images without optimisation; no unsafe SVG optimiser setting is needed. Mobile clients must support SVG or replace these temporary illustrations with actual product JPEG/WebP photographs in Admin.

On hosts where source/public is the real document root, no asset copy is needed. Standard seeder invocation is also available: `php artisan db:seed --class=NuvikozCatalogSeeder --force`. Prefer the dedicated command for its preview and lock.

## Activate actual stock in Admin

Products are published, but start **out_of_stock** with quantity zero. This fills shop/category listings, featured picks and new arrivals without accepting orders for unverified inventory. Indoor/outdoor/easy-care home rails that filter out-of-stock products fill after stock activation.

For each plant you actually carry:

1. Check the selling price, scientific name, size, pot option and description in Products. Replace the illustration with your own photograph, and specify the actual supplied size/variety.
2. Add the actual quantity through Inventory/stock adjustment for the selected warehouse. The importer uses the existing active default warehouse where possible, otherwise creates NUVIKOZ-MAIN without inventing an address.
3. Check the product stock status is in_stock (or low_stock where appropriate), and status is active. The customer page should show available quantity and permit add-to-cart.
4. For plants you do not carry, retain out_of_stock or unpublish them. Review all prices before enabling checkout.

Reruns skip an existing product with the same SKU, even if you changed its slug, price, description, stock or deleted it. Categories/warehouses already present are retained. There is no overwrite or delete mode. To change an imported product, use Admin, not another seed run. If both SKU and slug were changed, the importer cannot recognise that item; retain the NVK-PL-xxx SKU for repeatability.

Only the home-feed cache is invalidated after successful import. Allow roughly a minute for frontend/API cached pages to refresh. No database reset, fresh migration or broad cache deletion is required.

## Dataset basis and limitations

Prices are suggested starter prices, not supplier quotations or verified margins. Ugaoo's public catalog was checked on 2026-10-25 as a broad Indian retail reference (e.g. small money plants and snake plants in the ₹249–₹299 bracket): https://www.ugaoo.com/ . Differences in plant size, pot, transport and locality can be substantial. No retailer descriptions, photographs or review data were copied.

General care references:

- NC State Extension, pothos: https://plants.ces.ncsu.edu/plants/epipremnum-aureum/
- RHS houseplant growing guide: https://www.rhs.org.uk/plants/types/houseplants/growing-guide
- RHS succulent growing guide: https://www.rhs.org.uk/plants/types/cacti-succulents/houseplants/growing-guide

Care text is brief editorial starting guidance, not a guarantee or a fixed watering schedule. UK seasonal calendars were not copied into Indian listings. Flower colours, cultivar, graft status, plant sex, exact size and fruiting age are unspecified and must be confirmed for the actual stock. Pet safety is unknown by default; do not interpret this as safe. No medicinal, air-purification or guaranteed yield claims are made. Original illustrations are stylised placeholders, visibly labelled and not botanical identification photographs.

## Verification

Dataset checks cover 30 unique SKUs/slugs, category references, supported care enums, positive INR prices and complete script-free SVG assets. PHPUnit tests in tests/Feature/NuvikozCatalogSeederTest.php cover rollback preview, repeat import preserving Admin edits, soft deletes and collision rollback. Run on a development/test database with dependencies installed:

```bash
php artisan test --filter=NuvikozCatalogSeederTest
```

Never run database-refresh tests against production. Review the dry-run output on Hostinger before the live import.
