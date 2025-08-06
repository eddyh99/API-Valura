<?php

namespace App\Controllers\V1;

use App\Models\Mdl_agent;
use App\Controllers\BaseApiController;

class Agent extends BaseApiController
{
    // protected $modelName = Mdl_agent::class;
    protected $format    = 'json';
    protected $agentModel;

    public function __construct()
    {
        $this->agentModel = new Mdl_agent();
    }

    public function show_all_agents()
    {
        $agents = $this->agentModel->getAllAgentsRaw($this->tenantId);

        return $this->respond([
            'status' => true,
            'data' => $agents
        ]);
    }

    public function showAgent_ByID($id = null)
    {
        $agent = $this->agentModel->getAgentByIdRaw($this->tenantId, $id);

        return $this->respond([
            'status' => true,
            'data' => $agent
        ]);
    }

    public function create()
    {
        $validation = $this->validation;
        $validation->setRules([
            'name'      => [
                'label' => 'Nama Agen',
                'rules' => 'required|trim|max_length[100]|alpha_numeric_space',
                'errors' => [
                    'required' => '{field} wajib diisi.',
                    'alpha_numeric_space' => '{field} hanya boleh berisi huruf, angka, dan spasi.',
                ]
            ],
            'address'   => [
                'label' => 'Alamat',
                'rules' => 'trim|max_length[255]|alpha_numeric_space',
                'errors' => [
                    'alpha_numeric_space' => '{field} hanya boleh berisi huruf, angka, dan spasi.',
                ]
            ],
            'phone'   => [
                'label' => 'Nomor Telepon',
                'rules' => 'required|regex_match[/^((\+62|62|0)8[1-9][0-9]{6,9}|0[2-9][0-9]{1,3}[0-9]{5,8})$/]',
                'errors' => [
                    'required' => '{field} wajib diisi.',
                    'regex_match' => '{field} tidak valid. Masukkan nomor HP atau telepon rumah yang benar.',
                ]
            ],
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $data = $this->request->getJSON(true);
        
        $data['tenant_id']  = $this->tenantId;
        $data['created_by'] = $this->userId;
        $data['is_active']  = 1;

        $agent = $this->agentModel->setContext(current_context())->insert_agent($data);
        if (!$agent->status) {
            return $this->failValidationErrors($agent->message);
        }

        return $this->respondCreated(['message' => 'Agent berhasil ditambahkan']);
    }
    // public function create()
    // {
    //     $data = $this->request->getJSON(true);

    //     $data['tenant_id']  = auth_tenant_id();
    //     $data['created_by'] = auth_user_id();
    //     $data['is_active']  = 1;

    //     $rules = [
    //         'name' => 'required|string|max_length[50]',
    //     ];

    //     if (! $this->validate($rules)) {
    //         return $this->failValidationErrors($this->validator->getErrors());
    //     }

    //     if (!$this->model->insert($data)) {
    //         return $this->failValidationErrors($this->model->errors());
    //     }

    //     return $this->respondCreated(['message' => 'Agent berhasil ditambahkan']);
    // }

    public function update($id = null)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT)) {
            return $this->failValidationErrors('ID Agent tidak valid');
        }

        $validation = $this->validation;
        $validation->setRules([
            'name'      => [
                'label' => 'Nama Agen',
                'rules' => 'required|trim|max_length[100]|alpha_numeric_space',
                'errors' => [
                    'required' => '{field} wajib diisi.',
                    'alpha_numeric_space' => '{field} hanya boleh berisi huruf, angka, dan spasi.',
                ]
            ],
            'address'   => [
                'label' => 'Alamat',
                'rules' => 'trim|max_length[255]|alpha_numeric_space',
                'errors' => [
                    'alpha_numeric_space' => '{field} hanya boleh berisi huruf, angka, dan spasi.',
                ]
            ],
            'phone'   => [
                'label' => 'Nomor Telepon',
                'rules' => 'required|regex_match[/^((\+62|62|0)8[1-9][0-9]{6,9}|0[2-9][0-9]{1,3}[0-9]{5,8})$/]',
                'errors' => [
                    'required' => '{field} wajib diisi.',
                    'regex_match' => '{field} tidak valid. Masukkan nomor HP atau telepon rumah yang benar.',
                ]
            ],
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $data = $this->request->getJSON(true);

        $agent = $this->agentModel->setContext(current_context())->update_agent($id, $data);
        if (!$agent->status) {
            return $this->failValidationErrors($agent->message);
        }

        return $this->respond(['message' => 'Agent berhasil diupdate']);
    }
    // public function update($id = null)
    // {
    //     $data = $this->request->getJSON(true);

    //     $agent = $this->model
    //         ->where('id', $id)
    //         ->where('is_active', 1)
    //         ->first();

    //     if (!$agent) {
    //         return $this->failNotFound('Agent tidak ditemukan atau sudah dihapus');
    //     }

    //     $rules = [
    //         'name' => 'required|string|max_length[50]',
    //     ];

    //     if (! $this->validate($rules)) {
    //         return $this->failValidationErrors($this->validator->getErrors());
    //     }

    //     if (!$this->model->update($id, $data)) {
    //         return $this->failServerError('Gagal mengupdate data agent.');
    //     }

    //     return $this->respond(['message' => 'Agent berhasil diupdate']);
    // }

    public function delete($id = null)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT)) {
            return $this->failValidationErrors('ID Agent tidak valid');
        }
        
        $agent = $this->agentModel->setContext(current_context())->delete_agent($id);
        if (!$agent){
            return $this->failServerError('Agent gagal dihapus/sudah terhapus');
        }
        return $this->respondDeleted(['message' => 'Agent berhasil dihapus']);
    }
    // public function delete($id = null)
    // {
    //     $agent = $this->model->where('id', $id)->where('is_active', 1)->first();

    //     if (!$agent) {
    //         return $this->failNotFound('Agent tidak ditemukan atau sudah dihapus');
    //     }

    //     // Soft delete set is_active=0
    //     if (!$this->model->update($id, ['is_active' => 0])) {
    //         return $this->failServerError('Gagal melakukan soft delete');
    //     }

    //     return $this->respondDeleted(['message' => 'Agent berhasil di-nonaktifkan']);
    // }
}
