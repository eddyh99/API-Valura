<?php

namespace App\Models;

use App\Models\BaseModel;

class Mdl_role extends BaseModel
{
    protected $table      = 'roles';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'tenant_id', 'name', 'permissions', 'is_active'
    ];

    protected $useTimestamps = false;
    protected $auditEnabled = true;

    public function getAllRolesRaw($tenantId)
    {
        $sql = "SELECT 
                    *
                FROM roles
                WHERE tenant_id = ? AND is_active = 1
                ORDER BY id ASC";

        return $this->db->query($sql, [$tenantId])->getResultArray();
    }

    public function getRoleByIdRaw($tenantId, $currencyId)
    {
        $sql = "SELECT 
                    *
                FROM roles
                WHERE id = ? AND tenant_id = ? AND is_active = 1
                LIMIT 1";

        return $this->db->query($sql, [$currencyId, $tenantId])->getRowArray();
    }
}
