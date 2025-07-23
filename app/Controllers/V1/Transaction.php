<?php

namespace App\Controllers\V1;

use App\Models\Mdl_transaction;
use App\Models\Mdl_transaction_line;
use App\Models\Mdl_client;
use App\Models\Mdl_branch;
// use App\Models\Mdl_payment_type;
use App\Controllers\BaseApiController;

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
        helper(['auth']); // pastikan auth_helper dipanggil

        $data = $this->request->getJSON(true);

        // Ambil dari helper JWT
        $tenantId = auth_tenant_id();
        $userId   = auth_user_id();

        // Untuk validasi role user (optional)
        $payload  = decode_jwt_payload();
        $userRole = $payload->role ?? 'user';

        $today = date('Y-m-d');

        // 1. Tanggal
        $transactionDate = $userRole === 'admin'
            ? ($data['transaction_date'] ?? $today)
            : $today;

        // 2. Cabang
        $branchId = $data['branch_id'] ?? null;

        // 3. No Identitas
        $idNumber = $data['id_number'];
        $client   = $this->clientModel->getClientByIdNumberRaw($idNumber);

        if ($client) {
            $clientId = $client['id'];
        } else {
            // Buat client baru
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

        // 4. Insert transaksi
        $insertData = [
            'tenant_id'        => $tenantId,
            'branch_id'        => $branchId,
            'client_id'        => $clientId,
            'transaction_type' => $data['transaction_type'] ?? 'BUY',
            'payment_type_id'  => $data['payment_type_id'] ?? null,
            'bank_id'          => $data['bank_id'] ?? null,
            'transaction_date' => $transactionDate,
            'created_by'       => $userId
        ];

        $result = $this->transactionModel->insertTransactionRaw($insertData);

        if (!$result) {
            return $this->failServerError('Gagal menyimpan transaksi.');
        }

        return $this->respondCreated(['message' => 'Transaksi berhasil disimpan.']);
    }

}