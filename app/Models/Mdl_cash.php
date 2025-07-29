<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Mdl_branch;

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

    public function getAllCashesRaw($tenantId)
    {
        $branchId    = auth_branch_id();
        $today       = date('Y-m-d');
        $startOfDay  = $today . ' 00:00:00';
        $endOfDay    = $today . ' 23:59:59';

        $sql = "SELECT 
                    cm.id,
                    cm.tenant_id,
                    cm.branch_id,
                    b.name AS branch_name,
                    cm.currency_id,
                    c.code AS currency_code,
                    c.name AS currency_name,
                    cm.movement_type,
                    cm.amount,
                    cm.reference_type,
                    cm.reference_id,
                    cm.occurred_at,
                    cm.created_by
                FROM cash_movements cm
                LEFT JOIN branches b ON b.id = cm.branch_id
                LEFT JOIN currencies c ON c.id = cm.currency_id
                WHERE cm.tenant_id = ?
                    AND cm.branch_id = ?
                    AND cm.is_active = 1
                    AND cm.occurred_at BETWEEN ? AND ?
                ORDER BY cm.occurred_at ASC";

        return $this->db->query($sql, [$tenantId, $branchId, $startOfDay, $endOfDay])->getResultArray();
    }

    public function getCashByIdRaw($tenantId, $cashId)
    {
        $sql = "SELECT 
                    cm.id,
                    cm.tenant_id,
                    cm.branch_id,
                    b.name AS branch_name,
                    cm.currency_id,
                    c.code AS currency_code,
                    c.name AS currency_name,
                    cm.movement_type,
                    cm.amount,
                    cm.reference_type,
                    cm.reference_id,
                    cm.occurred_at,
                    cm.created_by
                FROM cash_movements cm
                LEFT JOIN branches b ON b.id = cm.branch_id
                LEFT JOIN currencies c ON c.id = cm.currency_id
                WHERE cm.id = ?
                    AND cm.tenant_id = ?
                    AND cm.is_active = 1
                LIMIT 1";

        return $this->db->query($sql, [$cashId, $tenantId])->getRowArray();
    }

    public function getDailyCashRaw($branchId)
    {
        $sql = "SELECT id, movement_type, amount
                FROM cash_movements
                WHERE branch_id = ?
                AND is_active = 1
                AND DATE(occurred_at) = CURDATE()";

        return $this->db->query($sql, [$branchId])->getResultArray();
    }

    public function getBranchNameById($branchId)
    {
        $sql = "SELECT name FROM branches WHERE id = ? AND is_active = 1 LIMIT 1";
        $row = $this->db->query($sql, [$branchId])->getRowArray();

        return $row ? $row['name'] : null;
    }

    public function getDailyCashByBranch($branchId)
    {
        $sql = "SELECT * 
                FROM cash_movements 
                WHERE branch_id = ? 
                AND is_active = 1 
                AND DATE(occurred_at) = CURDATE()";

        return $this->db->query($sql, [$branchId])->getResultArray();
    }

    public function getTodayCashByBranch($branchId)
    {
        $sql = "SELECT * 
                FROM cash_movements 
                WHERE branch_id = ? 
                AND is_active = 1 
                AND DATE(occurred_at) = CURDATE()";

        return $this->db->query($sql, [$branchId])->getResultArray();
    }
}
