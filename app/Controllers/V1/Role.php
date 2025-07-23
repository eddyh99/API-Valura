<?php

namespace App\Controllers\V1;

use App\Models\Mdl_role;
use App\Controllers\BaseApiController;

class Role extends BaseApiController
{
    protected $modelName = Mdl_role::class;
    protected $format    = 'json';

    public function create()
    {
        $data = $this->request->getJSON(true);
        $data['tenant_id'] = auth_tenant_id();
        $data['is_active'] = 1;

        if (!$this->model->insert($data)) {
            return $this->failValidationErrors($this->model->errors());
        }

        return $this->respondCreated(['message' => 'Role berhasil ditambahkan']);
    }

    public function update($id = null)
    {
        $data = $this->request->getJSON(true);

        if (!$this->model->where('is_active', 1)->find($id)) {
            return $this->failNotFound('Role tidak ditemukan atau sudah dihapus');
        }

        if (!$this->model->update($id, $data)) {
            return $this->failValidationErrors($this->model->errors());
        }

        return $this->respond(['message' => 'Role berhasil diupdate']);
    }

    public function delete($id = null)
    {
        $role = $this->model->where('is_active', 1)->find($id);

        if (!$role) {
            return $this->failNotFound('Role tidak ditemukan atau sudah dihapus');
        }

        if (!$this->model->update($id, ['is_active' => 0])) {
            return $this->failServerError('Gagal melakukan soft delete pada role');
        }

        return $this->respondDeleted(['message' => 'Role berhasil di-nonaktifkan']);
    }

    // Show All Roles
    public function show_all_roles()
    {
        $tenantId = auth_tenant_id();

        $roles = $this->model
            ->where('is_active', 1)
            ->where('tenant_id', $tenantId)
            ->findAll();

        return $this->respond([
            'status' => true,
            'data' => $roles
        ]);
    }

    // Show Role by ID
    public function showRole_ByID($id = null)
    {
        $tenantId = auth_tenant_id();

        $role = $this->model
            ->where('id', $id)
            ->where('is_active', 1)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$role) {
            return $this->failNotFound('Role tidak ditemukan atau sudah dihapus.');
        }

        return $this->respond([
            'status' => true,
            'data' => $role
        ]);
    }
}
