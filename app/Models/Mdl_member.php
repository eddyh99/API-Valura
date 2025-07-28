<?php
namespace App\Models;

use App\Models\BaseModel;
use App\Models\Mdl_role;

class Mdl_member extends BaseModel
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $allowedFields    = [
                                    'username', 'email', 'password_hash', 'tenant_id', 'role_id',
                                    'is_active', 'created_at', 'branch_id', 'otp_code', 'otp_requested_at'
                                ];
    protected $useTimestamps    = false;
    protected $createdField     = 'created_at';
    protected $retryTable       = 'login_retries';
    protected $logTable         = 'audit_logs';
    protected $maxRetry         = 5;
    protected $retryTimeout     = 15 * 60; // 15 minutes

    // Raw Query
    public function __construct()
    {
        parent::__construct();
        $this->roles = new Mdl_role();
    }

    public function getAllUsersRaw($tenantId)
    {
        $sql = "SELECT 
                    u.id,
                    u.username,
                    u.email,
                    r.name AS role,
                    u.tenant_id,
                    u.branch_id,
                    u.is_active
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.tenant_id = ? AND u.is_active = 1
                ORDER BY u.id ASC";

        return $this->db->query($sql, [$tenantId])->getResultArray();
    }

    public function getUserByIdRaw($tenantId, $userId)
    {
        $sql = "SELECT 
                    u.id,
                    u.username,
                    u.email,
                    u.tenant_id,
                    u.branch_id,
                    u.is_active
                    r.name AS role
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.id = ? AND u.tenant_id = ? AND u.is_active = 1
                LIMIT 1";

        return $this->db->query($sql, [$userId, $tenantId])->getRowArray();
    }

    public function insertUserRaw(array $data)
    {
        $sql = "INSERT INTO users 
                (username, email, tenant_id, password_hash, role_id, branch_id, is_active, otp_code, otp_requested_at, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $this->db->query($sql, [
            $data['username'],
            $data['email'],
            $data['tenant_id'],
            $data['password_hash'],
            $data['role_id'],
            $data['branch_id'],
            $data['is_active'],
            $data['otp_code'],
            $data['otp_requested_at'],
            $data['created_at']
        ]);

        if ($this->db->affectedRows() > 0) {
            return $this->db->insertID();
        }

        return false;
    }

    // Cari user berdasarkan otp_code dan is_active=0 menggunakan raw query
    public function getUserByOtpRaw(string $otp)
    {
        $sql = "SELECT * FROM users WHERE otp_code = ? AND is_active = 0 LIMIT 1";
        $query = $this->db->query($sql, [$otp]);
        return $query->getRowArray();
    }
    // Cek apakah OTP sudah expired berdasarkan waktu otp_requested_at (30 menit)
    public function isOtpExpired(string $otpRequestedAt): bool
    {
        $expiresAt = strtotime($otpRequestedAt) + (30 * 60); // 30 menit
        return time() > $expiresAt;
    }
    // Update user menjadi aktif dan reset otp_code & otp_requested_at menggunakan raw query
    public function activateUserByIdRaw(int $userId)
    {
        $sql = "UPDATE users SET is_active = 1, otp_code = NULL, otp_requested_at = NULL WHERE id = ?";
        return $this->db->query($sql, [$userId]);
    }

    // Ambil user yang belum aktif berdasarkan email menggunakan raw query
    public function getInactiveByEmailRaw(string $email)
    {
        $sql = "SELECT * FROM users WHERE email = ? AND is_active = 0 LIMIT 1";
        $query = $this->db->query($sql, [$email]);
        return $query->getRowArray();
    }
    // Simpan OTP baru ke user berdasarkan email menggunakan raw query
    public function saveOtpToUserRaw(string $email, string $otp)
    {
        $otpRequestedAt = date('Y-m-d H:i:s'); // waktu sekarang
        $sql = "UPDATE users SET otp_code = ?, otp_requested_at = ? WHERE email = ?";
        return $this->db->query($sql, [$otp, $otpRequestedAt, $email]);
    }

    // Validasi OTP dan cek otp_code aktif dan belum expired (30 menit)
    public function validateOTPRaw(string $email, string $otp): bool
    {
        $sql = "SELECT otp_requested_at FROM users WHERE email = ? AND otp_code = ? AND is_active = 1 LIMIT 1";
        $query = $this->db->query($sql, [$email, $otp]);
        $row = $query->getRowArray();

        if (!$row) {
            return false;
        }

        // Cek apakah OTP sudah expired (30 menit)
        $expiresAt = strtotime($row['otp_requested_at']) + (30 * 60);
        if (time() > $expiresAt) {
            return false;
        }

        return true;
    }
    // Update password_hash berdasarkan email
    public function updatePasswordByEmailRaw(string $email, string $hash)
    {
        $sql = "UPDATE users SET password_hash = ? WHERE email = ? AND is_active = 1";
        return $this->db->query($sql, [$hash, $email]);
    }
    // Hapus otp_code dan otp_requested_at berdasarkan email
    public function clearOtpByEmailRaw(string $email)
    {
        $sql = "UPDATE users SET otp_code = NULL, otp_requested_at = NULL WHERE email = ? AND is_active = 1";
        return $this->db->query($sql, [$email]);
    }

    public function insertAuditLogRaw(array $data)
    {
        $sql = "INSERT INTO audit_logs (tenant_id, user_id, action, table_name, record_id, change_data, ip_address, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        return $this->db->query($sql, [
            $data['tenant_id'], $data['user_id'], $data['action'], $data['table_name'], 
            $data['record_id'], $data['change_data'], $data['ip_address'], $data['created_at']
        ]);
    }
    public function logLogoutAction($tenantId, $userId, $ipAddress)
    {
        $data = [
            'tenant_id'   => $tenantId,
            'user_id'     => $userId,
            'action'      => 'LOGOUT_SUCCESS',
            'table_name'  => 'users',
            'record_id'   => $userId,
            'change_data' => json_encode(['message' => 'User logged out']),
            'ip_address'  => $ipAddress,
            'created_at'  => date('Y-m-d H:i:s')
        ];
        return $this->insertAuditLogRaw($data);
    }

    // Batas Bawah Raw Query

    public function getUserWithRole($username)
    {
        return $this->select('users.*, roles.name AS role_name, roles.permissions')
                    ->join('roles', 'roles.id = users.role_id', 'left')
                    ->where('users.username', $username)
                    ->where('users.is_active', 1)
                    ->first();
    }
    
    public function getUserWithIDRaw($uid,$tenant_id,$username){
        $sql="SELECT us.*, ro.name AS role_name, ro.permissions
                FROM users us LEFT JOIN roles ro ON us.role_id=ro.id
                INNER JOIN tenants te ON us.tenant_id=te.id
                WHERE us.username = ?
                AND us.tenant_id = ?
                AND us.id = ?
                AND us.is_active=1
                AND te.is_active=1
             ";
        $query=$this->db->query($sql,[$username,$tenant_id,$uid]);
        return $query->getRowArray();
             
    }

    public function getByUsername($username)
    {
        return $this->where('username', $username)->where('is_active', 1)->first();
    }

    public function getInactiveByEmail($email)
    {
        return $this->where('email', $email)->where('is_active', 0)->first();
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

    // Forgot Password
    public function getByEmail($email)
    {
        return $this->where('email', $email)->where('is_active', 1)->first();
    }
    public function updatePasswordByEmail($email, $newPasswordHash)
    {
        return $this->where('email', $email)
            ->set('password_hash', $newPasswordHash)
            ->update();
    }

    public function saveOTPToUser($email, $otp)
    {
        return $this->where('email', $email)->set([
            'otp_code' => $otp,
            'otp_requested_at' => date('Y-m-d H:i:s')
        ])->update();
    }
    public function validateOTP($email, $otp)
    {
        $user = $this->getByEmail($email);
        if (!$user || $user['otp_code'] !== $otp) {
            return false;
        }

        $expiresAt = strtotime($user['otp_requested_at']) + (30 * 60); // 30 menit
        return time() <= $expiresAt;
    }
    public function clearOTP($email)
    {
        return $this->where('email', $email)->set([
            'otp_code' => null,
            'otp_requested_at' => null
        ])->update();
    }


}
