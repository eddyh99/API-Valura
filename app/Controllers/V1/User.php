<?php

namespace App\Controllers\V1;
use App\Models\Mdl_member;
use CodeIgniter\RESTful\ResourceController;

class User extends ResourceController
{
    protected $format    = 'json';

    public function __construct()
    {
        $this->member = new Mdl_member();
    }

    public function create()
    {
        $rules = [
            'username'   => 'required|min_length[4]|is_unique[users.username]',
            'email'      => 'required|valid_email|is_unique[users.email]',
            'password'   => 'required|min_length[6]',
            'role_id'    => 'required|integer',
            'tenant_id'  => 'required|integer',
        ];

        if (! $this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $data = $this->request->getJSON(true);

        $insertData = [
            'username'      => htmlspecialchars($data['username']),
            'email'         => htmlspecialchars($data['email']),
            'role_id'       => $data['role_id'],
            'tenant_id'     => $data['tenant_id'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
        ];

        $userId = $this->member->insert($insertData);

        if (!$userId) {
            return $this->failServerError('Failed to create user.');
        }

        return $this->respondCreated([
            'message' => 'User created successfully.',
            'user_id' => $userId
        ]);
    }
}
