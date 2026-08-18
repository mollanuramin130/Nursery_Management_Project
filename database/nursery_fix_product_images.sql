-- Replace 404 Unsplash seed photos with live Unsplash + Wikimedia Commons images.
-- Verified 200 at apply-time for Unsplash IDs and Commons FilePath names used below.

SET NAMES utf8mb4;

-- Money plant / devil's ivy
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1773431456773-50853cea57cf?w=1000', `alt` = 'Money plant (Epipremnum aureum)', `updated_at` = NOW() WHERE `id` = 1;
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1509423350716-97f9360b4e09?w=1000', `updated_at` = NOW() WHERE `id` = 2;

-- Snake plant (old Unsplash 159348... 404s)
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1565626929866-e11c64e607cf?w=1000', `alt` = 'Snake plant (Dracaena trifasciata)', `updated_at` = NOW() WHERE `id` = 3;
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1744255788331-98dfc8be9401?w=1000', `updated_at` = NOW() WHERE `id` = 52;

-- Peace lily (old Unsplash 159369... 404s)
UPDATE `product_images` SET `url` = 'https://commons.wikimedia.org/wiki/Special:FilePath/Spathiphyllum_cochlearispathum_RTBG.jpg?width=1000', `alt` = 'Peace lily (Spathiphyllum)', `updated_at` = NOW() WHERE `id` = 4;
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1466781783364-36c955e42a7f?w=1000', `updated_at` = NOW() WHERE `id` = 53;

UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1466781783364-36c955e42a7f?w=1000', `alt` = 'Areca / indoor palm', `updated_at` = NOW() WHERE `id` = 5;
UPDATE `product_images` SET `url` = 'https://commons.wikimedia.org/wiki/Special:FilePath/Crassula_ovata_700.jpg?width=1000', `alt` = 'Jade plant (Crassula ovata)', `updated_at` = NOW() WHERE `id` = 6;
UPDATE `product_images` SET `url` = 'https://commons.wikimedia.org/wiki/Special:FilePath/Rosa_chinensis.jpg?width=1000', `alt` = 'China rose', `updated_at` = NOW() WHERE `id` = 7;
UPDATE `product_images` SET `url` = 'https://commons.wikimedia.org/wiki/Special:FilePath/Tulsi_or_Tulasi_Holy_basil.jpg?width=1000', `alt` = 'Tulsi (holy basil)', `updated_at` = NOW() WHERE `id` = 8;
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1592419044706-39796d40f98c?w=1000', `alt` = 'Tomato plant', `updated_at` = NOW() WHERE `id` = 9;
UPDATE `product_images` SET `url` = 'https://commons.wikimedia.org/wiki/Special:FilePath/Mangoes_(Magnifera_indica)_from_India.jpg?width=1000', `alt` = 'Mango', `updated_at` = NOW() WHERE `id` = 10;
UPDATE `product_images` SET `url` = 'https://commons.wikimedia.org/wiki/Special:FilePath/Tagetes_x_erecta1.jpg?width=1000', `alt` = 'Marigold', `updated_at` = NOW() WHERE `id` = 11;

