<?php

namespace App\Controllers\V1;

use App\Models\Mdl_transaction;

use App\Models\Mdl_client;
use App\Models\Mdl_branch;
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
        $this->clientModel      = new Mdl_client();
    }

    // Show Transaction by ID
    public function showTransaction_ByID($id = null)
    {
        $tenantId = auth_tenant_id();

        // 1. Ambil data transaksi utama
        $transaction = $this->transactionModel->getTransactionByIdRaw($tenantId, $id);

        return $this->respond([
            'status' => true,
            'transaction' => $transaction
        ]);
    }

    // Show Daily Transaction by Today & Branch
    public function showDailyTransaction()
    {
        $tenantId = auth_tenant_id();
        $branchId = auth_branch_id();

        $data = $this->transactionModel->getDailyTransactionRaw($tenantId, $branchId);

        return $this->respond([
            'status' => true,
            'data' => $data
        ]);
    }

    public function getTodayTransactionByBranch($branchId)
    {
        $sql = "SELECT * 
                FROM cash_movements 
                WHERE branch_id = ? 
                AND is_active = 1 
                AND DATE(occurred_at) = CURDATE()";

        return $this->db->query($sql, [$branchId])->getResultArray();
    }

    public function create()
    {
        $data = $this->request->getJSON(true);

        $tenantId         = auth_tenant_id();
        $userId           = auth_user_id();
        $payload          = decode_jwt_payload();
        $branchId         = $payload->branch_id ?? null;
        $transactionType  = strtoupper($data['transaction_type'] ?? '');
        $transactionDate  = $data['transaction_date'] ?? date('Y-m-d');

        $client = $data["client"];
        $client["tenant_id"] =  $tenantId;
        
        $transaksi = array(
            "transaction_type"  => $transactionType,
            "branch_id"         => $branchId,
            "tenant_id"         => $tenantId,
            "transaction_date"  => date("Y-m-d H:i:s"),
            "created_by"        => $userId,
            "created_at"        => date("Y-m-d H:i:s"),
            "idempotency_key"   => $data["idempotency_key"]
        );
        
        $transactionId= $this->transactionModel->insertTransactionRaw($transaksi, $client, $data["lines"]);

        if (!$transactionId){
             return $this->failNotFound('Transaksi gagal di tambahkan');
        }

        // Cek apakah ID dari transaksi tersebut didapat dari reuse idempotency (bukan insert baru)
        $reused = false;

        if (!empty($data['idempotency_key'])) {
            $reused = $this->transactionModel->isIdempotencyKeyReused();
        }

        if ($reused) {
            return $this->respond([
                'message'         => 'Transaksi sudah pernah dilakukan',
                'transaction_id'  => $transactionId,
                'idemp_key'       => $data['idempotency_key']
            ]);
        } else {
            return $this->respondCreated([
                'message'         => 'Transaksi baru berhasil disimpan',
                'transaction_id'  => $transactionId,
                'idemp_key'       => $data['idempotency_key']
            ]);
        }
    }

    public function update($id = null)
    {
        $json = $this->request->getJSON(true);

        try {
            // 1. Ambil transaksi
            $tenantId = auth_tenant_id();
            $trx = $this->transactionModel->getTransactionByIdRaw($tenantId, $id);
            if (!$trx) {
                return $this->failNotFound("Transaksi ID $id tidak ditemukan");
            }

            // 2. Cek apakah transaksi dari tanggal hari ini
            $trxDate = date('Y-m-d', strtotime($trx['transaction_date']));
            if ($trxDate !== date('Y-m-d')) {
                return $this->failValidationErrors("Transaksi hanya bisa diubah pada hari yang sama saat dibuat");
            }

            // 3. Validasi lines
            $lines = $json['lines'] ?? [];
            if (!is_array($lines) || count($lines) === 0) {
                return $this->failValidationErrors('Data lines tidak boleh kosong dan harus berupa array');
            }

            // Normalize jika single object
            if (isset($lines['currency_id'])) {
                $lines = [$lines];
            }

            // 4. Update langsung via transactionModel
            $success = $this->transactionModel->updateTransactionLinesTodayOnly($id, $tenantId, $lines);

            if (!$success) {
                return $this->fail('Gagal memperbarui transaksi. Pastikan data valid dan milik tenant Anda.');
            }

            return $this->respond([
                'message'        => 'Transaksi berhasil diperbarui',
                'transaction_id' => $id
            ]);
        } catch (\Throwable $e) {
            return $this->failServerError('Gagal update transaksi: ' . $e->getMessage());
        }
    }

    public function delete($id = null)
    {
        if (!$id || !is_numeric($id)) {
            return $this->failValidationErrors(['id' => 'Invalid or missing transaction ID']);
        }

        $tenantId = auth_tenant_id();

        try {
            // Hapus hanya jika transaksi milik tenant dan tanggalnya hari ini
            $deleted = $this->transactionModel->deleteTransactionTodayOnly($id, $tenantId);

            if (!$deleted) {
                return $this->failNotFound('Transaksi tidak ditemukan, bukan milik Anda, atau bukan dari hari ini');
            }

            return $this->respondDeleted([
                'message'        => 'Transaksi berhasil dihapus (permanen)',
                'transaction_id' => (int) $id
            ]);
        } catch (\Throwable $e) {
            return $this->failServerError('Gagal menghapus transaksi: ' . $e->getMessage());
        }
    }
}