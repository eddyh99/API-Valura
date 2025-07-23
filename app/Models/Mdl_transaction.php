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

    // Show by ID 
    public function getTransactionByIdRaw($tenantId, $transactionId)
    {
        $sql = "SELECT id, tenant_id, transaction_type
                FROM transactions 
                WHERE tenant_id = ? AND id = ? 
                LIMIT 1";

        return $this->db->query($sql, [$tenantId, $transactionId])->getRowArray();
    }

    // Create
    public function insertTransactionRaw($tenantId, $branchId, $clientId, $data, $userId, $transactionDate, $transactionType)
    {
        $sql = "INSERT INTO transactions (tenant_id, branch_id, client_id, transaction_type, payment_type_id, bank_id, transaction_date, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $this->db->query($sql, [
            $tenantId,
            $branchId,
            $clientId,
            $transactionType,
            $data['payment_type_id'] ?? null,
            $data['bank_id'] ?? null,
            $transactionDate,
            $userId
        ]);

        return $this->db->insertID();
    }

    // Update
    public function getTransactionToUpdateByIdRaw($transactionId)
    {
        $sql = "SELECT id, transaction_type FROM transactions WHERE id = ?";
        $row = $this->db->query($sql, [$transactionId])->getRowArray();
        return $row;
    }
    public function getTransactionById($id)
    {
        $sql = "SELECT id, tenant_id, transaction_type 
                FROM transactions 
                WHERE id = ? 
                LIMIT 1";

        $query = $this->db->query($sql, [$id]);
        return $query->getRowArray();
    }

    // Delete
    public function deleteTransactionById($transactionId, $tenantId)
    {
        $sql = "DELETE FROM transactions 
                WHERE id = ? AND tenant_id = ?";

        $this->db->query($sql, [$transactionId, $tenantId]);

        return $this->db->affectedRows() > 0;
    }
}
