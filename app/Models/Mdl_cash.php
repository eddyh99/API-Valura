<?php

namespace App\Models;

use App\Models\BaseModel;

class Mdl_cash extends BaseModel
{
    protected $table      = 'cash_movements';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'tenant_id',
        'branch_id',
        'currency_id',
        'movement_type',
        'amount',
        'reference_type',
        'reference_id',
        'occurred_at',
        'created_by',
        'is_active'
    ];

    protected $useTimestamps = false;
    protected $auditEnabled = true;
}
