<?php

namespace App\Controllers\V1;

use App\Models\Mdl_currency;
use App\Models\Mdl_default_currency;
use App\Controllers\BaseApiController;

class Currency extends BaseApiController
{
    protected $modelName = Mdl_currency::class;
    protected $format    = 'json';

    protected $defaultCurrencyModel;
    public function __construct()
    {
        $this->defaultCurrencyModel = new Mdl_default_currency();
    }

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

    // Show All Currencies
    public function show_all_currencies()
    {
        $tenantId = auth_tenant_id();

        $currencies = $this->model
            ->where('is_active', 1)
            ->where('tenant_id', $tenantId)
            ->findAll();

        return $this->respond([
            'status' => true,
            'data' => $currencies
        ]);
    }

    // Show Currency by ID
    public function showCurrency_ByID($id = null)
    {
        $tenantId = auth_tenant_id();

        $currency = $this->model
            ->where('id', $id)
            ->where('is_active', 1)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$currency) {
            return $this->failNotFound('Currency tidak ditemukan atau sudah dihapus.');
        }

        return $this->respond([
            'status' => true,
            'data' => $currency
        ]);
    }

    // Show Default Currencies
    public function show_default_currencies()
    {
        // Ambil data default currencies dari tabel default_currency
        $defaultCurrencies = $this->defaultCurrencyModel
            ->where('is_active', 1)
            ->findAll();

        return $this->respond([
            'status' => true,
            'data' => $defaultCurrencies
        ]);
    }
}
