<?php

namespace App\Models;

use App\Models\BaseModel;

class Mdl_default_currency extends BaseModel
{
    protected $table = 'default_currency';
    protected $primaryKey = 'id';

    protected $allowedFields = ['code', 'name', 'symbol', 'is_active'];
    protected $useTimestamps = false;
}
