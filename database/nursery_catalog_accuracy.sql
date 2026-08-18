-- =============================================================================
-- Catalog accuracy patch (local / staging)
-- Sources: Wikipedia / Kew / Plants of the World Online facts, paraphrased.
-- Safe: UPDATEs existing plant rows; INSERT IGNORE for new SKUs.
-- Does NOT truncate orders, users, or payments.
-- =============================================================================
-- mysql -u root --socket=/tmp/mysql.sock nursery_local < database/nursery_catalog_accuracy.sql
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+05:30';

-- -----------------------------------------------------------------------------
-- 1) Correct incomplete / inaccurate botanical data on existing plants
-- -----------------------------------------------------------------------------

-- Money plant / devil's ivy (Epipremnum aureum). Native to Mo'orea, French Polynesia.
UPDATE `products`
SET `name` = 'Money Plant (Devil''s Ivy)',
    `description` = 'Epipremnum aureum, sold across India as money plant. A climbing aroid native to Mo''orea (French Polynesia). Fast trailing indoor vine for bright indirect light. Contains calcium oxalate — keep away from pets and children who chew leaves.',
    `updated_at` = NOW()
WHERE `id` = 101;

UPDATE `plant_profiles` SET
  `common_name` = 'Money Plant',
  `scientific_name` = 'Epipremnum aureum',
  `local_names` = JSON_OBJECT('hi','मनी प्लांट','mr','मनी प्लांट','ta','மானி பிளாண்ட்'),
  `plant_kind` = 'climber',
  `indoor_outdoor` = 'indoor',
  `sunlight` = 'bright_indirect',
  `water_requirement` = 'medium',
  `soil_type` = 'well_draining',
  `temperature_min_c` = 18.00, `temperature_max_c` = 30.00,
  `humidity_requirement` = 'medium', `growth_rate` = 'fast',
  `mature_height_cm` = 200, `mature_width_cm` = 60,
  `lifespan` = 'perennial', `difficulty_level` = 'easy', `care_level` = 'low',
  `propagation_method` = 'stem cutting',
  `toxicity_info` = 'All parts contain insoluble calcium oxalate crystals. Chewing causes mouth and throat irritation.',
  `pet_safety` = 'toxic',
  `benefits` = JSON_ARRAY('Easy propagation','Fast trailing foliage','Beginner indoor vine'),
  `uses` = JSON_ARRAY('ornamental'),
  `growing_instructions` = 'Bright indirect light. Harsh afternoon sun scorches leaves. Water when the top few centimetres of mix are dry.',
  `planting_instructions` = 'Well-draining indoor mix in a pot with drainage. A moss stick helps it climb.',
  `pruning_instructions` = 'Pinch long vines to keep a bushier shape. Stem cuttings root readily in water or mix.',
  `fertilization_instructions` = 'Mild houseplant feed once a month in spring and summer. Skip in winter.',
  `pest_disease_info` = 'Mealybugs and spider mites. Wipe leaves. Root rot follows constant wet soil.',
  `updated_at` = NOW()
WHERE `product_id` = 101;

-- Snake plant: accepted name Dracaena trifasciata (syn. Sansevieria trifasciata, 2017).
UPDATE `products`
SET `description` = 'Dracaena trifasciata (formerly Sansevieria trifasciata). Native to tropical West Africa. Sword-shaped, drought-tolerant houseplant that stores water in thick leaves. Survives low light; the usual killer is overwatering.',
    `updated_at` = NOW()
WHERE `id` = 102;

UPDATE `plant_profiles` SET
  `scientific_name` = 'Dracaena trifasciata',
  `local_names` = JSON_OBJECT('hi','सांप का पौधा','en_synonym','Sansevieria trifasciata'),
  `plant_kind` = 'foliage',
  `toxicity_info` = 'Mildly toxic if ingested (saponins). Keep away from pets that chew plants.',
  `pet_safety` = 'toxic',
  `benefits` = JSON_ARRAY('Very drought tolerant','Tolerates low indoor light'),
  `growing_instructions` = 'Low to bright indirect light. Water sparingly. Let the mix dry thoroughly between waterings.',
  `planting_instructions` = 'Gritty, well-draining mix. Do not use a pot much larger than the root mass.',
  `pest_disease_info` = 'Rhizome and root rot from wet soil is the main failure. Rarely mealybugs.',
  `updated_at` = NOW()
WHERE `product_id` = 102;

UPDATE `products`
SET `description` = 'Spathiphyllum wallisii. Tropical flowering aroid from the Americas, grown for glossy leaves and white spathes. Likes evenly moist mix and bright shade. All parts are toxic if eaten.',
    `updated_at` = NOW()
WHERE `id` = 103;

UPDATE `plant_profiles` SET
  `scientific_name` = 'Spathiphyllum wallisii',
  `toxicity_info` = 'Contains calcium oxalate. Toxic to cats, dogs and humans if ingested.',
  `pet_safety` = 'toxic',
  `bloom_color` = 'white',
  `flowering_season` = JSON_ARRAY('spring','summer'),
  `growing_instructions` = 'Bright indirect light. Keep mix lightly moist; drooping often means it needs water. Distilled or rested water reduces leaf-tip burn.',
  `updated_at` = NOW()
WHERE `product_id` = 103;

-- Areca / golden cane palm. Madagascar. Dypsis lutescens is accepted (syn. Chrysalidocarpus lutescens).
UPDATE `products`
SET `description` = 'Dypsis lutescens, the golden cane or butterfly palm, native to Madagascar. Feather-like yellow-green fronds. Needs bright indirect light and humidity; brown tips usually mean dry air or fluoride-sensitive water.',
    `updated_at` = NOW()
WHERE `id` = 104;

UPDATE `plant_profiles` SET
  `scientific_name` = 'Dypsis lutescens',
  `local_names` = JSON_OBJECT('hi','अरेका पाम','en_synonym','Chrysalidocarpus lutescens'),
  `plant_kind` = 'palm',
  `pet_safety` = 'safe',
  `toxicity_info` = 'Generally regarded as non-toxic to cats and dogs. Frond tips may brown in dry rooms.',
  `updated_at` = NOW()
WHERE `product_id` = 104;

-- Jade: Crassula ovata, native to South Africa / Mozambique.
UPDATE `products`
SET `description` = 'Crassula ovata (jade plant, friendship tree). A succulent shrub from southern Africa. Thick oval leaves store water. Needs several hours of bright light; overwatering causes stem rot.',
    `updated_at` = NOW()
WHERE `id` = 105;

UPDATE `plant_profiles` SET
  `scientific_name` = 'Crassula ovata',
  `sunlight` = 'full_sun',
  `water_requirement` = 'low',
  `soil_type` = 'sandy',
  `toxicity_info` = 'Mildly toxic to dogs and cats if ingested.',
  `pet_safety` = 'toxic',
  `updated_at` = NOW()
WHERE `product_id` = 105;

-- Garden rose: Rosa indica is not a current accepted garden name. China / Bengal rose is Rosa chinensis.
UPDATE `products`
SET `name` = 'Red China Rose',
    `description` = 'Rosa chinensis (China rose / Bengal rose), the parent of many garden roses grown in India. Repeat-flowering shrub for full sun and airy sites. Thorny stems; watch for aphids and black spot in humid weather.',
    `updated_at` = NOW()
WHERE `id` = 106;

UPDATE `plant_profiles` SET
  `common_name` = 'China Rose',
  `scientific_name` = 'Rosa chinensis',
  `local_names` = JSON_OBJECT('hi','गुलाब','mr','गुलाब'),
  `pet_safety` = 'unknown',
  `toxicity_info` = 'Thorns cause mechanical injury. Petals of garden roses are sometimes used as garnish; do not assume every cultivar is edible.',
  `flowering_season` = JSON_ARRAY('winter','spring'),
  `planting_season` = JSON_ARRAY('monsoon','winter'),
  `updated_at` = NOW()
WHERE `product_id` = 106;

