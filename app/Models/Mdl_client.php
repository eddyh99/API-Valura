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
        'is_active',
        'created_at'
    ];

    protected $useTimestamps = false;
    protected $auditEnabled  = true;

    // Raw Query
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

    // public function insertClientIfNotExistRaw($tenantId, $data)
    // {
    //     $sql = "SELECT id FROM clients WHERE tenant_id = ? AND id_number = ? LIMIT 1";
    //     $row = $this->db->query($sql, [$tenantId, $data['id_number']])->getRow();

    //     if ($row) {
    //         return $row->id;
    //     }

    //     $insert = "INSERT INTO clients (tenant_id, name, id_type, id_number, address, job, created_at)
    //             VALUES (?, ?, ?, ?, ?, ?, NOW())";
    //     $this->db->query($insert, [
    //         $tenantId,
    //         $data['name'] ?? '',
    //         $data['id_type'] ?? '',
    //         $data['id_number'] ?? '',
    //         $data['address'] ?? '',
    //         $data['job'] ?? ''
    //     ]);

    //     return $this->db->insertID();
    // }
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

    // Batas Bawah Raw Query
}
