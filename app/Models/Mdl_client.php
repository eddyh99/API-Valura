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
    // Batas Bawah Raw Query
}
