<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name', 120);
            $table->string('slug', 160)->unique();
            $table->string('image_url', 500)->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 160)->unique();
            $table->string('logo_url', 500)->nullable();
            $table->string('status', 20)->default('active');
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->string('product_type', 40)->index();
            $table->string('name', 200);
            $table->string('slug', 220)->unique();
            $table->string('sku', 80)->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->char('currency', 3)->default('INR');
            $table->string('status', 20)->default('draft')->index();
            $table->string('stock_status', 20)->default('in_stock');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_new')->default(false);
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->string('tax_class', 40)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('products', function (Blueprint $table) {
                $table->fullText(['name', 'description']);
            });
        }

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku', 80)->unique();
            $table->string('name', 120)->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->json('attributes_json')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('barcode', 64)->nullable();
            $table->unsignedInteger('weight_grams')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('url', 500);
            $table->string('alt', 200)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->integer('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('url', 500);
            $table->string('thumbnail_url', 500)->nullable();
            $table->integer('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('plant_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->string('common_name', 160);
            $table->string('scientific_name', 190)->nullable();
            $table->json('local_names')->nullable();
            $table->string('plant_kind', 40)->nullable();
            $table->string('indoor_outdoor', 20)->nullable()->index();
            $table->string('sunlight', 40)->nullable()->index();
            $table->string('water_requirement', 40)->nullable()->index();
            $table->string('soil_type', 60)->nullable();
            $table->decimal('temperature_min_c', 5, 2)->nullable();
            $table->decimal('temperature_max_c', 5, 2)->nullable();
            $table->string('humidity_requirement', 20)->nullable();
            $table->string('growth_rate', 20)->nullable();
            $table->unsignedInteger('mature_height_cm')->nullable();
            $table->unsignedInteger('mature_width_cm')->nullable();
            $table->json('flowering_season')->nullable();
            $table->json('fruiting_season')->nullable();
            $table->json('planting_season')->nullable();
            $table->string('bloom_color', 60)->nullable();
            $table->string('flowering_duration', 80)->nullable();
            $table->string('lifespan', 40)->nullable();
            $table->string('difficulty_level', 20)->nullable()->index();
            $table->string('care_level', 20)->nullable();
            $table->string('propagation_method', 80)->nullable();
            $table->text('toxicity_info')->nullable();
            $table->string('pet_safety', 20)->nullable();
            $table->json('benefits')->nullable();
            $table->json('uses')->nullable();
            $table->text('growing_instructions')->nullable();
            $table->text('planting_instructions')->nullable();
            $table->text('pruning_instructions')->nullable();
            $table->text('fertilization_instructions')->nullable();
            $table->text('pest_disease_info')->nullable();
            $table->text('harvest_info')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('fertilizer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->string('npk_ratio', 40)->nullable();
            $table->json('suitable_plant_types')->nullable();
            $table->string('application_frequency', 80)->nullable();
            $table->string('application_quantity', 80)->nullable();
            $table->text('usage_instructions')->nullable();
            $table->boolean('organic')->default(false);
            $table->string('form', 40)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('pot_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->string('material', 60)->nullable();
            $table->decimal('diameter_cm', 8, 2)->nullable();
            $table->decimal('height_cm', 8, 2)->nullable();
            $table->decimal('capacity_liters', 8, 2)->nullable();
            $table->boolean('drainage_holes')->default(true);
            $table->string('indoor_outdoor_suitability', 20)->nullable();
            $table->string('color', 40)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('tool_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->string('material', 60)->nullable();
            $table->string('size', 60)->nullable();
            $table->string('usage', 120)->nullable();
            $table->unsignedInteger('warranty_months')->nullable();
            $table->string('brand_name', 120)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('soil_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->string('composition', 255)->nullable();
            $table->string('ph_range', 40)->nullable();
            $table->json('suitable_for')->nullable();
            $table->text('usage_instructions')->nullable();
            $table->boolean('organic')->default(false);
            $table->decimal('weight_kg', 8, 2)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('accessory_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->string('material', 60)->nullable();
            $table->string('usage', 120)->nullable();
            $table->string('indoor_outdoor_suitability', 20)->nullable();
            $table->unsignedInteger('pack_qty')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['product_id', 'category_id']);
        });

        Schema::create('product_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['product_id', 'tag_id']);
        });

        Schema::create('product_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedBigInteger('related_product_id');
            $table->string('relation_type', 30);
            $table->integer('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'related_product_id', 'relation_type'], 'product_relations_unique');
            $table->foreign('related_product_id')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::create('product_bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('status', 20)->default('active');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_id')->constrained('product_bundles')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_bundle_items');
        Schema::dropIfExists('product_bundles');
        Schema::dropIfExists('product_relations');
        Schema::dropIfExists('product_tags');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('accessory_profiles');
        Schema::dropIfExists('soil_profiles');
        Schema::dropIfExists('tool_profiles');
        Schema::dropIfExists('pot_profiles');
        Schema::dropIfExists('fertilizer_profiles');
        Schema::dropIfExists('plant_profiles');
        Schema::dropIfExists('product_videos');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
