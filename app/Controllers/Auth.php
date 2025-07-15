<?php

namespace App\Controllers;

use App\Models\Mdl_member;
use CodeIgniter\RESTful\ResourceController;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Config\Services;

use App\Models\Mdl_tenant;

class Auth extends ResourceController
{

    protected $format    = 'json';

    public function __construct()
    {
        $this->member = new Mdl_member();
    }

    // public function postRegister()
    // {
    //     //do Register
    // }
    public function postRegister()
    {
        $validation = \Config\Services::validation();
        $validation->setRules([
            'tenant_name' => 'required|min_length[3]',
            'username'    => 'required|min_length[4]|is_unique[users.username]',
            'email'       => 'required|valid_email|is_unique[users.email]',
            'password'    => 'required|min_length[6]',
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $data = $this->request->getJSON(true);

        $tenantModel = new Mdl_tenant();

        // Step 1: Simpan Tenant
        $tenantId = $tenantModel->insertTenant($data['tenant_name']);

        if (!$tenantId) {
            return $this->failServerError('Gagal membuat tenant');
        }

        // Step 2: Simpan User (otomatis role_id default: 2 dan branch_id = null)
        $otp = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $userData = [
            'username'      => htmlspecialchars($data['username']),
            'email'         => htmlspecialchars($data['email']),
            'tenant_id'     => $tenantId,
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role_id'       => 2, // default: member/user biasa
            'branch_id'     => null,
            'is_active'     => 0, // belum aktif
            'otp_code'      => $otp,
            'otp_requested_at' => date('Y-m-d H:i:s'),
            'created_at'    => date('Y-m-d H:i:s')
        ];

        $userId = $this->member->insert($userData);
        if (!$userId) {
            return $this->failServerError('Gagal membuat user');
        }

        // Step 3: Kirim OTP ke Email
        $emailService = \Config\Services::email();
        $emailService->setTo($data['email']);
        $emailService->setFrom('noreply@valura.com', 'Valura Support');
        $emailService->setSubject('OTP Activation Code');
        $emailService->setMessage("Welcome! Your OTP code is: <strong>$otp</strong><br>It will expire in 30 minutes.");

        if (!$emailService->send()) {
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
        $validation = \Config\Services::validation();
        $validation->setRules([
            'otp' => 'required|numeric|exact_length[4]'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $data = $this->request->getJSON(true);
        $otp = trim($data['otp']);

        // Cari user berdasarkan OTP
        $user = $this->member
            ->where('otp_code', $otp)
            ->where('is_active', 0)
            ->first();

        if (!$user) {
            return $this->failUnauthorized('OTP tidak valid atau akun sudah aktif');
        }

        // Cek apakah OTP expired
        $expiresAt = strtotime($user['otp_requested_at']) + (30 * 60); // 30 menit
        if (time() > $expiresAt) {
            return $this->failUnauthorized('OTP sudah kedaluwarsa');
        }

        // Aktifkan akun dan hapus OTP
        $this->member->update($user['id'], [
            'is_active' => 1,
            'otp_code' => null,
            'otp_requested_at' => null
        ]);

        return $this->respond([
            'status' => 200,
            'message' => 'Akun berhasil diaktifkan'
        ]);
    }
    public function postResendOtp()
    {
        $validation = \Config\Services::validation();
        $validation->setRules([
            'email' => 'required|valid_email'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $data = $this->request->getJSON(true);
        $email = trim($data['email']);

        // $user = $this->member->getByEmail($email);
        $user = $this->member->getInactiveByEmail($email);
        if (!$user) {
            return $this->failNotFound('Email tidak ditemukan');
        }

        $otp = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $this->member->saveOTPToUser($email, $otp);

        $emailService = \Config\Services::email();
        $emailService->setTo($email);
        $emailService->setFrom('noreply@valura.com', 'Valura Support');
        $emailService->setSubject('OTP Baru');
        $emailService->setMessage("OTP baru Anda adalah: <strong>$otp</strong><br>Berlaku selama 30 menit.");

        if (!$emailService->send()) {
            return $this->failServerError('Gagal mengirim ulang OTP');
        }

        return $this->respond(['message' => 'OTP baru dikirim ke email']);
    }

    // public function postLogin()
    // {

    //     $request = service('request');
    //     $validation = Services::validation();

    //     $validation->setRules([
    //         'username'    => 'required',
    //         'password'    => 'required',
    //         'ip_address'  => 'required|valid_ip',
    //         'domain'      => 'required'
    //     ]);

    //     if (!$validation->withRequest($request)->run()) {
    //         return $this->failValidationErrors($validation->getErrors());
    //     }

    //     $data = $request->getJSON();
    //     $username = $data->username;
    //     $password = $data->password;
    //     $ip = $data->ip_address;
        
    //     // Check login attempts
    //     if ($this->member->isLocked($username)) {
    //         return $this->fail('Account is temporarily locked. Try again later.', 429);
    //     }

    //     $user = $this->member->getByUsername($username);
    //     $this->member->logLoginAttempt($username, $ip, 'FAILED', 'Invalid credentials');
    //     $this->member->incrementRetry($username);

    //     if (!$user || !password_verify($password, $user['password_hash'])) {
    //         return $this->failUnauthorized('Invalid username or password');
    //     }


    //     $this->member->resetRetry($username);
    //     $this->member->logLoginAttempt($username, $ip, 'SUCCESS', 'Login successful');

    //     // JWT Token generation
    //    $accessTokenPayload = [
    //     'uid' => $user['id'],
    //     'tenant_id' => $user['tenant_id'],
    //     'username' => $user['username'],
    //     'iat' => time(),
    //     'exp' => time() + 900 // 15 minutes
    //     ];

    //     $refreshTokenPayload = [
    //         'uid' => $user['id'],
    //         'tenant_id' => $user['tenant_id'],
    //         'username' => $user['username'],
    //         'iat' => time(),
    //         'exp' => time() + 604800
    //     ];


    //     $accessToken = JWT::encode($accessTokenPayload, getenv('JWT_SECRET'), 'HS256');
    //     $refreshToken = JWT::encode($refreshTokenPayload, getenv('REFRESH_SECRET'), 'HS256');

    //     return $this->respond([
    //         'access_token' => $accessToken,
    //         'refresh_token' => $refreshToken,
    //         'token_type' => 'Bearer',
    //         'expires_in' => $accessTokenPayload['exp'],
    //         'user' => [
    //             'id' => $user['id'],
    //             'username' => $user['username']
    //         ]
    //     ]);

    // }
    // Remember Me
    public function postLogin()
    {
        $request = service('request');
        $validation = \Config\Services::validation();

        $validation->setRules([
            'username'    => 'required',
            'password'    => 'required',
            'ip_address'  => 'required|valid_ip',
            'domain'      => 'required',
            'remember_me' => 'permit_empty'
        ]);

        if (!$validation->withRequest($request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $data     = $request->getJSON();
        $username = $data->username;
        $password = $data->password;
        $ip       = $data->ip_address;

        // Cek akun terkunci
        if ($this->member->isLocked($username)) {
            return $this->fail('Account is temporarily locked. Try again later.', 429);
        }

        $user = $this->member->getByUsername($username);

        // Catat login gagal
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
        // $accessTokenTTL  = 15; // detik
        $refreshTokenTTL = 0;

        $accessTokenPayload = [
            'uid'       => $user['id'],
            'tenant_id' => $user['tenant_id'],
            'username'  => $user['username'],
            'iat'       => time(),
            'exp'       => time() + $accessTokenTTL
        ];

        $accessToken = JWT::encode($accessTokenPayload, getenv('JWT_SECRET'), 'HS256');

        // Jika remember me aktif, buatkan refresh token
        if ($remember) {
            $refreshTokenTTL = 60 * 60 * 24 * 7; // 7 hari
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
                    'id'       => $user['id'],
                    'username' => $user['username']
                ]
            ]);
        }

        // Jika Remember Me tidak aktif, tidak kirim refresh token
        return $this->respond([
            'access_token' => $accessToken,
            'token_type'   => 'Bearer',
            'expires_in'   => $accessTokenTTL,
            'user' => [
                'id'       => $user['id'],
                'username' => $user['username']
            ]
        ]);
    }

    // public function refreshToken()
    // {
    //     $request = service('request');
    //     $validation = \Config\Services::validation();

    //     // Validasi input refresh_token wajib ada
    //     $validation->setRules([
    //         'refresh_token' => 'required'
    //     ]);

    //     if (!$validation->withRequest($request)->run()) {
    //         return $this->failValidationErrors($validation->getErrors());
    //     }

    //     $data = $request->getJSON();
    //     $refreshToken = $data->refresh_token;

    //     try {
    //         // Decode refresh token dengan secret REFRESH_SECRET
    //         $decoded = \Firebase\JWT\JWT::decode($refreshToken, new \Firebase\JWT\Key(getenv('REFRESH_SECRET'), 'HS256'));

    //         // Cek expiry token
    //         if ($decoded->exp < time()) {
    //             return $this->failUnauthorized('Refresh token expired.');
    //         }

    //         // Ambil data user berdasarkan uid yang ada di token
    //         $user = $this->member->find($decoded->uid);

    //         if (!$user) {
    //             return $this->failNotFound('User not found.');
    //         }

    //         // Generate access token baru
    //         $accessTokenPayload = [
    //             'uid'      => $user['id'],
    //             // Hapus 'tenant_id' karena tidak dipakai di sini dan tidak ada di tabel user_logins
    //             'username' => $user['username'],
    //             'iat'      => time(),
    //             'exp'      => time() + 900 // 15 menit
    //         ];
    //         $newAccessToken = \Firebase\JWT\JWT::encode($accessTokenPayload, getenv('JWT_SECRET'), 'HS256');

    //         // Insert log login ke tabel user_logins sesuai struktur tabel Anda
    //         $db = \Config\Database::connect();

    //         $userAgent = $request->getUserAgent()->getAgentString();

    //         $db->table('user_logins')->insert([
    //             'user_id'    => $user['id'],
    //             'ip_address' => $request->getIPAddress(),
    //             'user_agent' => $userAgent,
    //             'status'     => 'SUCCESS',
    //             'message'    => 'Refresh token successful'
    //             // Kolom attempted_at otomatis diatur oleh database dengan current_timestamp()
    //         ]);

    //         // Response sukses dengan access token baru
    //         return $this->respond([
    //             'access_token'  => $newAccessToken,
    //             'refresh_token' => $refreshToken,
    //             'token_type'    => 'Bearer',
    //             'expires_in'    => $accessTokenPayload['exp'],
    //             'user'          => [
    //                 'id'       => $user['id'],
    //                 'username' => $user['username']
    //             ]
    //         ]);

    //     } catch (\Exception $e) {
    //         // Jika token refresh invalid atau error lain
    //         return $this->failUnauthorized('Invalid refresh token: ' . $e->getMessage());
    //     }
    // }
    // Remember Me
    public function refreshToken()
    {
        $request = service('request');
        $validation = \Config\Services::validation();

        $validation->setRules([
            'refresh_token' => 'required'
        ]);

        if (!$validation->withRequest($request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $data = $request->getJSON(true);
        $refreshToken = trim($data['refresh_token']);

        try {
            // Decode refresh token
            $decoded = JWT::decode($refreshToken, new Key(getenv('REFRESH_SECRET'), 'HS256'));

            // Cek apakah refresh token expired
            if ($decoded->exp < time()) {
                return $this->failUnauthorized('Refresh token expired.');
            }

            // Ambil user dari DB
            $user = $this->member->find($decoded->uid);
            if (!$user) {
                return $this->failNotFound('User not found.');
            }

            // Generate access token baru (1 jam)
            $newAccessTokenPayload = [
                'uid'       => $user['id'],
                'tenant_id' => $user['tenant_id'],
                'username'  => $user['username'],
                'iat'       => time(),
                'exp'       => time() + 3600
            ];

            $newAccessToken = JWT::encode($newAccessTokenPayload, getenv('JWT_SECRET'), 'HS256');

            return $this->respond([
                'access_token' => $newAccessToken,
                'token_type'   => 'Bearer',
                'expires_in'   => $newAccessTokenPayload['exp'],
                'user' => [
                    'id'       => $user['id'],
                    'username' => $user['username']
                ]
            ]);

        } catch (\Exception $e) {
            return $this->failUnauthorized('Invalid refresh token: ' . $e->getMessage());
        }
    }

    // Forgot Password
    public function postForgotPasswordOtp()
    {
        $validation = \Config\Services::validation();
        $validation->setRules([
            'email' => 'required|valid_email'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->fail($validation->getErrors());
        }

        $data = $this->request->getJSON(true);
        $email = trim($data['email']);

        $user = $this->member->getByEmail($email);
        if (!$user) {
            return $this->failNotFound('Email not registered');
        }

        $otp = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT); // contoh OTP: 0832
        $this->member->saveOTPToUser($email, $otp);

        $emailService = \Config\Services::email();
        $emailService->setTo($email);
        $emailService->setFrom('noreply@valura.com', 'Valura Support');
        $emailService->setSubject('Your Password Reset OTP');
        $emailService->setMessage("Your OTP is: <strong>$otp</strong><br><br>It will expire in 30 minutes.");

        if (!$emailService->send()) {
            return $this->failServerError('Failed to send OTP email');
        }

        return $this->respond(['message' => 'OTP sent to email']);
    }

    public function postResetPasswordOtp()
    {
        $validation = \Config\Services::validation();
        $validation->setRules([
            'email'    => 'required|valid_email',
            'password' => 'required|min_length[8]',
            'otp'      => 'required|numeric|exact_length[4]'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->fail($validation->getErrors());
        }

        $data = $this->request->getJSON(true);
        $email = trim($data['email']);
        $password = trim($data['password']);
        $otp = trim($data['otp']);

        if (!$this->member->validateOTP($email, $otp)) {
            return $this->failUnauthorized('Invalid or expired OTP');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->member->updatePasswordByEmail($email, $hash);
        $this->member->clearOTP($email);

        return $this->respond(['message' => 'Password reset successful']);
    }

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

            // Audit logout
            $db = db_connect();
            $db->table('audit_logs')->insert([
                'tenant_id'   => $tenantId,
                'user_id'     => $userId,
                'action'      => 'LOGOUT_SUCCESS',
                'table_name'  => 'users',
                'record_id'   => $userId,
                'change_data' => json_encode(['message' => 'User logged out']),
                'ip_address'  => $this->request->getIPAddress(),
                'created_at'  => date('Y-m-d H:i:s')
            ]);

            // Tidak ada token invalidasi karena JWT stateless
            return $this->respond(['message' => 'Logout successful']);
        } catch (\Exception $e) {
            return $this->failUnauthorized('Invalid token: ' . $e->getMessage());
        }
    }
}
