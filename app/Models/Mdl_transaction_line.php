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
}
