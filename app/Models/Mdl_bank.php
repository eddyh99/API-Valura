<?php

namespace App\Models;

use App\Models\BaseModel;

class Mdl_bank extends BaseModel
{
    protected $table      = 'banks';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'tenant_id',
        'name',
        'account_no',
        'branch',
        'is_active'
    ];

    protected $useTimestamps = false;
    protected $auditEnabled  = true;
}
