<?php

namespace App\Models;

use App\Models\BaseModel;

class Mdl_bank_deposit extends BaseModel
{
    protected $table      = 'bank_deposits';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'tenant_id',
        'branch_id',
        'type',
        'note',
        'currency_id',
        'amount',
        'bank_id',
        'deposit_date',
        'created_by',
        'created_at'
    ];

    protected $useTimestamps = false;
    protected $auditEnabled  = true;
}
