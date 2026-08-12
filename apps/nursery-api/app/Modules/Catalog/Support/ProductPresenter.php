<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\PlantProfile;
use App\Modules\Catalog\Models\Product;

class ProductPresenter
{
    public static function card(Product $product): array
    {
        $product->loadMissing(['images']);

        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'slug' => $product->slug,
            'product_type' => $product->product_type,
            'price' => (float) $product->price,
            'compare_at_price' => $product->compare_at_price !== null ? (float) $product->compare_at_price : null,
            'currency' => $product->currency,
            'stock_status' => $product->stock_status,
            'thumbnail_url' => $product->primaryImageUrl(),
            'rating_avg' => (float) $product->rating_avg,
            'rating_count' => (int) $product->rating_count,
            'badges' => $product->badges(),
        ];
    }

    public static function detail(Product $product): array
    {
        $product->loadMissing([
            'brand', 'categories', 'tags', 'images', 'videos', 'variants',
            'plantProfile', 'fertilizerProfile', 'potProfile', 'toolProfile',
            'soilProfile', 'accessoryProfile', 'inventoryItems',
            'relatedLinks.relatedProduct.images',
            'campaigns',
        ]);

        $data = [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'slug' => $product->slug,
            'product_type' => $product->product_type,
            'description' => $product->description,
            'price' => (float) $product->price,
            'compare_at_price' => $product->compare_at_price !== null ? (float) $product->compare_at_price : null,
            'currency' => $product->currency,
            'stock_status' => $product->stock_status,
            'available_qty' => $product->availableQty(),
            'rating_avg' => (float) $product->rating_avg,
            'rating_count' => (int) $product->rating_count,
            'badges' => $product->badges(),
            'brand' => $product->brand ? [
                'id' => $product->brand->id,
                'name' => $product->brand->name,
                'slug' => $product->brand->slug,
            ] : null,
            'categories' => $product->categories->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
            ])->values()->all(),
            'tags' => $product->tags->pluck('slug')->values()->all(),
            'images' => $product->images->map(fn ($img) => [
                'id' => $img->id,
                'url' => $img->url,
                'alt' => $img->alt,
                'is_primary' => (bool) $img->is_primary,
                'sort_order' => $img->sort_order,
            ])->values()->all(),
            'videos' => $product->videos->map(fn ($v) => [
                'id' => $v->id,
                'url' => $v->url,
                'thumbnail_url' => $v->thumbnail_url,
                'sort_order' => $v->sort_order,
            ])->values()->all(),
            'variants' => $product->variants->map(fn ($v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'name' => $v->name,
                'price' => $v->price !== null ? (float) $v->price : null,
                'compare_at_price' => $v->compare_at_price !== null ? (float) $v->compare_at_price : null,
                'attributes' => $v->attributes_json,
                'status' => $v->status,
            ])->values()->all(),
            'related' => $product->relatedLinks
                ->where('relation_type', 'related')
                ->map(fn ($rel) => $rel->relatedProduct ? self::card($rel->relatedProduct) : null)
                ->filter()
                ->values()
                ->all(),
            'frequently_bought_together' => $product->relatedLinks
                ->where('relation_type', 'fbt')
                ->map(fn ($rel) => $rel->relatedProduct ? self::card($rel->relatedProduct) : null)
                ->filter()
                ->values()
                ->all(),
        ];

        if ($product->plantProfile) {
            $data['plant'] = self::plant($product->plantProfile);
        } elseif ($product->fertilizerProfile) {
            $data['fertilizer'] = $product->fertilizerProfile->only([
                'npk_ratio', 'suitable_plant_types', 'application_frequency',
                'application_quantity', 'usage_instructions', 'organic', 'form',
            ]);
        } elseif ($product->potProfile) {
            $data['pot'] = $product->potProfile->only([
                'material', 'diameter_cm', 'height_cm', 'capacity_liters',
                'drainage_holes', 'indoor_outdoor_suitability', 'color',
            ]);
        } elseif ($product->toolProfile) {
            $data['tool'] = $product->toolProfile->only([
                'material', 'size', 'usage', 'warranty_months', 'brand_name',
            ]);
        } elseif ($product->soilProfile) {
            $data['soil'] = $product->soilProfile->only([
                'composition', 'ph_range', 'suitable_for', 'usage_instructions', 'organic', 'weight_kg',
            ]);
        } elseif ($product->accessoryProfile) {
            $data['accessory'] = $product->accessoryProfile->only([
                'material', 'usage', 'indoor_outdoor_suitability', 'pack_qty',
            ]);
        }

        $now = now();
        $data['active_campaigns'] = $product->campaigns
            ->filter(function ($c) use ($now) {
                if ($c->status !== 'active') {
                    return false;
                }
                if ($c->starts_at && $c->starts_at->gt($now)) {
                    return false;
                }
                if ($c->ends_at && $c->ends_at->lt($now)) {
                    return false;
                }

                return true;
            })
            ->sortByDesc('priority')
            ->take(3)
            ->map(fn ($c) => [
                'id' => $c->id,
                'slug' => $c->slug,
                'title' => $c->title,
            ])
            ->values()
            ->all();

        $plans = \App\Modules\Subscription\Models\SubscriptionPlan::query()
            ->where('product_id', $product->id)
            ->where('status', 'active')
            ->orderBy('unit_price')
            ->get();
        $data['subscription_enabled'] = $plans->isNotEmpty();
        $data['subscription_plans'] = $plans->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'frequency' => $p->frequency,
            'quantity_default' => (int) $p->quantity_default,
            'unit_price' => (float) $p->unit_price,
            'compare_at_price' => $p->compare_at_price !== null ? (float) $p->compare_at_price : null,
            'currency' => $p->currency,
            'description' => $p->description,
            'billing_model' => 'pay_per_cycle',
        ])->values()->all();

        return $data;
    }

    public static function plant(PlantProfile $plant): array
    {
        return [
            'common_name' => $plant->common_name,
            'scientific_name' => $plant->scientific_name,
            'local_names' => $plant->local_names,
            'indoor_outdoor' => $plant->indoor_outdoor,
            'sunlight' => $plant->sunlight,
            'sunlight_label' => self::sunlightLabel($plant->sunlight),
            'water_requirement' => $plant->water_requirement,
            'water_label' => self::waterLabel($plant->water_requirement),
            'soil_type' => $plant->soil_type,
            'temperature_min_c' => $plant->temperature_min_c !== null ? (float) $plant->temperature_min_c : null,
            'temperature_max_c' => $plant->temperature_max_c !== null ? (float) $plant->temperature_max_c : null,
            'humidity_requirement' => $plant->humidity_requirement,
            'growth_rate' => $plant->growth_rate,
            'mature_height_cm' => $plant->mature_height_cm,
            'mature_width_cm' => $plant->mature_width_cm,
            'planting_season' => $plant->planting_season ?? [],
            'flowering_season' => $plant->flowering_season ?? [],
            'fruiting_season' => $plant->fruiting_season ?? [],
            'bloom_color' => $plant->bloom_color,
            'difficulty_level' => $plant->difficulty_level,
            'care_level' => $plant->care_level,
            'propagation_method' => $plant->propagation_method,
            'toxicity_info' => $plant->toxicity_info,
            'pet_safety' => $plant->pet_safety,
            'benefits' => $plant->benefits ?? [],
            'uses' => $plant->uses ?? [],
            'growing_instructions' => $plant->growing_instructions,
            'planting_instructions' => $plant->planting_instructions,
            'pruning_instructions' => $plant->pruning_instructions,
            'fertilization_instructions' => $plant->fertilization_instructions,
            'pest_disease_info' => $plant->pest_disease_info,
            'harvest_info' => $plant->harvest_info,
        ];
    }

    public static function care(Product $product): array
    {
        $product->loadMissing('plantProfile');
        $plant = $product->plantProfile;
        if (! $plant) {
            return [];
        }

        return [
            'product_id' => $product->id,
            'name' => $product->name,
            'sunlight_label' => self::sunlightLabel($plant->sunlight),
            'water_label' => self::waterLabel($plant->water_requirement),
            'soil_type' => $plant->soil_type,
            'temperature_min_c' => $plant->temperature_min_c !== null ? (float) $plant->temperature_min_c : null,
            'temperature_max_c' => $plant->temperature_max_c !== null ? (float) $plant->temperature_max_c : null,
            'difficulty_level' => $plant->difficulty_level,
            'care_level' => $plant->care_level,
            'growing_instructions' => $plant->growing_instructions,
            'planting_instructions' => $plant->planting_instructions,
            'pruning_instructions' => $plant->pruning_instructions,
            'fertilization_instructions' => $plant->fertilization_instructions,
            'pest_disease_info' => $plant->pest_disease_info,
            'pet_safety' => $plant->pet_safety,
            'toxicity_info' => $plant->toxicity_info,
        ];
    }

    private static function sunlightLabel(?string $value): ?string
    {
        return match ($value) {
            'low' => 'Low light',
            'bright_indirect' => 'Bright indirect light',
            'partial' => 'Partial sun',
            'full_sun' => 'Full sun',
            default => $value,
        };
    }

    private static function waterLabel(?string $value): ?string
    {
        return match ($value) {
            'low' => 'Water sparingly',
            'medium' => '2–3 times per week',
            'high' => 'Keep soil consistently moist',
            default => $value,
        };
    }
}
