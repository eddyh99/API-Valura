<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Mdl_client;
use App\Models\Mdl_currency;

class Mdl_transaction extends BaseModel
{
    protected $table            = 'transactions';
    protected $primaryKey       = 'id';
    protected $allowedFields    = [
        'tenant_id', 'branch_id', 'client_id', 'transaction_type',
        'payment_type_id', 'bank_id', 'transaction_date', 'created_by', 'created_at'
    ];
    protected $useTimestamps    = false;
    protected $auditEnabled     = true;

    // Raw Query
    public function __construct()
    {
        parent::__construct();
        $this->clients = new Mdl_client();
        $this->currencies = new Mdl_currency();
    }

    public function getTotalBuySellAmount($branchId)
    {
        $sql = "
            SELECT 
                tr.transaction_type,
                SUM(tl.amount_foreign * tl.rate_used) AS total
            FROM transactions tr
            JOIN transaction_lines tl ON tl.transaction_id = tr.id
            WHERE tr.branch_id = ?
            AND DATE(tr.created_at) = CURDATE()
            GROUP BY tr.transaction_type
        ";

        $result = $this->db->query($sql, [$branchId])->getResultArray();

        $output = [
            'BUY' => 0,
            'SELL' => 0
        ];

        foreach ($result as $row) {
            $type = strtoupper($row['transaction_type']);
            $output[$type] = (float) $row['total'];
        }

        return $output;
    }

    public function getClientRecapRaw($tenantId, $branchId = null, $dateStart = null, $dateEnd = null)
    {
        $dateStart = $dateStart ?? date('Y-m-d');
        $dateEnd   = $dateEnd ?? date('Y-m-d');

        $params = [$tenantId, $dateStart . ' 00:00:00', $dateEnd . ' 23:59:59'];
        $branchFilter = "";

        if ($branchId) {
            $branchFilter = "AND tr.branch_id = ?";
            $params[] = $branchId;
        }

        $sql = "
            SELECT 
                cl.name AS nama,
                cl.address AS alamat,
                cl.id_type AS jenis_identitas,
                cl.id_number AS no_identitas,
                cl.country AS negara,
                cl.phone AS no_telp,
                cl.job AS pekerjaan,
                SUM(tl.amount_foreign * tl.rate_used) AS total_tukar_rupiah
            FROM transactions tr
            JOIN clients cl ON cl.id = tr.client_id
            JOIN transaction_lines tl ON tl.transaction_id = tr.id
            WHERE tr.tenant_id = ?
            AND tr.created_at BETWEEN ? AND ?
            $branchFilter
            GROUP BY cl.id, cl.name, cl.address, cl.id_type, cl.id_number, cl.country, cl.phone, cl.job
            ORDER BY cl.name ASC
        ";

        return $this->db->query($sql, $params)->getResultArray();
    }

    // Show by ID 
    public function getTransactionByIdRaw($tenantId, $transactionId)
    {
        
        $sql = "SELECT 
                    tr.id AS transaction_id,
                    tr.tenant_id,
                    tr.transaction_type,
                    tr.transaction_date,

                    CONCAT('[', GROUP_CONCAT(
                        JSON_OBJECT(
                            'det_id', tl.id,
                            'currency_id', tl.currency_id,
                            'amount_foreign', tl.amount_foreign,

                            'rate_used', tl.rate_used,
                            'currency_code', c.code,
                            'currency_name', c.name
                        )
                    ), ']') AS detail

                FROM transactions tr
                JOIN transaction_lines tl ON tl.transaction_id = tr.id
                JOIN currencies c ON c.id = tl.currency_id

                WHERE tr.tenant_id = ? AND tr.id = ?
                GROUP BY tr.id;";

        return $this->db->query($sql, [$tenantId, $transactionId])->getRowArray();
    }
    
