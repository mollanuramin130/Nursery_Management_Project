<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\AnalyticsService;
use App\Modules\Admin\Support\AnalyticsDateRange;
use App\Shared\Exceptions\ApiException;
use App\Shared\Support\ApiResponse;
use App\Shared\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    public function overview(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->analytics->overview($this->range($request)),
            'Analytics overview retrieved successfully',
        );
    }

    public function sales(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->analytics->sales(
                $this->range($request),
                $request->query('payment_method'),
            ),
            'Sales analytics retrieved successfully',
        );
    }

    public function orders(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->analytics->orders($this->range($request)),
            'Order analytics retrieved successfully',
        );
    }

    public function products(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->analytics->products(
                $this->range($request),
                (string) $request->query('sort', 'revenue'),
                min(max((int) $request->query('limit', 50), 1), 200),
            ),
            'Product analytics retrieved successfully',
        );
    }

    public function categories(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->analytics->categories($this->range($request)),
            'Category analytics retrieved successfully',
        );
    }

    public function customers(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->analytics->customers($this->range($request)),
            'Customer analytics retrieved successfully',
        );
    }

    public function inventory(): JsonResponse
    {
        return ApiResponse::success(
            $this->analytics->inventory(),
            'Inventory analytics retrieved successfully',
        );
    }

    public function campaigns(): JsonResponse
    {
        return ApiResponse::success(
            $this->analytics->campaigns(),
            'Campaign analytics status retrieved',
        );
    }

    public function coupons(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->analytics->coupons($this->range($request)),
            'Coupon analytics retrieved successfully',
        );
    }

    public function returns(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->analytics->returns($this->range($request)),
            'Returns analytics retrieved successfully',
        );
    }

    public function seasonal(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->analytics->seasonal($this->range($request)),
            'Seasonal demand analytics retrieved successfully',
        );
    }

    public function payments(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->analytics->payments($this->range($request)),
            'Payment analytics retrieved successfully',
        );
    }

    public function plants(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->analytics->plants($this->range($request)),
            'Plant taxonomy analytics retrieved successfully',
        );
    }

    public function reviewsAnalytics(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->analytics->reviews($this->range($request)),
            'Review analytics retrieved successfully',
        );
    }

    public function search(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->analytics->search($this->range($request)),
            'Search analytics retrieved successfully',
        );
    }

    public function cohorts(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->analytics->cohorts($this->range($request)),
            'Cohort analytics retrieved successfully',
        );
    }

    public function attention(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->analytics->attention($this->range($request)),
            'Inventory attention analytics retrieved successfully',
        );
    }

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:sales,products,orders_status,returns,coupons,payments,search'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'preset' => ['nullable', 'string'],
        ]);

        $range = $this->range($request);
        $type = $validated['type'];
        $rows = $this->analytics->exportRows($type, $range);

        if (count($rows) > 5000) {
            throw new ApiException(
                'Export too large for synchronous download. Narrow the date range (max 5000 rows).',
                422,
                'EXPORT_TOO_LARGE',
            );
        }

        AuditLogger::log(
            'analytics.export',
            'analytics',
            null,
            null,
            ['type' => $type, 'rows' => count($rows)],
            $request->user()?->id,
            $range->meta(),
        );

        $filename = 'greenleaf-'.$type.'-'.$range->from->toDateString().'-'.$range->to->toDateString().'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            if ($rows === []) {
                fputcsv($out, ['message']);
                fputcsv($out, ['No rows']);
                fclose($out);

                return;
            }
            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => is_scalar($v) || $v === null ? $v : json_encode($v), $row));
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function range(Request $request): AnalyticsDateRange
    {
        try {
            return AnalyticsDateRange::fromRequest(
                $request->query('from'),
                $request->query('to'),
                $request->query('preset'),
            );
        } catch (InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 422, 'VALIDATION_ERROR');
        }
    }
}
