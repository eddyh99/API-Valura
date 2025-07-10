<?php
namespace App\Models;

use CodeIgniter\Model;

class Mdl_member extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $allowedFields    = ['username', 'email', 'password_hash', 'tenant_id', 'role_id', 'is_active', 'created_at', 'branch_id'];
    protected $useTimestamps    = false;
    protected $createdField     = 'created_at';
    protected $retryTable       = 'login_retries';
    protected $logTable         = 'audit_logs';
    protected $maxRetry         = 5;
    protected $retryTimeout     = 15 * 60; // 15 minutes

    public function getByUsername($username)
    {
        return $this->where('username', $username)->where('is_active', 1)->first();
    }

    public function isLocked($username)
    {
        $retry = $this->db->table($this->retryTable)
            ->where('username', $username)
            ->get()
            ->getRow();

        if (!$retry) return false;

        if ($retry->attempts >= $this->maxRetry && strtotime($retry->last_attempt) + $this->retryTimeout > time()) {
            return true;
        }

        return false;
    }

    public function incrementRetry($username)
    {
        $builder = $this->db->table($this->retryTable);
        $existing = $builder->where('username', $username)->get()->getRow();

        if ($existing) {
            $builder->where('username', $username)
                ->update([
                    'attempts' => $existing->attempts + 1,
                    'last_attempt' => date('Y-m-d H:i:s')
                ]);
        } else {
            $builder->insert([
                'username' => $username,
                'attempts' => 1,
                'last_attempt' => date('Y-m-d H:i:s')
            ]);
        }
    }

    public function resetRetry($username)
    {
        $this->db->table($this->retryTable)->where('username', $username)->delete();
    }

    public function logLoginAttempt($username, $ip, $status, $message)
    {
        $user = $this->where('username', $username)->first();
        $userId = $user['id'] ?? null;
        $tenantId = $user['tenant_id'] ?? null;

        $db = db_connect();
        $db->table($this->logTable)->insert([
            'tenant_id'   => $tenantId,
            'user_id'     => $userId,
            'action'      => 'LOGIN_' . strtoupper($status),
            'table_name'  => 'users',
            'record_id'   => $userId,
            'change_data' => json_encode(['message' => $message]),
            'ip_address'  => $ip,
            'created_at'  => date('Y-m-d H:i:s')
        ]);
    }

}