-- Holy basil: Ocimum tenuiflorum (syn. Ocimum sanctum).
UPDATE `products`
SET `description` = 'Ocimum tenuiflorum (holy basil / tulsi; syn. Ocimum sanctum). Aromatic perennial herb native to the Indian subcontinent. Full sun, regular pinch-harvest. Culinary and traditional home-garden plant — this listing is not a medical claim.',
    `updated_at` = NOW()
WHERE `id` = 107;

UPDATE `plant_profiles` SET
  `scientific_name` = 'Ocimum tenuiflorum',
  `local_names` = JSON_OBJECT('hi','तुलसी','ta','துளசி','sa','तुलसी'),
  `pet_safety` = 'safe',
  `toxicity_info` = 'Culinary/traditional herb. Essential oils are concentrated — do not treat as medicine from this listing.',
  `updated_at` = NOW()
WHERE `product_id` = 107;

UPDATE `products`
SET `description` = 'Solanum lycopersicum kitchen-garden starter. Needs 6+ hours of sun, staking and even moisture. Unripe fruit and foliage contain tomatine — do not eat green parts.',
    `updated_at` = NOW()
WHERE `id` = 108;

UPDATE `plant_profiles` SET
  `scientific_name` = 'Solanum lycopersicum',
  `pet_safety` = 'toxic',
  `toxicity_info` = 'Leaves and unripe fruit contain glycoalkaloids. Ripe fruit is the edible part.',
  `fruiting_season` = JSON_ARRAY('winter','spring'),
  `harvest_info` = 'Pick when fruits colour fully and feel firm. In much of India, winter–spring is the easier fruiting window.',
  `updated_at` = NOW()
WHERE `product_id` = 108;

UPDATE `products`
SET `description` = 'Mangifera indica grafted sapling. Native to South Asia. Needs a sunny open site and room for a full-sized tree. Fruiting takes several seasons after graft establishment. Sap and peel can irritate sensitive skin.',
    `updated_at` = NOW()
WHERE `id` = 109;

UPDATE `plant_profiles` SET
  `scientific_name` = 'Mangifera indica',
  `local_names` = JSON_OBJECT('hi','आम','ta','மா'),
  `pet_safety` = 'unknown',
  `toxicity_info` = 'Sap and peel of some varieties can cause contact dermatitis. Fruit is a food crop.',
  `fruiting_season` = JSON_ARRAY('summer'),
  `updated_at` = NOW()
WHERE `product_id` = 109;

UPDATE `products`
SET `description` = 'Tagetes erecta (African / Mexican marigold). Annual grown across India for winter and festive borders. Full sun. Strong scent; often used as a companion around vegetables.',
    `updated_at` = NOW()
WHERE `id` = 110;

UPDATE `plant_profiles` SET
  `scientific_name` = 'Tagetes erecta',
  `local_names` = JSON_OBJECT('hi','गेंदा','ta','சாமந்தி'),
  `updated_at` = NOW()
WHERE `product_id` = 110;

UPDATE `products`
SET `description` = 'Zamioculcas zamiifolia (ZZ plant). Native to eastern Africa. Waxy pinnate leaves on thick rhizomes that store water. Outstanding low-light office plant. Toxic if eaten; never overwater.',
    `updated_at` = NOW()
WHERE `id` = 111;

UPDATE `plant_profiles` SET
  `scientific_name` = 'Zamioculcas zamiifolia',
  `toxicity_info` = 'Calcium oxalate. Toxic if ingested. Sap may irritate skin.',
  `updated_at` = NOW()
WHERE `product_id` = 111;

-- Aloe vera (L.) Burm.f. — Aloe barbadensis is a synonym.
UPDATE `products`
SET `description` = 'Aloe vera (syn. Aloe barbadensis). Succulent native to the Arabian Peninsula, now grown worldwide. Needs bright light and gritty soil. Inner leaf gel is widely used on minor skin irritation; the yellow latex is a strong laxative and should not be eaten.',
    `updated_at` = NOW()
WHERE `id` = 112;

UPDATE `plant_profiles` SET
  `scientific_name` = 'Aloe vera',
  `local_names` = JSON_OBJECT('hi','एलोवेरा','ta','கற்றாழை'),
  `toxicity_info` = 'Pets: toxic if ingested. Humans: outer latex (aloin) is a strong purgative. Use only the inner gel and never treat this as medical advice.',
  `pet_safety` = 'toxic',
  `updated_at` = NOW()
WHERE `product_id` = 112;

UPDATE `products`
SET `description` = 'Chlorophytum comosum, native to southern Africa. Produces hanging plantlets. Among the easier indoor plants; generally listed as non-toxic to cats and dogs. Brown tips often come from fluoride in tap water.',
    `updated_at` = NOW()
WHERE `id` = 113;

UPDATE `plant_profiles` SET
  `scientific_name` = 'Chlorophytum comosum',
  `pet_safety` = 'safe',
  `toxicity_info` = 'Generally considered non-toxic to cats and dogs. Plantlets root easily in water or mix.',
  `updated_at` = NOW()
WHERE `product_id` = 113;

UPDATE `products`
SET `description` = 'Ficus elastica (rubber fig), native to South and Southeast Asia. Large leathery leaves. Bright light and dusting the leaves keep it vigorous. Milky sap is an irritant and is toxic if eaten.',
    `updated_at` = NOW()
WHERE `id` = 114;

UPDATE `plant_profiles` SET
  `scientific_name` = 'Ficus elastica',
  `toxicity_info` = 'Latex sap irritates skin and is toxic if ingested.',
  `updated_at` = NOW()
WHERE `product_id` = 114;

UPDATE `products`
SET `description` = 'Nephrolepis exaltata (Boston fern). A humid-loving fern; bathrooms and shaded balconies suit it. Keep the mix moist — dry air browns fronds quickly.',
    `updated_at` = NOW()
WHERE `id` = 115;

UPDATE `plant_profiles` SET
  `scientific_name` = 'Nephrolepis exaltata',
  `pet_safety` = 'safe',
  `updated_at` = NOW()
WHERE `product_id` = 115;

-- English lavender is Mediterranean; honest Indian-climate note (not junk "any balcony").
UPDATE `products`
SET `description` = 'Lavandula angustifolia (English lavender), native to the Mediterranean. Needs full sun and sharp drainage. In most of India it is happier in hill stations and cool, dry winters than in hot humid monsoon lowlands. Avoid soggy pots.',
    `updated_at` = NOW()
WHERE `id` = 116;

UPDATE `plant_profiles` SET
  `scientific_name` = 'Lavandula angustifolia',
  `difficulty_level` = 'moderate',
  `growing_instructions` = 'Full sun and excellent drainage. In humid Indian summers, shelter from prolonged rain. Do not treat as a monsoon bedding plant.',
  `pest_disease_info` = 'Root rot in waterlogged monsoon soil is the usual failure.',
  `updated_at` = NOW()
WHERE `product_id` = 116;

UPDATE `products`
SET `description` = 'Mentha spicata (spearmint). Fast, invasive herb — grow in a pot so roots cannot run. Keep mix moist. Kitchen staple for chutney and tea.',
    `updated_at` = NOW()
WHERE `id` = 117;

UPDATE `plant_profiles` SET
  `scientific_name` = 'Mentha spicata',
  `local_names` = JSON_OBJECT('hi','पुदीना','ta','புதினா'),
  `updated_at` = NOW()
WHERE `product_id` = 117;

UPDATE `products`
SET `description` = 'Cymbopogon citratus (West Indian lemongrass). Clumping tropical grass. Culinary stalks for tea and curries; lemon-scented foliage. Full sun and regular water in heat.',
    `updated_at` = NOW()
WHERE `id` = 118;

UPDATE `plant_profiles` SET
  `scientific_name` = 'Cymbopogon citratus',
  `local_names` = JSON_OBJECT('hi','लेमनग्रास','ta','எலுமிச்சைப் புல்'),
  `updated_at` = NOW()
WHERE `product_id` = 118;

UPDATE `products`
SET `description` = 'Hibiscus rosa-sinensis (China rose / shoeblack plant in parts of India). Tropical evergreen shrub; not a true rose. Large red blooms in sun. Native range uncertain; long cultivated across the Indian Ocean tropics.',
    `updated_at` = NOW()
