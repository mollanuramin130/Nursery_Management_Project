# Nuvikoz clean delivery banners — version 2

Three minimal studio-style banners replace the earlier dense garden scenes. Built-in ImageGen generated the original PNG artwork; lossless-dimension WebP encoding produces 1536 × 1024 images of about 85–110 KB each. Website headings and working buttons are HTML, so they remain readable and editable. The page uses an ivory/sage background, dark green text and light overlays.

Assets under `apps/nursery-api/public/banners/nuvikoz/`:

- `indoor-v2.webp`: two potted plants and delivery packaging; “Plants, delivered to your door”; `/shop`.
- `flowers-v2.webp`: three clearly separated foliage, flowering and succulent plants; “A plant for every corner”; `/shop?product_type=plant`.
- `grow-v2.webp`: gardening tools, gloves, watering can and shipping box; “Garden tools, delivered”; `/shop?product_type=tool`.

The tools banner is a promotion, not a stock import. Add actual tool products and inventory through Admin for items to appear in that collection. No tool stock, discounts, delivery times or service areas are invented. Images are styled AI-generated marketing artwork, not exact-stock photographs. Old v1 files remain available to avoid breaking existing links.

## Hostinger commands

Run each command from SSH; stop if a command fails:

```bash
cd /home/u317570912/domains/api.nuvikoz.com/source/apps/nursery-api
GIT_SSH_COMMAND='ssh -i /home/u317570912/.ssh/nursery_api_deploy -o IdentitiesOnly=yes' git pull --ff-only
composer dump-autoload --no-dev --optimize --no-scripts
php artisan package:discover --ansi
mkdir -p /home/u317570912/domains/api.nuvikoz.com/public_html/banners/nuvikoz
cp -a public/banners/nuvikoz/. /home/u317570912/domains/api.nuvikoz.com/public_html/banners/nuvikoz/
curl -I --max-time 20 https://api.nuvikoz.com/banners/nuvikoz/indoor-v2.webp
php artisan banners:seed-nuvikoz --refresh-artwork --dry-run
```

The image should return HTTP 200 with image/webp. Preview rolls back all inserts/updates. Expect 3 new banners on a first install, or 3 updated if v1 was already installed (deleted banners stay skipped). Then apply:

```bash
php artisan banners:seed-nuvikoz --refresh-artwork --force
```

`--refresh-artwork` replaces title, image URL and collection link only for identified seeded banners. It retains their ID, visibility, sort order and schedule, and preserves unrelated metadata. Deleted banners are never restored. Other banners are untouched. Without this flag, existing seeded banners are skipped. MySQL preview can consume auto-increment numbers; gaps are harmless.

In hPanel redeploy **Customer Portal nuvikoz.com**, branch `development/v1.0.0`, root `apps/nursery-web`, unless auto-deployment has already successfully deployed this commit. This is required for the lighter design and Tools button. No Admin redeployment or migrations are required. Do not overwrite public_html/index.php. APP_URL should remain https://api.nuvikoz.com.

After import, the home-feed cache is cleared; allow about a minute for frontend caching. Manage banners under Admin → Banners. Set NEXT_PUBLIC_STORE_NAME=NUVIKOZ in the customer deployment if an older environment value overrides the brand name. Lowest sort order is the hero, up to four following banners are collection cards.

## Validation

PHP syntax, schema column names, TSX rendering and link destinations are checked during authoring. PHPUnit tests cover rollback, repeatability, soft-deletes and explicit artwork refresh preserving status/order. PHP runtime is unavailable in the authoring environment, so run the production dry-run before applying. Run `php artisan test --filter=NuvikozBannerSeederTest` only on a test database with dev dependencies, never on the live database.

## ImageGen prompt set

Common: premium clean ecommerce website banner, 1536×1024 landscape, warm ivory or light sage studio background, restrained sage/terracotta palette, soft daylight, realistic commercial product photography, separated objects on the right, generous negative space on the left for website headlines. No dense foliage, clutter, people, embedded text, watermark, discount badges or delivery guarantees.

Delivery: exactly two potted plants, golden pothos and upright snake plant, alongside a neat open kraft shipping box, folded protective paper and simple leaf icon. Clear silhouettes, subtle shadows, elegant studio arrangement.

Plants: rubber plant, compact red flowering hibiscus and aloe vera, coordinated terracotta and cream pots on ivory plinths, clear separated silhouettes with generous spacing.

Tools: wooden handled hand trowel, three-prong cultivator, closed pruning shears, one pair of gloves and sage watering can, neatly laid out with open kraft shipping box and folded packing paper; one tiny potted plant as an accent. Clearly communicates gardening tools delivery.
