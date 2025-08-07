<?php

namespace App\Controllers\V1;

use App\Models\Mdl_bank_settlement;
use App\Controllers\BaseApiController;

class BankSettlement extends BaseApiController
{
    protected $format    = 'json';
    protected $settlementModel;

    public function __construct()
    {
        $this->settlementModel = new Mdl_bank_settlement();
    }

    public function show_all_settlements()
    {
        $settlements = $this->settlementModel->getAllSettlementsRaw($this->tenantId);

        return $this->respond([
            'status' => true,
            'data' => $settlements
        ]);
    }

    public function create() 
    {
        $db = \Config\Database::connect();
        $trx = $db->table('bank_transactions');
        $trxLines = $db->table('transaction_lines');
        $settlementDetails = $db->table('settlement_details');

        $payload = $this->request->getJSON(true);

        log_message('debug', 'PAYLOAD MASUK: ' . json_encode($payload)); // ✅ log payload awal

        $transactionDate = $payload['transaction_date'];
        $settlementItems = $payload['items'];

        $bankName = $payload['bank_name'];
        $accountNumber = $payload['account_number'] ?? '';
        $now = date('Y-m-d H:i:s');
        $userId = auth_user_id();
        $tenantId = auth_tenant_id();

        $db->transStart();

        foreach ($settlementItems as $item) {
            $currencyId = $item['currency_id'];
            $rateUsed = $item['rate_used'];
            $amountToSettle = $item['amount_to_settle'];
            $localTotal = $item['local_total'];
            $pendingAmount = $item['pending_amount'];
            $selectedTrxIds = $item['selected_transaction_ids'];

            // ✅ skip jika tidak ada transaction_lines yang dipilih
            if (empty($selectedTrxIds)) {
                log_message('warning', 'SETTLEMENT SKIPPED: currency_id=' . $currencyId . ' karena tidak ada selected_transaction_ids.');
                continue;
            }

            // ✅ insert ke bank_transactions
            $bankTransactionData = [
                'tenant_id' => $tenantId,
                'currency_id' => $currencyId,
                'bank_name' => $bankName,
                'account_number' => $accountNumber,
                'amount_foreign' => $pendingAmount,
                'rate_used' => $rateUsed,
                'amount_settled' => $amountToSettle,
                'local_total' => $localTotal,
                'transaction_date' => $transactionDate,
                'created_by' => $userId,
                'created_at' => $now
            ];

            if (!$trx->insert($bankTransactionData)) {
                log_message('error', 'INSERT GAGAL ke bank_transactions: ' . json_encode($db->error()));
                $db->transRollback();
                return $this->failServerError('Gagal menyimpan bank transaction.');
            }

            $bankTransactionId = $db->insertID();
            if (!$bankTransactionId) {
                log_message('error', 'INSERT ID NULL setelah insert bank_transactions.');
                $db->transRollback();
                return $this->failServerError('Gagal mendapatkan ID dari bank transaction.');
            }

            $remaining = $amountToSettle;

            foreach ($selectedTrxIds as $lineId) {
                $line = $trxLines->getWhere(['id' => $lineId])->getRow();
                if (!$line || $remaining <= 0) {
                    log_message('warning', "SKIP LINE: line_id={$lineId}, line_exists=" . ($line ? 'yes' : 'no') . ", remaining=$remaining");
                    continue;
                }

                $lineAmount = $line->amount_foreign;
                $settleAmount = 0;
                $status = 'PARTIAL';

                if ($remaining >= $lineAmount) {
                    $settleAmount = $lineAmount;
                    $status = 'SETTLED';
                } else {
                    $settleAmount = $remaining;
                }

                // ✅ update ke transaction_lines
                $updateSuccess = $trxLines->where('id', $lineId)->update([
                    'settlement_status' => $status,
                    'settlement_date' => $transactionDate
                ]);

                if (!$updateSuccess) {
                    log_message('error', "UPDATE GAGAL transaction_lines id={$lineId}: " . json_encode($db->error()));
                    $db->transRollback();
                    return $this->failServerError('Gagal mengupdate status transaksi.');
                }

                // ✅ insert ke settlement_details
                $insertSuccess = $settlementDetails->insert([
                    'bank_transaction_id' => $bankTransactionId,
                    'transaction_id' => $line->transaction_id
                ]);

                if (!$insertSuccess) {
                    log_message('error', "INSERT GAGAL settlement_details (bank_transaction_id={$bankTransactionId}, trx_id={$line->transaction_id}): " . json_encode($db->error()));
                    $db->transRollback();
                    return $this->failServerError('Gagal menyimpan detail settlement.');
                }

                $remaining -= $settleAmount;
            }
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            log_message('error', 'TRANSAKSI GAGAL TOTAL: ' . json_encode($db->error()));
            return $this->failServerError('Gagal menyimpan data settlement.');
        }

        return $this->respondCreated(['message' => 'Settlement berhasil disimpan.']);
    }
}
