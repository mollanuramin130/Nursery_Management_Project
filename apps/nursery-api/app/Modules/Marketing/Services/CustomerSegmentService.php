<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Marketing\Models\CustomerSegment;
use App\Shared\Exceptions\ApiException;
use App\Shared\Support\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Server-side customer segments — criteria evaluated in SQL where possible.
 *
 * Criteria JSON shape:
 * { "all": [ {"field":"order_count","op":"gte","value":2}, ... ] }
 */
class CustomerSegmentService
{
    public const NON_REVENUE = ['CANCELLED', 'PAYMENT_FAILED'];

    public const ALLOWED_FIELDS = [
        'order_count',
        'total_spent',
        'last_order_days',
        'registered_days',
        'inactive_days',
        'has_subscription',
        'loyalty_balance',
        'marketing_opt_in',
        'has_wishlist',
        'has_open_cart',
        'cart_inactive_hours',
    ];

    public function list(): array
    {
        return CustomerSegment::query()
            ->where('status', '!=', 'deleted')
            ->orderBy('name')
            ->get()
            ->map(fn (CustomerSegment $s) => $this->present($s))
            ->values()
            ->all();
    }

    public function show(int $id): array
    {
        $segment = CustomerSegment::query()->findOrFail($id);

        return $this->present($segment, withCount: true);
    }

    public function create(array $payload, ?int $actorId): CustomerSegment
    {
        $criteria = $payload['criteria_json'] ?? $payload['criteria'] ?? null;
        $this->validateCriteria($criteria);

        $key = $payload['key'] ?? Str::slug($payload['name']);
        $segment = CustomerSegment::query()->create([
            'key' => $key,
            'name' => $payload['name'],
            'description' => $payload['description'] ?? null,
            'criteria_json' => $criteria,
            'is_system' => false,
            'status' => $payload['status'] ?? 'active',
            'created_by' => $actorId,
        ]);

        AuditLogger::log('segment.create', 'customer_segment', $segment->id, null, $segment->toArray(), $actorId);

        return $segment;
    }

    public function update(int $id, array $payload, ?int $actorId): CustomerSegment
    {
        $segment = CustomerSegment::query()->findOrFail($id);
        if ($segment->is_system && isset($payload['criteria_json'])) {
            // Allow description/status edits; criteria changes OK for ops flexibility but log heavily.
        }
        $before = $segment->toArray();
        if (isset($payload['criteria_json']) || isset($payload['criteria'])) {
            $criteria = $payload['criteria_json'] ?? $payload['criteria'];
            $this->validateCriteria($criteria);
            $segment->criteria_json = $criteria;
        }
        foreach (['name', 'description', 'status'] as $field) {
            if (array_key_exists($field, $payload)) {
                $segment->{$field} = $payload[$field];
            }
        }
        $segment->save();
        AuditLogger::log('segment.update', 'customer_segment', $id, $before, $segment->toArray(), $actorId);

        return $segment;
    }

    public function archive(int $id, ?int $actorId): void
    {
        $segment = CustomerSegment::query()->findOrFail($id);
        if ($segment->is_system) {
            throw new ApiException('System segments cannot be archived', 422, 'VALIDATION_ERROR');
        }
        $before = $segment->toArray();
        $segment->status = 'archived';
        $segment->save();
        AuditLogger::log('segment.archive', 'customer_segment', $id, $before, $segment->toArray(), $actorId);
    }

    public function duplicate(int $id, ?int $actorId): CustomerSegment
    {
        $source = CustomerSegment::query()->findOrFail($id);
        $copy = CustomerSegment::query()->create([
            'key' => $source->key.'_copy_'.Str::lower(Str::random(4)),
            'name' => $source->name.' (copy)',
            'description' => $source->description,
            'criteria_json' => $source->criteria_json,
            'is_system' => false,
            'status' => 'active',
            'created_by' => $actorId,
        ]);
        AuditLogger::log('segment.duplicate', 'customer_segment', $copy->id, ['source_id' => $id], $copy->toArray(), $actorId);

        return $copy;
    }

    /**
     * Paginated members — never dump full audience to browser.
     */
    public function members(CustomerSegment $segment, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->buildQuery($segment->criteria_json ?? ['all' => []]);
        $perPage = min(100, max(1, $perPage));

        return $query->paginate($perPage, ['users.id', 'users.name', 'users.email', 'users.status', 'users.created_at'], 'page', $page);
    }

    public function count(CustomerSegment $segment): int
    {
        return $this->buildQuery($segment->criteria_json ?? ['all' => []])->count();
    }

