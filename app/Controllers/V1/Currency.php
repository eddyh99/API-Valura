<?php

namespace App\Controllers\V1;

use App\Models\Mdl_currency;
use App\Models\Mdl_default_currency;
use App\Models\Mdl_branch;
use App\Models\Mdl_transaction;
use App\Controllers\BaseApiController;

class Currency extends BaseApiController
{
    // protected $modelName = Mdl_currency::class;
    protected $format    = 'json';
    protected $currencyModel;
    protected $defaultCurrencyModel;

    public function __construct()
    {
        $this->currencyModel        = new Mdl_currency();
        $this->defaultCurrencyModel = new Mdl_default_currency();
        $this->branchModel          = new Mdl_branch();
        $this->transactionModel     = new Mdl_transaction();
    }

    // Show All Currencies
    public function show_all_currencies()
    {
        $currencies = $this->currencyModel->getAllCurrenciesRaw($this->tenantId);

        return $this->respond([
            'status' => true,
            'data' => $currencies
        ]);
    }

    // Show Today Currency by Branch ID
    public function showCurrency_ByID($id = null)
    {
        $currency = $this->currencyModel->getCurrencyByIdRaw($this->tenantId, $id);

        return $this->respond([
            'status' => true,
            'data' => $currency
        ]);
    }

    public function create()
    {
        $validation = $this->validation;
        $validation->setRules([
            'code' => [
                'label'  => 'Kode Mata Uang',
                'rules'  => 'required|trim|alpha|max_length[10]|is_unique[currencies.code,tenant_id,' . $this->tenantId . ']',
                'errors' => [
                    'required'   => '{field} wajib diisi.',
                    'alpha'      => '{field} hanya boleh berisi huruf.',
                    'max_length' => '{field} maksimal 10 karakter.',
                    'is_unique'  => '{field} sudah digunakan.',
                ]
            ],
            'name' => [
                'label'  => 'Nama Mata Uang',
                'rules'  => 'required|trim|max_length[100]|alpha_numeric_space',
                'errors' => [
                    'required'            => '{field} wajib diisi.',
                    'max_length'          => '{field} maksimal 100 karakter.',
                    'alpha_numeric_space' => '{field} hanya boleh berisi huruf, angka, dan spasi.',
                ]
            ],
            'symbol' => [
                'label'  => 'Simbol Mata Uang',
                'rules'  => 'required|trim|max_length[10]',
                'errors' => [
                    'required'   => '{field} wajib diisi.',
                    'max_length' => '{field} maksimal 10 karakter.',
                ]
            ],
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $data = $this->request->getJSON(true);

        $data['tenant_id'] = $this->tenantId;
        $data['is_active'] = 1;

        $currency = $this->currencyModel->setContext(current_context())->insert_currency($data);
        if (!$currency->status) {
            return $this->failValidationErrors($currency->message);
        }

        return $this->respondCreated(['message' => 'Currency berhasil ditambahkan']);
    }

    public function update($id = null)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT)) {
            return $this->failValidationErrors('ID Currency tidak valid');
        }

        $validation = $this->validation;
        $validation->setRules([
            'code' => [
                'label'  => 'Kode Mata Uang',
                'rules'  => 'required|trim|alpha|max_length[10]|is_unique[currencies.code,tenant_id,' . $this->tenantId . ']',
                'errors' => [
                    'required'   => '{field} wajib diisi.',
                    'alpha'      => '{field} hanya boleh berisi huruf.',
                    'max_length' => '{field} maksimal 10 karakter.',
                    'is_unique'  => '{field} sudah digunakan.',
                ]
            ],
            'name' => [
                'label'  => 'Nama Mata Uang',
                'rules'  => 'required|trim|max_length[100]|alpha_numeric_space',
                'errors' => [
                    'required'            => '{field} wajib diisi.',
                    'max_length'          => '{field} maksimal 100 karakter.',
                    'alpha_numeric_space' => '{field} hanya boleh berisi huruf, angka, dan spasi.',
                ]
            ],
            'symbol' => [
                'label'  => 'Simbol Mata Uang',
                'rules'  => 'required|trim|max_length[10]',
                'errors' => [
                    'required'   => '{field} wajib diisi.',
                    'max_length' => '{field} maksimal 10 karakter.',
                ]
            ],
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $data = $this->request->getJSON(true);

        $currency = $this->currencyModel->setContext(current_context())->update_currency($id, $data);
        if (!$currency->status) {
            return $this->failValidationErrors($currency->message);
        }

        return $this->respond(['message' => 'Currency berhasil diupdate']);
    }

    public function delete($id = null)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT)) {
            return $this->failValidationErrors('ID Currency tidak valid');
        }
        
        $currency = $this->currencyModel->setContext(current_context())->delete_currency($id);
        if (!$currency){
            return $this->failServerError('Currency gagal dihapus/sudah terhapus');
        }
        return $this->respondDeleted(['message' => 'Currency berhasil dihapus']);
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

    // Show Currencies Recap
    public function show_currency_recap()
    {
        $currencies = $this->currencyModel->getAllCurrenciesRaw($this->tenantId);

        return $this->respond([
            'status' => true,
            'data' => $currencies
        ]);
    }
}
