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

    public function __construct()
    {
        parent::__construct();

        $request = Services::request();

        $this->ipAddress = $request->getIPAddress();
        $this->userId = auth_user_id();     // Ambil dari helper
        $this->tenantId = auth_tenant_id(); // Ambil dari helper
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
