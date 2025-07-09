<?php

namespace App\Models;

use App\Models\BaseModel;

class Mdl_role extends BaseModel
{
    protected $table      = 'roles';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'tenant_id', 'name', 'permissions', 'is_active'
    ];

    protected $useTimestamps = false;
    protected $auditEnabled = true;
}
