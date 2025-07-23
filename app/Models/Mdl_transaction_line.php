<?php

namespace App\Models;

use App\Models\BaseModel;

class Mdl_transaction_line extends BaseModel
{
    protected $table            = 'transaction_lines';
    protected $primaryKey       = 'id';
    protected $allowedFields    = [
        'transaction_id', 'currency_id', 'amount_foreign', 'amount_local', 'rate_used'
    ];
    protected $useTimestamps    = false;
    protected $auditEnabled     = true;

    // Raw Query
    public function insertLineRaw($data)
    {
        $sql = "INSERT INTO transaction_lines (transaction_id, currency_id, amount_foreign, amount_local, rate_used)
                VALUES (?, ?, ?, ?, ?)";

        return $this->db->query($sql, [
            $data['transaction_id'],
            $data['currency_id'],
            $data['amount_foreign'],
            $data['amount_local'],
            $data['rate_used']
        ]);
    }

    public function insertLinesRaw($transactionId, $transactionType, $currencies, $tenantId)
    {
        foreach ($currencies as $item) {
            $currencyId = $item['currency_id'];
            $amount     = (float) $item['amount'];

            // Ambil rate
            $rate = $this->getRateByCurrency($tenantId, $currencyId, $transactionType);
            if (!$rate) {
                throw new \RuntimeException("Rate tidak ditemukan untuk currency ID $currencyId");
            }

            $sql = "INSERT INTO transaction_lines (transaction_id, currency_id, rate_used, amount_foreign, amount_local)
                    VALUES (?, ?, ?, ?, ?)";

            $this->db->query($sql, [
                $transactionId,
                $currencyId,
                $rate,
                $transactionType === 'SELL' ? $amount : 0,
                $transactionType === 'BUY'  ? $amount : 0,
            ]);
        }
    }
 
    protected function getRateByCurrency($tenantId, $currencyId, $transactionType)
    {
        // Pastikan hanya BUY atau SELL saja
        if (!in_array($transactionType, ['BUY', 'SELL'])) {
            throw new \InvalidArgumentException("Jenis transaksi tidak valid: $transactionType");
        }

        // Pilih kolom rate yang sesuai
        $rateColumn = $transactionType === 'BUY' ? 'buy_rate' : 'sell_rate';

        $sql = "SELECT $rateColumn AS rate
                FROM exchange_rates
                WHERE tenant_id = ? AND currency_id = ?
                ORDER BY rate_date DESC, created_at DESC
                LIMIT 1";

        $row = $this->db->query($sql, [$tenantId, $currencyId])->getRow();
        return $row->rate ?? null;
    }

    public function deleteLinesByTransactionId($transactionId, $tenantId)
    {
        $sql = "DELETE tl
                FROM transaction_lines tl
                JOIN transactions t ON t.id = tl.transaction_id
                WHERE tl.transaction_id = ? AND t.tenant_id = ?";

        $this->db->query($sql, [$transactionId, $tenantId]);
    }

    public function getTransactionLinesByTransactionIdRaw($tenantId, $transactionId, $transactionType)
    {
        $amountColumn = ($transactionType === 'BUY') ? 'amount_local' : 'amount_foreign';

        $sql = "SELECT 
                    c.code AS currency,
                    tl.$amountColumn AS amount,
                    tl.rate_used AS rate
                FROM transaction_lines tl
                JOIN transactions t ON tl.transaction_id = t.id
                JOIN currencies c ON tl.currency_id = c.id
                WHERE tl.transaction_id = ? AND t.tenant_id = ?
                ORDER BY c.code";

        return $this->db->query($sql, [$transactionId, $tenantId])->getResultArray();
    }

    // Update
    public function updateLinesRaw($transactionId, $transactionType, array $currencies, $tenantId)
    {
        $db = \Config\Database::connect();

        foreach ($currencies as $index => $item) {
            $currencyCode = $item['currency'] ?? null;
            $amount       = $item['amount'] ?? null;
            $rate         = $item['rate'] ?? null;

            if (!$currencyCode || $amount === null || $rate === null) {
                throw new \Exception("Data baris ke-$index tidak lengkap (butuh currency, amount, rate)");
            }

            // Mapping currency code → id
            $sqlCurrency = "SELECT id FROM currencies WHERE tenant_id = ? AND code = ? LIMIT 1";
            $currencyRow = $db->query($sqlCurrency, [$tenantId, $currencyCode])->getRow();

            if (!$currencyRow) {
                throw new \Exception("Currency code '$currencyCode' tidak ditemukan");
            }

            $currencyId = $currencyRow->id;

            // Update hanya 1 baris (pakai LIMIT 1)
            $updateSql = "UPDATE transaction_lines 
                        SET currency_id = ?, rate_used = ?, 
                            amount_local = ?, 
                            amount_foreign = ?
                        WHERE transaction_id = ?
                        LIMIT 1";

            $isBuy  = $transactionType === 'BUY';
            $local  = $isBuy ? $amount : 0;
            $foreign = !$isBuy ? $amount : 0;

            $db->query($updateSql, [
                $currencyId,
                $rate,
                $local,
                $foreign,
                $transactionId
            ]);
        }
    }
}
