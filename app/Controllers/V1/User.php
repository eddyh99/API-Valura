<?php

namespace App\Controllers\V1;

use App\Models\Mdl_member;
use App\Controllers\BaseApiController;

class User extends BaseApiController
{
    protected $format = 'json';
    protected $member;

    public function __construct()
    {
        $this->member = new Mdl_member();
    }

    // Show All Users
    public function show_all_users()
    {
        $tenantId = auth_tenant_id();

        $users = $this->member->getAllUsersRaw($tenantId);

        return $this->respond([
            'status' => true,
            'data' => $users
        ]);
    }

    // Show User by ID
    public function showUser_ByID($id = null)
    {
        $tenantId = auth_tenant_id();

        $user = $this->member->getUserByIdRaw($tenantId, $id);

        return $this->respond([
            'status' => true,
            'data' => $user
        ]);
    }

    public function create()
    {
        $rules = [
            'username'   => 'required|min_length[4]|is_unique[users.username]',
            'email'      => 'required|valid_email|is_unique[users.email]',
            'password'   => 'required|min_length[6]',
            'role_id'    => 'required|integer',
            'tenant_id'  => 'required|integer',
            'branch_id'  => 'required|integer' // optional
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $data = $this->request->getJSON(true);

        $insertData = [
            'username'      => htmlspecialchars($data['username']),
            'email'         => htmlspecialchars($data['email']),
            'role_id'       => $data['role_id'],
            'tenant_id'     => $data['tenant_id'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'is_active'     => 1,
            'branch_id'     => $data['branch_id']
        ];

        $userId = $this->member->insert($insertData);

        if (!$userId) {
            return $this->failServerError('Gagal membuat user.');
        }

        return $this->respondCreated([
            'message' => 'User berhasil dibuat.',
            'user_id' => $userId
        ]);
    }

    public function update($id = null)
    {
        $existingUser = $this->member->where('is_active', 1)->find($id);

        if (!$existingUser) {
            return $this->failNotFound('User tidak ditemukan atau sudah dihapus.');
        }

        $data = $this->request->getJSON(true);

        $updateData = [];

        if (!empty($data['username'])) {
            $updateData['username'] = htmlspecialchars($data['username']);
        }

        if (!empty($data['email'])) {
            $updateData['email'] = htmlspecialchars($data['email']);
        }

        if (!empty($data['role_id'])) {
            $updateData['role_id'] = (int)$data['role_id'];
        }

        if (!empty($data['tenant_id'])) {
            $updateData['tenant_id'] = (int)$data['tenant_id'];
        }

        if (!empty($data['password'])) {
            $updateData['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if (!empty($data['branch_id'])) {
            $updateData['branch_id'] = (int)$data['branch_id'];
        }

        if (empty($updateData)) {
            return $this->fail('Tidak ada data yang dikirim untuk diperbarui.');
        }

        if (!$this->member->update($id, $updateData)) {
            return $this->failServerError('Gagal memperbarui data user.');
        }

        return $this->respond(['message' => 'User berhasil diperbarui.']);
    }

    public function delete($id = null)
    {
        $user = $this->member->where('is_active', 1)->find($id);

        if (!$user) {
            return $this->failNotFound('User tidak ditemukan atau sudah dihapus.');
        }

        if (!$this->member->update($id, ['is_active' => 0])) {
            return $this->failServerError('Gagal melakukan soft delete pada user.');
        }

        return $this->respondDeleted(['message' => 'User berhasil dinonaktifkan.']);
    }
}