WHERE `id` = 119;

UPDATE `plant_profiles` SET
  `scientific_name` = 'Hibiscus rosa-sinensis',
  `local_names` = JSON_OBJECT('hi','गुड़हल','ta','செம்பருத்தி'),
  `updated_at` = NOW()
WHERE `product_id` = 119;

UPDATE `products`
SET `description` = 'Bougainvillea glabra. Woody climber from Brazil, grown for colourful papery bracts. Needs full sun; slightly drier soil encourages blooming. Sharp thorns.',
    `updated_at` = NOW()
WHERE `id` = 120;

UPDATE `plant_profiles` SET
  `scientific_name` = 'Bougainvillea glabra',
  `toxicity_info` = 'Thorns. Sap may irritate skin in sensitive people.',
  `pet_safety` = 'unknown',
  `updated_at` = NOW()
WHERE `product_id` = 120;

UPDATE `plant_profiles` SET
  `scientific_name` = 'Echeveria elegans',
  `updated_at` = NOW()
WHERE `product_id` = 121;

-- Same species as money plant: keep as named golden cultivar SKU, not a fake second species.
UPDATE `products`
SET `name` = 'Golden Pothos',
    `description` = 'Golden-variegated cultivar of Epipremnum aureum (same species as money plant / devil''s ivy). Brighter gold-green leaves than the plain green form. Bright indirect light keeps the variegation; low light reverts leaves greener.',
    `updated_at` = NOW()
WHERE `id` = 122;

UPDATE `plant_profiles` SET
  `common_name` = 'Golden Pothos',
  `scientific_name` = 'Epipremnum aureum',
  `local_names` = JSON_OBJECT('hi','गोल्डन पोथोस','note','cultivar of money plant'),
  `toxicity_info` = 'Calcium oxalate. Same toxicity as money plant.',
  `pet_safety` = 'toxic',
  `growing_instructions` = 'Brighter light than the green form to keep gold variegation. Water when the top of the mix is dry.',
  `updated_at` = NOW()
WHERE `product_id` = 122;

UPDATE `products`
SET `description` = 'Citrus × limon grafted sapling for terrace or garden. Needs full sun, even moisture and citrus feed. Thorns; peel oils can irritate. Fruiting depends on graft and climate.',
    `updated_at` = NOW()
WHERE `id` = 123;

UPDATE `plant_profiles` SET
  `scientific_name` = 'Citrus limon',
  `local_names` = JSON_OBJECT('hi','निंबू','ta','எலுமிச்சை'),
  `updated_at` = NOW()
WHERE `product_id` = 123;

UPDATE `products`
SET `description` = 'Psidium guajava. Small tropical fruit tree native to the Americas, now naturalised across India. Sun-loving and relatively hardy. Fruit fly is the usual pest on ripening fruit.',
    `updated_at` = NOW()
WHERE `id` = 124;

UPDATE `plant_profiles` SET
  `scientific_name` = 'Psidium guajava',
  `local_names` = JSON_OBJECT('hi','अमरूद','ta','கொய்யா'),
  `updated_at` = NOW()
WHERE `product_id` = 124;

UPDATE `products`
SET `description` = 'Coriandrum sativum seed pack. Cool-season annual herb. Bolts quickly in extreme heat; sow in winter and monsoon in most Indian plains.',
    `updated_at` = NOW()
WHERE `id` = 125;

UPDATE `plant_profiles` SET
  `scientific_name` = 'Coriandrum sativum',
  `local_names` = JSON_OBJECT('hi','धनिया','ta','கொத்தமல்லி'),
  `updated_at` = NOW()
WHERE `product_id` = 125;

UPDATE `products`
SET `description` = 'Capsicum annuum chilli seed pack. Nightshade fruiting annual for sunny pots. Harvest green or fully coloured fruit as you prefer.',
    `updated_at` = NOW()
WHERE `id` = 126;

UPDATE `plant_profiles` SET
  `scientific_name` = 'Capsicum annuum',
  `local_names` = JSON_OBJECT('hi','मिर्च','ta','மிளகாய்'),
  `updated_at` = NOW()
WHERE `product_id` = 126;

UPDATE `products`
SET `description` = 'Chrysanthemum × morifolium florist chrysanthemum. Short-day winter bloomer widely grown in India for decorations. Full sun, pinch early, deadhead spent blooms. May irritate sensitive skin.',
    `updated_at` = NOW()
WHERE `id` = 127;

UPDATE `plant_profiles` SET
  `scientific_name` = 'Chrysanthemum morifolium',
  `updated_at` = NOW()
WHERE `product_id` = 127;

UPDATE `products`
SET `description` = 'Codiaeum variegatum (garden croton). Native to Indonesia, Malaysia, Australia and islands of the western Pacific. Bright light keeps leaf colour. Milky sap is an irritant and toxic if eaten.',
    `updated_at` = NOW()
WHERE `id` = 128;

UPDATE `plant_profiles` SET
  `scientific_name` = 'Codiaeum variegatum',
  `indoor_outdoor` = 'outdoor',
  `sunlight` = 'full_sun',
  `toxicity_info` = 'Euphorbia-family latex: skin and eye irritant; toxic if ingested.',
  `updated_at` = NOW()
WHERE `product_id` = 128;

-- More accurate primary photos where the old file reused one garden image for everything.
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1565626929866-e11c64e607cf?w=1000', `alt` = 'Snake plant (Dracaena trifasciata)' WHERE `id` = 3;
UPDATE `product_images` SET `url` = 'https://upload.wikimedia.org/wikipedia/commons/thumb/b/bd/Spathiphyllum_cochlearispathum_RTBG.jpg/960px-Spathiphyllum_cochlearispathum_RTBG.jpg', `alt` = 'Peace lily (Spathiphyllum)' WHERE `id` = 4;
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1614594975525-e45190c55d0b?w=1000', `alt` = 'Tropical foliage houseplant' WHERE `id` = 5;
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1459411552884-841db9b3cc2a?w=1000', `alt` = 'Jade succulent' WHERE `id` = 6;
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=1000', `alt` = 'Garden rose bloom' WHERE `id` = 7;
UPDATE `product_images` SET `url` = 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/01/Tulsi_or_Tulasi_Holy_basil.jpg/960px-Tulsi_or_Tulasi_Holy_basil.jpg', `alt` = 'Tulsi / holy basil' WHERE `id` = 8;
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1592419044706-39796d40f98c?w=1000', `alt` = 'Tomato plant' WHERE `id` = 9;
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1464965911861-746a04b4bca6?w=1000', `alt` = 'Mango fruit tree' WHERE `id` = 10;
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1501004318641-b39e6451bec6?w=1000', `alt` = 'Marigold flowers' WHERE `id` = 11;
UPDATE `product_images` SET `url` = 'https://images.unsplash.com/photo-1509423350716-97f9360b4e09?w=1000', `alt` = 'Money plant trailing vines' WHERE `id` = 1;

-- -----------------------------------------------------------------------------
-- 2) Extra popular Indian-nursery plants with Wikipedia-backed names
-- -----------------------------------------------------------------------------

