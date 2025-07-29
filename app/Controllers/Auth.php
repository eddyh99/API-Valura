<?php

namespace App\Controllers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\Mdl_tenant;
use App\Models\Mdl_member;
use App\Controllers\BaseApiController;

class Auth extends BaseApiController
{

    protected $format    = 'json';

    public function __construct()
    {
        // parent::__construct();
        $this->member = new Mdl_member();
        $this->tenant = new Mdl_tenant();
    }

    // Register
    public function postRegister()
    {
        $this->validation->setRules([
            'tenant_name' => 'required|min_length[3]',
            'username'    => 'required|min_length[4]|is_unique[users.username]',
            'email'       => 'required|valid_email|is_unique[users.email]',
            'password'    => 'required|min_length[6]'
        ]);

        if (!$this->validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($this->validation->getErrors());
        }

        $data = $this->request->getJSON(true);

        // Step 1: Simpan tenant (RAW)
        $tenantId = $this->tenant->insertTenantRaw($data['tenant_name']);
        if (!$tenantId) {
            return $this->failServerError('Gagal membuat tenant');
        }

        // Step 2: Simpan user (RAW)
        $otp = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $userData = [
            'username'      => htmlspecialchars($data['username']),
            'email'         => htmlspecialchars($data['email']),
            'tenant_id'     => $tenantId,
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role_id'       => 2,
            'branch_id'     => null,
            'is_active'     => 0,
            'otp_code'      => $otp,
            'otp_requested_at' => date('Y-m-d H:i:s'),
            'created_at'    => date('Y-m-d H:i:s')
        ];

        $userId = $this->member->insertUserRaw($userData);
        if (!$userId) {
            return $this->failServerError('Gagal membuat user');
        }

        // Step 3: Kirim OTP ke email
        $this->emailService->setTo($data['email']);
        $this->emailService->setFrom('noreply@valura.com', 'Valura Support');
        $this->emailService->setSubject('OTP Activation Code');
        $this->emailService->setMessage("Welcome! Your OTP code is: <strong>$otp</strong><br>It will expire in 30 minutes.");

        if (!$this->emailService->send()) {
            return $this->failServerError('Gagal mengirim OTP email');
        }

        return $this->respondCreated([
            'message'    => 'Akun berhasil dibuat. Silakan cek email untuk aktivasi OTP.',
            'tenant_id'  => $tenantId,
            'user_id'    => $userId
        ]);
    }

    public function postActivateOtp()
    {
        // Validasi input OTP menggunakan $this->validation yang sudah otomatis tersedia
        $this->validation->setRules([
            'otp' => 'required|numeric|exact_length[4]'
        ]);

        if (!$this->validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($this->validation->getErrors());
        }

        $data = $this->request->getJSON(true);
        $otp = trim($data['otp']);

        // Panggil method model untuk cari user berdasarkan OTP (raw query)
        $user = $this->member->getUserByOtpRaw($otp);

        if (!$user) {
            return $this->failUnauthorized('OTP tidak valid atau akun sudah aktif');
        }

        // Cek apakah OTP expired, juga di-refactor ke dalam model (bisa dicek di model atau di sini)
        if ($this->member->isOtpExpired($user['otp_requested_at'])) {
            return $this->failUnauthorized('OTP sudah kedaluwarsa');
        }

        // Update user untuk aktivasi dan hapus OTP, melalui method model raw query
        $this->member->activateUserByIdRaw($user['id']);

        return $this->respond([
            'status' => 200,
            'message' => 'Akun berhasil diaktifkan'
        ]);
    }
    
