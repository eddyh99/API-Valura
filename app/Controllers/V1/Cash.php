<?php

namespace App\Controllers\V1;

use App\Models\Mdl_cash;
use App\Models\Mdl_branch;
use App\Models\Mdl_transaction;

use App\Controllers\BaseApiController;

class Cash extends BaseApiController
{
    protected $modelName = Mdl_cash::class;
    protected $format    = 'json';

    protected $branchModel;

    public function __construct()
    {
        $this->branchModel      = new Mdl_branch();
        $this->transactionModel = new Mdl_transaction();
    }

    public function show_all_cashes()
    {
        $tenantId = auth_tenant_id();

        $cashes = $this->model->getAllCashesRaw($tenantId);

        return $this->respond([
            'status' => true,
            'today'  => date('Y-m-d'),
            'data' => $cashes
        ]);
    }

    public function showCash_ByID($id = null)
    {
        $tenantId = auth_tenant_id();

        $cash = $this->model->getCashByIdRaw($tenantId, $id);

        return $this->respond([
            'status' => true,
            'data' => $cash
        ]);
    }

    // Show kas harian/Rekap harian
public function showDailyCash()
{
    $tenantId = auth_tenant_id();
    $branchId = auth_branch_id();

    // Ambil data cash hari ini
    $cashData = $this->model->getDailyCashRaw($branchId);

    // Kelompokkan berdasarkan movement_type
    $grouped = [
        'AWAL' => [],
        'IN' => [],
        'OUT' => [],
    ];

    $totals = [
        'total_awal' => 0,
        'total_in' => 0,
        'total_out' => 0,
    ];

    foreach ($cashData as $row) {
        $type = strtoupper($row['movement_type']);
        if (isset($grouped[$type])) {
            $grouped[$type][] = [
                'cash_id' => (int) $row['id'],
                'amount' => (float) $row['amount'],
            ];
            $totals["total_" . strtolower($type)] += (float) $row['amount'];
        }
    }

    // Ambil data tambahan
    $sisaKas = $this->model->getSisaKasSebelumnya($branchId);
    $trxSummary = $this->transactionModel->getTotalBuySellAmount($branchId);

    // Perhitungan total akhir kas hari ini
    // $totalAkhir = $totals['total_awal'] + $totals['total_in'] - $totals['total_out'];
    $totalAkhir = $totals['total_awal'] + $totals['total_in'] + $sisaKas + $trxSummary['SELL'] - $totals['total_out'] -$trxSummary['BUY'];

    return $this->respond([
        'tanggal' => date('Y-m-d'),
        'cabang' => $this->branchModel->getBranchNameById($branchId),
        'AWAL' => $grouped['AWAL'],
        'IN' => $grouped['IN'],
        'OUT' => $grouped['OUT'],
        'sisa_kas' => (float) $sisaKas,
        'total_penjualan' => (float) $trxSummary['SELL'],
        'total_pembelian' => (float) $trxSummary['BUY'],
        'total_awal' => $totals['total_awal'],
        'total_in' => $totals['total_in'],
        'total_out' => $totals['total_out'],
        'total_akhir' => $totalAkhir
    ]);
}

    // public function showDailyCash()
    // {
    //     $tenantId = auth_tenant_id();
    //     $branchId = auth_branch_id();

    //     // Ambil data cash hari ini
    //     $cashData = $this->model->getDailyCashRaw($branchId);

    //     // Kelompokkan berdasarkan movement_type
    //     $grouped = [
    //         'AWAL' => [],
    //         'IN' => [],
    //         'OUT' => [],
    //     ];

    //     $totals = [
    //         'total_awal' => 0,
    //         'total_in' => 0,
    //         'total_out' => 0,
    //     ];

    //     foreach ($cashData as $row) {
    //         $type = strtoupper($row['movement_type']);

    //         if (isset($grouped[$type])) {
    //             $grouped[$type][] = [
    //                 'cash_id' => (int) $row['id'],
    //                 'amount' => (float) $row['amount'],
    //             ];

    //             $totals["total_" . strtolower($type)] += (float) $row['amount'];
    //         }
    //     }

    //     // Perhitungan total kas hari ini
    //     $totalKasHariIni = $totals['total_awal'] + $totals['total_in'] - $totals['total_out'];

    //     return $this->respond([
    //         'tanggal' => date('Y-m-d'),
    //         'cabang' => $this->branchModel->getBranchNameById($branchId),
    //         'AWAL' => $grouped['AWAL'],
    //         'IN' => $grouped['IN'],
    //         'OUT' => $grouped['OUT'],
    //         'total_awal' => $totals['total_awal'],
    //         'total_in' => $totals['total_in'],
    //         'total_out' => $totals['total_out'],
    //         'total' => $totalKasHariIni
    //     ]);
    // }

