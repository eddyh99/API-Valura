<?php

namespace App\Controllers\V1;

use App\Models\Mdl_transaction;
use App\Models\Mdl_transaction_line;
use CodeIgniter\RESTful\ResourceController;

class Transaction extends ResourceController
{
    protected $format = 'json';
    protected $transactionModel;
    protected $lineModel;

    public function __construct()
    {
        $this->transactionModel = new Mdl_transaction();
        $this->lineModel = new Mdl_transaction_line();
    }

    public function create()
    {
        $data = $this->request->getJSON(true);

        // Validasi minimal
        $rules = [
            'transaction_type'   => 'required|in_list[BUY,SELL]',
            'transaction_date'   => 'required|valid_date',
            'payment_type_id'    => 'required|integer',
            'branch_id'          => 'permit_empty|integer',
            'client_id'          => 'permit_empty|integer',
            'bank_id'            => 'permit_empty|integer',
            'lines'              => 'required'
        ];

        if (! $this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        // Verifikasi user login dari JWT helper
        $userId = auth_user_id();
        $tenantId = auth_tenant_id();

        if (!$userId || !$tenantId) {
            return $this->failUnauthorized('User tidak valid atau token tidak ditemukan.');
        }

        // Simpan transaksi utama
        $transactionData = [
            'tenant_id'         => $tenantId,
            'branch_id'         => $data['branch_id'] ?? null,
            'client_id'         => $data['client_id'] ?? null,
            'transaction_type'  => $data['transaction_type'],
            'payment_type_id'   => $data['payment_type_id'],
            'bank_id'           => $data['bank_id'] ?? null,
            'transaction_date'  => $data['transaction_date'],
            'created_by'        => $userId,
            'created_at'        => date('Y-m-d H:i:s')
        ];

        $transactionId = $this->transactionModel->insert($transactionData);
        if (!$transactionId) {
            return $this->failServerError('Gagal menyimpan transaksi.');
        }

        // Simpan baris-baris currency
        foreach ($data['lines'] as $line) {
            if (
                !isset($line['currency_id']) ||
                !isset($line['amount_foreign']) ||
                !isset($line['amount_local']) ||
                !isset($line['rate_used'])
            ) {
                return $this->failValidationErrors('Data line currency tidak lengkap.');
            }

            $line['transaction_id'] = $transactionId;
            $this->lineModel->insert($line);
        }

        return $this->respondCreated([
            'message' => 'Transaksi berhasil disimpan.',
            'transaction_id' => $transactionId
        ]);
    }
}