    public function postResendOtp()
    {
        // Validasi input email menggunakan $this->validation yang sudah tersedia
        $this->validation->setRules([
            'email' => 'required|valid_email'
        ]);

        if (!$this->validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($this->validation->getErrors());
        }

        $data = $this->request->getJSON(true);
        $email = trim($data['email']);

        // Panggil model untuk mendapatkan user yang belum aktif berdasarkan email
        $user = $this->member->getInactiveByEmailRaw($email);

        if (!$user) {
            return $this->failNotFound('Email tidak ditemukan');
        }

        // Generate OTP baru
        $otp = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

        // Simpan OTP baru ke user melalui method model dengan raw query
        $this->member->saveOtpToUserRaw($email, $otp);

        // Gunakan emailService yang sudah ada di BaseApiController
        $this->emailService->setTo($email);
        $this->emailService->setFrom('noreply@valura.com', 'Valura Support');
        $this->emailService->setSubject('OTP Baru');
        $this->emailService->setMessage("OTP baru Anda adalah: <strong>$otp</strong><br>Berlaku selama 30 menit.");

        if (!$this->emailService->send()) {
            return $this->failServerError('Gagal mengirim ulang OTP');
        }

        return $this->respond(['message' => 'OTP baru dikirim ke email']);
    }
    
    public function postLogin()
    {
        $validation = $this->validation;
        $validation->setRules([
            'username'    => 'required',
            'password'    => 'required',
            'ip_address'  => 'required|valid_ip',
            'domain'      => 'required',
            'remember_me' => 'permit_empty'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $data     = $this->request->getJSON();
        $username = $data->username;
        $password = $data->password;
        $ip       = $data->ip_address;

        // Cek akun terkunci
        if ($this->member->isLocked($username)) {
            return $this->fail('Account is temporarily locked. Try again later.', 429);
        }

        $user = $this->member->getUserWithRole($username);

        // Catat login gagal (sementara)
        $this->member->logLoginAttempt($username, $ip, 'FAILED', 'Invalid credentials');
        $this->member->incrementRetry($username);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return $this->failUnauthorized('Invalid username or password');
        }

        // Reset percobaan login
        $this->member->resetRetry($username);
        $this->member->logLoginAttempt($username, $ip, 'SUCCESS', 'Login successful');

        // Cek Remember Me
        $remember = false;
        if (isset($data->remember_me)) {
            $val = $data->remember_me;
            $remember = ($val === true || $val === 'true' || $val === 1 || $val === '1');
        }

        // Set waktu expired
        $accessTokenTTL  = 3600; // 1 Jam
        $refreshTokenTTL = $remember ? (60 * 60 * 24 * 7) : (60 * 60 * 24); // 7 hari atau 1 hari

        $accessTokenPayload = [
            'uid'       => $user['id'],
            'tenant_id' => $user['tenant_id'],
            'username'  => $user['username'],
            'branch_id'   => $user["branch_id"],
            'role'        => $user['role_name'],
            'permissions' => json_decode($user['permissions']),
            'iat'       => time(),
            'exp'       => time() + $accessTokenTTL
        ];

        $accessToken = JWT::encode($accessTokenPayload, getenv('JWT_SECRET'), 'HS256');

        // Buatkan refresh token
        $refreshTokenPayload = [
            'uid'       => $user['id'],
            'tenant_id' => $user['tenant_id'],
            'username'  => $user['username'],
            'iat'       => time(),
            'exp'       => time() + $refreshTokenTTL
        ];

        $refreshToken = JWT::encode($refreshTokenPayload, getenv('REFRESH_SECRET'), 'HS256');

        return $this->respond([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type'    => 'Bearer',
            'expires_in'    => $accessTokenTTL,
            'remember_for'  => $refreshTokenTTL,
            'user' => [
                'id'          => $user['id'],
                'username'    => $user['username'],
                'branch_id'   => $user["branch_id"],
                'role'        => $user['role_name'],
                'permissions' => json_decode($user['permissions']) // decode dari JSON
            ]
        ]);
    }

    // Refresh Token
    public function refreshToken()
    {
        // Gunakan validation yang sudah disediakan oleh BaseApiController
        $this->validation->setRules([
            'refresh_token' => 'required'
        ]);

        if (!$this->validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($this->validation->getErrors());
        }

        $data = $this->request->getJSON(true);
        $refreshToken = trim($data['refresh_token']);

        try {
            // Decode refresh token
            $decoded = JWT::decode($refreshToken, new Key(getenv('REFRESH_SECRET'), 'HS256'));

            // Cek apakah refresh token expired
            if ($decoded->exp < time()) {
                return $this->failUnauthorized('Refresh token expired.');
            }

            // Ambil user dengan raw query dari model (getUserWithIDRaw)
            $user = $this->member->getUserWithIDRaw($decoded->uid, $decoded->tenant_id, $decoded->username);
            if (!$user) {
                return $this->failNotFound('User not found.');
            }

            // Generate access token baru (1 jam)
            $newAccessTokenPayload = [
                'uid'         => $user['id'],
                'tenant_id'   => $user['tenant_id'],
                'username'    => $user['username'],
                'branch_id'   => $user["branch_id"],
                'role'        => $user['role_name'],
                'permissions' => json_decode($user['permissions']),
                'iat'         => time(),
                'exp'         => time() + 3600
            ];

            $newAccessToken = JWT::encode($newAccessTokenPayload, getenv('JWT_SECRET'), 'HS256');

            return $this->respond([
                'access_token' => $newAccessToken,
                'token_type'   => 'Bearer',
                'expires_in'   => $newAccessTokenPayload['exp'],
                'user' => [
                    'id'          => $user['id'],
                    'username'    => $user['username'],
                    'branch_id'   => $user["branch_id"],
                    'role'        => $user['role_name'],
                    'permissions' => json_decode($user['permissions'])
                ]
            ]);

        } catch (\Exception $e) {
            return $this->failUnauthorized('Invalid refresh token: ' . $e->getMessage());
        }
    }

    // Forgot Password
    public function postForgotPasswordOtp()
    {
        $this->validation->setRules([
            'email' => 'required|valid_email'
        ]);

        if (!$this->validation->withRequest($this->request)->run()) {
            return $this->fail($this->validation->getErrors());
        }

        $data = $this->request->getJSON(true);
        $email = trim($data['email']);

        $user = $this->member->getInactiveByEmailRaw($email);
        // if (!$user) {
        //     return $this->failNotFound('Email not registered or user already active');
        // }
        if ($user) {
            return $this->failNotFound('Email not registered or user already active');
        }

        $otp = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT); // contoh OTP: 0832
        $this->member->saveOtpToUserRaw($email, $otp);

        $this->emailService->setTo($email);
        $this->emailService->setFrom('noreply@valura.com', 'Valura Support');
        $this->emailService->setSubject('Your Password Reset OTP');
        $this->emailService->setMessage("Your OTP is: <strong>$otp</strong><br><br>It will expire in 30 minutes.");

        if (!$this->emailService->send()) {
            return $this->failServerError('Failed to send OTP email');
        }

        return $this->respond(['message' => 'OTP sent to email']);
    }

