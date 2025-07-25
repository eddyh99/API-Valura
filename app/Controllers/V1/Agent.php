<?php

namespace App\Controllers\V1;

use App\Models\Mdl_agent;
use App\Controllers\BaseApiController;

class Agent extends BaseApiController
{
    protected $modelName = Mdl_agent::class;
    protected $format    = 'json';

    public function show_all_agents()
    {
        $tenantId = auth_tenant_id();

        $agents = $this->model->getAllAgentsRaw($tenantId);

        return $this->respond([
            'status' => true,
            'data' => $agents
        ]);
    }

    public function showAgent_ByID($id = null)
    {
        $tenantId = auth_tenant_id();

        $agent = $this->model->getAgentByIdRaw($tenantId, $id);

        return $this->respond([
            'status' => true,
            'data' => $agent
        ]);
    }

    public function create()
    {
        $data = $this->request->getJSON(true);

        $data['tenant_id']  = auth_tenant_id();
        $data['created_by'] = auth_user_id();
        $data['is_active']  = 1;

        $rules = [
            'name' => 'required|string|max_length[50]',
        ];

        if (! $this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

                if (!$this->model->insert($data)) {
            return $this->failValidationErrors($this->model->errors());
        }

        return $this->respondCreated(['message' => 'Agent berhasil ditambahkan']);
    }

    public function update($id = null)
    {
        $data = $this->request->getJSON(true);

        $agent = $this->model
            ->where('id', $id)
            ->where('is_active', 1)
            ->first();

        if (!$agent) {
            return $this->failNotFound('Agent tidak ditemukan atau sudah dihapus');
        }

        $rules = [
            'name' => 'required|string|max_length[50]',
        ];

        if (! $this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        if (!$this->model->update($id, $data)) {
            return $this->failServerError('Gagal mengupdate data agent.');
        }

        return $this->respond(['message' => 'Agent berhasil diupdate']);
    }

    public function delete($id = null)
    {
        $agent = $this->model->where('id', $id)->where('is_active', 1)->first();

        if (!$agent) {
            return $this->failNotFound('Agent tidak ditemukan atau sudah dihapus');
        }

        // Soft delete set is_active=0
        if (!$this->model->update($id, ['is_active' => 0])) {
            return $this->failServerError('Gagal melakukan soft delete');
        }

        return $this->respondDeleted(['message' => 'Agent berhasil di-nonaktifkan']);
    }
}