    // Show Daily Transaction by Today & Branch
    public function getDailyTransactionRaw($tenantId, $branchId, $date)
    {
        // Pertama, pastikan branch valid dan aktif
        $branchCheckSql = "
            SELECT id
            FROM branches
            WHERE id = ?
            AND tenant_id = ?
            AND is_active = 1
            LIMIT 1
        ";
        $branchCheck = $this->db->query($branchCheckSql, [$branchId, $tenantId])->getRow();

        if (!$branchCheck) {
            // Branch tidak ditemukan atau tidak aktif
            return false;
        }

        // Kalau lolos validasi, lanjut ambil transaksi harian
        $sql = "
            SELECT 
                tr.id AS transaction_id,
                tr.created_at AS transaction_date,
                tr.transaction_type,
                c.code AS currency,
                tl.rate_used AS rate,
                tl.amount_foreign,
                (tl.amount_foreign * tl.rate_used) AS subtotal_local_estimation
            FROM transactions tr
            JOIN transaction_lines tl ON tl.transaction_id = tr.id
            JOIN currencies c ON c.id = tl.currency_id
            WHERE tr.tenant_id = ?
            AND tr.branch_id = ?
            AND DATE(tr.created_at) = ?
            ORDER BY tr.created_at ASC
        ";

        return $this->db->query($sql, [$tenantId, $branchId, $date])->getResultArray();
    }

    // Show Monthly Profit
    public function getMonthlyProfitRaw($tenantId, $month, $year)
    {
        $sql = "
            SELECT 
                -- Bank settlement (profit from selisih kurs bank)
                SUM((bt.amount_foreign * bt.rate_used) - bt.local_total) AS bank_settlement,

                -- Total pembelian lokal
                SUM(CASE WHEN tr.transaction_type = 'BUY' THEN tl.amount_foreign * tl.rate_used ELSE 0 END) AS total_buy_local,

                -- Total penjualan lokal
                SUM(CASE WHEN tr.transaction_type = 'SELL' THEN tl.amount_foreign * tl.rate_used ELSE 0 END) AS total_sell_local,

                -- Biaya-biaya dari kas (cash_movements) OUT
                (
                    SELECT COALESCE(SUM(cm.amount), 0)
                    FROM cash_movements cm
                    WHERE cm.tenant_id = ?
                    AND cm.movement_type = 'OUT'
                    AND MONTH(cm.occurred_at) = ?
                    AND YEAR(cm.occurred_at) = ?
                    AND cm.is_active = 1
                ) AS total_costs

            FROM transactions tr
            JOIN transaction_lines tl ON tl.transaction_id = tr.id
            LEFT JOIN settlement_details sd ON sd.transaction_id = tr.id
            LEFT JOIN bank_transactions bt ON bt.id = sd.bank_transaction_id AND bt.tenant_id = tr.tenant_id
            WHERE tr.tenant_id = ?
            AND MONTH(tr.transaction_date) = ?
            AND YEAR(tr.transaction_date) = ?
        ";

        // urutan parameter = cm.tenant_id, cm.month, cm.year, cm.tenant_id, tr.month, tr.year
        return $this->db->query($sql, [$tenantId, $month, $year, $tenantId, $month, $year])->getRowArray();
    }
    // public function getMonthlyProfitRaw($tenantId, $month, $year)
    // {
    //     $sql = "
    //         SELECT 
    //             AVG(bt.rate_used) AS avg_settlement_rate,
    //             SUM(CASE WHEN tr.transaction_type = 'BUY' THEN tl.amount_foreign * tl.rate_used ELSE 0 END) AS total_buy_local,
    //             SUM(CASE WHEN tr.transaction_type = 'SELL' THEN tl.amount_foreign * tl.rate_used ELSE 0 END) AS total_sell_local,
    //             SUM(CASE WHEN tl.settlement_status = 'SETTLED' THEN tl.amount_foreign ELSE 0 END) AS saldo_foreign,
    //             SUM(
    //                 CASE 
    //                     WHEN tr.transaction_type = 'SELL' THEN 
    //                         tl.amount_foreign * tl.rate_used 
    //                     ELSE 0 
    //                 END
    //             ) - 
    //             SUM(
    //                 CASE 
    //                     WHEN tr.transaction_type = 'BUY' THEN 
    //                         tl.amount_foreign * tl.rate_used 
    //                     ELSE 0 
    //                 END
    //             ) AS retail_profit,
    //             SUM(
    //                 (bt.amount_foreign * bt.rate_used) - bt.local_total
    //             ) AS bank_profit
    //         FROM transactions tr
    //         JOIN transaction_lines tl ON tl.transaction_id = tr.id
    //         JOIN currencies c ON c.id = tl.currency_id
    //         LEFT JOIN settlement_details sd ON sd.transaction_id = tr.id
    //         LEFT JOIN bank_transactions bt ON bt.id = sd.bank_transaction_id AND bt.tenant_id = tr.tenant_id
    //         WHERE tr.tenant_id = ?
    //         AND MONTH(tr.transaction_date) = ?
    //         AND YEAR(tr.transaction_date) = ?
    //     ";

