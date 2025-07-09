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
}
