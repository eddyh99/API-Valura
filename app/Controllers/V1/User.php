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
        $users = $this->member->getAllUsersRaw($this->tenantId);

        return $this->respond([
            'status' => true,
            'data' => $users
        ]);
    }

    // Show User by ID
    public function showUser_ByID($id = null)
    {
        $user = $this->member->getUserByIdRaw($this->tenantId, $id);

        return $this->respond([
            'status' => true,
            'data' => $user
        ]);
    }

    public function create()
    {
        $validation = $this->validation;
        $validation->setRules([
            'username' => [
                'label' => 'Username',
                'rules' => 'required|trim|min_length[4]|max_length[100]|alpha_numeric|is_unique[users.username]',
                'errors' => [
                    'required'          => '{field} wajib diisi.',
                    'alpha_numeric'     => '{field} hanya boleh berisi huruf dan angka.',
                    'is_unique'         => '{field} sudah digunakan.',
                ]
            ],
            'email' => [
                'label' => 'Email',
                'rules' => 'required|trim|valid_email|is_unique[users.email]',
                'errors' => [
                    'required'      => '{field} wajib diisi.',
                    'valid_email'   => '{field} tidak valid.',
                    'is_unique'     => '{field} sudah digunakan.',
                ]
            ],
            'password' => [
                'label' => 'Password',
                'rules' => 'required|trim|min_length[6]',
                'errors' => [
                    'required'      => '{field} wajib diisi.',
                    'min_length'    => '{field} minimal 6 karakter.',
                ]
            ],
            'role_id' => [
                'label' => 'Role',
                'rules' => 'required|integer',
                'errors' => [
                    'required' => '{field} wajib diisi.',
                ]
            ],
            'branch_id' => [
                'label' => 'Cabang',
                'rules' => 'required|integer',
                'errors' => [
                    'required' => '{field} wajib diisi.',
                ]
            ],
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $data = $this->request->getJSON(true);

        $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        unset($data['password']); // jangan disimpan plaintext

        $data['tenant_id']  = $this->tenantId;
        $data['created_by'] = $this->userId;
        $data['is_active']  = 1;

        $user = $this->member->setContext(current_context())->insert_user($data);
        if (!$user->status) {
            return $this->failValidationErrors($user->message);
        }

        return $this->respondCreated(['message' => 'User berhasil ditambahkan']);
    }
    // public function create()
    // {
    //     $rules = [
    //         'username'   => 'required|min_length[4]|is_unique[users.username]',
    //         'email'      => 'required|valid_email|is_unique[users.email]',
    //         'password'   => 'required|min_length[6]',
    //         'role_id'    => 'required|integer',
    //         'branch_id'  => 'required|integer'
    //     ];

    //     if (!$this->validate($rules)) {
    //         return $this->failValidationErrors($this->validator->getErrors());
    //     }

    //     $data = $this->request->getJSON(true);

    //     $insertData = [
    //         'username'      => htmlspecialchars($data['username']),
    //         'email'         => htmlspecialchars($data['email']),
    //         'role_id'       => $data['role_id'],
    //         'branch_id'     => $data['branch_id'],
    //         'tenant_id'     => auth_tenant_id(), // ← Ambil otomatis dari helper
    //         'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
    //         'is_active'     => 1
    //     ];

    //     $userId = $this->member->insert($insertData);

    //     if (!$userId) {
    //         return $this->failServerError('Gagal membuat user.');
    //     }

    //     return $this->respondCreated([
    //         'message' => 'User berhasil dibuat.',
    //         'user_id' => $userId
    //     ]);
    // }

    public function update($id = null)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT)) {
            return $this->failValidationErrors('ID User tidak valid');
        }

        $validation = $this->validation;
        $validation->setRules([
            'username' => [
                'label' => 'Username',
                'rules' => 'required|trim|min_length[4]|max_length[100]|alpha_numeric|is_unique[users.username]',
                'errors' => [
                    'required'          => '{field} wajib diisi.',
                    'alpha_numeric'     => '{field} hanya boleh berisi huruf dan angka.',
                    'is_unique'         => '{field} sudah digunakan.',
                ]
            ],
            'email' => [
                'label' => 'Email',
                'rules' => 'required|trim|valid_email|is_unique[users.email]',
                'errors' => [
                    'required'      => '{field} wajib diisi.',
                    'valid_email'   => '{field} tidak valid.',
                    'is_unique'     => '{field} sudah digunakan.',
                ]
            ],
            'password' => [
                'label' => 'Password',
                'rules' => 'required|trim|min_length[6]',
                'errors' => [
                    'required'      => '{field} wajib diisi.',
                    'min_length'    => '{field} minimal 6 karakter.',
                ]
            ],
            'role_id' => [
                'label' => 'Role',
                'rules' => 'required|integer',
                'errors' => [
                    'required' => '{field} wajib diisi.',
                ]
            ],
            'branch_id' => [
                'label' => 'Cabang',
                'rules' => 'required|integer',
                'errors' => [
                    'required' => '{field} wajib diisi.',
                ]
            ],
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $data = $this->request->getJSON(true);

        $user = $this->member->setContext(current_context())->update_user($id, $data);
        if (!$user->status) {
            return $this->failValidationErrors($user->message);
        }

        return $this->respond(['message' => 'User berhasil diupdate']);
    }

    // public function update($id = null)
    // {
    //     $existingUser = $this->member->where('is_active', 1)->find($id);

    //     if (!$existingUser) {
    //         return $this->failNotFound('User tidak ditemukan atau sudah dihapus.');
    //     }

    //     $data = $this->request->getJSON(true);

    //     $updateData = [];

    //     if (!empty($data['username'])) {
    //         $updateData['username'] = htmlspecialchars($data['username']);
    //     }

    //     if (!empty($data['email'])) {
    //         $updateData['email'] = htmlspecialchars($data['email']);
    //     }

    //     if (!empty($data['role_id'])) {
    //         $updateData['role_id'] = (int)$data['role_id'];
    //     }

    //     if (!empty($data['tenant_id'])) {
    //         $updateData['tenant_id'] = (int)$data['tenant_id'];
    //     }

    //     if (!empty($data['password'])) {
    //         $updateData['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
    //     }

    //     if (!empty($data['branch_id'])) {
    //         $updateData['branch_id'] = (int)$data['branch_id'];
    //     }

    //     if (empty($updateData)) {
    //         return $this->fail('Tidak ada data yang dikirim untuk diperbarui.');
    //     }

    //     if (!$this->member->update($id, $updateData)) {
    //         return $this->failServerError('Gagal memperbarui data user.');
    //     }

    //     return $this->respond(['message' => 'User berhasil diperbarui.']);
    // }

    public function delete($id = null)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT)) {
            return $this->failValidationErrors('ID Branch tidak valid');
        }
        
        $user = $this->member->setContext(current_context())->delete_user($id);
        if (!$user){
            return $this->failServerError('User gagal dihapus/sudah terhapus');
        }
        return $this->respondDeleted(['message' => 'User berhasil dihapus']);
    }
    // public function delete($id = null)
    // {
    //     $user = $this->member->where('is_active', 1)->find($id);

    //     if (!$user) {
    //         return $this->failNotFound('User tidak ditemukan atau sudah dihapus.');
    //     }

    //     if (!$this->member->update($id, ['is_active' => 0])) {
    //         return $this->failServerError('Gagal melakukan soft delete pada user.');
    //     }

    //     return $this->respondDeleted(['message' => 'User berhasil dinonaktifkan.']);
    // }

    // // Show All Users
    // public function show_all_users()
    // {
    //     $tenantId = auth_tenant_id();

    //     $users = $this->member->showAll($tenantId);

    //     return $this->respond([
    //         'status' => true,
    //         'data' => $users
    //     ]);
    // }

    // Show User by ID
    // public function showUser_ByID($id = null)
    // {
    //     $tenantId = auth_tenant_id();

    //     $user = $this->member
    //         ->where('id', $id)
    //         ->where('is_active', 1)
    //         ->where('tenant_id', $tenantId)
    //         ->first();

    //     if (!$user) {
    //         return $this->failNotFound('User tidak ditemukan atau sudah dihapus.');
    //     }

    //     return $this->respond([
    //         'status' => true,
    //         'data' => $user
    //     ]);
    // }
}