    //     return $this->db->query($sql, [$tenantId, $month, $year])->getRowArray();
    // }
    // public function getMonthlyProfitRaw($tenantId, $month, $year)
    // {
    //     $sql = "
    //     SELECT 
    //         c.code AS currency_code,

    //         -- Total pembelian (BUY)
    //         SUM(CASE WHEN tr.transaction_type = 'BUY' THEN tl.amount_foreign ELSE 0 END) AS total_buy_amount,
    //         SUM(CASE WHEN tr.transaction_type = 'BUY' THEN tl.amount_foreign * tl.rate_used ELSE 0 END) AS total_buy_local,

    //         -- Total penjualan (SELL)
    //         SUM(CASE WHEN tr.transaction_type = 'SELL' THEN tl.amount_foreign ELSE 0 END) AS total_sell_amount,
    //         SUM(CASE WHEN tr.transaction_type = 'SELL' THEN tl.amount_foreign * tl.rate_used ELSE 0 END) AS total_sell_local,

    //         -- Saldo akhir valas
    //         (SUM(CASE WHEN tr.transaction_type = 'BUY' THEN tl.amount_foreign ELSE 0 END) -
    //         SUM(CASE WHEN tr.transaction_type = 'SELL' THEN tl.amount_foreign ELSE 0 END)) AS saldo_foreign,

    //         -- Profit Retail
    //         SUM(CASE WHEN tr.transaction_type = 'SELL' THEN 
    //             (tl.rate_used - (
    //                 SELECT AVG(tl2.rate_used)
    //                 FROM transaction_lines tl2
    //                 JOIN transactions tr2 ON tr2.id = tl2.transaction_id
    //                 WHERE tr2.transaction_type = 'BUY'
    //                 AND tl2.currency_id = c.id
    //                 AND tr2.tenant_id = ?
    //                 AND MONTH(tr2.transaction_date) = ?
    //                 AND YEAR(tr2.transaction_date) = ?
    //             )) * tl.amount_foreign ELSE 0 END) AS retail_profit,

    //         -- Profit dari Settlement (keuntungan dari bank)
    //         (
    //             SELECT COALESCE(SUM(
    //                 (bt.rate_used - (
    //                     SELECT AVG(tl3.rate_used)
    //                     FROM transaction_lines tl3
    //                     JOIN transactions tr3 ON tr3.id = tl3.transaction_id
    //                     WHERE tr3.transaction_type = 'BUY'
    //                     AND tl3.currency_id = c.id
    //                     AND tr3.tenant_id = ?
    //                     AND MONTH(tr3.transaction_date) = ?
    //                     AND YEAR(tr3.transaction_date) = ?
    //                 )) * bt.amount_foreign
    //             ), 0)
    //             FROM bank_transactions bt
    //             WHERE bt.currency_id = c.id
    //             AND bt.tenant_id = ?
    //             AND MONTH(bt.transaction_date) = ?
    //             AND YEAR(bt.transaction_date) = ?
    //         ) AS bank_profit

    //     FROM transaction_lines tl
    //     JOIN transactions tr ON tr.id = tl.transaction_id
    //     JOIN currencies c ON c.id = tl.currency_id