INSERT IGNORE INTO `products`
(`id`,`brand_id`,`product_type`,`name`,`slug`,`sku`,`description`,`price`,`compare_at_price`,`currency`,`status`,`stock_status`,`is_featured`,`is_new`,`rating_avg`,`rating_count`,`published_at`,`meta`,`created_at`,`updated_at`,`deleted_at`,`tax_class`)
VALUES
(129,2,'plant','Swiss Cheese Plant (Monstera)','swiss-cheese-plant','PLT-MONST-001','Monstera deliciosa, native to southern Mexico and Panama. Fenestrated leaves as the plant matures. Bright indirect light; moss pole recommended. Unripe fruit and all green parts contain calcium oxalate — do not chew leaves.',899.00,1099.00,'INR','active','in_stock',1,1,4.70,34,NOW(),JSON_OBJECT('badges',JSON_ARRAY('new-arrival','office')),NOW(),NOW(),NULL,'gst5'),
(130,2,'plant','Arrowhead Plant','arrowhead-plant','PLT-SYNG-001','Syngonium podophyllum. Arrow-shaped juvenile leaves; climbs with age. Native from Mexico to Bolivia. All parts contain oxalates and can burn the mouth if eaten.',279.00,329.00,'INR','active','in_stock',1,1,4.55,28,NOW(),JSON_OBJECT('badges',JSON_ARRAY('new-arrival','beginner')),NOW(),NOW(),NULL,'gst5'),
(131,2,'plant','Chinese Evergreen','chinese-evergreen','PLT-AGLA-001','Aglaonema commutatum and related Chinese evergreens. Native to tropical Asia. Classic low-light indoor plant. Chilling injury starts near 15 C. Calcium oxalate — toxic if eaten.',449.00,529.00,'INR','active','in_stock',1,0,4.50,41,NOW(),JSON_OBJECT('badges',JSON_ARRAY('office','low_maintenance')),NOW(),NOW(),NULL,'gst5'),
(132,2,'plant','Fiddle Leaf Fig','fiddle-leaf-fig','PLT-FICUS-LYR-001','Ficus lyrata, native to western Africa. Large violin-shaped leaves. Needs bright stable light; sudden moves cause leaf drop. Sap is an irritant.',1299.00,1499.00,'INR','active','in_stock',1,1,4.25,19,NOW(),JSON_OBJECT('badges',JSON_ARRAY('new-arrival')),NOW(),NOW(),NULL,'gst5'),
(133,1,'plant','Lucky Bamboo','lucky-bamboo','PLT-BAMBOO-001','Dracaena sanderiana (lucky bamboo). Not a true bamboo. Often grown in water or light mix in bright shade. Member of Asparagaceae; mildly toxic if eaten by pets.',199.00,249.00,'INR','active','in_stock',0,0,4.40,88,NOW(),JSON_OBJECT('badges',JSON_ARRAY('gift','office')),NOW(),NOW(),NULL,'gst5'),
(134,1,'plant','Desert Rose (Adenium)','desert-rose-adenium','PLT-ADEN-001','Adenium obesum. Caudex succulent from the Sahel and Arabian Peninsula. Showy pink-red flowers in sun. Sap contains cardiac glycosides — poisonous. Water like a cactus.',449.00,549.00,'INR','active','in_stock',1,0,4.60,56,NOW(),JSON_OBJECT('badges',JSON_ARRAY('summer','gift')),NOW(),NOW(),NULL,'gst5'),
(135,5,'plant','Mogra (Arabian Jasmine)','mogra-arabian-jasmine','PLT-MOGRA-001','Jasminum sambac, native to the Indian subcontinent. National association with mogra / sampaguita fragrance. White flowers open at night. Full sun to light shade; frost tender.',249.00,299.00,'INR','active','in_stock',1,0,4.75,132,NOW(),JSON_OBJECT('badges',JSON_ARRAY('bestseller','balcony')),NOW(),NOW(),NULL,'gst5'),
(136,1,'plant','Ixora (Jungle Geranium)','ixora-jungle-geranium','PLT-IXORA-001','Ixora coccinea. Native to southern India, Sri Lanka and Bangladesh. Dense clusters of tubular scarlet flowers almost year-round in warm climates. Full sun and acidic, well-drained soil.',299.00,349.00,'INR','active','in_stock',1,0,4.45,77,NOW(),JSON_OBJECT('badges',JSON_ARRAY('bestseller','balcony')),NOW(),NOW(),NULL,'gst5'),
(137,5,'plant','Curry Leaf Plant','curry-leaf-plant','PLT-CURRY-001','Bergera koenigii (widely sold as Murraya koenigii). Native to the Indian subcontinent. Aromatic pinnate leaves for South Indian cooking. Full sun to partial shade; protect from hard dry winds.',199.00,249.00,'INR','active','in_stock',1,0,4.80,210,NOW(),JSON_OBJECT('badges',JSON_ARRAY('bestseller','medicinal')),NOW(),NOW(),NULL,'gst5'),
(138,1,'tree','Neem Sapling','neem-sapling','TRE-NEEM-001','Azadirachta indica (neem / Indian lilac), native to the Indian subcontinent. Fast tropical shade tree. Leaves and seed oil are used in traditional and horticultural practice — this listing is a sapling, not a medicine.',249.00,299.00,'INR','active','in_stock',1,0,4.35,48,NOW(),JSON_OBJECT('badges',JSON_ARRAY('medicinal')),NOW(),NOW(),NULL,'gst5'),
(139,1,'tree','Drumstick (Moringa)','drumstick-moringa','TRE-MORINGA-001','Moringa oleifera. Fast, drought-tolerant tree native to northern India. Young pods (drumsticks) and leaves are vegetables. Needs full sun and well-drained soil.',199.00,249.00,'INR','active','in_stock',1,1,4.50,62,NOW(),JSON_OBJECT('badges',JSON_ARRAY('new-arrival')),NOW(),NOW(),NULL,'gst5'),
(140,1,'tree','Pomegranate Sapling','pomegranate-sapling','TRE-ANAR-001','Punica granatum. Deciduous fruiting shrub originally from the Iranian plateau and nearby region, long grown in India. Full sun. Fruit after establishment; watch fruit fly and watering at bloom.',499.00,599.00,'INR','active','in_stock',1,0,4.30,39,NOW(),JSON_OBJECT('badges',JSON_ARRAY('gift')),NOW(),NOW(),NULL,'gst5'),
(141,1,'tree','Papaya Sapling','papaya-sapling','TRE-PAPAYA-001','Carica papaya. Fast tropical fruit plant native to the Americas. Frost tender. Needs full sun and excellent drainage. Plants may be male, female or hermaphrodite depending on seed source.',179.00,219.00,'INR','active','in_stock',0,1,4.20,33,NOW(),JSON_OBJECT('badges',JSON_ARRAY('new-arrival')),NOW(),NOW(),NULL,'gst5'),
(142,1,'plant','Moss Rose (9 O''Clock)','moss-rose-portulaca','PLT-PORTU-001','Portulaca grandiflora. Low succulent annual from South America, sold in India as 9 o''clock. Neon flowers in full sun that often close later in the day. Very drought tolerant.',129.00,159.00,'INR','active','in_stock',0,1,4.40,54,NOW(),JSON_OBJECT('badges',JSON_ARRAY('summer','balcony')),NOW(),NOW(),NULL,'gst5'),
(143,1,'plant','Coleus (Painted Nettle)','coleus-painted-nettle','PLT-COLEUS-001','Coleus scutellarioides (syn. Plectranthus scutellarioides). Grown for coloured foliage, not flowers. Bright shade to morning sun; pinch tips. Tender perennial treated as an annual in cooler spots.',149.00,179.00,'INR','active','in_stock',0,1,4.35,40,NOW(),JSON_OBJECT('badges',JSON_ARRAY('new-arrival')),NOW(),NOW(),NULL,'gst5'),
(144,2,'plant','Anthurium (Flamingo Flower)','anthurium-flamingo-flower','PLT-ANTH-001','Anthurium andraeanum. Tropical aroid from Colombia and Ecuador. Waxy red spathe and spadix. Bright indirect light, chunky airy mix, high humidity. Calcium oxalate — toxic if eaten.',649.00,749.00,'INR','active','in_stock',1,1,4.45,26,NOW(),JSON_OBJECT('badges',JSON_ARRAY('new-arrival','gift')),NOW(),NOW(),NULL,'gst5'),
(145,1,'plant','Kalanchoe','kalanchoe-blossfeldiana','PLT-KALAN-001','Kalanchoe blossfeldiana. Flowering succulent from Madagascar. Clusters of long-lasting blooms. Bright light, dry between waterings. Contains cardiac glycosides — toxic to pets.',249.00,299.00,'INR','active','in_stock',0,0,4.50,71,NOW(),JSON_OBJECT('badges',JSON_ARRAY('gift','succulent')),NOW(),NOW(),NULL,'gst5'),
(146,2,'plant','Heartleaf Philodendron','heartleaf-philodendron','PLT-PHILO-001','Philodendron hederaceum. Heart-shaped climbing aroid, often confused with pothos but a different genus. Native to Central America and the Caribbean. Calcium oxalate — toxic if eaten.',329.00,379.00,'INR','active','in_stock',1,0,4.60,47,NOW(),JSON_OBJECT('badges',JSON_ARRAY('hanging','beginner')),NOW(),NOW(),NULL,'gst5');

