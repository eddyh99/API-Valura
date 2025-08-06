<?php

namespace App\Controllers\V1;

use App\Models\Mdl_role;
use App\Controllers\BaseApiController;

class Role extends BaseApiController
{
    protected $format    = 'json';
    // protected $modelName = Mdl_role::class;
    protected $roleModel;

    public function __construct()
    {
        $this->roleModel = new Mdl_role();
    }

    // Show All Roles
    public function show_all_roles()
    {
        $roles = $this->roleModel->getAllRolesRaw($this->tenantId);

        return $this->respond([
            'status' => true,
            'data' => $roles
        ]);
    }

    // Show Role by ID
    public function showRole_ByID($id = null)
    {

        $role = $this->roleModel->getRoleByIdRaw($this->tenantId, $id);

        return $this->respond([
            'status' => true,
            'data' => $role
        ]);
    }

    public function create()
    {
        $validation = $this->validation;
        $validation->setRules([
            'name' => [
                'label' => 'Nama Role',
                'rules' => 'required|trim|max_length[50]|alpha_numeric_space',
                'errors' => [
                    'required'             => '{field} wajib diisi.',
                    'max_length'           => '{field} maksimal 50 karakter.',
                    'alpha_numeric_space'  => '{field} hanya boleh berisi huruf, angka, dan spasi.',
                ]
            ],
            'permissions' => [
                'label' => 'Permissions',
                'rules' => 'required|valid_json',
                'errors' => [
                    'required'   => '{field} wajib diisi.',
                    'valid_json' => '{field} harus berupa format JSON yang valid.',
                ]
            ],
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $data = $this->request->getJSON(true);

        $data['tenant_id'] = $this->tenantId;
        $data['is_active'] = 1;

        $role = $this->roleModel->setContext(current_context())->insert_role($data);
        if (!$role->status) {
            return $this->failValidationErrors($role->message);
        }

        return $this->respondCreated(['message' => 'Role berhasil ditambahkan']);
    }

    public function update($id = null)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT)) {
            return $this->failValidationErrors('ID Role tidak valid');
        }

        $validation = $this->validation;
        $validation->setRules([
            'name' => [
                'label' => 'Nama Role',
                'rules' => 'required|trim|max_length[50]|alpha_numeric_space',
                'errors' => [
                    'required'             => '{field} wajib diisi.',
                    'max_length'           => '{field} maksimal 50 karakter.',
                    'alpha_numeric_space'  => '{field} hanya boleh berisi huruf, angka, dan spasi.',
                ]
            ],
            'permissions' => [
                'label' => 'Permissions',
                'rules' => 'required|valid_json',
                'errors' => [
                    'required'   => '{field} wajib diisi.',
                    'valid_json' => '{field} harus berupa format JSON yang valid.',
                ]
            ],
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $data = $this->request->getJSON(true);
        
        $role = $this->roleModel->setContext(current_context())->update_role($id, $data);
        if (!$role->status) {
            return $this->failValidationErrors($role->message);
        }

        return $this->respond(['message' => 'Role berhasil diupdate']);
    }

    public function delete($id = null)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT)) {
            return $this->failValidationErrors('ID Role tidak valid');
        }
        
        $role = $this->roleModel->setContext(current_context())->delete_role($id);
        if (!$role){
            return $this->failServerError('Role gagal dihapus/sudah terhapus');
        }
        return $this->respondDeleted(['message' => 'Role berhasil dihapus']);
    }
}
