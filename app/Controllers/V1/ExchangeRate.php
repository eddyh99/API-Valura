<?php

namespace App\Controllers\V1;

use App\Models\Mdl_exchange_rate;
use App\Controllers\BaseApiController;

class ExchangeRate extends BaseApiController
{
    // protected $modelName = Mdl_exchange_rate::class;
    protected $format = 'json';
    protected $exchangeRateModel;

    public function __construct()
    {
        $this->exchangeRateModel = new Mdl_exchange_rate();
    }

    // Show All Exchange Rates
    public function show_all_exchangeRates()
    {
        $exchange_rates = $this->exchangeRateModel->getAllExchangeRatesRaw($this->tenantId);

        return $this->respond([
            'status' => true,
            'data' => $exchange_rates
        ]);
    }

    // Show Exchange Rate by ID
    public function showExchangeRate_ByID($id = null)
    {
        $exchange_rate = $this->exchangeRateModel->getExchangeRateByIdRaw($this->tenantId, $id);

        return $this->respond([
            'status' => true,
            'data' => $exchange_rate
        ]);
    }

    public function create()
    {
        $validation = $this->validation;
        $validation->setRules([
            'currency_id' => [
                'label'  => 'Mata Uang',
                'rules'  => 'required|is_natural_no_zero',
                'errors' => [
                    'required'           => '{field} wajib diisi.',
                    'is_natural_no_zero' => '{field} tidak valid.'
                ]
            ],
            'buy_rate' => [
                'label'  => 'Rate Beli',
                'rules'  => 'required|numeric|greater_than[0]',
                'errors' => [
                    'required'     => '{field} wajib diisi.',
                    'numeric'      => '{field} harus berupa angka.',
                    'greater_than' => '{field} harus lebih besar dari 0.'
                ]
            ],
            'sell_rate' => [
                'label'  => 'Rate Jual',
                'rules'  => 'required|numeric|greater_than[0]',
                'errors' => [
                    'required'     => '{field} wajib diisi.',
                    'numeric'      => '{field} harus berupa angka.',
                    'greater_than' => '{field} harus lebih besar dari 0.'
                ]
            ]
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $data = $this->request->getJSON(true);

        $currencyId = $data['currency_id'];
        $today      = date('Y-m-d');

        // Cek apakah sudah ada rate untuk currency tersebut
        $alreadyExists = $this->exchangeRateModel->checkExistingRateTodayRaw($this->tenantId, $currencyId);
        if ($alreadyExists) {
            return $this->failValidationErrors([
                'currency_id' => "Currency dengan ID {$currencyId} sudah ditetapkan rate-nya."
            ]);
        }

        $data['tenant_id']  = $this->tenantId;
        $data['created_by'] = $this->userId;
        $data['is_active']  = 1;
        $data['rate_date']  = $today;

        $rate = $this->exchangeRateModel->setContext(current_context())->insert_exchangeRate($data);

        if (!$rate->status) {
            return $this->failValidationErrors($rate->message);
        }

        return $this->respondCreated(['message' => 'Exchange rate berhasil ditambahkan']);
    }

    public function update($id = null)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT)) {
            return $this->failValidationErrors('ID Exchange rate tidak valid');
        }

        $validation = $this->validation;
        $validation->setRules([
            'currency_id' => [
                'label'  => 'Mata Uang',
                'rules'  => 'required|is_natural_no_zero',
                'errors' => [
                    'required'           => '{field} wajib diisi.',
                    'is_natural_no_zero' => '{field} tidak valid.'
                ]
            ],
            'buy_rate' => [
                'label'  => 'Rate Beli',
                'rules'  => 'required|numeric|greater_than[0]',
                'errors' => [
                    'required'     => '{field} wajib diisi.',
                    'numeric'      => '{field} harus berupa angka.',
                    'greater_than' => '{field} harus lebih besar dari 0.'
                ]
            ],
            'sell_rate' => [
                'label'  => 'Rate Jual',
                'rules'  => 'required|numeric|greater_than[0]',
                'errors' => [
                    'required'     => '{field} wajib diisi.',
                    'numeric'      => '{field} harus berupa angka.',
                    'greater_than' => '{field} harus lebih besar dari 0.'
                ]
            ]
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $data = $this->request->getJSON(true);

        $exchangeRate = $this->exchangeRateModel->setContext(current_context())->update_exchangeRate($id, $data);
        if (!$exchangeRate->status) {
            return $this->failValidationErrors($exchangeRate->message);
        }

        return $this->respond(['message' => 'Exchange rate berhasil diupdate']);
    }

    public function delete($id = null)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT)) {
            return $this->failValidationErrors('ID Exchange rate tidak valid');
        }
        
        $exchangeRate = $this->exchangeRateModel->setContext(current_context())->delete_exchangeRate($id);
        if (!$exchangeRate){
            return $this->failServerError('Exchange rate gagal dihapus/sudah terhapus');
        }
        return $this->respondDeleted(['message' => 'Exchange rate berhasil dihapus']);
    }
}
