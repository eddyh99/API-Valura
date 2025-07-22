<?php

namespace App\Models;

use App\Models\BaseModel;

class Mdl_transaction extends BaseModel
{
    protected $table            = 'transactions';
    protected $primaryKey       = 'id';
    protected $allowedFields    = [
        'tenant_id', 'branch_id', 'client_id', 'transaction_type',
        'payment_type_id', 'bank_id', 'transaction_date', 'created_by', 'created_at'
    ];
    protected $useTimestamps    = false;
    protected $auditEnabled     = true;

    // Raw Query
    public function insertTransactionRaw($data)
    {
        $sql = "INSERT INTO transactions (tenant_id, branch_id, client_id, transaction_type, payment_type_id, bank_id, transaction_date, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        return $this->db->query($sql, [
            $data['tenant_id'],
            $data['branch_id'],
            $data['client_id'],
            $data['transaction_type'],
            $data['payment_type_id'],
            $data['bank_id'],
            $data['transaction_date'],
            $data['created_by']
        ]);
    }
    // Batas Bawah Raw Query

    // public function getDailyReport(string $startDate, string $endDate, ?int $branchId = null): array
    // {
    //     $sql = "
    //         SELECT 
    //             t.id AS transaction_id,
    //             t.transaction_type,
    //             t.transaction_date,
    //             t.client_id,
    //             t.branch_id,
    //             t.created_by,
    //             t.created_at,
    //             tl.currency_id,
    //             tl.amount_foreign,
    //             tl.amount_local,
    //             tl.rate_used,
    //             c.name AS currency_name
    //         FROM transactions t
    //         JOIN transaction_lines tl ON tl.transaction_id = t.id
    //         LEFT JOIN currencies c ON c.id = tl.currency_id
    //         WHERE t.transaction_date >= ? AND t.transaction_date <= ?
    //     ";

    //     $params = [$startDate, $endDate];

    //     if ($branchId !== null) {
    //         $sql .= " AND t.branch_id = ?";
    //         $params[] = $branchId;
    //     }

    //     $sql .= " ORDER BY t.transaction_date ASC";

    //     $query = $this->db->query($sql, $params);
    //     $results = $query->getResultArray();

    //     // Group by transaction id, build nested array for lines
    //     $grouped = [];
    //     foreach ($results as $row) {
    //         $trxId = $row['transaction_id'];
    //         if (!isset($grouped[$trxId])) {
    //             $grouped[$trxId] = [
    //                 'transaction_id' => $trxId,
    //                 'transaction_type' => $row['transaction_type'],
    //                 'transaction_date' => $row['transaction_date'],
    //                 'client_id' => $row['client_id'],
    //                 'branch_id' => $row['branch_id'],
    //                 'created_by' => $row['created_by'],
    //                 'created_at' => $row['created_at'],
    //                 'lines' => []
    //             ];
    //         }
    //         $grouped[$trxId]['lines'][] = [
    //             'currency_id' => $row['currency_id'],
    //             'currency_name' => $row['currency_name'],
    //             'amount_foreign' => (float) $row['amount_foreign'],
    //             'rate_used' => (float) $row['rate_used'],
    //             'amount_local' => (float) $row['amount_local'],
    //         ];
    //     }

    //     return array_values($grouped);
    // }
}