    // public function show_all_cashes()
    // {
    //     $tenantId = auth_tenant_id();
    //     $branchId = auth_branch_id();

    //     // Dapatkan awal dan akhir hari ini dalam format datetime
    //     $startOfDay = date('Y-m-d 00:00:00');
    //     $endOfDay   = date('Y-m-d 23:59:59');

    //     $cashes = $this->model
    //         ->where('is_active', 1)
    //         ->where('tenant_id', $tenantId)
    //         ->where('branch_id', $branchId)
    //         // Filter berdasarkan occurred_at di rentang hari ini
    //         ->where('occurred_at >=', $startOfDay)
    //         ->where('occurred_at <=', $endOfDay)
    //         ->findAll();

    //     return $this->respond([
    //         'status' => true,
    //         'today'  => date('Y-m-d'),
    //         'data' => $cashes
    //     ]);
    // }

    // public function showCash_ByID($id = null)
    // {
    //     $tenantId = auth_tenant_id();

    //     $cash = $this->model
    //         ->where('id', $id)
    //         ->where('is_active', 1)
    //         ->where('tenant_id', $tenantId)
    //         ->first();

    //     if (!$cash) {
    //         return $this->failNotFound('Cash tidak ditemukan atau sudah dihapus.');
    //     }

    //     return $this->respond([
    //         'status' => true,
    //         'data' => $cash
    //     ]);
    // }

    public function showCash_ByBranchID($id = null)
    {
        $branchId = auth_branch_id(); 

        $data = $this->model->getTodayCashByBranch($id);

        return $this->respond([
            'status' => true,
            'data'   => $data 
        ]);
    }

    public function create()
    {
        $data = $this->request->getJSON(true);

        // Jika tidak ada 'occurred_at' di request, set ke tanggal & waktu sekarang
        if (empty($data['occurred_at'])) {
            $data['occurred_at'] = date('Y-m-d H:i:s');
        }

        // Set data tambahan
        $data['tenant_id']  = auth_tenant_id();
        $data['branch_id']  = auth_branch_id();
        $data['created_by'] = auth_user_id();
        $data['is_active']  = 1;

        // Validasi movement_type harus IN, OUT, atau AWAL
        if (!in_array($data['movement_type'] ?? '', ['IN', 'OUT', 'AWAL'])) {
            return $this->failValidationErrors(['movement_type' => 'Movement type harus IN/OUT/AWAL']);
        }

        if (!$this->model->insert($data)) {
            return $this->failValidationErrors($this->model->errors());
        }

        return $this->respondCreated(['message' => 'Cash berhasil ditambahkan']);
    }

    public function update($id = null)
    {
        $data = $this->request->getJSON(true);

        // Cek data cash aktif
        $cash = $this->model->where('id', $id)->where('is_active', 1)->first();
        if (!$cash) {
            return $this->failNotFound('Cash tidak ditemukan atau sudah dihapus');
        }

        // Cek apakah tanggal occurred_at sama dengan tanggal hari ini
        $today = date('Y-m-d');
        $occurredDate = date('Y-m-d', strtotime($cash['occurred_at']));
        if ($occurredDate !== $today) {
            return $this->fail('Hanya bisa Update Cash di tanggal sekarang: ' . $today);
        }

        // Validasi movement_type jika ada
        if (isset($data['movement_type']) && !in_array($data['movement_type'], ['IN', 'OUT', 'AWAL'])) {
            return $this->failValidationErrors(['movement_type' => 'Movement type harus IN, OUT, atau AWAL']);
        }

        if (!$this->model->update($id, $data)) {
            return $this->failValidationErrors($this->model->errors());
        }

        return $this->respond(['message' => 'Cash berhasil diupdate']);
    }

    public function delete($id = null)
    {
        // Cek data cash aktif
        $cash = $this->model->where('id', $id)->where('is_active', 1)->first();
        if (!$cash) {
            return $this->failNotFound('Cash tidak ditemukan atau sudah dihapus');
        }

        // Cek apakah tanggal occurred_at sama dengan tanggal hari ini
        $today = date('Y-m-d');
        $occurredDate = date('Y-m-d', strtotime($cash['occurred_at']));
        if ($occurredDate !== $today) {
            return $this->fail('Hanya bisa Delete Cash di tanggal sekarang: ' . $today);
        }

        // Soft delete: set is_active = 0
        if (!$this->model->update($id, ['is_active' => 0])) {
            return $this->failServerError('Gagal melakukan soft delete');
        }

        return $this->respondDeleted(['message' => 'Cash berhasil di-nonaktifkan']);
    }
}
