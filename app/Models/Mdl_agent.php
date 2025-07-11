<?php

namespace App\Models;

use App\Models\BaseModel;

class Mdl_agent extends BaseModel
{
    protected $table      = 'agents';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'tenant_id',
        'name',
        'created_by',
        'is_active'
    ];

    protected $useTimestamps = false;
    protected $auditEnabled  = true;
}
