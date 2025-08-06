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
        $sql = "SELECT *
                FROM currencies
                WHERE tenant_id = ? AND is_active = 1
                ORDER BY id ASC";

        return $this->db->query($sql, [$tenantId])->getResultArray();
    }

    public function getCurrencyByIdRaw($tenantId, $clientId)
    {
        $sql = "SELECT *
                FROM currencies
                WHERE id = ? AND tenant_id = ? AND is_active = 1
                LIMIT 1";

        return $this->db->query($sql, [$clientId, $tenantId])->getRowArray();
    }

    
    public function insert_currency($data)
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

    public function update_currency($id, $data)
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

    public function delete_currency($id)
    {
        return $this->softDelete($id, ['is_active' => 0]);
    }

    public function getTodayCurrencyByBranch($branchId)
    {
        $sql = "SELECT * 
                FROM cash_movements 
                WHERE branch_id = ? 
                AND is_active = 1 
                AND DATE(occurred_at) = CURDATE()";

        return $this->db->query($sql, [$branchId])->getResultArray();
    }
}
