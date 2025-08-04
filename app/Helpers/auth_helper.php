<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use CodeIgniter\HTTP\Exceptions\HTTPException;

function auth_user_id()
{
    $payload = decode_jwt_payload();
    return $payload->uid ?? null;  // Sesuaikan dengan "uid" payload Anda dari JWT
}

function auth_tenant_id()
{
    $payload = decode_jwt_payload();
    return $payload->tenant_id ?? null;  // Ambil tenant_id dari token, jika ada
}

function auth_branch_id()
{
    $payload = decode_jwt_payload();
    return $payload->branch_id ?? null;  // Ambil branch_id dari token, jika ada
}


function decode_jwt_payload()
{
    $request = service('request');
    $path = $request->getPath();

    // Biarkan login dan refresh token tanpa token
    if (in_array($path, ['login', 'refresh-token'])) {
        return null;
    }

    $authHeader = $request->getHeaderLine('Authorization');

    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        throw new \CodeIgniter\HTTP\Exceptions\HTTPException('Token expired', 401);
    }

    $token = $matches[1];
    $key = getenv('JWT_SECRET');

    try {
        return JWT::decode($token, new Key($key, 'HS256'));
    } catch (Exception $e) {
        throw new \CodeIgniter\HTTP\Exceptions\HTTPException('Token expired', 401);
    }
}

if (!function_exists('current_context')) {
    function current_context(): array
    {
        $request = service('request');

        return [
            'user_id'    => auth_user_id(),
            'tenant_id'  => auth_tenant_id(),
            'ip_address' => $request->getIPAddress(),
        ];
    }
}
