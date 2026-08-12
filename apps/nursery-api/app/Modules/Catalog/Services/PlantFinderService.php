<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\ProductPresenter;
use App\Shared\Exceptions\ApiException;

class PlantFinderService
{
    private const LOCATIONS = ['indoor', 'balcony', 'terrace', 'garden', 'office', 'outdoor', 'both'];

    private const SUNLIGHT = ['low', 'indirect', 'bright_indirect', 'bright', 'partial', 'direct', 'full_sun'];

    private const WATERING = ['rarely', 'weekly', 'often', 'frequently', 'low', 'medium', 'high'];

    private const EXPERIENCE = ['beginner', 'intermediate', 'experienced', 'expert', 'easy', 'moderate', 'advanced'];

    private const PURPOSES = [
        'air_purifying', 'low_maintenance', 'decoration', 'flowers', 'edible', 'pet_friendly', 'shade_loving',
    ];

    public function options(): array
    {
        return [
            'location' => [
                ['value' => 'indoor', 'label' => 'Indoor'],
                ['value' => 'balcony', 'label' => 'Balcony'],
                ['value' => 'terrace', 'label' => 'Terrace'],
                ['value' => 'garden', 'label' => 'Garden'],
                ['value' => 'office', 'label' => 'Office'],
            ],
            'sunlight' => [
                ['value' => 'low', 'label' => 'Low'],
                ['value' => 'bright_indirect', 'label' => 'Bright indirect'],
                ['value' => 'partial', 'label' => 'Partial'],
                ['value' => 'full_sun', 'label' => 'Direct / full sun'],
            ],
            'watering' => [
                ['value' => 'rarely', 'label' => 'Rarely'],
                ['value' => 'weekly', 'label' => 'Once a week'],
                ['value' => 'often', 'label' => 'Several times a week'],
            ],
            'experience' => [
                ['value' => 'beginner', 'label' => 'Beginner'],
                ['value' => 'intermediate', 'label' => 'Intermediate'],
                ['value' => 'experienced', 'label' => 'Experienced'],
            ],
            'purpose' => [
                ['value' => 'low_maintenance', 'label' => 'Low maintenance'],
                ['value' => 'air_purifying', 'label' => 'Air purifying'],
                ['value' => 'flowers', 'label' => 'Flowers'],
                ['value' => 'decoration', 'label' => 'Decoration'],
                ['value' => 'pet_friendly', 'label' => 'Pet-friendly'],
                ['value' => 'edible', 'label' => 'Edible / herbs'],
                ['value' => 'shade_loving', 'label' => 'Shade-loving'],
            ],
        ];
    }

    public function match(array $input): array
    {
        $criteria = $this->validateAndNormalize($input);

        // Cap candidate set to avoid loading the entire catalog into memory.
        $products = Product::query()
            ->active()
            ->whereIn('product_type', ['plant', 'tree', 'seed'])
            ->with(['images', 'plantProfile', 'tags'])
            ->whereHas('plantProfile')
            ->orderByDesc('rating_count')
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        $scored = $products
            ->map(fn (Product $p) => $this->scoreProduct($p, $criteria))
            ->filter()
            ->sort(function (array $a, array $b) {
                if ($a['match_score'] !== $b['match_score']) {
                    return $b['match_score'] <=> $a['match_score'];
                }
                $ra = (float) ($a['product']['rating_avg'] ?? 0);
                $rb = (float) ($b['product']['rating_avg'] ?? 0);
                if ($ra !== $rb) {
                    return $rb <=> $ra;
                }

                return ($a['product']['id'] ?? 0) <=> ($b['product']['id'] ?? 0);
            })
            ->values();

        $exact = $scored->filter(fn ($r) => $r['match_score'] >= 50)->values();
        $approximate = false;
        if ($exact->isEmpty()) {
            $exact = $scored->take(8)->values();
            $approximate = $exact->isNotEmpty();
        } else {
            $exact = $exact->take(12)->values();
        }

        return [
            'criteria' => $criteria,
            'results' => $exact->all(),
            'approximate' => $approximate,
            'meta' => [
                'total' => $exact->count(),
                'approximate' => $approximate,
            ],
        ];
    }

