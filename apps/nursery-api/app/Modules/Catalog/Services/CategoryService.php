<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Category;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CategoryService
{
    public function list(?int $parentId = null, bool $flat = false): array
    {
        if ($flat) {
            return Category::query()->active()->orderBy('sort_order')->get()
                ->map(fn (Category $c) => $this->toArray($c, false))
                ->values()
                ->all();
        }

        $query = Category::query()->active()->with(['children' => fn ($q) => $q->active()->orderBy('sort_order')]);

        if ($parentId) {
            $query->where('parent_id', $parentId);
        } else {
            $query->whereNull('parent_id');
        }

        return $query->orderBy('sort_order')->get()
            ->map(fn (Category $c) => $this->toArray($c, true))
            ->values()
            ->all();
    }

    public function findBySlug(string $slug): array
    {
        $category = Category::query()
            ->active()
            ->with(['children' => fn ($q) => $q->active()->orderBy('sort_order')])
            ->where('slug', $slug)
            ->first();

        if (! $category) {
            throw new NotFoundHttpException('Category not found');
        }

        return $this->toArray($category, true);
    }

    private function toArray(Category $category, bool $withChildren): array
    {
        $data = [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'image_url' => $category->image_url,
            'parent_id' => $category->parent_id,
            'description' => $category->description,
        ];

        if ($withChildren) {
            $data['children'] = $category->children
                ->map(fn (Category $child) => $this->toArray($child, false) + ['children' => []])
                ->values()
                ->all();
        }

        return $data;
    }
}
