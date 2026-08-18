-- Unique category tiles that load on Android (Unsplash only).
-- Wikimedia upload.wikimedia.org is blocked for Dart's default User-Agent,
-- which left Seeds / Medicinal / Vegetable / Outdoor / Soil as green placeholders.
SET NAMES utf8mb4;

-- Top-level (Shop)
UPDATE categories SET image_url='https://images.unsplash.com/photo-1466781783364-36c955e42a7f?w=800', updated_at=NOW() WHERE id=1 AND slug='indoor-plants';
UPDATE categories SET image_url='https://images.unsplash.com/photo-1519378058457-4c29a0a2efac?w=800', updated_at=NOW() WHERE id=2 AND slug='outdoor-plants';
UPDATE categories SET image_url='https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=800', updated_at=NOW() WHERE id=3 AND slug='flowering-plants';
UPDATE categories SET image_url='https://images.unsplash.com/photo-1464965911861-746a04b4bca6?w=800', updated_at=NOW() WHERE id=4 AND slug='fruit-plants';
UPDATE categories SET image_url='https://images.unsplash.com/photo-1592924357228-91a4daadcfea?w=800', updated_at=NOW() WHERE id=5 AND slug='vegetable-plants';
UPDATE categories SET image_url='https://images.unsplash.com/photo-1589923188900-85dae523342b?w=800', updated_at=NOW() WHERE id=6 AND slug='seeds';
UPDATE categories SET image_url='https://images.unsplash.com/photo-1485955900006-10f4d324d411?w=800', updated_at=NOW() WHERE id=7 AND slug='pots-planters';
UPDATE categories SET image_url='https://images.unsplash.com/photo-1492496913980-501348b61469?w=800', updated_at=NOW() WHERE id=8 AND slug='soil-fertilizers';
UPDATE categories SET image_url='https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=800', updated_at=NOW() WHERE id=9 AND slug='gardening-tools';
UPDATE categories SET image_url='https://images.unsplash.com/photo-1719871766569-622dabd367e0?w=800', updated_at=NOW() WHERE id=10 AND slug='plant-care';
UPDATE categories SET image_url='https://images.unsplash.com/photo-1576045057995-568f588f82fb?w=800', updated_at=NOW() WHERE id=14 AND slug='medicinal-plants';
UPDATE categories SET image_url='https://images.unsplash.com/photo-1459411552884-841db9b3cc2a?w=800', updated_at=NOW() WHERE id=15 AND slug='succulents';
UPDATE categories SET image_url='https://images.unsplash.com/photo-1509423350716-97f9360b4e09?w=800', updated_at=NOW() WHERE id=16 AND slug='hanging-plants';

-- Children
UPDATE categories SET image_url='https://images.unsplash.com/photo-1565626929866-e11c64e607cf?w=800', updated_at=NOW() WHERE id=11 AND slug='low-maintenance';
UPDATE categories SET image_url='https://images.unsplash.com/photo-1773431456773-50853cea57cf?w=800', updated_at=NOW() WHERE id=12 AND slug='air-purifying';
UPDATE categories SET image_url='https://images.unsplash.com/photo-1487530811176-3780de880c2d?w=800', updated_at=NOW() WHERE id=13 AND slug='balcony-specials';
UPDATE categories SET image_url='https://images.unsplash.com/photo-1545243424-0ce743321e11?w=800', updated_at=NOW() WHERE id=17 AND slug='office-plants';
UPDATE categories SET image_url='https://images.unsplash.com/photo-1754994641900-f5bfbdb88e63?w=800', updated_at=NOW() WHERE id=18 AND slug='seasonal-flowers';
