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


    public function getBranchNameById($id)
    {
        $sql = "SELECT name FROM branches WHERE id = ? AND is_active = 1 LIMIT 1";
        $result = $this->db->query($sql, [$id])->getRowArray();
        return $result ? $result['name'] : null;
    }

    public function getAllBranchesRaw($tenantId)
    {
        $sql = "SELECT b.*
                FROM branches b
                JOIN tenants t ON t.id = b.tenant_id
                WHERE b.tenant_id = ? AND b.is_active = 1 AND t.is_active=1";

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

    public function getBranchIdByName($name)
    {
        $sql = "SELECT id FROM branches WHERE name = ? AND is_active = 1";
        $result = $this->db->query($sql, $name)->getRow();
        return $result ? $result->id : null;
    }
    
    public function getCountBranch($tenantId){
        $sql = "SELECT count(1) as branch, te.max_branch 
                FROM branches br INNER JOIN tenants te 
                ON br.tenant_id=te.id 
                WHERE te.is_active=1 AND br.is_active=1 AND te.id=?";
        $query = $this->db->query($sql,$tenantId);
        return $query->getRow();
    }

    public function insert_branch($data){
        $branch = $this->db->table("branches");
        if (!$branch->insert($data)){
            $error = (object) [
                "status"  => false,
                "message" => $this->db->error()
            ];
        }

        $error = (object) [
            "status"  => true,
            "message" => []
        ];
        
        return $error;
    }
    
    public function update_branch($id, $data){
        $branch = $this->db->table("branches");
        $branch->where($this->primaryKey);
        $branch->update($data);
        
        $success =  $this->db->affectedRows() > 0;
        $error = (object) [
            "status"  => $success,
            "message" => $success ? [] : $this->db->error()
        ];
    
        return $error;
    }
    
    public function delete_branch($id){
        $branch = $this->db->table("branches");
        $branch->set('is_active', 0)
            ->where($this->primaryKey, $id)
            ->update();
        return $this->db->affectedRows() > 0 ? true:false;
    }
    
    
    // Batas Bawah Raw Query
}
