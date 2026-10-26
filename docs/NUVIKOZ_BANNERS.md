# Nuvikoz home banners

Three original AI-generated garden scenes, created with the built-in ImageGen tool, converted without cropping to 1536 × 1024 WebP (about 190–280 KB each). Text and working links are rendered by the website, not embedded in the image, so headings stay readable on mobile and can be edited in Admin.

- `apps/nursery-api/public/banners/nuvikoz/indoor-v1.webp`: indoor foliage, “Bring nature home”, links to `/shop`.
- `apps/nursery-api/public/banners/nuvikoz/flowers-v1.webp`: flowering balcony, “Flowers for brighter balconies”, links to `/category/flowering-plants`.
- `apps/nursery-api/public/banners/nuvikoz/grow-v1.webp`: fruit and aromatic plants, “Grow your own green corner”, links to `/category/outdoor-plants`.

These are styled gardening inspiration, not photographs of the exact stock supplied. No prices, discounts, delivery promises, reviews or guarantees are embedded. Existing products and inventory are unchanged.

## Deploy on this Hostinger installation

Run from the API app:

```bash
cd /home/u317570912/domains/api.nuvikoz.com/source/apps/nursery-api
GIT_SSH_COMMAND='ssh -i /home/u317570912/.ssh/nursery_api_deploy -o IdentitiesOnly=yes' git pull --ff-only
composer dump-autoload --no-dev --optimize --no-scripts
php artisan package:discover --ansi
mkdir -p /home/u317570912/domains/api.nuvikoz.com/public_html/banners/nuvikoz
cp -a public/banners/nuvikoz/. /home/u317570912/domains/api.nuvikoz.com/public_html/banners/nuvikoz/
curl -I --max-time 20 https://api.nuvikoz.com/banners/nuvikoz/indoor-v1.webp
php artisan banners:seed-nuvikoz --dry-run
```

The image should return HTTP 200 with image/webp. Preview should report 3 new banners and roll back. Once successful:

```bash
php artisan banners:seed-nuvikoz --force
curl -sS --max-time 20 'https://api.nuvikoz.com/api/v1/banners?placement=home'
```

Expected: `IMPORTED: 3 new banners; 0 existing banners skipped.` Reruns skip the three records, retaining Admin changes and soft deletes. The command serialises imports with a cache lock. It requires the active categories created by the catalog seeder. Preview uses a rolled-back transaction; MySQL auto-increment numbers may have gaps.

**Redeploy the Customer Portal (`nuvikoz.com`) in hPanel**, using branch `development/v1.0.0`, root `apps/nursery-web`. This is required for the new collection cards and the Next.js image allowlist. Auto-deployment may already have built the commit: confirm the latest deployment completed. No Admin portal redeployment is needed. The existing `npm run build` command uses Webpack.

The first active home banner by sort order is the hero, and up to four additional banners appear as collection cards below. New banners use sort orders -300, -200 and -100; existing banners are preserved. Change title, link, sort order or status in Admin → Banners. If an existing banner sorts before these, it remains the hero until you reorder it. Home-feed cache is invalidated on import; allow around a minute for the customer portal cache to refresh.

The home brand fallback is NUVIKOZ. If an older NEXT_PUBLIC_STORE_NAME environment variable overrides it, set that variable to NUVIKOZ in the customer deployment and rebuild.

Only the new banner asset directory is copied to public_html. Never copy the entire Laravel public directory over the customised index.php. No migrations or catalog reseeding are required.

## Validation

PHP syntax and inserted columns are checked against the repository migration. PHPUnit coverage includes preview rollback, repeatability and preserving Admin-edited/deleted banners. Run `php artisan test --filter=NuvikozBannerSeederTest` only against the development/test database; database-refresh tests must not target production. PHP is unavailable in the authoring environment, so runtime database validation is performed with the Hostinger preview command.

## Artwork prompts

Common direction: ads-marketing; premium photographic banner background for NUVIKOZ Indian plant nursery; 1536×1024 landscape; botanical editorial photography; emerald green and warm cream; natural textures and directional light. No lettering, logos, watermarks, buttons or collage panels. Calm dark green left 45% for website text; plants concentrated to the right with foliage through the centre for mobile cropping. No people, discount or certification badges.

Indoor: sculptural snake plant, trailing golden pothos on warm wood, glossy rubber plant and feathery areca palm, terracotta and cream ceramic pots, warm plaster wall, morning side light, inviting home corner.

Flowers: sunny Indian balcony with red hibiscus, white Arabian jasmine and pink bougainvillea, terracotta pots, subtle curved architectural opening, dappled warm light, natural saturated colour.

Grow: sunlit Indian kitchen garden, young guava and lemon plants, tulsi and curry leaf in earthen pots, tactile soil, small watering can at the right, layered foliage and garden stone. Realistic container plants, not enormous trees.