INSERT IGNORE INTO `plant_profiles`
(`id`,`product_id`,`common_name`,`scientific_name`,`local_names`,`plant_kind`,`indoor_outdoor`,`sunlight`,`water_requirement`,`soil_type`,
 `temperature_min_c`,`temperature_max_c`,`humidity_requirement`,`growth_rate`,`mature_height_cm`,`mature_width_cm`,
 `flowering_season`,`fruiting_season`,`planting_season`,`bloom_color`,`flowering_duration`,`lifespan`,
 `difficulty_level`,`care_level`,`propagation_method`,`toxicity_info`,`pet_safety`,`benefits`,`uses`,
 `growing_instructions`,`planting_instructions`,`pruning_instructions`,`fertilization_instructions`,`pest_disease_info`,`harvest_info`,
 `meta`,`created_at`,`updated_at`)
VALUES
(29,129,'Swiss Cheese Plant','Monstera deliciosa',JSON_OBJECT('hi','मॉन्स्टेरा','en','Swiss cheese plant'),'climber','indoor','bright_indirect','medium','well_draining',
 15.00,30.00,'high','medium',250,150,
 JSON_ARRAY(),JSON_ARRAY(),JSON_ARRAY('year_round'),NULL,NULL,'perennial',
 'easy','medium','cutting','Calcium oxalate in leaves and unripe fruit. Unripe fruit irritates the mouth.','toxic',
 JSON_ARRAY('Architectural foliage','Aerial roots climb a pole'),JSON_ARRAY('ornamental'),
 'Bright indirect light 20–30 C. Water when the top of the mix dries. Indoor height typically 2–3 m with support.','Chunky peat or coco mix with bark and perlite. Give a moss pole.','Remove yellow leaves. Cut just below a node to propagate.','Mild feed monthly in the growing season.','Scale and spider mites. Root rot if the pot stays wet.',NULL,
 JSON_OBJECT('source','wikipedia:Monstera_deliciosa','native','southern Mexico to Panama'),NOW(),NOW()),
(30,130,'Arrowhead Plant','Syngonium podophyllum',JSON_OBJECT('hi','एरोहेड प्लांट','en','nephthytis'),'climber','indoor','bright_indirect','medium','well_draining',
 16.00,32.00,'medium','fast',150,60,
 JSON_ARRAY(),JSON_ARRAY(),JSON_ARRAY('year_round'),NULL,NULL,'perennial',
 'easy','low','cutting','All parts poisonous; oxalic acid / raphides burn mouth and skin.','toxic',
 JSON_ARRAY('Colourful cultivars','Easy water or soil cuttings'),JSON_ARRAY('ornamental'),
 'Bright to medium light. Pink or white cultivars need more light than dark green ones. Keep evenly moist, not soggy.','Humus-rich indoor mix; can also grow in water with weekly changes.','Cut back lanky vines; they will branch.','Dilute feed in summer.','Spider mites in dry rooms. Sap burns skin — wear gloves when cutting.',NULL,
 JSON_OBJECT('source','wikipedia:Syngonium_podophyllum'),NOW(),NOW()),
(31,131,'Chinese Evergreen','Aglaonema commutatum',JSON_OBJECT('hi','चाइनीज एवरग्रीन'),'foliage','indoor','low','medium','well_draining',
 16.00,30.00,'medium','slow',80,60,
 JSON_ARRAY(),JSON_ARRAY(),JSON_ARRAY('year_round'),NULL,NULL,'perennial',
 'easy','low','division','Calcium oxalate. Juice can inflame skin and mouth.','toxic',
 JSON_ARRAY('Low indoor light','Patterned leaves'),JSON_ARRAY('ornamental'),
 'Protect from temperatures under about 15 C. Avoid harsh sun which bleaches leaves. Keep mix slightly moist.','Standard indoor mix; do not pack roots into a huge pot.','Remove spent inflorescences to keep foliage vigorous.','Light feeding; excess fertiliser burns this genus.','False spider mites, root nematodes, bacterial leaf spot if wet and cold.',NULL,
 JSON_OBJECT('source','wikipedia:Aglaonema','native','tropical Asia'),NOW(),NOW()),
(32,132,'Fiddle Leaf Fig','Ficus lyrata',JSON_OBJECT('hi','फिडल लीफ फिग'),'foliage','indoor','bright_indirect','medium','well_draining',
 16.00,29.00,'medium','medium',300,150,
 JSON_ARRAY(),JSON_ARRAY(),JSON_ARRAY('year_round'),NULL,NULL,'perennial',
 'moderate','medium','cutting','Fig latex irritates skin and is toxic if ingested.','toxic',
 JSON_ARRAY('Large statement leaves'),JSON_ARRAY('ornamental'),
 'Bright, consistent light. Drafts and moving the pot often cause leaf drop. Water when the top several centimetres are dry.','Airy mix; sturdy pot because top-heavy. Wipe dust from leaves.','Prune to a single leader or branch as preferred. Wear gloves (sap).','Monthly in spring/summer.','Scale insects. Overwatering yellows lower leaves.',NULL,
 JSON_OBJECT('source','wikipedia:Ficus_lyrata','native','western Africa'),NOW(),NOW()),
(33,133,'Lucky Bamboo','Dracaena sanderiana',JSON_OBJECT('hi','लकी बैम्बू'),'foliage','indoor','bright_indirect','medium','water_or_light_mix',
 16.00,30.00,'medium','slow',100,30,
 JSON_ARRAY(),JSON_ARRAY(),JSON_ARRAY('year_round'),NULL,NULL,'perennial',
 'easy','low','cutting','Related to Dracaena; mildly toxic to cats and dogs if chewed. Not a bamboo (Poaceae).','toxic',
 JSON_ARRAY('Can live in water','Gift plant'),JSON_ARRAY('ornamental'),
 'Bright shade, no hot sun. If grown in water, change water weekly and keep stems above rotting debris.','Clean vase or a small pot of light mix. Do not add metal coins to the water.','Trim yellow canes. New shoots from remaining nodes.','Very light feed if in soil; skip if in plain water.','Algae in stale water; yellow canes from fluoride or rotting ends.',NULL,
 JSON_OBJECT('source','wikipedia:Dracaena_sanderiana'),NOW(),NOW()),
(34,134,'Desert Rose','Adenium obesum',JSON_OBJECT('hi','एडेनियम','en','Sabi star'),'succulent','outdoor','full_sun','low','sandy',
 12.00,40.00,'low','slow',150,80,
 JSON_ARRAY('summer','monsoon'),JSON_ARRAY(),JSON_ARRAY('year_round'),'pink','long','perennial',
 'easy','low','cutting','Cardiac glycosides in sap. Arrow-poison plant. Keep away from children and pets.','toxic',
 JSON_ARRAY('Caudex bonsai look','Drought tolerant blooms'),JSON_ARRAY('ornamental'),
 'Maximum sun. Water deeply then dry out. Can drop leaves in cool or dry spells. Winter indoor minimum around 10 C.','Very free-draining cactus mix. Never leave the caudex in a saucer of water.','Light prune after a flush; sap is poisonous — gloves.','Low nitrogen cactus feed in the growing season.','Root rot from monsoon waterlogging. Oleander caterpillar in some regions.',NULL,
 JSON_OBJECT('source','wikipedia:Adenium_obesum','native','Sahel and Arabian Peninsula'),NOW(),NOW()),
