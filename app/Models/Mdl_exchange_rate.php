<?php

namespace App\Models;

use App\Models\BaseModel;

class Mdl_exchange_rate extends BaseModel
{
    protected $table      = 'exchange_rates';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'tenant_id', 'currency_id', 'rate_date',
        'buy_rate', 'sell_rate', 'created_by', 'is_active'
    ];

    protected $useTimestamps = false;
    protected $auditEnabled = true;

    // pakai AS currency_id agar sama seperti di FE (JS rate)
    public function getAllExchangeRatesRaw($tenantId)
    {
        $sql = "SELECT
                    er.id,
                    c.id AS curr_id_no,             
                    c.code AS currency_id, 
                    c.is_active,
                    er.buy_rate,
                    er.sell_rate,
                    er.rate_date,
                    er.is_active
                FROM exchange_rates er
                JOIN currencies c ON c.id = er.currency_id
                WHERE er.tenant_id = ? 
                AND er.is_active = 1
                AND c.is_active = 1
                ORDER BY c.id ASC";

        return $this->db->query($sql, [$tenantId])->getResultArray();
    }

    public function getExchangeRateByIdRaw($tenantId, $exchangeRateId)
    {
        $sql = "SELECT 
                    c.id,
                    c.tenant_id,
                    c.code AS currency_id,
                    c.is_active,
                    er.id,
                    er.buy_rate,
                    er.sell_rate,
                    er.is_active
                FROM exchange_rates er
                JOIN currencies c ON c.id = er.currency_id
                WHERE er.id = ? 
                AND er.tenant_id = ? 
                AND er.is_active = 1
                AND c.is_active = 1
                LIMIT 1";

        return $this->db->query($sql, [$exchangeRateId, $tenantId])->getRowArray();
    }

    public function insert_exchangeRate($data)
    {
        $id = $this->insert($data, true); // pakai method bawaan dari BaseModel

        if (!$id) {
            return (object) [
                'status'  => false,
                'message' => $this->errors(), // kalau pakai Validation bawaan Model
            ];
        }

        return (object) [
            'status'  => true,
            'message' => [],
            'id'      => $id,
        ];
    }

    public function update_exchangeRate($id, $data)
    {
        $success = $this->update($id, $data); // <- pakai method bawaan Model

        if (!$success) {
            return (object)[
                'status'  => false,
                'message' => $this->errors() ?: $this->db->error(),
            ];
        }

        return (object)[
            'status'  => true,
            'message' => [],
        ];
    }

    public function delete_exchangeRate($id)
    {
        return $this->softDelete($id, ['is_active' => 0]);
    }

    public function checkExistingRateTodayRaw($tenantId, $currencyId)
    {
        $sql = "SELECT 1 FROM exchange_rates
                WHERE tenant_id = ? AND currency_id = ? AND is_active = 1
                LIMIT 1";
        
        $result = $this->db->query($sql, [$tenantId, $currencyId])->getRow();

        return (bool) $result;
    }

    // Tanpa JOIN dan AS

    // public function getAllExchangeRatesRaw($tenantId)
    // {
    //     $sql = "SELECT 
    //                 currency_id,
    //                 buy_rate,
    //                 sell_rate
    //             FROM exchange_rates
    //             WHERE tenant_id = ? AND is_active = 1
    //             ORDER BY currency_id ASC";

    //     return $this->db->query($sql, [$tenantId])->getResultArray();
    // }

    // public function getExchangeRateByIdRaw($tenantId, $clientId)
    // {
    //     $sql = "SELECT 
    //                 currency_id,
    //                 buy_rate,
    //                 sell_rate
    //             FROM exchange_rates
    //             WHERE id = ? AND tenant_id = ? AND is_active = 1
    //             LIMIT 1";

    //     return $this->db->query($sql, [$clientId, $tenantId])->getRowArray();
    // }

    public function getRateByBranchCurrency($currencyId)
    {
        $sql = "SELECT buy_rate, sell_rate 
                FROM exchange_rates 
                WHERE currency_id = ? 
                ORDER BY rate_date DESC 
                LIMIT 1";

        return $this->db->query($sql, [$currencyId])->getRowArray();
    }

    public function getRateByCurrencyAndType($currencyId, $type)
    {
        $rateColumn = $type === 'SELL' ? 'sell_rate' : 'buy_rate';

        $sql = "SELECT {$rateColumn} AS rate_used 
                FROM exchange_rates 
                WHERE currency_id = ? 
                ORDER BY rate_date DESC 
                LIMIT 1";

        return $this->db->query($sql, [$currencyId])->getRowArray();
    }
}
