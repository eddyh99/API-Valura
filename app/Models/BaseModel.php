<?php

namespace App\Models;

use CodeIgniter\Model;
use Config\Services;

class BaseModel extends Model
{
    protected $auditEnabled = true;
    protected $auditTable = 'audit_logs';
    protected $userId;
    protected $tenantId;
    protected $ipAddress;
    
    public function setContext(array $context)
    {
        $this->userId   = $context['user_id'] ?? null;
        $this->tenantId = $context['tenant_id'] ?? null;
        $this->ipAddress = $context['ip_address'] ?? null;
    
        return $this;
    }

    public function insert($data = null, bool $returnID = true)
    {
        $id = parent::insert($data, $returnID);

        if ($this->auditEnabled && $id) {
            $this->logAudit('INSERT', $id, null, $data);
        }

        return $id;
    }

    public function update($id = null, $data = null): bool
    {
        $oldData = $this->find($id);
        $result = parent::update($id, $data);

        if ($this->auditEnabled && $result) {
            $this->logAudit('UPDATE', $id, $oldData, $data);
        }

        return $result;
    }

    public function delete($id = null, bool $purge = false)
    {
        $oldData = $this->find($id);
        $result = parent::delete($id, $purge);

        if ($this->auditEnabled && $result) {
            $this->logAudit('DELETE', $id, $oldData, null);
        }

        return $result;
    }
    public function softDelete($id, array $data = [])
    {
        $oldData = $this->find($id);

        // Lakukan update manual tanpa trigger log bawaan
        $result = parent::update($id, $data); // langsung ke parent

        if ($this->auditEnabled && $result) {
            $this->logAudit('DELETE', $id, $oldData, $data);
        }

        return $result;
    }

    protected function logAudit(string $action, $recordId, $oldData = null, $newData = null)
    {
        $db = db_connect();

        $changeData = [
            'old' => $oldData,
            'new' => $newData,
        ];

        $db->table($this->auditTable)->insert([
            'tenant_id'   => $this->tenantId ?? 0,
            'user_id'     => $this->userId ?? null,
            'action'      => $action,
            'table_name'  => $this->table,
            'record_id'   => $recordId,
            'change_data' => json_encode($changeData),
            'ip_address'  => $this->ipAddress,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }
}