    /**
     * @return Builder<User>
     */
    public function buildQuery(array $criteria): Builder
    {
        $query = User::query()
            ->whereHas('roles', fn ($r) => $r->where('slug', 'customer'))
            ->where('status', 'active');

        $rules = $criteria['all'] ?? [];
        foreach ($rules as $rule) {
            $field = $rule['field'] ?? null;
            $op = $rule['op'] ?? 'eq';
            $value = $rule['value'] ?? null;
            if (! $field || ! in_array($field, self::ALLOWED_FIELDS, true)) {
                continue;
            }
            $this->applyRule($query, $field, $op, $value);
        }

        return $query;
    }

    private function applyRule(Builder $query, string $field, string $op, mixed $value): void
    {
        $opSql = $this->sqlOp($op);
        $dayDiff = fn (string $col) => $this->dayDiffSql($col);
        $hourDiff = fn (string $col) => $this->hourDiffSql($col);
        $deletedOrders = $this->ordersSoftDeleteClause();

        match ($field) {
            'order_count' => $query->whereRaw(
                "(SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id AND orders.status NOT IN (?,?) {$deletedOrders}) {$opSql} ?",
                [...self::NON_REVENUE, $value]
            ),
            'total_spent' => $query->whereRaw(
                "(SELECT COALESCE(SUM(grand_total),0) FROM orders WHERE orders.user_id = users.id AND orders.status NOT IN (?,?) {$deletedOrders}) {$opSql} ?",
                [...self::NON_REVENUE, $value]
            ),
            'last_order_days', 'inactive_days' => $query->whereRaw(
                '(SELECT '.$dayDiff('MAX(COALESCE(placed_at, created_at))')." FROM orders WHERE orders.user_id = users.id AND orders.status NOT IN (?,?) {$deletedOrders}) {$opSql} ?",
                [...self::NON_REVENUE, $value]
            ),
            'registered_days' => $query->whereRaw($dayDiff('users.created_at')." {$opSql} ?", [$value]),
            'has_subscription' => (bool) $value
                ? $query->whereExists(fn ($q) => $q->selectRaw('1')->from('subscriptions')
                    ->whereColumn('subscriptions.user_id', 'users.id')
                    ->where('subscriptions.status', 'ACTIVE')
                    ->whereNull('subscriptions.deleted_at'))
                : $query->whereNotExists(fn ($q) => $q->selectRaw('1')->from('subscriptions')
                    ->whereColumn('subscriptions.user_id', 'users.id')
                    ->where('subscriptions.status', 'ACTIVE')
                    ->whereNull('subscriptions.deleted_at')),
            'loyalty_balance' => $query->whereExists(fn ($q) => $q->selectRaw('1')->from('loyalty_accounts')
                ->whereColumn('loyalty_accounts.user_id', 'users.id')
                ->where('loyalty_accounts.balance', $opSql, $value)),
            'marketing_opt_in' => (bool) $value
                ? $query->whereHas('customerProfile', fn ($p) => $p->where('marketing_opt_in', true))
                : $query->where(function ($q) {
                    $q->whereDoesntHave('customerProfile')
                        ->orWhereHas('customerProfile', fn ($p) => $p->where('marketing_opt_in', false));
                }),
            'has_wishlist' => (bool) $value
                ? $query->whereExists(fn ($q) => $q->selectRaw('1')->from('wishlists')
                    ->whereColumn('wishlists.user_id', 'users.id')->whereNull('wishlists.deleted_at'))
                : $query->whereNotExists(fn ($q) => $q->selectRaw('1')->from('wishlists')
                    ->whereColumn('wishlists.user_id', 'users.id')->whereNull('wishlists.deleted_at')),
            'has_open_cart' => (bool) $value
                ? $query->whereExists(fn ($q) => $q->selectRaw('1')->from('carts')
                    ->whereColumn('carts.user_id', 'users.id')->where('carts.status', 'active')->whereNull('carts.deleted_at'))
                : $query->whereNotExists(fn ($q) => $q->selectRaw('1')->from('carts')
                    ->whereColumn('carts.user_id', 'users.id')->where('carts.status', 'active')->whereNull('carts.deleted_at')),
            'cart_inactive_hours' => $query->whereExists(fn ($q) => $q->selectRaw('1')->from('carts')
                ->whereColumn('carts.user_id', 'users.id')
                ->where('carts.status', 'active')
                ->whereNull('carts.deleted_at')
                ->whereRaw($hourDiff('carts.updated_at')." {$opSql} ?", [$value])),
            default => null,
        };
    }

