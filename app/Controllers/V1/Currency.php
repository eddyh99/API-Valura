<?php

namespace App\Controllers\V1;

use App\Models\Mdl_currency;
use App\Models\Mdl_default_currency;
use App\Controllers\BaseApiController;

use App\Models\Mdl_branch;
use App\Models\Mdl_transaction;

class Currency extends BaseApiController
{
    protected $modelName = Mdl_currency::class;
    protected $format    = 'json';

    protected $defaultCurrencyModel;
    public function __construct()
    {
        $this->defaultCurrencyModel = new Mdl_default_currency();

        $this->branchModel = new Mdl_branch();
        $this->transactionModel = new Mdl_transaction();
    }

    // Show All Currencies
    public function show_all_currencies()
    {
        $tenantId = auth_tenant_id();

        $currencies = $this->model->getAllCurrenciesRaw($tenantId);

        return $this->respond([
            'status' => true,
            'data' => $currencies
        ]);
    }

    // Show Currency Amount by Branch ID
    public function showTodayCurrency_byBranchID()
    {
        $tenantId = auth_tenant_id();
        $branchId = auth_branch_id();

        $data = $this->transactionModel->getCurrencyAmountByBranchIdRaw($tenantId, $branchId);

        return $this->respond([
            'status' => true,
            'data' => $data
        ]);
    }

    public function rekapCurrencyPenukaran()
    {
        $tenantId = auth_tenant_id();
        $data = $this->request->getJSON();

        // Ambil range tanggal dari request (default: hari ini)
        $today = date('Y-m-d');
        $startDate = $data->start_date ?? $today;
        $endDate = $data->end_date ?? $today;
        // Ambil cabang yang dipilih
        $branchid = $data->branch_id ?? null;

        // Query ke model
        $data = $this->transactionModel->getCurrencySummaryRaw($tenantId, $branchid, $startDate, $endDate);

        // Format response
        $response = [
            'Range Date' => $startDate . ' - ' . $endDate,
            'Data' => $data
        ];

        return $this->respond($response);
    }

    // Show Today Currency by Branch ID
    public function showCurrency_ByID($id = null)
    {
        $tenantId = auth_tenant_id();

        $currency = $this->model->getCurrencyByIdRaw($tenantId, $id);

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
