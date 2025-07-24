<?php

namespace App\Controllers\V1;

use App\Models\Mdl_transaction;
use App\Models\Mdl_transaction_line;
use App\Models\Mdl_client;
use App\Models\Mdl_branch;
// use App\Models\Mdl_payment_type;
use App\Controllers\BaseApiController;
use App\Models\Mdl_exchange_rate;

class Transaction extends BaseApiController
{
    protected $transactionModel;
    protected $lineModel;
    protected $clientModel;

    public function __construct()
    {
        $this->transactionModel = new Mdl_transaction();
        $this->lineModel        = new Mdl_transaction_line();
        $this->clientModel      = new Mdl_client();
    }

    public function create()
    {
        helper(['auth']);
        $data = $this->request->getJSON(true);

        $tenantId = auth_tenant_id();
        $userId   = auth_user_id();
        $payload  = decode_jwt_payload();
        $userRole = $payload->role ?? 'user';

        $today = date('Y-m-d');
        $transactionDate = $userRole === 'admin'
            ? ($data['transaction_date'] ?? $today)
            : $today;

        $branchId = $data['branch_id'] ?? null;
        $idNumber = $data['id_number'];
        $client   = $this->clientModel->getClientByIdNumberRaw($idNumber);

        if ($client) {
            $clientId = $client['id'];
        } else {
            $clientData = [
                'tenant_id'  => $tenantId,
                'name'       => $data['name'] ?? '',
                'id_type'    => $data['id_type'] ?? '',
                'id_number'  => $idNumber,
                'address'    => $data['address'] ?? '',
                'job'        => $data['job'] ?? '',
            ];
            $clientId = $this->clientModel->insertClientRaw($clientData);
        }

        $transactionType = $data['transaction_type'] ?? 'BUY';

        // Insert transaksi utama
        $insertData = [
            'tenant_id'        => $tenantId,
            'branch_id'        => $branchId,
            'client_id'        => $clientId,
            'transaction_type' => $transactionType,
            'payment_type_id'  => $data['payment_type_id'] ?? null,
            'bank_id'          => $data['bank_id'] ?? null,
            'transaction_date' => $transactionDate,
            'created_by'       => $userId
        ];

        $this->transactionModel->insertTransactionRaw($insertData);

        // Ambil last inserted ID
        $db = \Config\Database::connect();
        $transactionId = $db->insertID();

        // Insert line-line currency
        $exchangeRateModel = new Mdl_exchange_rate();
        $lines = $data['currencies'] ?? [];

        foreach ($lines as $line) {
            $currencyId = $line['currency_id'];
            $amount     = $line['amount'];

            $rate = $exchangeRateModel->getRateByCurrencyAndType($currencyId, $transactionType);
            $rateUsed = $rate['rate_used'] ?? 0;

            $lineData = [
                'transaction_id' => $transactionId,
                'currency_id'    => $currencyId,
                'rate_used'      => $rateUsed,
                'amount_foreign' => $transactionType === 'SELL' ? $amount : 0,
                'amount_local'   => $transactionType === 'BUY'  ? $amount : 0,
            ];

            $this->lineModel->insertLineRaw($lineData);
        }

        return $this->respondCreated(['message' => 'Transaksi berhasil disimpan.']);
    }

}