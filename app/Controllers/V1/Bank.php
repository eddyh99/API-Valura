<?php

namespace App\Controllers\V1;

use App\Models\Mdl_bank;
use App\Controllers\BaseApiController;

class Bank extends BaseApiController
{
    protected $modelName = Mdl_bank::class;
    protected $format    = 'json';

    public function show_all_banks()
    {
        $tenantId = auth_tenant_id();

        $banks = $this->model->getAllBanksRaw($tenantId);

        return $this->respond([
            'status' => true,
            'data' => $banks
        ]);
    }

    public function showBank_ByID($id = null)
    {
        $tenantId = auth_tenant_id();

        $bank = $this->model->getBankByIdRaw($tenantId, $id);

        return $this->respond([
            'status' => true,
            'data' => $bank
        ]);
    }

    public function create()
    {
        $data = $this->request->getJSON(true);

        $data['tenant_id']  = auth_tenant_id();
        $data['created_by'] = auth_user_id();
        $data['is_active']  = 1;
        $data['created_at'] = date('Y-m-d H:i:s');

        $rules = [
            'name'       => 'required|string|max_length[100]',
            'account_no' => 'permit_empty|string|max_length[50]',
            'branch'     => 'permit_empty|string|max_length[100]',
        ];

        if (! $this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        if (!$this->model->insert($data)) {
            return $this->failServerError('Gagal menambahkan data bank.');
        }

        return $this->respondCreated(['message' => 'Bank berhasil ditambahkan']);
    }

    public function update($id = null)
    {
        $data = $this->request->getJSON(true);

        $bank = $this->model
            ->where('id', $id)
            ->where('is_active', 1)
            ->first();

        if (!$bank) {
            return $this->failNotFound('Bank tidak ditemukan atau sudah dihapus');
        }

        $rules = [
            'name'       => 'required|string|max_length[100]',
            'account_no' => 'permit_empty|string|max_length[50]',
            'branch'     => 'permit_empty|string|max_length[100]',
        ];

        if (! $this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $data['updated_by'] = auth_user_id();
        $data['updated_at'] = date('Y-m-d H:i:s');

        if (!$this->model->update($id, $data)) {
            return $this->failServerError('Gagal mengupdate data bank.');
        }

        return $this->respond(['message' => 'Bank berhasil diupdate']);
    }

    public function delete($id = null)
    {
        $bank = $this->model->where('id', $id)->where('is_active', 1)->first();

        if (!$bank) {
            return $this->failNotFound('Bank tidak ditemukan atau sudah dihapus');
        }

        // Soft delete set is_active=0
        if (!$this->model->update($id, ['is_active' => 0])) {
            return $this->failServerError('Gagal melakukan soft delete');
        }

        return $this->respondDeleted(['message' => 'Bank berhasil di-nonaktifkan']);
    }
}