    private function validateAndNormalize(array $input): array
    {
        $location = strtolower((string) ($input['location'] ?? ''));
        $sunlight = strtolower((string) ($input['sunlight'] ?? ''));
        $watering = strtolower((string) ($input['watering'] ?? ''));
        $experience = strtolower((string) ($input['experience'] ?? ''));
        $purposes = $input['purpose'] ?? [];
        if (! is_array($purposes)) {
            $purposes = $purposes ? [$purposes] : [];
        }
        $purposes = array_values(array_unique(array_map(
            fn ($p) => strtolower((string) $p),
            $purposes,
        )));

        $errors = [];
        if ($location === '' || ! in_array($location, self::LOCATIONS, true)) {
            $errors['location'] = ['Invalid or missing location'];
        }
        if ($sunlight === '' || ! in_array($sunlight, self::SUNLIGHT, true)) {
            $errors['sunlight'] = ['Invalid or missing sunlight'];
        }
        if ($watering === '' || ! in_array($watering, self::WATERING, true)) {
            $errors['watering'] = ['Invalid or missing watering'];
        }
        if ($experience === '' || ! in_array($experience, self::EXPERIENCE, true)) {
            $errors['experience'] = ['Invalid or missing experience'];
        }
        foreach ($purposes as $p) {
            if (! in_array($p, self::PURPOSES, true)) {
                $errors['purpose'] = ['Invalid purpose value: '.$p];
                break;
            }
        }

        if ($errors) {
            throw new ApiException('Invalid plant finder criteria', 422, 'VALIDATION_ERROR', null, $errors);
        }

        return [
            'location' => $location,
            'sunlight' => $this->normalizeSunlight($sunlight),
            'watering' => $this->normalizeWatering($watering),
            'experience' => $this->normalizeExperience($experience),
            'purpose' => $purposes,
            'placement' => $this->mapLocationToPlacement($location),
        ];
    }

    private function mapLocationToPlacement(string $location): string
    {
        return match ($location) {
            'indoor', 'office' => 'indoor',
            'garden', 'terrace' => 'outdoor',
            'balcony' => 'both',
            default => $location,
        };
    }

    private function normalizeSunlight(string $v): string
    {
        return match ($v) {
            'indirect', 'bright' => 'bright_indirect',
            'direct' => 'full_sun',
            default => $v,
        };
    }

    private function normalizeWatering(string $v): string
    {
        return match ($v) {
            'rarely' => 'low',
            'weekly' => 'medium',
            'often', 'frequently' => 'high',
            default => $v,
        };
    }

    private function normalizeExperience(string $v): string
    {
        return match ($v) {
            'beginner' => 'easy',
            'intermediate' => 'moderate',
            'experienced', 'expert' => 'advanced',
            default => $v,
        };
    }

