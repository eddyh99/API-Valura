<?php

namespace App\Controllers;

use App\Models\Mdl_member;
use CodeIgniter\RESTful\ResourceController;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Config\Services;

class Auth extends ResourceController
{

    protected $format    = 'json';

    public function __construct()
    {
        $this->member = new Mdl_member();
    }

    public function postRegister()
    {
        //do Register
    }

    public function postLogin()
    {

        $request = service('request');
        $validation = Services::validation();

        $validation->setRules([
            'username'    => 'required',
            'password'    => 'required',
            'ip_address'  => 'required|valid_ip',
            'domain'      => 'required'
        ]);

        if (!$validation->withRequest($request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $data = $request->getJSON();
        $username = $data->username;
        $password = $data->password;
        $ip = $data->ip_address;
        
        // Check login attempts
        if ($this->member->isLocked($username)) {
            return $this->fail('Account is temporarily locked. Try again later.', 429);
        }

        $user = $this->member->getByUsername($username);
        $this->member->logLoginAttempt($username, $ip, 'FAILED', 'Invalid credentials');
        $this->member->incrementRetry($username);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return $this->failUnauthorized('Invalid username or password');
        }


        $this->member->resetRetry($username);
        $this->member->logLoginAttempt($username, $ip, 'SUCCESS', 'Login successful');

        // JWT Token generation
       $accessTokenPayload = [
        'uid' => $user['id'],
        'tenant_id' => $user['tenant_id'],
        'username' => $user['username'],
        'iat' => time(),
        'exp' => time() + 900 // 15 minutes
        ];

        $refreshTokenPayload = [
            'uid' => $user['id'],
            'tenant_id' => $user['tenant_id'],
            'username' => $user['username'],
            'iat' => time(),
            'exp' => time() + 604800
        ];


        $accessToken = JWT::encode($accessTokenPayload, getenv('JWT_SECRET'), 'HS256');
        $refreshToken = JWT::encode($refreshTokenPayload, getenv('REFRESH_SECRET'), 'HS256');

        return $this->respond([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $accessTokenPayload['exp'],
            'user' => [
                'id' => $user['id'],
                'username' => $user['username']
            ]
        ]);

    }

    public function refreshToken()
    {
        $request = service('request');
        $validation = \Config\Services::validation();

        // Validasi input refresh_token wajib ada
        $validation->setRules([
            'refresh_token' => 'required'
        ]);

        if (!$validation->withRequest($request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $data = $request->getJSON();
        $refreshToken = $data->refresh_token;

        try {
            // Decode refresh token dengan secret REFRESH_SECRET
            $decoded = \Firebase\JWT\JWT::decode($refreshToken, new \Firebase\JWT\Key(getenv('REFRESH_SECRET'), 'HS256'));

            // Cek expiry token
            if ($decoded->exp < time()) {
                return $this->failUnauthorized('Refresh token expired.');
            }

            // Ambil data user berdasarkan uid yang ada di token
            $user = $this->member->find($decoded->uid);

            if (!$user) {
                return $this->failNotFound('User not found.');
            }

            // Generate access token baru
            $accessTokenPayload = [
                'uid'      => $user['id'],
                // Hapus 'tenant_id' karena tidak dipakai di sini dan tidak ada di tabel user_logins
                'username' => $user['username'],
                'iat'      => time(),
                'exp'      => time() + 900 // 15 menit
            ];
            $newAccessToken = \Firebase\JWT\JWT::encode($accessTokenPayload, getenv('JWT_SECRET'), 'HS256');

            // Insert log login ke tabel user_logins sesuai struktur tabel Anda
            $db = \Config\Database::connect();

            $userAgent = $request->getUserAgent()->getAgentString();

            $db->table('user_logins')->insert([
                'user_id'    => $user['id'],
                'ip_address' => $request->getIPAddress(),
                'user_agent' => $userAgent,
                'status'     => 'SUCCESS',
                'message'    => 'Refresh token successful'
                // Kolom attempted_at otomatis diatur oleh database dengan current_timestamp()
            ]);

            // Response sukses dengan access token baru
            return $this->respond([
                'access_token'  => $newAccessToken,
                'refresh_token' => $refreshToken,
                'token_type'    => 'Bearer',
                'expires_in'    => $accessTokenPayload['exp'],
                'user'          => [
                    'id'       => $user['id'],
                    'username' => $user['username']
                ]
            ]);

        } catch (\Exception $e) {
            // Jika token refresh invalid atau error lain
            return $this->failUnauthorized('Invalid refresh token: ' . $e->getMessage());
        }
    }
}
