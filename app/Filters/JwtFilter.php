<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

public function before(RequestInterface $request)
{
    $authHeader = $request->getHeaderLine('Authorization');
    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
        return Services::response()->setJSON(['error' => 'Unauthorized'])->setStatusCode(401);
    }

    $token = explode(' ', $authHeader)[1];

    try {
        $decoded = JWT::decode($token, new Key(getenv('JWT_SECRET'), 'HS256'));
        $request->user = $decoded;
    } catch (\Exception $e) {
        return Services::response()->setJSON(['error' => 'Invalid or expired token'])->setStatusCode(401);
    }

    return $request;
}
