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

    public function insert_branch($data)
    {
        $id = $this->insert($data, true); // pakai method bawaan dari BaseModel

        if (!$id) {
            return (object) [
                'status'  => false,
                'message' => $this->errors(), // kalau pakai Validation bawaan Model
            ];
        }

        return (object) [
            'status'  => true,
            'message' => [],
            'id'      => $id,
        ];
    }
    // public function insert_branch($data){
    //     $branch = $this->db->table("branches");
    //     if (!$branch->insert($data)){
    //         $error = (object) [
    //             "status"  => false,
    //             "message" => $this->db->error()
    //         ];
    //     }

    //     $error = (object) [
    //         "status"  => true,
    //         "message" => []
    //     ];
        
    //     return $error;
    // }
    
    public function update_branch($id, $data)
    {
        $success = $this->update($id, $data); // <- pakai method bawaan Model

        if (!$success) {
            return (object)[
                'status'  => false,
                'message' => $this->errors() ?: $this->db->error(),
            ];
        }

        return (object)[
            'status'  => true,
            'message' => [],
        ];
    }
    // public function update_branch($id, $data)
    // {
    //     try {
    //         $branch = $this->db->table("branches");

    //         $branch->where($this->primaryKey, $id);
    //         $branch->update($data);

    //         $affected = $this->db->affectedRows();

    //         if ($affected > 0) {
    //             return (object)[
    //                 "status"  => true,
    //                 "message" => "Cabang berhasil diperbarui."
    //             ];
    //         }

    //         // Tidak ada baris yang berubah
    //         return (object)[
    //             "status"  => false,
    //             "message" => "Tidak ada data yang diubah atau ID tidak ditemukan."
    //         ];
    //     } catch (\Throwable $e) {
    //         // Tangkap exception (misalnya karena query error)
    //         return (object)[
    //             "status"  => false,
    //             "message" => "Exception: " . $e->getMessage()
    //         ];
    //     }
    // }
    // public function update_branch($id, $data){
    //     $branch = $this->db->table("branches");
    //     $branch->where($this->primaryKey);
    //     $branch->update($data);
        
    //     $success =  $this->db->affectedRows() > 0;
    //     $error = (object) [
    //         "status"  => $success,
    //         "message" => $success ? [] : $this->db->error()
    //     ];
    
    //     return $error;
    // }

    public function delete_branch($id)
    {
        return $this->softDelete($id, ['is_active' => 0]);
    }
    // public function delete_branch($id)
    // {
    //     // Soft delete: set is_active = 0
    //     $data = ['is_active' => 0];

    //     // Panggil method update(), agar log tercatat sebagai DELETE
    //     // Tapi kita override log-nya nanti jadi 'DELETE' bukan 'UPDATE'
    //     $result = $this->update($id, $data);

    //     if ($this->auditEnabled && $result) {
    //         $oldData = $this->find($id);
    //         $this->logAudit('DELETE', $id, $oldData, $data); // force log as DELETE
    //     }

    //     return $result;
    // }
    // public function delete_branch($id)
    // {
    //     $success = $this->update($id, ['is_active' => 0]); // ini akan tercatat sbg "DELETE" jika kamu mau anggap begitu

    //     if (!$success) {
    //         return false;
    //     }

    //     return true;
    // }
    // public function delete_branch($id){
    //     $branch = $this->db->table("branches");
    //     $branch->set('is_active', 0)
    //         ->where($this->primaryKey, $id)
    //         ->update();
    //     return $this->db->affectedRows() > 0 ? true:false;
    // }
    // Batas Bawah Raw Query
}