    //     WHERE tr.tenant_id = ?
    //     AND tl.settlement_status = 'SETTLED'
    //     AND MONTH(tr.transaction_date) = ?
    //     AND YEAR(tr.transaction_date) = ?

    //     GROUP BY c.id, c.code
    //     ORDER BY c.code
    //     ";

    //     $params = [
    //         $tenantId, $month, $year,   // AVG rate retail profit
    //         $tenantId, $month, $year,   // AVG rate bank profit
    //         $tenantId, $month, $year,   // bank rate profit
    //         $tenantId, $month, $year    // main query
    //     ];

    //     return $this->db->query($sql, $params)->getResultArray();
    // }

    public function getCurrencySummaryRaw($tenantId, $branch_id, $startDate = null, $endDate = null)
    {
        // Default ke hari ini jika tidak diberikan range tanggal
        if (!$startDate || !$endDate) {
            $startDate = $endDate = date('Y-m-d');
        }
    
        // Handle branch filter logic
        if ($branch_id === null) {
            // If no branch_id is provided, don't add any filter
            $branchFilterSql = "";
        } else {
            // If branch_id is provided, add the condition to filter by branch_id
            $branchFilterSql = "AND tr.branch_id = $branch_id";
        }
    
        // SQL utama
        $sql = "
            SELECT 
                b.name AS branch,
                c.code AS currency,
                SUM(CASE WHEN tr.transaction_type = 'BUY' THEN tl.amount_foreign ELSE 0 END) AS amount
            FROM transactions tr
            JOIN transaction_lines tl ON tl.transaction_id = tr.id
            JOIN currencies c ON c.id = tl.currency_id
            JOIN branches b ON b.id = tr.branch_id
            WHERE tr.tenant_id = ?
                AND DATE(tr.created_at) >= ? 
                AND DATE(tr.created_at) <= ?
                $branchFilterSql
            GROUP BY tr.branch_id, tl.currency_id
            ORDER BY b.name ASC, c.code ASC
        ";
        return $this->db->query($sql, [$tenantId, $startDate, $endDate])->getResultArray();
    }


    // public function getCurrencySummaryRaw($tenantId, $startDate = null, $endDate = null, $branchIdList = [])
    // {
    //     // Default ke hari ini jika tidak diberikan range tanggal
    //     if (!$startDate || !$endDate) {
    //         $startDate = $endDate = date('Y-m-d');
    //     }

    //     // Buat bagian filter branch jika diberikan daftar cabang
    //     $branchFilterSql = '';
    //     if (!empty($branchIdList)) {
    //         $placeholders = implode(',', array_fill(0, count($branchIdList), '?'));
    //         $branchFilterSql = "AND tr.branch_id IN ($placeholders)";
    //     }

    //     // SQL utama
    //     $sql = "
    //         SELECT 
    //             b.name AS branch,
    //             c.code AS currency,
    //             SUM(CASE 
    //                 WHEN tr.transaction_type = 'BUY' THEN tl.amount_foreign
    //                 WHEN tr.transaction_type = 'SELL' THEN tl.amount_local
    //                 ELSE 0
    //             END) AS amount
    //         FROM transactions tr
    //         JOIN transaction_lines tl ON tl.transaction_id = tr.id
    //         JOIN currencies c ON c.id = tl.currency_id
    //         JOIN branches b ON b.id = tr.branch_id
    //         WHERE tr.tenant_id = ?
    //             AND DATE(tr.created_at) BETWEEN ? AND ?
    //             $branchFilterSql
    //         GROUP BY tr.branch_id, tl.currency_id
    //         ORDER BY b.name ASC, c.code ASC
    //     ";

    //     $params = array_merge([$tenantId, $startDate, $endDate], $branchIdList);

    //     return $this->db->query($sql, $params)->getResultArray();
    // }

    // Create
    private $reusedIdempotency = false;
    public function isIdempotencyKeyReused(): bool
    {
        return $this->reusedIdempotency;
    }

