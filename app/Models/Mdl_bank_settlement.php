<?php

namespace App\Models;

use App\Models\BaseModel;

class Mdl_bank_settlement extends BaseModel
{
    protected $table      = 'bank_transactions';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'tenant_id',
        'currency_id',
        'bank_name',
        'account_number',
        'amount_foreign',
        'rate_used',
        'transaction_date',
        'created_by',
        'created_at'
    ];

    protected $useTimestamps = false;
    protected $auditEnabled  = true;
}
