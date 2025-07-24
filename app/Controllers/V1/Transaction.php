<?php

namespace App\Controllers\V1;

use App\Models\Mdl_transaction;
use App\Models\Mdl_transaction_line;
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
        $this->lineModel        = new Mdl_transaction_line();
        $this->clientModel      = new Mdl_client();
    }

    // Show Transaction by ID
    public function showTransaction_ByID($id = null)
    {
        $tenantId = auth_tenant_id();

        // 1. Ambil data transaksi utama
        $transaction = $this->transactionModel->getTransactionByIdRaw($tenantId, $id);
        if (!$transaction) {
            return $this->failNotFound("Transaksi dengan ID $id tidak ditemukan.");
        }

        // 2. Ambil data lines (berdasarkan tipe transaksi BUY/SELL)
        $lines = $this->lineModel->getTransactionLinesByTransactionIdRaw($tenantId, $id, $transaction['transaction_type']);

        return $this->respond([
            'status' => true,
            'transaction_id' => (int) $id,
            'transaction_type' => $transaction['transaction_type'],
            'currencies' => $lines
        ]);
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

        // 1. SIMPAN CLIENT (dengan cek ID Number)
        $clientId = $this->clientModel->insertClientIfNotExistRaw($tenantId, $data);

        // 2. SIMPAN TRANSAKSI
        $transactionId = $this->transactionModel->insertTransactionRaw(
            $tenantId,
            $branchId,
            $clientId,
            $data,
            $userId,
            $transactionDate,
            $transactionType
        );

        // 3. SIMPAN CURRENCY LINEs
        $this->lineModel->insertLinesRaw(
            $transactionId,
            $transactionType,
            $data['currencies'],
            $tenantId
        );

        return $this->respondCreated([
            'message'        => 'Transaksi berhasil disimpan',
            'transaction_id' => $transactionId
        ]);
    }

    public function update($id = null)
    {
        $json = $this->request->getJSON(true);

        try {
            // 1. Ambil data transaksi untuk cek type (BUY/SELL)
            $trx = $this->transactionModel->getTransactionById($id);
            if (!$trx) {
                return $this->failNotFound("Transaksi ID $id tidak ditemukan");
            }

            $transactionType = strtoupper($trx['transaction_type']);
            $tenantId        = $trx['tenant_id'];

            // 2. Ambil array currencies
            $currencies = $json['currencies'] ?? [];

            if (!is_array($currencies) || count($currencies) === 0) {
                return $this->failValidationErrors('Data currencies tidak boleh kosong dan harus berupa array');
            }

            // 3. Update ke tabel transaction_lines
            $this->lineModel->updateLinesRaw($id, $transactionType, $currencies, $tenantId);

            return $this->respond([
                'message'         => 'Transaksi berhasil diperbarui',
                'transaction_id'  => $id
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

        $tenantId = auth_tenant_id(); // pastikan data hanya milik tenant ini

        try {
            // Hapus detail (transaction_lines)
            $this->lineModel->deleteLinesByTransactionId($id, $tenantId);

            // Hapus transaksi utama
            $deleted = $this->transactionModel->deleteTransactionById($id, $tenantId);

            if (!$deleted) {
                return $this->failNotFound('Transaksi tidak ditemukan atau bukan milik tenant Anda');
            }

            return $this->respondDeleted([
                'message'         => 'Transaksi berhasil dihapus (permanen)',
                'transaction_id'  => (int) $id
            ]);
        } catch (\Throwable $e) {
            return $this->failServerError('Gagal menghapus transaksi: ' . $e->getMessage());
        }
    }
}