(35,135,'Mogra','Jasminum sambac',JSON_OBJECT('hi','मोगरा','ta','மல்லிகை','en','Arabian jasmine'),'shrub','outdoor','full_sun','medium','loamy',
 12.00,38.00,'medium','medium',250,150,
 JSON_ARRAY('summer','monsoon','year_round'),JSON_ARRAY(),JSON_ARRAY('monsoon'),'white','year-round in tropics','perennial',
 'easy','medium','cutting','Not a food-safety warning plant; flowers are used in garlands and tea in many cultures.','safe',
 JSON_ARRAY('Night-opening fragrance','Traditional Indian garden shrub'),JSON_ARRAY('ornamental','culinary'),
 'Sun to light shade. Frost will kill it. Flowers open evening to morning. Harvest buds in the early morning for strongest scent.','Rich, well-drained soil. Support if grown as a small vine.','After a flush, light trim to keep a dense bush. Does not come true from seed in cultivation — use cuttings.','Organic compost monthly in the growing season.','Bud worm and scale. Avoid waterlogged clay.',NULL,
 JSON_OBJECT('source','wikipedia:Jasminum_sambac','native','India and Bhutan'),NOW(),NOW()),
(36,136,'Jungle Geranium','Ixora coccinea',JSON_OBJECT('hi','रुक्मिणी','ta','வெட்சி','en','flame of the woods'),'shrub','outdoor','full_sun','medium','loamy',
 14.00,38.00,'medium','medium',180,180,
 JSON_ARRAY('year_round'),JSON_ARRAY(),JSON_ARRAY('monsoon'),'scarlet','nearly year-round','perennial',
 'easy','medium','cutting','Traditional folk uses exist; this shop listing is ornamental, not a medicine.','safe',
 JSON_ARRAY('Native to southern India','Long flowering hedges'),JSON_ARRAY('ornamental'),
 'Full sun for heaviest bloom. Alkaline water/soil can cause yellowing — use acidic organic mix when possible.','Well-drained slightly acidic soil. Excellent as a clipped hedge in warm climates.','Tolerates hard pruning after flowering.','Flowering feed every 3–4 weeks in season.','Scale and sooty mould. Chlorosis on limey soil.',NULL,
 JSON_OBJECT('source','wikipedia:Ixora_coccinea','native','Southern India, Sri Lanka, Bangladesh'),NOW(),NOW()),
(37,137,'Curry Leaf','Bergera koenigii',JSON_OBJECT('hi','कढ़ी पत्ता','ta','கறிவேப்பிலை','synonym','Murraya koenigii'),'tree','outdoor','full_sun','medium','loamy',
 15.00,38.00,'medium','medium',600,400,
 JSON_ARRAY(),JSON_ARRAY(),JSON_ARRAY('monsoon'),'white',NULL,'perennial',
 'easy','medium','seed',
 'Leaves used in cooking. Fruit pulp is sweet; seeds may be toxic — do not eat the seed.','safe',
 JSON_ARRAY('Essential South Indian seasoning','Small home-garden tree'),JSON_ARRAY('culinary'),
 'Full sun or light shade, sheltered from drying wind. Keep soil from baking bone-dry. Fresh leaves are far more aromatic than dried.','Well-drained loam. Plant out of strong wind.','Harvest leaflets as needed. Do not strip the whole canopy.','Compost two or three times a year.','Psyllid and sooty mould on stressed plants. Scale occasional.',
 'Pick fully expanded, dark green leaflets. Use within hours for best flavour.',
 JSON_OBJECT('source','wikipedia:Murraya_koenigii','accepted','Bergera koenigii'),NOW(),NOW()),
(38,138,'Neem','Azadirachta indica',JSON_OBJECT('hi','नीम','ta','வேம்பு'),'tree','outdoor','full_sun','low','loamy',
 10.00,45.00,'low','fast',1500,800,
 JSON_ARRAY(),JSON_ARRAY(),JSON_ARRAY('monsoon'),NULL,NULL,'perennial',
 'easy','low','seed','Not for internal self-medication from this listing. Large landscape tree — plant only where height is acceptable.','unknown',
 JSON_ARRAY('Native Indian shade tree','Drought tolerant once established'),JSON_ARRAY('ornamental','traditional'),
 'Full sun and space. Young trees need some water; mature trees are drought-hardy.','Large pit with native soil and compost. Keep 6 m+ from buildings if possible.','Formative prune when young.','Little fertiliser needed on average Indian soils.','Few serious pests; avoid waterlogging of young roots.',NULL,
 JSON_OBJECT('source','wikipedia:Azadirachta_indica'),NOW(),NOW()),
(39,139,'Drumstick Tree','Moringa oleifera',JSON_OBJECT('hi','सहजन','ta','முருங்கை','en','horseradish tree'),'tree','outdoor','full_sun','low','loamy',
 12.00,42.00,'low','fast',800,400,
 JSON_ARRAY(),JSON_ARRAY('year_round'),JSON_ARRAY('monsoon'),'white',NULL,'perennial',
 'easy','low','cutting',
 'Leaves, flowers and young pods are vegetables. Roots of some plants are very pungent; harvest pods, not wild claims of cures.','safe',
 JSON_ARRAY('Drought tolerant vegetable tree','Fast growth'),JSON_ARRAY('culinary'),
 'Full sun. Water young plants; mature trees tolerate dry spells. Can be coppiced (cut back) to keep picking height low.','Well-drained soil; hates sitting water.','Cut back hard to keep a shrub form for leaf/pod harvest.','Light compost. Too much nitrogen makes weak wood.','Caterpillars on new flush. Pod fly in some districts.',
 'Pick slender green pods while still tender. Harvest mature leaves for cooking.',
 JSON_OBJECT('source','wikipedia:Moringa_oleifera','native','northern India'),NOW(),NOW()),
(40,140,'Pomegranate','Punica granatum',JSON_OBJECT('hi','अनार','ta','மாதுளை'),'tree','outdoor','full_sun','medium','loamy',
 5.00,40.00,'low','medium',500,400,
 JSON_ARRAY('summer'),JSON_ARRAY('winter'),JSON_ARRAY('monsoon','winter'),'orange-red','seasonal','perennial',
 'moderate','medium','cutting','Fruit is edible. Plant has thorns on some types.','safe',
 JSON_ARRAY('Home fruit shrub','Drought-tolerant once established'),JSON_ARRAY('culinary','ornamental'),
 'Hottest, sunniest spot. Overwatering at bloom can drop flowers. Deciduous in cooler winters.','Deep pit, good drainage. Large container possible with regular feeding.','Open-centre prune in the dormant or dry season.','Fruit tree feed after fruit set. Avoid heavy nitrogen only.','Fruit fly / anar butterfly. Split fruit from irregular watering.',
 'Harvest when the fruit sounds metallic and the calyx dries. Colour alone is not always ripe.',
 JSON_OBJECT('source','wikipedia:Pomegranate'),NOW(),NOW()),
(41,141,'Papaya','Carica papaya',JSON_OBJECT('hi','पपीता','ta','பப்பாளி'),'tree','outdoor','full_sun','medium','loamy',
 16.00,38.00,'medium','fast',400,150,
 JSON_ARRAY(),JSON_ARRAY('year_round'),JSON_ARRAY('monsoon'),'cream',NULL,'perennial',
 'easy','medium','seed','Unripe fruit latex can irritate skin. Ripe fruit is food. Frost will kill the plant.','unknown',
 JSON_ARRAY('Fast tropical fruit','Shallow-rooted — stake in wind'),JSON_ARRAY('culinary'),
 'Full sun, no frost. Keep evenly moist but never waterlogged. Plants may be male (flowers only), female or hermaphrodite.','Rich, draining soil. Do not transplant large specimens if avoidable.','Remove dead leaves. Single trunk; no heavy prune.','Regular compost. High potassium once flowering.','Root rot in clay. Papaya ringspot virus in some areas; buy healthy saplings.',
 'Harvest when the fruit shows yellow colouring and gives slightly to a thumb press.',
 JSON_OBJECT('source','wikipedia:Carica_papaya'),NOW(),NOW()),
