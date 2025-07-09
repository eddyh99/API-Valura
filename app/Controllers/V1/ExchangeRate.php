<?php

namespace App\Controllers\V1;

use App\Models\Mdl_exchange_rate;
use CodeIgniter\RESTful\ResourceController;

class ExchangeRate extends ResourceController
{
    protected $modelName = Mdl_exchange_rate::class;
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

        return $this->respondCreated(['message' => 'Exchange rate berhasil ditambahkan']);
    }

    public function update($id = null)
    {
        $data = $this->request->getJSON(true);

        if (!$this->model->where('is_active', 1)->find($id)) {
            return $this->failNotFound('Exchange rate tidak ditemukan atau sudah dihapus');
        }

        if (!$this->model->update($id, $data)) {
            return $this->failValidationErrors($this->model->errors());
        }

        return $this->respond(['message' => 'Exchange rate berhasil diupdate']);
    }

    public function delete($id = null)
    {
        $rate = $this->model->where('is_active', 1)->find($id);

        if (!$rate) {
            return $this->failNotFound('Exchange rate tidak ditemukan atau sudah dihapus');
        }

        // Soft delete: set is_active = 0
        if (!$this->model->update($id, ['is_active' => 0])) {
            return $this->failServerError('Gagal melakukan soft delete');
        }

        return $this->respondDeleted(['message' => 'Exchange rate berhasil di-nonaktifkan']);
    }
}
