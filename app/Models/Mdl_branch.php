<?php

namespace App\Models;

use App\Models\BaseModel;

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
    public function getBranchIdByName(string $name)
    {
        $sql = "SELECT id FROM branches WHERE name = :name: AND is_active = 1";
        $result = $this->db->query($sql, ['name' => $name])->getRow();
        return $result ? $result->id : null;
    }
    // Batas Bawah Raw Query
}
