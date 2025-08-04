<?php

namespace App\Models;

use App\Models\BaseModel;

class Mdl_bank extends BaseModel
{
    protected $table      = 'banks';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'tenant_id',
        'name',
        'account_no',
        'branch',
        'is_active'
    ];

    protected $useTimestamps = false;
    protected $auditEnabled  = true;

    public function getAllBanksRaw($tenantId)
    {
        $sql = "SELECT 
                    id,
                    tenant_id,
                    name,
                    account_no,
                    branch,
                    is_active
                FROM banks
                WHERE tenant_id = ? AND is_active = 1
                ORDER BY id ASC";

        return $this->db->query($sql, [$tenantId])->getResultArray();
    }

    // public function getBankById($tenantId, $id)
    // {
    //     return $this->where('id', $id)
    //                 ->where('is_active', 1)
    //                 ->where('tenant_id', $tenantId)
    //                 ->first();
    // }
    public function getBankByIdRaw($tenantId, $bankId)
    {
        $sql = "SELECT 
                    id,
                    tenant_id,
                    name,
                    account_no,
                    branch,
                    is_active
                FROM banks
                WHERE id = ? AND tenant_id = ? AND is_active = 1
                LIMIT 1";

        return $this->db->query($sql, [$bankId, $tenantId])->getRowArray();
    }
}
