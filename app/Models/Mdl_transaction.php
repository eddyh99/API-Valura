<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Mdl_client;

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
                            'amount_local', tl.amount_local,
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
            // 2. Cek apakah idempotency_key sudah ada (Query Builder)
            // if (!empty($data['idempotency_key'])) {
            //     $existing = $this->db->table($this->table)
            //         ->select('id')
            //         ->where('tenant_id', $data['tenant_id'])
            //         ->where('idempotency_key', $data['idempotency_key'])
            //         ->get()
            //         ->getRowArray();

            //     if ($existing && isset($existing['id'])) {
            //         $this->reusedIdempotency = true; // ✅ Tandai kalau reused
            //         return $existing['id'];
            //     }
            // }

            // 3. Insert transaction
            $this->db->table($this->table)->insert($data);
            $transactionId = $this->db->insertID();
    
            // 4. Make sure $detail is an array of arrays
            if (!isset($detail[0]) || !is_array($detail[0])) {
                $detail = [$detail]; // wrap if it's a single row
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
    // Update tanpa hapus lines lama
    // public function updateTransactionLinesTodayOnly($transactionId, $tenantId, array $newLines)
    // {
    //     $this->db->transStart();

    //     // Pastikan transaksi milik tenant dan dibuat hari ini
    //     $sql = "SELECT id FROM {$this->table}
    //             WHERE id = ? AND tenant_id = ? AND DATE(transaction_date) = CURDATE()";
    //     $exists = $this->db->query($sql, [$transactionId, $tenantId])->getRowArray();

    //     if (!$exists) {
    //         return false;
    //     }

    //     try {
    //         // Ambil semua line ID lama milik transaksi ini
    //         $oldLines = $this->db->table('transaction_lines')
    //                             ->select('id')
    //                             ->where('transaction_id', $transactionId)
    //                             ->get()
    //                             ->getResultArray();

    //         $oldIds = array_column($oldLines, 'id'); // misal [1, 2, 3]
    //         $incomingIds = [];

    //         foreach ($newLines as $line) {
    //             $line['transaction_id'] = $transactionId;

    //             if (!empty($line['id'])) {
    //                 // Simpan id yang dikirim user
    //                 $incomingIds[] = $line['id'];

    //                 // Update baris existing
    //                 $updateData = $line;
    //                 $id = $updateData['id'];
    //                 unset($updateData['id']); // jangan update kolom id

    //                 $this->db->table('transaction_lines')
    //                         ->where('id', $id)
    //                         ->where('transaction_id', $transactionId) // tambahan safety
    //                         ->update($updateData);
    //             } else {
    //                 // Insert baru (karena tidak ada id)
    //                 $this->db->table('transaction_lines')->insert($line);
    //             }
    //         }

    //         // Hapus lines yang lama tapi tidak dikirim ulang oleh user
    //         if (!empty($oldIds)) {
    //             $idsToDelete = array_diff($oldIds, $incomingIds);
    //             if (!empty($idsToDelete)) {
    //                 $this->db->table('transaction_lines')
    //                         ->where('transaction_id', $transactionId)
    //                         ->whereIn('id', $idsToDelete)
    //                         ->delete();
    //             }
    //         }

    //         $this->db->transComplete();
    //         return $this->db->transStatus();
    //     } catch (\Throwable $e) {
    //         $this->db->transRollback();
    //         log_message('error', 'Update lines (tanpa delete total) gagal: ' . $e->getMessage());
    //         return false;
    //     }
    // }

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
