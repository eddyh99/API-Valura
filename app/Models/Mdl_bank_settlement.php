<?php

namespace App\Models;

use App\Models\BaseModel;

class Mdl_bank_settlement extends BaseModel
{
    protected $table      = 'bank_transactions';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'tenant_id',
        'currency_id',
        'bank_name',
        'account_number',
        'amount_foreign',
        'rate_used',
        'amount_settled',
        'local_total',
        'transaction_date',
        'created_by',
        'created_at'
    ];

    protected $useTimestamps = false;
    protected $auditEnabled  = true;

    public function getAllSettlementsRaw($tenantId)
    {
        $sql = "
            SELECT 
                tl.currency_id,
                c.code AS currency_code,
                SUM(tl.amount_foreign) AS pending_amount
            FROM 
                transaction_lines tl
            JOIN 
                transactions t ON t.id = tl.transaction_id
            JOIN 
                currencies c ON c.id = tl.currency_id
            WHERE 
                t.tenant_id = ?
                AND tl.settlement_status = 'PENDING'
                AND c.is_active = 1
            GROUP BY 
                tl.currency_id, c.code
            ORDER BY 
                c.code ASC
        ";

        return $this->db->query($sql, [$tenantId])->getResultArray();
    }

    public function getTransactLinesByCurrIdRaw($tenantId, $currencyId)
    {
        $sql = "
            SELECT 
                tl.id,
                tl.transaction_id,
                tl.amount_foreign,
                tl.settlement_status
            FROM 
                transaction_lines tl
            JOIN 
                transactions t ON t.id = tl.transaction_id
            WHERE 
                tl.currency_id = ?
                AND tl.settlement_status = 'PENDING'
                AND t.tenant_id = ?
            ORDER BY 
                tl.id ASC
        ";

        return $this->db->query($sql, [$currencyId, $tenantId])->getResultArray();
    }
}
