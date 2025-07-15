<?php

namespace App\Models;

use App\Models\BaseModel;

class Mdl_currency extends BaseModel
{
    protected $table = 'currencies';
    protected $primaryKey = 'id';

    protected $allowedFields = ['tenant_id', 'code', 'name', 'symbol', 'is_active'];
    protected $useTimestamps = false;

    protected $auditEnabled = true;
}
