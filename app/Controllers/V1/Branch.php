<?php

namespace App\Controllers\V1;

use App\Models\Mdl_branch;
use App\Controllers\BaseApiController;

class Branch extends BaseApiController
{
    protected $modelName = Mdl_branch::class;
    protected $format    = 'json';

    // Show All Branches
    public function show_all_branches()
    {
        $tenantId = auth_tenant_id();

        // Ambil semua cabang aktif milik tenant ini
        $branches = $this->model
            ->where('is_active', 1)
            ->where('tenant_id', $tenantId)
            ->findAll();

        // Ambil data tenant max_branch dari model tenant
        $mdlTenant = new \App\Models\Mdl_tenant();
        $tenant = $mdlTenant->find($tenantId);

        $maxBranch = 0;
        if ($tenant && isset($tenant['max_branch'])) {
            $maxBranch = (int) $tenant['max_branch'];
        }

        // Hitung jumlah cabang aktif saat ini
        $currentBranchCount = count($branches);

        return $this->respond([
            'status' => true,
            'data' => $branches,
            'max_branch' => $maxBranch,
            'current_branch_count' => $currentBranchCount
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

    public function create()
    {
        $data = $this->request->getJSON(true);

        $tenantId = auth_tenant_id();

        // Load model tenant untuk cek max_branch
        $mdlTenant = new \App\Models\Mdl_tenant();
        $tenant = $mdlTenant->find($tenantId);

        if (!$tenant) {
            return $this->failNotFound('Tenant tidak ditemukan.');
        }

        $maxBranch = (int) $tenant['max_branch'];

        // Hitung jumlah branch aktif tenant ini
        $currentBranchCount = $this->model
            ->where('tenant_id', $tenantId)
            ->where('is_active', 1)
            ->countAllResults();

        if ($currentBranchCount >= $maxBranch) {
            return $this->failForbidden("MAX BRANCH: User dengan Tenant ID {$tenantId} hanya bisa menambahkan {$maxBranch} cabang saja.");
        }

        $data['tenant_id']  = $tenantId;
        $data['created_by'] = auth_user_id();
        $data['is_active']  = 1;

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
}
