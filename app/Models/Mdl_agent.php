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
        $sql = "SELECT *
                FROM agents
                WHERE tenant_id = ? AND is_active = 1
                ORDER BY id ASC";

        return $this->db->query($sql, [$tenantId])->getResultArray();
    }

    public function getAgentByIdRaw($tenantId, $agentId)
    {
        $sql = "SELECT *
                FROM agents
                WHERE id = ? AND tenant_id = ? AND is_active = 1
                LIMIT 1";

        return $this->db->query($sql, [$agentId, $tenantId])->getRowArray();
    }

    public function insert_agent($data)
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

    public function update_agent($id, $data)
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

    public function delete_agent($id)
    {
        return $this->softDelete($id, ['is_active' => 0]);
    }
}
