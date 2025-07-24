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

    public function getRateByBranchCurrency($currencyId)
    {
        $sql = "SELECT buy_rate, sell_rate 
                FROM exchange_rates 
                WHERE currency_id = ? 
                ORDER BY rate_date DESC 
                LIMIT 1";

        return $this->db->query($sql, [$currencyId])->getRowArray();
    }

    // app/Models/Mdl_exchange_rate.php

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
