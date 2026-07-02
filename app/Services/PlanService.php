<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Plan;
use Illuminate\Pagination\LengthAwarePaginator;

class PlanService
{
    public function list(int $userId, array $filters = []): LengthAwarePaginator
    {
        $query = Plan::forUser($userId);

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (!empty($filters['search'])) {
            $query->where('name', 'ilike', "%{$filters['search']}%");
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $perPage = min($filters['per_page'] ?? 15, 100);

        return $query->orderBy($sortBy, $sortDir)->paginate($perPage);
    }

    public function create(int $userId, array $data): Plan
    {
        $data['user_id'] = $userId;

        $plan = Plan::create($data);

        AuditLog::log('plan.created', $plan, null, $data);

        return $plan;
    }

    public function update(Plan $plan, array $data): Plan
    {
        $oldValues = $plan->toArray();

        $plan->update($data);

        AuditLog::log('plan.updated', $plan, $oldValues, $data);

        return $plan->fresh();
    }

    public function delete(Plan $plan): void
    {
        if ($plan->subscriptions()->active()->exists()) {
            throw new \RuntimeException('Cannot delete a plan with active subscriptions');
        }

        AuditLog::log('plan.deleted', $plan, $plan->toArray());

        $plan->delete();
    }
}