UPDATE `product_images` SET `url` = 'https://commons.wikimedia.org/wiki/Special:FilePath/Zamioculcas_zamiifolia_1.jpg?width=1000', `alt` = 'ZZ plant', `updated_at` = NOW() WHERE `id` = 21;
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1459411552884-841db9b3cc2a?w=1000', `alt` = 'Aloe vera', `updated_at` = NOW() WHERE `id` = 22;
UPDATE `product_images` SET `url` = 'https://commons.wikimedia.org/wiki/Special:FilePath/Chlorophytum_comosum.jpg?width=1000', `alt` = 'Spider plant', `updated_at` = NOW() WHERE `id` = 23;
UPDATE `product_images` SET `url` = 'https://commons.wikimedia.org/wiki/Special:FilePath/Ficus_elastica_leaves_02.JPG?width=1000', `alt` = 'Rubber plant', `updated_at` = NOW() WHERE `id` = 24;
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1000', `alt` = 'Boston fern', `updated_at` = NOW() WHERE `id` = 25;
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=1000', `alt` = 'Lavender', `updated_at` = NOW() WHERE `id` = 26;
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1000', `alt` = 'Mint', `updated_at` = NOW() WHERE `id` = 27;
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1000', `alt` = 'Lemongrass', `updated_at` = NOW() WHERE `id` = 28;
UPDATE `product_images` SET `url` = 'https://commons.wikimedia.org/wiki/Special:FilePath/Hibiscus_Brilliant.jpg?width=1000', `alt` = 'Red hibiscus', `updated_at` = NOW() WHERE `id` = 29;
UPDATE `product_images` SET `url` = 'https://commons.wikimedia.org/wiki/Special:FilePath/Bougainvillea_closeup.jpg?width=1000', `alt` = 'Bougainvillea', `updated_at` = NOW() WHERE `id` = 30;
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1463320726281-696a485928c7?w=1000', `alt` = 'Echeveria succulent', `updated_at` = NOW() WHERE `id` = 31;
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1773431456773-50853cea57cf?w=1000', `alt` = 'Golden pothos', `updated_at` = NOW() WHERE `id` = 32;
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1526318472351-c75fcf070305?w=1000', `alt` = 'Citrus / lemon', `updated_at` = NOW() WHERE `id` = 33;
UPDATE `product_images` SET `url` = 'https://commons.wikimedia.org/wiki/Special:FilePath/Psidium_guajava_fruit.jpg?width=1000', `alt` = 'Guava', `updated_at` = NOW() WHERE `id` = 34;
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1000', `alt` = 'Coriander', `updated_at` = NOW() WHERE `id` = 35;
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1592419044706-39796d40f98c?w=1000', `alt` = 'Chili peppers', `updated_at` = NOW() WHERE `id` = 36;
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=1000', `alt` = 'Chrysanthemum', `updated_at` = NOW() WHERE `id` = 37;
UPDATE `product_images` SET `url` = 'https://commons.wikimedia.org/wiki/Special:FilePath/Colpfl05.jpg?width=1000', `alt` = 'Croton', `updated_at` = NOW() WHERE `id` = 38;

UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1755442975535-0d6e856cfeb8?w=1000', `alt` = 'Monstera deliciosa', `updated_at` = NOW() WHERE `id` = 54;
UPDATE `product_images` SET `url` = 'https://commons.wikimedia.org/wiki/Special:FilePath/Syngonium_podophyllum.jpg?width=1000', `alt` = 'Arrowhead plant', `updated_at` = NOW() WHERE `id` = 55;
UPDATE `product_images` SET `url` = 'https://commons.wikimedia.org/wiki/Special:FilePath/Aglaonema_commutatum2.jpg?width=1000', `alt` = 'Chinese evergreen', `updated_at` = NOW() WHERE `id` = 56;
UPDATE `product_images` SET `url` = 'https://commons.wikimedia.org/wiki/Special:FilePath/Ficus_lyrata.jpg?width=1000', `alt` = 'Fiddle leaf fig', `updated_at` = NOW() WHERE `id` = 57;
UPDATE `product_images` SET `url` = 'https://commons.wikimedia.org/wiki/Special:FilePath/Dracaena_sanderiana_2.jpg?width=1000', `alt` = 'Lucky bamboo', `updated_at` = NOW() WHERE `id` = 58;
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1463320726281-696a485928c7?w=1000', `alt` = 'Desert rose / succulent', `updated_at` = NOW() WHERE `id` = 59;
UPDATE `product_images` SET `url` = 'https://commons.wikimedia.org/wiki/Special:FilePath/Jasminum_sambac.jpg?width=1000', `alt` = 'Mogra (Jasminum sambac)', `updated_at` = NOW() WHERE `id` = 60;
UPDATE `product_images` SET `url` = 'https://commons.wikimedia.org/wiki/Special:FilePath/Ixora_coccinea.jpg?width=1000', `alt` = 'Ixora', `updated_at` = NOW() WHERE `id` = 61;
UPDATE `product_images` SET `url` = 'https://commons.wikimedia.org/wiki/Special:FilePath/Curry_Trees.jpg?width=1000', `alt` = 'Curry leaf tree', `updated_at` = NOW() WHERE `id` = 62;
UPDATE `product_images` SET `url` = 'https://commons.wikimedia.org/wiki/Special:FilePath/Neem_Tree_in_Rajasthan,_India.jpg?width=1000', `alt` = 'Neem tree', `updated_at` = NOW() WHERE `id` = 63;
UPDATE `product_images` SET `url` = 'https://upload.wikimedia.org/wikipedia/commons/2/2e/DrumstickFlower.jpg', `alt` = 'Drumstick / moringa', `updated_at` = NOW() WHERE `id` = 64;
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1464965911861-746a04b4bca6?w=1000', `alt` = 'Pomegranate', `updated_at` = NOW() WHERE `id` = 65;
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1526318472351-c75fcf070305?w=1000', `alt` = 'Papaya', `updated_at` = NOW() WHERE `id` = 66;
UPDATE `product_images` SET `url` = 'https://commons.wikimedia.org/wiki/Special:FilePath/PortulacaGrandiflora.jpg?width=1000', `alt` = 'Moss rose', `updated_at` = NOW() WHERE `id` = 67;
UPDATE `product_images` SET `url` = 'https://commons.wikimedia.org/wiki/Special:FilePath/Coleus_scutellarioides.jpg?width=1000', `alt` = 'Coleus', `updated_at` = NOW() WHERE `id` = 68;
UPDATE `product_images` SET `url` = 'https://upload.wikimedia.org/wikipedia/commons/a/a8/Anthurium_andraeanum.jpg', `alt` = 'Anthurium flamingo flower', `updated_at` = NOW() WHERE `id` = 69;
UPDATE `product_images` SET `url` = 'https://commons.wikimedia.org/wiki/Special:FilePath/Kalanchoe_blossfeldiana.jpg?width=1000', `alt` = 'Kalanchoe', `updated_at` = NOW() WHERE `id` = 70;
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1773431456773-50853cea57cf?w=1000', `alt` = 'Heartleaf philodendron / trailing aroid', `updated_at` = NOW() WHERE `id` = 71;

-- Dead category / campaign / banner Unsplash IDs.
-- Do not map category 404s onto the trowel photo (it is gardening-tools only).
UPDATE `categories` SET `image_url` = 'https://images.unsplash.com/photo-1519378058457-4c29a0a2efac?w=800', `updated_at` = NOW()
 WHERE `image_url` LIKE '%1466692476866%';
UPDATE `categories` SET `image_url` = 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=800', `updated_at` = NOW()
 WHERE `image_url` LIKE '%1463936575829%' AND `slug` <> 'gardening-tools';
UPDATE `banners` SET `image_url` = 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=1400', `updated_at` = NOW()
 WHERE `image_url` LIKE '%1463936575829%';
UPDATE `campaigns` SET `image_url` = 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=1200', `updated_at` = NOW()
 WHERE `image_url` LIKE '%1463936575829%';

-- Historical order thumbs that still point at 404s
UPDATE `order_items` SET `thumbnail_url` = 'https://images.unsplash.com/photo-1459411552884-841db9b3cc2a?w=400'
 WHERE `thumbnail_url` LIKE '%1466692476866%';
UPDATE `order_items` SET `thumbnail_url` = 'https://images.unsplash.com/photo-1565626929866-e11c64e607cf?w=400'
 WHERE `thumbnail_url` LIKE '%1593482892290%';
UPDATE `order_items` SET `thumbnail_url` = 'https://images.unsplash.com/photo-1466781783364-36c955e42a7f?w=400'
 WHERE `thumbnail_url` LIKE '%1593691509543%';
UPDATE `order_items` SET `thumbnail_url` = 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=400'
 WHERE `thumbnail_url` LIKE '%1463936575829%';
