<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\SearchEvent;
use Illuminate\Http\Request;
use Throwable;

class SearchEventRecorder
{
    /**
     * Best-effort analytics — never throws to callers.
     */
    public static function record(Request $request, string $query, int $resultsCount): void
    {
        $query = trim($query);
        if ($query === '' || mb_strlen($query) < 2) {
            return;
        }

        try {
            $truncated = mb_substr($query, 0, 200);
            SearchEvent::query()->create([
                'query' => $truncated,
                'normalized_query' => mb_strtolower($truncated),
                'results_count' => max(0, $resultsCount),
                'user_id' => $request->user()?->id,
                'platform' => mb_substr((string) $request->header('X-Platform', 'unknown'), 0, 40),
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Swallow
        }
    }
}