    private function scoreProduct(Product $product, array $criteria): ?array
    {
        $profile = $product->plantProfile;
        if (! $profile) {
            return null;
        }

        $score = 0;
        $reasons = [];
        $placement = (string) $profile->indoor_outdoor;
        $want = $criteria['placement'];

        // Hard filter: indoor-only request excludes outdoor-only plants (and inverse).
        if ($want === 'indoor' && $placement === 'outdoor') {
            return null;
        }
        if ($want === 'outdoor' && $placement === 'indoor') {
            return null;
        }

        if ($placement === $want || $placement === 'both' || $want === 'both') {
            $score += 25;
            $reasons[] = $want === 'indoor' || $placement === 'indoor'
                ? 'Suitable for indoor spaces'
                : ($want === 'outdoor' ? 'Suitable for outdoor spaces' : 'Flexible placement');
        } else {
            $score += 8;
        }

        $sun = (string) $profile->sunlight;
        $wantSun = $criteria['sunlight'];
        if ($sun === $wantSun) {
            $score += 25;
            $reasons[] = 'Matches your sunlight';
        } elseif ($this->sunAdjacent($sun, $wantSun)) {
            $score += 12;
            $reasons[] = 'Compatible sunlight needs';
        }

        $water = (string) $profile->water_requirement;
        $wantWater = $criteria['watering'];
        if ($water === $wantWater) {
            $score += 20;
            $reasons[] = 'Matches your watering routine';
        } elseif ($this->waterAdjacent($water, $wantWater)) {
            $score += 10;
        }

        $diff = (string) $profile->difficulty_level;
        $wantDiff = $criteria['experience'];
        if ($diff === $wantDiff) {
            $score += 15;
            $reasons[] = $wantDiff === 'easy' ? 'Beginner friendly' : 'Matches your experience level';
        } elseif ($wantDiff === 'easy' && $diff === 'moderate') {
            $score += 5;
        } elseif ($wantDiff !== 'easy' && $diff === 'easy') {
            $score += 10;
            $reasons[] = 'Easy-care option';
        } elseif ($wantDiff === 'easy' && $diff === 'advanced') {
            // Soft penalty path — still allow but low score contribution
            $score += 0;
        } else {
            $score += 4;
        }

        $purposeScore = 0;
        $tags = $product->tags->pluck('slug')->map(fn ($s) => strtolower((string) $s))->all();
        $benefits = collect($profile->benefits ?? [])->map(fn ($b) => strtolower((string) $b))->all();
        $uses = collect($profile->uses ?? [])->map(fn ($u) => strtolower((string) $u))->all();

        foreach ($criteria['purpose'] as $purpose) {
            if ($this->purposeMatches($purpose, $tags, $benefits, $uses, $profile, $sun)) {
                $purposeScore += 8;
                $reasons[] = $this->purposeReason($purpose);
            }
        }
        $score += min(15, $purposeScore);

        $score = min(100, $score);
        if ($score < 20) {
            return null;
        }

        return [
            'product' => ProductPresenter::card($product),
            'match_score' => $score,
            'match_reasons' => array_values(array_unique($reasons)),
        ];
    }

    private function sunAdjacent(string $a, string $b): bool
    {
        $groups = [
            ['low', 'bright_indirect', 'partial'],
            ['bright_indirect', 'partial', 'full_sun'],
        ];
        foreach ($groups as $g) {
            if (in_array($a, $g, true) && in_array($b, $g, true)) {
                return true;
            }
        }

        return false;
    }

    private function waterAdjacent(string $a, string $b): bool
    {
        $order = ['low' => 0, 'medium' => 1, 'high' => 2];

        return isset($order[$a], $order[$b]) && abs($order[$a] - $order[$b]) === 1;
    }

    private function purposeMatches(
        string $purpose,
        array $tags,
        array $benefits,
        array $uses,
        $profile,
        string $sun,
    ): bool {
        return match ($purpose) {
            'air_purifying' => in_array('air-purifying', $tags, true)
                || collect($benefits)->contains(fn ($b) => str_contains($b, 'air')),
            'low_maintenance' => in_array('low-maintenance', $tags, true)
                || in_array('beginner', $tags, true)
                || ($profile->difficulty_level === 'easy' && $profile->water_requirement === 'low'),
            'decoration' => in_array('ornamental', $uses, true) || in_array('gift', $tags, true),
            'flowers' => in_array('flowering', $tags, true)
                || ! empty($profile->flowering_season)
                || ($profile->plant_kind === 'flowering'),
            'edible' => in_array('culinary', $uses, true)
                || in_array('medicinal', $uses, true)
                || in_array('medicinal', $tags, true),
            'pet_friendly' => ($profile->pet_safety === 'safe') || in_array('pet-safe', $tags, true),
            'shade_loving' => $sun === 'low' || in_array('shade', $tags, true),
            default => false,
        };
    }

    private function purposeReason(string $purpose): string
    {
        return match ($purpose) {
            'air_purifying' => 'Air purifying',
            'low_maintenance' => 'Low maintenance',
            'decoration' => 'Great for decoration',
            'flowers' => 'Offers flowers / seasonal blooms',
            'edible' => 'Edible or kitchen-friendly',
            'pet_friendly' => 'Pet-friendly choice',
            'shade_loving' => 'Shade / low-light friendly',
            default => 'Matches your preferences',
        };
    }
}