    public function postResetPasswordOtp()
    {
        // Gunakan $this->validation yg sudah tersedia di BaseApiController
        $this->validation->setRules([
            'email'    => 'required|valid_email',
            'password' => 'required|min_length[8]',
            'otp'      => 'required|numeric|exact_length[4]'
        ]);

        if (!$this->validation->withRequest($this->request)->run()) {
            return $this->fail($this->validation->getErrors());
        }

        $data = $this->request->getJSON(true);
        $email = trim($data['email']);
        $password = trim($data['password']);
        $otp = trim($data['otp']);

        // Validasi OTP menggunakan fungsi model raw query
        if (!$this->member->validateOTPRaw($email, $otp)) {
            return $this->failUnauthorized('Invalid or expired OTP');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Update password dan hapus OTP menggunakan raw query
        $this->member->updatePasswordByEmailRaw($email, $hash);
        $this->member->clearOtpByEmailRaw($email);

        return $this->respond(['message' => 'Password reset successful']);
    }

    // Log Out
    public function postLogout()
    {
        $authHeader = $this->request->getHeaderLine('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return $this->failUnauthorized('Missing or invalid Authorization header');
        }

        $token = trim(str_replace('Bearer', '', $authHeader));

        try {
            $decoded = JWT::decode($token, new Key(getenv('JWT_SECRET'), 'HS256'));
            $userId = $decoded->uid ?? null;
            $tenantId = $decoded->tenant_id ?? null;

            // Panggil fungsi model untuk insert audit logout
            $this->member->logLogoutAction($tenantId, $userId, $this->request->getIPAddress());

            // JWT stateless, tidak ada invalidasi token
            return $this->respond(['message' => 'Logout successful']);
        } catch (\Exception $e) {
            return $this->failUnauthorized('Invalid token: ' . $e->getMessage());
        }
    }
}