(42,142,'Moss Rose','Portulaca grandiflora',JSON_OBJECT('hi','नौ बजे का फूल','en','9 o''clock'),'succulent','outdoor','full_sun','low','sandy',
 15.00,40.00,'low','fast',15,30,
 JSON_ARRAY('summer'),JSON_ARRAY(),JSON_ARRAY('summer'),'mixed','seasonal','annual',
 'easy','low','seed','Ornamental succulent; not a salad purslane (that is Portulaca oleracea).','safe',
 JSON_ARRAY('Heat and drought tolerant','Bright summer colour'),JSON_ARRAY('ornamental'),
 'Hottest, driest balcony edge. Flowers often open in the morning and may close later. Do not overwater.','Sandy mix; shallow pots are fine.','Deadhead if you want more flowers; or let it self-seed.','Almost none. Rich soil makes fewer blooms.','Stem rot if monsoon-soggy. Little pest pressure.',NULL,
 JSON_OBJECT('source','wikipedia:Portulaca_grandiflora'),NOW(),NOW()),
(43,143,'Coleus','Coleus scutellarioides',JSON_OBJECT('hi','कोलियस','synonym','Plectranthus scutellarioides'),'foliage','outdoor','partial','medium','well_draining',
 15.00,35.00,'medium','fast',60,50,
 JSON_ARRAY(),JSON_ARRAY(),JSON_ARRAY('monsoon'),NULL,NULL,'perennial',
 'easy','low','cutting','Grown as an ornamental foliage plant. Mildly irritating sap possible for sensitive skin.','unknown',
 JSON_ARRAY('Bold coloured leaves','Easy cuttings'),JSON_ARRAY('ornamental'),
 'Morning sun or bright shade. Harsh noon sun can bleach leaves. Pinch often so it does not get leggy.','Rich, moist but draining mix.','Pinch flower spikes if you want foliage only.','Balanced feed every 3–4 weeks.','Mealybugs. Downy mildew if crowded and wet.',NULL,
 JSON_OBJECT('source','wikipedia:Coleus_scutellarioides'),NOW(),NOW()),
(44,144,'Flamingo Flower','Anthurium andraeanum',JSON_OBJECT('hi','एन्थूरियम'),'flowering','indoor','bright_indirect','medium','well_draining',
 18.00,30.00,'high','slow',60,50,
 JSON_ARRAY('year_round'),JSON_ARRAY(),JSON_ARRAY('year_round'),'red','long-lasting spathe','perennial',
 'moderate','medium','division','Calcium oxalate. Toxic to pets and people if chewed.','toxic',
 JSON_ARRAY('Long-lasting coloured spathes'),JSON_ARRAY('ornamental'),
 'Bright indirect light, high humidity, never icy drafts. Let the top of the chunky mix dry slightly.','Orchid-like aroid mix: bark, coco, perlite. Do not use heavy garden soil.','Cut spent spathes at the base.','Weak aroid feed in the growing season.','Root rot in dense mix. Bacterial blight if leaves stay wet and cold.',NULL,
 JSON_OBJECT('source','wikipedia:Anthurium_andraeanum','native','Colombia and Ecuador'),NOW(),NOW()),
(45,145,'Florist Kalanchoe','Kalanchoe blossfeldiana',JSON_OBJECT('hi','कलन्चोए'),'succulent','indoor','full_sun','low','sandy',
 12.00,32.00,'low','slow',30,30,
 JSON_ARRAY('winter'),JSON_ARRAY(),JSON_ARRAY('year_round'),'red','several weeks','perennial',
 'easy','low','cutting','Cardiac glycosides. Toxic to cats and dogs.','toxic',
 JSON_ARRAY('Long indoor bloom','Low water'),JSON_ARRAY('ornamental'),
 'South or west window. Water only when the leaves slightly soften. Short-day plant for reblooming.','Cactus mix, drainage hole required.','Deadhead clusters. Rest dry after blooming.','Very light succulent feed.','Stem rot from overwatering. Mealybugs in leaf axils.',NULL,
 JSON_OBJECT('source','wikipedia:Kalanchoe_blossfeldiana','native','Madagascar'),NOW(),NOW()),
(46,146,'Heartleaf Philodendron','Philodendron hederaceum',JSON_OBJECT('hi','फिलोडेंड्रॉन'),'climber','indoor','bright_indirect','medium','well_draining',
 16.00,30.00,'medium','fast',200,60,
 JSON_ARRAY(),JSON_ARRAY(),JSON_ARRAY('year_round'),NULL,NULL,'perennial',
 'easy','low','cutting','Calcium oxalate like other aroids. Distinct from Epipremnum (pothos).','toxic',
 JSON_ARRAY('Classic hanging vine','Different species from money plant'),JSON_ARRAY('ornamental'),
 'Bright indirect light. Allow the top of the mix to dry. Leaves are thinner and more heart-shaped than pothos.','Standard indoor mix; hanging basket or moss pole.','Pinch to branch.','Monthly mild feed in the growing season.','Mealybugs. Soft rot if constantly wet.',NULL,
 JSON_OBJECT('source','wikipedia:Philodendron_hederaceum'),NOW(),NOW());

INSERT IGNORE INTO `product_images` (`id`,`product_id`,`url`,`alt`,`is_primary`,`sort_order`,`meta`,`created_at`,`updated_at`,`deleted_at`) VALUES
(54,129,'https://images.unsplash.com/photo-1755442975535-0d6e856cfeb8?w=1000','Monstera deliciosa',1,1,NULL,NOW(),NOW(),NULL),
(55,130,'https://commons.wikimedia.org/wiki/Special:FilePath/Syngonium_podophyllum.jpg?width=1000','Arrowhead / syngonium vine',1,1,NULL,NOW(),NOW(),NULL),
(56,131,'https://commons.wikimedia.org/wiki/Special:FilePath/Aglaonema_commutatum2.jpg?width=1000','Chinese evergreen foliage',1,1,NULL,NOW(),NOW(),NULL),
(57,132,'https://commons.wikimedia.org/wiki/Special:FilePath/Ficus_lyrata.jpg?width=1000','Fiddle leaf fig',1,1,NULL,NOW(),NOW(),NULL),
(58,133,'https://commons.wikimedia.org/wiki/Special:FilePath/Dracaena_sanderiana_2.jpg?width=1000','Lucky bamboo stems',1,1,NULL,NOW(),NOW(),NULL),
(59,134,'https://images.unsplash.com/photo-1463320726281-696a485928c7?w=1000','Desert rose flowers',1,1,NULL,NOW(),NOW(),NULL),
(60,135,'https://commons.wikimedia.org/wiki/Special:FilePath/Jasminum_sambac.jpg?width=1000','Mogra jasmine flowers',1,1,NULL,NOW(),NOW(),NULL),
(61,136,'https://commons.wikimedia.org/wiki/Special:FilePath/Ixora_coccinea.jpg?width=1000','Ixora flowering shrub',1,1,NULL,NOW(),NOW(),NULL),
(62,137,'https://commons.wikimedia.org/wiki/Special:FilePath/Curry_Trees.jpg?width=1000','Curry leaf plant',1,1,NULL,NOW(),NOW(),NULL),
(63,138,'https://commons.wikimedia.org/wiki/Special:FilePath/Neem_Tree_in_Rajasthan,_India.jpg?width=1000','Neem sapling',1,1,NULL,NOW(),NOW(),NULL),
(64,139,'https://upload.wikimedia.org/wikipedia/commons/2/2e/DrumstickFlower.jpg','Drumstick / moringa',1,1,NULL,NOW(),NOW(),NULL),
(65,140,'https://images.unsplash.com/photo-1464965911861-746a04b4bca6?w=1000','Pomegranate',1,1,NULL,NOW(),NOW(),NULL),
(66,141,'https://images.unsplash.com/photo-1526318472351-c75fcf070305?w=1000','Papaya plant',1,1,NULL,NOW(),NOW(),NULL),
(67,142,'https://commons.wikimedia.org/wiki/Special:FilePath/PortulacaGrandiflora.jpg?width=1000','Moss rose / portulaca',1,1,NULL,NOW(),NOW(),NULL),
(68,143,'https://commons.wikimedia.org/wiki/Special:FilePath/Coleus_scutellarioides.jpg?width=1000','Coleus foliage',1,1,NULL,NOW(),NOW(),NULL),
(69,144,'https://upload.wikimedia.org/wikipedia/commons/a/a8/Anthurium_andraeanum.jpg','Anthurium spathe',1,1,NULL,NOW(),NOW(),NULL),
(70,145,'https://commons.wikimedia.org/wiki/Special:FilePath/Kalanchoe_blossfeldiana.jpg?width=1000','Kalanchoe succulent',1,1,NULL,NOW(),NOW(),NULL),
(71,146,'https://images.unsplash.com/photo-1773431456773-50853cea57cf?w=1000','Heartleaf philodendron',1,1,NULL,NOW(),NOW(),NULL);

