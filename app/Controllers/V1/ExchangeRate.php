<?php

namespace App\Controllers\V1;

use App\Models\Mdl_exchange_rate;
use App\Controllers\BaseApiController;

class ExchangeRate extends BaseApiController
{
    protected $modelName = Mdl_exchange_rate::class;
    protected $format    = 'json';

    // Show All Exchange Rates
    public function show_all_exchangeRates()
    {
        $tenantId = auth_tenant_id();

        $exchange_rates = $this->model->getAllExchangeRatesRaw($tenantId);

        return $this->respond([
            'status' => true,
            'data' => $exchange_rates
        ]);
    }

    // Show Exchange Rate by ID
    public function showExchangeRate_ByID($id = null)
    {
        $tenantId = auth_tenant_id();

        $exchange_rate = $this->model->getExchangeRateByIdRaw($tenantId, $id);

        return $this->respond([
            'status' => true,
            'data' => $exchange_rate
        ]);
    }

    public function create()
    {
        $data = $this->request->getJSON(true);

        $data['tenant_id']   = auth_tenant_id();
        $data['created_by']  = auth_user_id();
        $data['rate_date']   = date('Y-m-d');
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
