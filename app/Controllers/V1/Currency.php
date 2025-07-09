<?php

namespace App\Controllers\V1;

use App\Models\Mdl_currency;
use CodeIgniter\RESTful\ResourceController;

class Currency extends ResourceController
{
    protected $modelName = Mdl_currency::class;
    protected $format    = 'json';

    public function create()
    {
        $data = $this->request->getJSON(true);

        $data['tenant_id'] = auth_tenant_id(); // Pastikan helper ini tersedia
        $data['is_active'] = 1;

        if (!$this->model->insert($data)) {
            return $this->failValidationErrors($this->model->errors());
        }

        return $this->respondCreated(['message' => 'Currency berhasil ditambahkan']);
    }

    public function update($id = null)
    {
        $data = $this->request->getJSON(true);

        if (!$this->model->find($id)) {
            return $this->failNotFound('Currency tidak ditemukan');
        }

        if (!$this->model->update($id, $data)) {
            return $this->failValidationErrors($this->model->errors());
        }

        return $this->respond(['message' => 'Currency berhasil diupdate']);
    }

    public function delete($id = null)
    {
        $currency = $this->model->find($id);

        if (!$currency) {
            return $this->failNotFound('Currency tidak ditemukan');
        }

        // Soft delete: hanya ubah is_active = 0
        if (!$this->model->update($id, ['is_active' => 0])) {
            return $this->failServerError('Gagal menghapus currency');
        }

        return $this->respondDeleted(['message' => 'Currency berhasil di-nonaktifkan (soft delete)']);
    }
}
