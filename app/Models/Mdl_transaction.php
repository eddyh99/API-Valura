<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Mdl_client;

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
    public function __construct()
    {
        parent::__construct();
        $this->clients = new Mdl_client();
    }
    // Show by ID 
    public function getTransactionByIdRaw($tenantId, $transactionId)
    {
        
        $sql = "SELECT 
                    tr.id AS transaction_id,
                    tr.tenant_id,
                    tr.transaction_type,

                    CONCAT('[', GROUP_CONCAT(
                        JSON_OBJECT(
                            'det_id', tl.id,
                            'currency_id', tl.currency_id,
                            'amount_foreign', tl.amount_foreign,
                            'amount_local', tl.amount_local,
                            'rate_used', tl.rate_used,
                            'currency_code', c.code,
                            'currency_name', c.name
                        )
                    ), ']') AS detail

                FROM transactions tr
                JOIN transaction_lines tl ON tl.transaction_id = tr.id
                JOIN currencies c ON c.id = tl.currency_id

                WHERE tr.tenant_id = ? AND tr.id = ?
                GROUP BY tr.id;";

        return $this->db->query($sql, [$tenantId, $transactionId])->getRowArray();
    }

    // Create
    public function insertTransactionRaw(array $data, array $clientData, array $detail)
    {
        $this->db->transStart();
    
        try {
            // 1. Save or reuse client
            $clientId = $this->clients->insertClientIfNotExistRaw($clientData);
            $data['client_id'] = $clientId;
    
            // 2. Insert transaction
            $this->db->table($this->table)->insert($data);
            $transactionId = $this->db->insertID();
    
            // 3. Make sure $detail is an array of arrays
            if (!isset($detail[0]) || !is_array($detail[0])) {
                $detail = [$detail]; // wrap if it's a single row
            }

            // 4. Add transaction_id to each detail row
            foreach ($detail as &$row) {
                $row['transaction_id'] = $transactionId;
            }

    
            // 4. Batch insert lines
            $this->db->table('transaction_lines')->insertBatch($detail);
    
            $this->db->transComplete();
    
            if ($this->db->transStatus() === false) {
                $this->db->transRollback();
                return false;
            }
    
            return $transactionId;
    
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Transaction insert failed: ' . $e->getMessage());
            return false;
        }
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
