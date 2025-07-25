<?php

namespace App\Models;

use App\Models\BaseModel;

class Mdl_currency extends BaseModel
{
    protected $table = 'currencies';
    protected $primaryKey = 'id';

    protected $allowedFields = ['tenant_id', 'code', 'name', 'symbol', 'is_active'];
    protected $useTimestamps = false;

    protected $auditEnabled = true;

    public function getAllCurrenciesRaw($tenantId)
    {
        $sql = "SELECT 
                    name,
                    code,
                    symbol
                FROM currencies
                WHERE tenant_id = ? AND is_active = 1
                ORDER BY id ASC";

        return $this->db->query($sql, [$tenantId])->getResultArray();
    }

    public function getCurrencyByIdRaw($tenantId, $clientId)
    {
        $sql = "SELECT 
                    code,
                    name,
                    symbol
                FROM currencies
                WHERE id = ? AND tenant_id = ? AND is_active = 1
                LIMIT 1";

        return $this->db->query($sql, [$clientId, $tenantId])->getRowArray();
    }
}
