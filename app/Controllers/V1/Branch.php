<?php

namespace App\Controllers\V1;

use App\Models\Mdl_branch;
use CodeIgniter\RESTful\ResourceController;

class Branch extends ResourceController
{
    protected $modelName = Mdl_branch::class;
    protected $format    = 'json';

    public function create()
    {
        $data = $this->request->getJSON(true);

        $data['tenant_id']   = auth_tenant_id();
        $data['created_by']  = auth_user_id();
        $data['is_active']   = 1;

        if (!$this->model->insert($data)) {
            return $this->failValidationErrors($this->model->errors());
        }

        return $this->respondCreated(['message' => 'Branch berhasil ditambahkan']);
    }

    public function update($id = null)
    {
        $data = $this->request->getJSON(true);

        if (!$this->model->where('is_active', 1)->find($id)) {
            return $this->failNotFound('Branch tidak ditemukan atau sudah dihapus');
        }

        if (!$this->model->update($id, $data)) {
            return $this->failValidationErrors($this->model->errors());
        }

        return $this->respond(['message' => 'Branch berhasil diupdate']);
    }

    public function delete($id = null)
    {
        $branch = $this->model->where('is_active', 1)->find($id);

        if (!$branch) {
            return $this->failNotFound('Branch tidak ditemukan atau sudah dihapus');
        }

        // Soft delete: set is_active = 0
        if (!$this->model->update($id, ['is_active' => 0])) {
            return $this->failServerError('Gagal melakukan soft delete');
        }

        return $this->respondDeleted(['message' => 'Branch berhasil di-nonaktifkan']);
    }

    // Show All Branches
    public function show_all_branches()
    {
        $tenantId = auth_tenant_id();

        $branches = $this->model
            ->where('is_active', 1)
            ->where('tenant_id', $tenantId)
            ->findAll();

        return $this->respond([
            'status' => true,
            'data' => $branches
        ]);
    }

    // Show Branch by ID
    public function showBranch_ByID($id = null)
    {
        $tenantId = auth_tenant_id();

        $branch = $this->model
            ->where('id', $id)
            ->where('is_active', 1)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$branch) {
            return $this->failNotFound('Branch tidak ditemukan atau sudah dihapus.');
        }

        return $this->respond([
            'status' => true,
            'data' => $branch
        ]);
    }
}
