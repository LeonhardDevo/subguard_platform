<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Customer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;

class CustomerService
{
    public function list(int $userId, array $filters = []): LengthAwarePaginator
    {
        $query = Customer::forUser($userId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $operator) {
                $q->where('name', $operator, "%{$search}%")
                  ->orWhere('email', $operator, "%{$search}%")
                  ->orWhere('phone', $operator, "%{$search}%")
                  ->orWhere('external_id', $operator, "%{$search}%");
            });
        }

        $allowedSorts = ['created_at', 'name', 'email', 'status'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts) ? $filters['sort_by'] : 'created_at';
        $sortDir = in_array(strtolower($filters['sort_dir'] ?? ''), ['asc', 'desc']) ? $filters['sort_dir'] : 'desc';
        $perPage = min($filters['per_page'] ?? 15, 100);

        return $query->orderBy($sortBy, $sortDir)->paginate($perPage);
    }

    public function create(int $userId, array $data): Customer
    {
        $data['user_id'] = $userId;

        $customer = Customer::create($data);

        AuditLog::log('customer.created', $customer, null, $data);

        return $customer;
    }

    public function update(Customer $customer, array $data): Customer
    {
        $oldValues = $customer->toArray();

        $customer->update($data);

        AuditLog::log('customer.updated', $customer, $oldValues, $data);

        return $customer->fresh();
    }

    public function delete(Customer $customer): void
    {
        AuditLog::log('customer.deleted', $customer, $customer->toArray());

        $customer->delete();
    }

    public function importCsv(int $userId, string $csvContent): array
    {
        $reader = Reader::createFromString($csvContent);
        $reader->setHeaderOffset(0);

        $records = $reader->getRecords();
        $imported = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($records as $offset => $record) {
                try {
                    $this->validateCsvRecord($record);

                    Customer::updateOrCreate(
                        [
                            'user_id' => $userId,
                            'email' => $record['email'],
                        ],
                        [
                            'name' => $record['name'],
                            'phone' => $record['phone'] ?? null,
                            'external_id' => $record['external_id'] ?? null,
                            'status' => $record['status'] ?? 'active',
                        ]
                    );

                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'row' => $offset + 2,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'imported' => $imported,
            'errors' => $errors,
            'total_errors' => count($errors),
        ];
    }

    private function validateCsvRecord(array $record): void
    {
        if (empty($record['name'])) {
            throw new \InvalidArgumentException('Name is required');
        }
        if (empty($record['email']) || !filter_var($record['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Valid email is required');
        }
    }
}