    public function insertTransactionRaw(array $data, array $clientData, array $detail)
    {
        $this->db->transStart();
    
        try {
            // 1. Save or reuse client
            $clientId = $this->clients->insertClientIfNotExistRaw($clientData);
            $data['client_id'] = $clientId;
    
            // 2. Cek apakah idempotency_key sudah ada (Raw Query)
            if (!empty($data['idempotency_key'])) {
                $checkSql = "SELECT id FROM transactions WHERE tenant_id = ? AND idempotency_key = ?";
                $existing = $this->db->query($checkSql, [$data['tenant_id'], $data['idempotency_key']])->getRowArray();

                if ($existing && isset($existing['id'])) {
                    // Sudah pernah di-insert, return ID yang lama
                    $this->reusedIdempotency = true; // ✅ Tandai kalau reused
                    return $existing['id'];
                }
            }

            // 3. Insert transaction
            $this->db->table($this->table)->insert($data);
            $transactionId = $this->db->insertID();
    
            // 4. Make sure $detail is an array of arrays
            if (!isset($detail[0]) || !is_array($detail[0])) {
                $detail = [$detail]; // wrap if it's a single row
            }

            // Filter detail yang amount_foreign > 0
            $detail = array_filter($detail, function ($row) {
                return isset($row['amount_foreign']) && floatval($row['amount_foreign']) > 0;
            });

            // Kalau semua 0, hentikan transaksi
            // if (empty($detail)) {
            //     $this->db->transRollback();
            //     throw new \Exception("Transaksi gagal: Semua amount_foreign bernilai 0");
            // }
            // Kalau semua 0, hentikan transaksi dan kirim pesan error
            if (empty($detail)) {
                $this->db->transRollback();
                return [
                    'status' => 'error',
                    'message' => 'Amount Foreign tidak boleh 0!'
                ];
            }

            // 5. Add transaction_id to each detail row
            foreach ($detail as &$row) {
                $row['transaction_id'] = $transactionId;
            }

            // 6. Batch insert lines
            $this->db->table('transaction_lines')->insertBatch($detail);
    
            $this->db->transComplete();
    
            if ($this->db->transStatus() === false) {
                $this->db->transRollback();
                return false;
            }
    
            return $transactionId;
    
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Transaction insert failed: ' . $e->getMessage());
            return false;
        }
    }

    // Update (Only Today)
    public function updateTransactionLinesTodayOnly($transactionId, $tenantId, array $newLines)
    {
        $this->db->transStart();

        // Cek transaksi valid dan tanggal hari ini
        $sql = "SELECT id FROM {$this->table} 
                WHERE id = ? AND tenant_id = ? AND DATE(transaction_date) = CURDATE()";

        $exists = $this->db->query($sql, [$transactionId, $tenantId])->getRowArray();
        if (!$exists) {
            return false;
        }

        try {
            // 1. Hapus lines lama
            $this->db->table('transaction_lines')
                    ->where('transaction_id', $transactionId)
                    ->delete();

            // 2. Tambah transaction_id ke tiap baris baru
            foreach ($newLines as &$line) {
                $line['transaction_id'] = $transactionId;
            }

            // 3. Insert batch lines baru
            $this->db->table('transaction_lines')->insertBatch($newLines);

            $this->db->transComplete();

            return $this->db->transStatus();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Update lines failed: ' . $e->getMessage());
            return false;
        }
    }

    // Delete (Only Today)
    public function deleteTransactionTodayOnly($id, $tenantId)
    {
        // Cek dulu apakah data ada dan tanggalnya hari ini
        $sql = "SELECT id FROM {$this->table}
                WHERE id = ? AND tenant_id = ? AND DATE(transaction_date) = CURDATE()";

        $found = $this->db->query($sql, [$id, $tenantId])->getRowArray();

        if (!$found) {
            return false; // Tidak ditemukan atau bukan transaksi hari ini
        }

        // Hapus transaction_lines dulu biar ga orphaned
        $this->db->table('transaction_lines')
                ->where('transaction_id', $id)
                ->delete();

        // Lalu hapus transaksi utama
        return $this->db->table($this->table)
                        ->where('id', $id)
                        ->where('tenant_id', $tenantId)
                        ->delete();
    }
}