INSERT IGNORE INTO `product_categories` (`product_id`,`category_id`,`created_at`,`updated_at`) VALUES
(129,1,NOW(),NOW()),(129,17,NOW(),NOW()),
(130,1,NOW(),NOW()),(130,16,NOW(),NOW()),
(131,1,NOW(),NOW()),(131,11,NOW(),NOW()),(131,17,NOW(),NOW()),
(132,1,NOW(),NOW()),(132,17,NOW(),NOW()),
(133,1,NOW(),NOW()),(133,17,NOW(),NOW()),(133,11,NOW(),NOW()),
(134,2,NOW(),NOW()),(134,15,NOW(),NOW()),(134,3,NOW(),NOW()),
(135,2,NOW(),NOW()),(135,3,NOW(),NOW()),(135,13,NOW(),NOW()),
(136,2,NOW(),NOW()),(136,3,NOW(),NOW()),(136,13,NOW(),NOW()),
(137,5,NOW(),NOW()),(137,14,NOW(),NOW()),
(138,2,NOW(),NOW()),(138,14,NOW(),NOW()),
(139,4,NOW(),NOW()),(139,5,NOW(),NOW()),
(140,4,NOW(),NOW()),(140,2,NOW(),NOW()),
(141,4,NOW(),NOW()),(141,2,NOW(),NOW()),
(142,3,NOW(),NOW()),(142,13,NOW(),NOW()),(142,15,NOW(),NOW()),
(143,2,NOW(),NOW()),(143,13,NOW(),NOW()),
(144,1,NOW(),NOW()),(144,3,NOW(),NOW()),
(145,1,NOW(),NOW()),(145,15,NOW(),NOW()),
(146,1,NOW(),NOW()),(146,16,NOW(),NOW()),(146,11,NOW(),NOW());

INSERT IGNORE INTO `product_tags` (`product_id`,`tag_id`,`created_at`,`updated_at`) VALUES
(129,7,NOW(),NOW()),(129,10,NOW(),NOW()),(129,4,NOW(),NOW()),
(130,1,NOW(),NOW()),(130,7,NOW(),NOW()),(130,4,NOW(),NOW()),
(131,1,NOW(),NOW()),(131,3,NOW(),NOW()),(131,10,NOW(),NOW()),(131,4,NOW(),NOW()),
(132,7,NOW(),NOW()),(132,4,NOW(),NOW()),
(133,14,NOW(),NOW()),(133,10,NOW(),NOW()),(133,3,NOW(),NOW()),
(134,15,NOW(),NOW()),(134,12,NOW(),NOW()),(134,4,NOW(),NOW()),(134,14,NOW(),NOW()),
(135,6,NOW(),NOW()),(135,8,NOW(),NOW()),
(136,6,NOW(),NOW()),(136,8,NOW(),NOW()),
(137,6,NOW(),NOW()),(137,11,NOW(),NOW()),
(138,11,NOW(),NOW()),
(139,7,NOW(),NOW()),
(140,14,NOW(),NOW()),
(141,7,NOW(),NOW()),
(142,15,NOW(),NOW()),(142,8,NOW(),NOW()),(142,1,NOW(),NOW()),
(143,7,NOW(),NOW()),(143,8,NOW(),NOW()),
(144,7,NOW(),NOW()),(144,14,NOW(),NOW()),(144,4,NOW(),NOW()),
(145,12,NOW(),NOW()),(145,14,NOW(),NOW()),(145,4,NOW(),NOW()),
(146,1,NOW(),NOW()),(146,13,NOW(),NOW()),(146,4,NOW(),NOW());

INSERT IGNORE INTO `product_relations` (`product_id`,`related_product_id`,`relation_type`,`sort_order`,`meta`,`created_at`,`updated_at`) VALUES
(129,205,'fbt',1,NULL,NOW(),NOW()),(129,602,'fbt',2,NULL,NOW(),NOW()),(129,146,'related',1,NULL,NOW(),NOW()),
(130,201,'fbt',1,NULL,NOW(),NOW()),(130,101,'related',1,NULL,NOW(),NOW()),
(131,203,'fbt',1,NULL,NOW(),NOW()),(131,111,'related',1,NULL,NOW(),NOW()),
(135,202,'fbt',1,NULL,NOW(),NOW()),(135,301,'fbt',2,NULL,NOW(),NOW()),
(137,117,'related',1,NULL,NOW(),NOW()),(137,107,'related',2,NULL,NOW(),NOW()),
(134,403,'fbt',1,NULL,NOW(),NOW()),(134,121,'related',1,NULL,NOW(),NOW()),
(146,101,'related',1,NULL,NOW(),NOW()),(146,122,'related',2,NULL,NOW(),NOW()),
(136,119,'related',1,NULL,NOW(),NOW());

INSERT IGNORE INTO `inventory_items`
(`id`,`warehouse_id`,`product_id`,`product_variant_id`,`qty_on_hand`,`qty_reserved`,`qty_damaged`,`low_stock_threshold`,`meta`,`created_at`,`updated_at`,`version`,`deleted_at`)
VALUES
(55,1,129,NULL,35,0,0,5,NULL,NOW(),NOW(),1,NULL),
(56,1,130,NULL,80,0,0,10,NULL,NOW(),NOW(),1,NULL),
(57,1,131,NULL,55,0,0,8,NULL,NOW(),NOW(),1,NULL),
(58,1,132,NULL,18,0,0,4,NULL,NOW(),NOW(),1,NULL),
(59,1,133,NULL,90,0,0,12,NULL,NOW(),NOW(),1,NULL),
(60,1,134,NULL,48,0,0,6,NULL,NOW(),NOW(),1,NULL),
(61,1,135,NULL,95,0,0,12,NULL,NOW(),NOW(),1,NULL),
(62,1,136,NULL,70,0,0,10,NULL,NOW(),NOW(),1,NULL),
(63,1,137,NULL,110,0,0,12,NULL,NOW(),NOW(),1,NULL),
(64,1,138,NULL,40,0,0,6,NULL,NOW(),NOW(),1,NULL),
(65,1,139,NULL,60,0,0,8,NULL,NOW(),NOW(),1,NULL),
(66,1,140,NULL,32,0,0,5,NULL,NOW(),NOW(),1,NULL),
(67,1,141,NULL,50,0,0,8,NULL,NOW(),NOW(),1,NULL),
(68,1,142,NULL,120,0,0,15,NULL,NOW(),NOW(),1,NULL),
(69,1,143,NULL,85,0,0,10,NULL,NOW(),NOW(),1,NULL),
(70,1,144,NULL,28,0,0,5,NULL,NOW(),NOW(),1,NULL),
(71,1,145,NULL,64,0,0,8,NULL,NOW(),NOW(),1,NULL),
(72,1,146,NULL,75,0,0,10,NULL,NOW(),NOW(),1,NULL);

INSERT IGNORE INTO `campaign_products` (`campaign_id`,`product_id`,`sort_order`,`created_at`,`updated_at`) VALUES
(5,135,6,NOW(),NOW()),
(5,144,7,NOW(),NOW()),
(4,134,6,NOW(),NOW()),
(4,142,7,NOW(),NOW()),
(2,129,9,NOW(),NOW()),
(2,131,10,NOW(),NOW());
