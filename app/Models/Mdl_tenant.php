<?php
namespace App\Models;

use CodeIgniter\Model;

class Mdl_tenant extends Model
{
    protected $table            = 'tenants';
    protected $primaryKey       = 'id';
    protected $allowedFields    = ['name', 'subdomain', 'custom_domain', 'logo_url', 'config_json', 'is_active', 'created_at', 'updated_at'];
    protected $useTimestamps    = false;

    // Raw Query
    public function insertTenantRaw(string $tenantName)
    {
        $subdomain = strtolower(preg_replace('/\s+/', '', $tenantName));
        $createdAt = date('Y-m-d H:i:s');

        $sql = "INSERT INTO tenants (
                    name,
                    subdomain,
                    custom_domain,
                    logo_url,
                    config_json,
                    is_active,
                    created_at,
                    updated_at
                ) VALUES (?, ?, NULL, NULL, NULL, 1, ?, '0000-00-00 00:00:00')";

        $this->db->query($sql, [$tenantName, $subdomain, $createdAt]);

        if ($this->db->affectedRows() > 0) {
            return $this->db->insertID();
        }

        return false;
    }
    // Batas Raw Query

    public function insertTenant($tenantName)
    {
        $data = [
            'name'         => $tenantName,
            'subdomain'    => strtolower(preg_replace('/\s+/', '', $tenantName)), 
            // 'subdomain'    => null,
            'custom_domain'=> null,
            'logo_url'     => null,
            'config_json'  => null,
            'is_active'    => 1,
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => '0000-00-00 00:00:00'
        ];

        $this->insert($data);
        return $this->getInsertID();
    }
}
