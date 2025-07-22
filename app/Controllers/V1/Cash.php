<?php

namespace App\Controllers\V1;

use App\Models\Mdl_cash;
use App\Controllers\BaseApiController;

class Cash extends BaseApiController
{
    protected $modelName = Mdl_cash::class;
    protected $format    = 'json';

    public function create()
    {
        $data = $this->request->getJSON(true);

        // Set data tambahan
        $data['tenant_id']  = auth_tenant_id();
        $data['created_by'] = auth_user_id();
        $data['is_active']  = 1;

        // Validasi movement_type harus IN atau OUT
        if (!in_array($data['movement_type'] ?? '', ['IN', 'OUT', 'AWAL'])) {
            return $this->failValidationErrors(['movement_type' => 'Movement type harus IN/OUT/AWAL']);
        }

        if (!$this->model->insert($data)) {
            return $this->failValidationErrors($this->model->errors());
        }

        return $this->respondCreated(['message' => 'Cash berhasil ditambahkan']);
    }

    public function update($id = null)
    {
        $data = $this->request->getJSON(true);

        // Cek data cash aktif
        $cash = $this->model->where('id', $id)->where('is_active', 1)->first();
        if (!$cash) {
            return $this->failNotFound('Cash tidak ditemukan atau sudah dihapus');
        }

        // Validasi movement_type jika ada
        if (isset($data['movement_type']) && !in_array($data['movement_type'], ['IN', 'OUT'])) {
            return $this->failValidationErrors(['movement_type' => 'Movement type harus IN atau OUT']);
        }

        if (!$this->model->update($id, $data)) {
            return $this->failValidationErrors($this->model->errors());
        }

        return $this->respond(['message' => 'Cash berhasil diupdate']);
    }

    public function delete($id = null)
    {
        $cash = $this->model->where('id', $id)->where('is_active', 1)->first();

        if (!$cash) {
            return $this->failNotFound('Cash tidak ditemukan atau sudah dihapus');
        }

        // Soft delete set is_active=0
        if (!$this->model->update($id, ['is_active' => 0])) {
            return $this->failServerError('Gagal melakukan soft delete');
        }

        return $this->respondDeleted(['message' => 'Cash berhasil di-nonaktifkan']);
    }

    public function show_all_cashes()
    {
        $tenantId = auth_tenant_id();

        $cashes = $this->model
            ->where('is_active', 1)
            ->where('tenant_id', $tenantId)
            ->findAll();

        return $this->respond([
            'status' => true,
            'data' => $cashes
        ]);
    }

    public function showCash_ByID($id = null)
    {
        $tenantId = auth_tenant_id();

        $cash = $this->model
            ->where('id', $id)
            ->where('is_active', 1)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$cash) {
            return $this->failNotFound('Cash tidak ditemukan atau sudah dihapus.');
        }

        return $this->respond([
            'status' => true,
            'data' => $cash
        ]);
    }

    public function showCash_ByBranchID($id = null)
    {
        $branchId = auth_branch_id();

        $cash = $this->model
            ->where('branch_id', $id)
            ->where('is_active', 1)
            ->first();

        if (!$cash) {
            return $this->failNotFound('Branch tidak ditemukan atau sudah dihapus.');
        }

        return $this->respond([
            'status' => true,
            'data' => $cash
        ]);
    }
}
