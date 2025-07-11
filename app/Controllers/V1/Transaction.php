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

    public function dailyReport()
    {
        $data = $this->request->getJSON(true); // Ambil data dari body JSON

        $startDate = $data['start_date'] ?? null;
        $endDate   = $data['end_date'] ?? null;
        $branchId  = $data['branch_id'] ?? null;

        // Validasi
        if (!$startDate || !$endDate) {
            return $this->failValidationErrors('Tanggal mulai dan akhir wajib diisi.');
        }

        $start = date('Y-m-d 00:00:00', strtotime($startDate));
        $end   = date('Y-m-d 23:59:59', strtotime($endDate));

        $db = \Config\Database::connect();

        $builder = $db->table('transactions t')
            ->select('t.id as transaction_id, t.transaction_type, t.transaction_date, t.client_id, t.branch_id, t.created_by, t.created_at,
                    tl.currency_id, tl.amount_foreign, tl.amount_local, tl.rate_used, c.name as currency_name')
            ->join('transaction_lines tl', 'tl.transaction_id = t.id')
            ->join('currencies c', 'c.id = tl.currency_id', 'left')
            ->where('t.transaction_date >=', $start)
            ->where('t.transaction_date <=', $end);

        if ($branchId) {
            $builder->where('t.branch_id', $branchId);
        }

        $builder->orderBy('t.transaction_date', 'ASC');
        $results = $builder->get()->getResultArray();

        // Group by transaction ID
        $grouped = [];
        foreach ($results as $row) {
            $trxId = $row['transaction_id'];
            if (!isset($grouped[$trxId])) {
                $grouped[$trxId] = [
                    'transaction_id' => $trxId,
                    'transaction_type' => $row['transaction_type'],
                    'transaction_date' => $row['transaction_date'],
                    'client_id' => $row['client_id'],
                    'branch_id' => $row['branch_id'],
                    'created_by' => $row['created_by'],
                    'created_at' => $row['created_at'],
                    'lines' => []
                ];
            }

            $grouped[$trxId]['lines'][] = [
                'currency_id' => $row['currency_id'],
                'currency_name' => $row['currency_name'],
                'amount_foreign' => (float) $row['amount_foreign'],
                'rate_used' => (float) $row['rate_used'],
                'amount_local' => (float) $row['amount_local'],
            ];
        }

        return $this->respond(array_values($grouped));
    }
}
