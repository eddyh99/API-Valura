<?php

namespace App\Controllers\V1;

use App\Models\Mdl_bank_deposit;
use CodeIgniter\RESTful\ResourceController;

class BankDeposit extends ResourceController
{
    protected $format = 'json';
    protected $depositModel;

    public function __construct()
    {
        $this->depositModel = new Mdl_bank_deposit();
    }

    public function create()
    {
        $data = $this->request->getJSON(true);

        $rules = [
            'branch_id'  => 'permit_empty|integer',
            'type'       => 'required|in_list[Bank,Agen]',
            'deposit_date' => 'required|valid_date',
            'currencies' => 'required',
            'currencies.*.currency_id' => 'required|integer',
            'currencies.*.amount'      => 'required|numeric',
            'bank_id'     => 'required|integer',
            'note'        => 'permit_empty|string'
        ];

        if (! $this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $userId   = auth_user_id();
        $tenantId = auth_tenant_id();

        if (!$userId || !$tenantId) {
            return $this->failUnauthorized('User tidak valid atau token tidak ditemukan.');
        }

        $inserted = 0;

        foreach ($data['currencies'] as $row) {
            $entry = [
                'tenant_id'    => $tenantId,
                'branch_id'    => $data['branch_id'] ?? null,
                'type'         => $data['type'],
                'note'         => $data['note'] ?? null,
                'currency_id'  => $row['currency_id'],
                'amount'       => $row['amount'],
                'bank_id'      => $data['bank_id'],
                'deposit_date' => $data['deposit_date'],
                'created_by'   => $userId,
                'created_at'   => date('Y-m-d H:i:s')
            ];

            $this->depositModel->insert($entry);
            $inserted++;
        }

        return $this->respondCreated([
            'message' => 'Penukaran berhasil disimpan.',
            'inserted_rows' => $inserted
        ]);
    }
}
