<?php

namespace App\Models;

use App\Models\BaseModel;

class Mdl_agent extends BaseModel
{
    protected $table      = 'agents';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'tenant_id',
        'name',
        'address',
        'phone',
        'created_by',
        'is_active'
    ];

    protected $useTimestamps = false;
    protected $auditEnabled  = true;

    public function getAllAgentsRaw($tenantId)
    {
        $sql = "SELECT
                    id,
                    tenant_id, 
                    name,
                    address,
                    phone,
                    is_active
                FROM agents
                WHERE tenant_id = ? AND is_active = 1
                ORDER BY id ASC";

        return $this->db->query($sql, [$tenantId])->getResultArray();
    }

    public function getAgentByIdRaw($tenantId, $agentId)
    {
        $sql = "SELECT
                    id,
                    name,
                    address,
                    phone
                FROM agents
                WHERE id = ? AND tenant_id = ? AND is_active = 1
                LIMIT 1";

        return $this->db->query($sql, [$agentId, $tenantId])->getRowArray();
    }
}
