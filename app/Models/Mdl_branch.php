<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Mdl_tenant;

class Mdl_branch extends BaseModel
{
    protected $table      = 'branches';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'tenant_id', 'name', 'address', 'phone', 'is_active', 'created_at'
    ];

    protected $useTimestamps = false;
    protected $auditEnabled = true;

    // Raw Query
    public function __construct()
    {
        parent::__construct();
        $this->tenants = new Mdl_tenant();
    }

    public function getBranchNameById($id)
    {
        $sql = "SELECT name FROM branches WHERE id = ? AND is_active = 1 LIMIT 1";
        $result = $this->db->query($sql, [$id])->getRowArray();
        return $result ? $result['name'] : null;
    }

    public function getAllBranchesRaw($tenantId)
    {
        $sql = "SELECT 
                    b.id,
                    b.name,
                    b.address,
                    b.phone,
                    b.is_active,
                    t.max_branch
                FROM branches b
                JOIN tenants t ON t.id = b.tenant_id
                WHERE b.tenant_id = ? AND b.is_active = 1";

        return $this->db->query($sql, [$tenantId])->getResultArray();
    }

    public function getBranchByIdRaw($tenantId, $branchId)
    {
        $sql = "SELECT 
                    b.id,
                    b.name,
                    b.address,
                    b.phone
                FROM branches b
                JOIN tenants t ON t.id = b.tenant_id
                WHERE b.tenant_id = ? AND b.id = ? AND b.is_active = 1
                LIMIT 1";

        return $this->db->query($sql, [$tenantId, $branchId])->getRowArray();
    }

    public function getBranchIdByName(string $name)
    {
        $sql = "SELECT id FROM branches WHERE name = :name: AND is_active = 1";
        $result = $this->db->query($sql, ['name' => $name])->getRow();
        return $result ? $result->id : null;
    }
    // Batas Bawah Raw Query
}
