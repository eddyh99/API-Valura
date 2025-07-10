<?php

namespace App\Models;

use App\Models\BaseModel;

class Mdl_branch extends BaseModel
{
    protected $table      = 'branches';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'tenant_id', 'name', 'address', 'phone', 'is_active', 'created_at'
    ];

    protected $useTimestamps = false;
    protected $auditEnabled = true;
}
