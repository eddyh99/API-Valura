<?php

namespace App\Controllers\V1;

use App\Models\Mdl_client;
use App\Controllers\BaseApiController;

class Client extends BaseApiController
{
    protected $modelName = Mdl_client::class;
    protected $format    = 'json';

    public function create()
    {
        $data = $this->request->getJSON(true);

        $data['tenant_id']  = auth_tenant_id();
        $data['is_active']  = 1;
        $data['created_at'] = date('Y-m-d H:i:s');

        $rules = [
            'name'      => 'required|string|max_length[100]',
            'id_type'   => 'permit_empty|string|max_length[20]',
            'id_number' => 'permit_empty|string|max_length[50]',
            'country'   => 'permit_empty|string|max_length[30]',
            'phone'     => 'permit_empty|string|max_length[30]',
            'email'     => 'permit_empty|valid_email|max_length[100]',
            'address'   => 'permit_empty|string|max_length[255]',
        ];

        if (! $this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        if (!$this->model->insert($data)) {
            return $this->failValidationErrors($this->model->errors());
        }

        return $this->respondCreated(['message' => 'Client berhasil ditambahkan']);
    }

    public function update($id = null)
    {
        $data = $this->request->getJSON(true);

        $client = $this->model
            ->where('id', $id)
            ->where('is_active', 1)
            ->first();

        if (!$client) {
            return $this->failNotFound('Client tidak ditemukan atau sudah dihapus');
        }

        $rules = [
            'name'      => 'required|string|max_length[100]',
            'id_type'   => 'permit_empty|string|max_length[20]',
            'id_number' => 'permit_empty|string|max_length[50]',
            'country'   => 'permit_empty|string|max_length[30]',
            'phone'     => 'permit_empty|string|max_length[30]',
            'email'     => 'permit_empty|valid_email|max_length[100]',
            'address'   => 'permit_empty|string|max_length[255]',
        ];

        if (! $this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        if (!$this->model->update($id, $data)) {
            return $this->failServerError('Gagal mengupdate data client.');
        }

        return $this->respond(['message' => 'Client berhasil diupdate']);
    }

    public function delete($id = null)
    {
        $client = $this->model->where('id', $id)->where('is_active', 1)->first();

        if (!$client) {
            return $this->failNotFound('Client tidak ditemukan atau sudah dihapus');
        }

        // Soft delete set is_active = 0
        if (!$this->model->update($id, ['is_active' => 0])) {
            return $this->failServerError('Gagal melakukan soft delete');
        }

        return $this->respondDeleted(['message' => 'Client berhasil di-nonaktifkan']);
    }

    public function show_all_clients()
    {
        $tenantId = auth_tenant_id();

        $clients = $this->model
            ->where('is_active', 1)
            ->where('tenant_id', $tenantId)
            ->findAll();

        return $this->respond([
            'status' => true,
            'data'   => $clients
        ]);
    }

    public function showClient_ByID($id = null)
    {
        $tenantId = auth_tenant_id();

        $client = $this->model
            ->where('id', $id)
            ->where('is_active', 1)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$client) {
            return $this->failNotFound('Client tidak ditemukan atau sudah dihapus.');
        }

        return $this->respond([
            'status' => true,
            'data'   => $client
        ]);
    }
}
