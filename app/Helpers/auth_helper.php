<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

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

function decode_jwt_payload()
{
    $authHeader = service('request')->getHeaderLine('Authorization');

    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        return null;
    }

    $token = $matches[1];
    $key = getenv('JWT_SECRET');

    try {
        return JWT::decode($token, new Key($key, 'HS256'));
    } catch (Exception $e) {
        return null;
    }
}