    private function dayDiffSql(string $columnExpr): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST((julianday('now') - julianday({$columnExpr})) AS INTEGER)"
            : "TIMESTAMPDIFF(DAY, {$columnExpr}, NOW())";
    }

    private function hourDiffSql(string $columnExpr): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST((julianday('now') - julianday({$columnExpr})) * 24 AS INTEGER)"
            : "TIMESTAMPDIFF(HOUR, {$columnExpr}, NOW())";
    }

    private function ordersSoftDeleteClause(): string
    {
        return Schema::hasColumn('orders', 'deleted_at') ? 'AND orders.deleted_at IS NULL' : '';
    }

    private function sqlOp(string $op): string
    {
        return match ($op) {
            'gt' => '>',
            'gte' => '>=',
            'lt' => '<',
            'lte' => '<=',
            'neq' => '<>',
            default => '=',
        };
    }

    private function validateCriteria(?array $criteria): void
    {
        if (! is_array($criteria) || ! isset($criteria['all']) || ! is_array($criteria['all'])) {
            throw new ApiException('criteria_json must contain an "all" array of rules', 422, 'VALIDATION_ERROR');
        }
        foreach ($criteria['all'] as $rule) {
            $field = $rule['field'] ?? null;
            if (! in_array($field, self::ALLOWED_FIELDS, true)) {
                throw new ApiException("Unsupported segment field: {$field}", 422, 'VALIDATION_ERROR');
            }
        }
    }

    private function present(CustomerSegment $s, bool $withCount = false): array
    {
        $row = [
            'id' => $s->id,
            'key' => $s->key,
            'name' => $s->name,
            'description' => $s->description,
            'criteria' => $s->criteria_json,
            'criteria_json' => $s->criteria_json,
            'is_system' => (bool) $s->is_system,
            'status' => $s->status,
            'created_at' => optional($s->created_at)?->toIso8601String(),
            'updated_at' => optional($s->updated_at)?->toIso8601String(),
        ];
        if ($withCount || request()->boolean('include_count')) {
            $count = $this->count($s);
            $row['customer_count'] = $count;
            $row['member_count'] = $count;
        }

        return $row;
    }

    public function seedDefaults(): void
    {
        $defaults = [
            [
                'key' => 'new_customers',
                'name' => 'New Customers',
                'description' => 'Registered within last 30 days with zero qualifying orders',
                'criteria_json' => ['all' => [
                    ['field' => 'registered_days', 'op' => 'lte', 'value' => 30],
                    ['field' => 'order_count', 'op' => 'eq', 'value' => 0],
                ]],
            ],
            [
                'key' => 'returning_customers',
                'name' => 'Returning Customers',
                'description' => 'At least 2 revenue-qualifying orders',
                'criteria_json' => ['all' => [
                    ['field' => 'order_count', 'op' => 'gte', 'value' => 2],
                ]],
            ],
            [
                'key' => 'high_value',
                'name' => 'High Value Customers',
                'description' => 'Lifetime spend >= 5000 (store currency units)',
                'criteria_json' => ['all' => [
                    ['field' => 'total_spent', 'op' => 'gte', 'value' => 5000],
                ]],
            ],
            [
                'key' => 'inactive_90d',
                'name' => 'Inactive Customers (90d)',
                'description' => 'Last qualifying order 90+ days ago',
                'criteria_json' => ['all' => [
                    ['field' => 'order_count', 'op' => 'gte', 'value' => 1],
                    ['field' => 'inactive_days', 'op' => 'gte', 'value' => 90],
                ]],
            ],
            [
                'key' => 'cart_abandoners',
                'name' => 'Cart Abandoners',
                'description' => 'Active cart inactive for configured abandonment hours (default 24)',
                'criteria_json' => ['all' => [
                    ['field' => 'has_open_cart', 'op' => 'eq', 'value' => true],
                    ['field' => 'cart_inactive_hours', 'op' => 'gte', 'value' => (int) config('marketing.abandoned_cart.inactive_hours', 24)],
                ]],
            ],
            [
                'key' => 'subscription_customers',
                'name' => 'Subscription Customers',
                'description' => 'Has an ACTIVE subscription',
                'criteria_json' => ['all' => [
                    ['field' => 'has_subscription', 'op' => 'eq', 'value' => true],
                ]],
            ],
            [
                'key' => 'loyalty_members',
                'name' => 'Loyalty Members',
                'description' => 'Loyalty balance >= 1',
                'criteria_json' => ['all' => [
                    ['field' => 'loyalty_balance', 'op' => 'gte', 'value' => 1],
                ]],
            ],
            [
                'key' => 'wishlist_users',
                'name' => 'Wishlist Users',
                'description' => 'Has at least one wishlist item',
                'criteria_json' => ['all' => [
                    ['field' => 'has_wishlist', 'op' => 'eq', 'value' => true],
                ]],
            ],
            [
                'key' => 'marketing_opted_in',
                'name' => 'Marketing Opted-In',
                'description' => 'marketing_opt_in = true',
                'criteria_json' => ['all' => [
                    ['field' => 'marketing_opt_in', 'op' => 'eq', 'value' => true],
                ]],
            ],
        ];

        foreach ($defaults as $row) {
            CustomerSegment::query()->updateOrCreate(
                ['key' => $row['key']],
                [
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'criteria_json' => $row['criteria_json'],
                    'is_system' => true,
                    'status' => 'active',
                ]
            );
        }
    }
}
