<?php

namespace App\Models;

use App\Models\BaseModel;

class Mdl_client extends BaseModel
{
    protected $table      = 'clients';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'tenant_id',
        'name',
        'id_type',
        'id_number',
        'country',
        'phone',
        'email',
        'address',
        'job',
        'is_active',
        'created_at'
    ];

    protected $useTimestamps = false;
    protected $auditEnabled  = true;

    public function getAllClientsRaw($tenantId)
    {
        $sql = "SELECT 
                    id,
                    tenant_id,
                    name,
                    id_type,
                    id_number,
                    country,
                    phone,
                    is_active
                FROM clients
                WHERE tenant_id = ? AND is_active = 1
                ORDER BY id ASC";

        return $this->db->query($sql, [$tenantId])->getResultArray();
    }

    // public function getClientByIdRaw($tenantId, $currencyId)
    // {
    //     $sql = "SELECT 
    //                 name,
    //                 country,
    //                 id_type,
    //                 phone,
    //             FROM clients
    //             WHERE id = ? AND tenant_id = ? AND is_active = 1
    //             LIMIT 1";

    //     return $this->db->query($sql, [$currencyId, $tenantId])->getRowArray();
    // }
    public function getClientByIdRaw($tenantId, $currencyId)
    {
        $sql = "SELECT *
                FROM clients
                WHERE id = ? AND tenant_id = ? AND is_active = 1
                LIMIT 1";

        return $this->db->query($sql, [$currencyId, $tenantId])->getRowArray();
    }
    public function getClientByIdNumberRaw($idNumber)
    {
        $sql = "SELECT * FROM clients WHERE id_number = ? LIMIT 1";
        return $this->db->query($sql, [$idNumber])->getRowArray();
    }
    
    public function insertClientRaw($data)
    {
        $sql = "INSERT INTO clients (tenant_id, name, id_type, id_number, address, job, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW())";

        $this->db->query($sql, [
            $data['tenant_id'],
            $data['name'],
            $data['id_type'],
            $data['id_number'],
            $data['address'],
            $data['job']
        ]);

        return $this->db->insertID();
    }

    public function insert_client($data)
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

    public function update_client($id, $data)
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

    public function delete_client($id)
    {
        return $this->softDelete($id, ['is_active' => 0]);
    }

    public function insertClientIfNotExistRaw($data)
    {
        // Build the insert SQL with duplicate handling
        $sql = "
            INSERT INTO clients (tenant_id, name, id_type, id_number, phone, email, address, job, country)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                is_active = 1,
                name = VALUES(name),
                address = VALUES(address),
                job = VALUES(job)
        ";
    
        // Execute the insert or update
        $this->db->query($sql, [
            $data['tenant_id'],
            $data['name'],
            $data['id_type'],
            $data['id_number'],
            $data['phone'],
            $data['email'],
            $data['address'],
            $data['job'] ?? null,
            $data["country"] ?? null
        ]);
    
        // Get insert ID
        $insertId = $this->db->insertID();
    
        if ($insertId > 0) {
            return $insertId; // ✅ New row inserted
        }
    
        // ❌ Was duplicate: fetch existing client ID manually
        $existing = $this->db->table('clients')
            ->select('id')
            ->where('tenant_id', $data['tenant_id'])
            ->where('id_number', $data['id_number'])
            ->get()
            ->getRowArray();
    
        return $existing['id'] ?? null;
    }
}
