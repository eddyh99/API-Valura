<?php

namespace App\Controllers\V1;

use App\Models\Mdl_branch;
use App\Models\Mdl_tenant;
use App\Controllers\BaseApiController;

class Branch extends BaseApiController
{
    protected $format    = 'json';
    protected $branchModel;

    public function __construct()
    {
        $this->branchModel = new Mdl_branch();
    }

    public function show_all_branches()
    {
        $branches = $this->branchModel->getAllBranchesRaw($this->tenantId);

        return $this->respond([
            'status' => true,
            'data' => $branches,
        ]);
    }

    public function showBranch_ByID($id = null)
    {

        $branch = $this->branchModel->getBranchByIdRaw($this->tenantId, $id);

        return $this->respond([
            'status' => true,
            'data' => $branch
        ]);
    }

    public function create()
    {
        $validation = $this->validation;
        $validation->setRules([
            'name'      => [
                'label' => 'Nama Cabang',
                'rules' => 'required|trim|max_length[100]|alpha_numeric_space',
                'errors' => [
                    'required' => '{field} wajib diisi.',
                    'alpha_numeric_space' => '{field} anya boleh berisi huruf, angka, dan spasi.',
                ]
            ],
            'address'   => [
                'label' => 'Alamat',
                'rules' => 'trim|max_length[255]|alpha_numeric_space',
                'errors' => [
                    'alpha_numeric_space' => '{field} anya boleh berisi huruf, angka, dan spasi.',
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

        $tenant = $this->branchModel->getCountBranch($this->tenantId);
        if (empty($tenant)){
            return $this->failNotFound('Tenant tidak ditemukan.');
        }
        
        if ($tenant->branch>=$tenant->max_branch){
            return $this->failForbidden("Max Branch untuk {$this->tenantId} hanya {$maxBranch} cabang saja.");
        }
        
        $data['tenant_id']  = $this->tenantId;
        $data['created_by'] = auth_user_id();
        $data['is_active']  = 1;

        $branch = $this->branchModel->insert_branch($data);
        if (!$branch->status) {
            return $this->failValidationErrors($branch->message);
        }

        return $this->respondCreated(['message' => 'Branch berhasil ditambahkan']);
    }

    public function update($id = null)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT)) {
            return $this->failValidationErrors('ID Branch tidak valid');
        }

        $validation = $this->validation;
        $validation->setRules([
            'name'      => [
                'label' => 'Nama Cabang',
                'rules' => 'required|trim|max_length[100]|alpha_numeric_space',
                'errors' => [
                    'required' => '{field} wajib diisi.',
                    'alpha_numeric_space' => '{field} anya boleh berisi huruf, angka, dan spasi.',
                ]
            ],
            'address'   => [
                'label' => 'Alamat',
                'rules' => 'trim|max_length[255]|alpha_numeric_space',
                'errors' => [
                    'alpha_numeric_space' => '{field} anya boleh berisi huruf, angka, dan spasi.',
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
        $branch = $this->branchModel->update_branch($id, $data);
        if (!$branch->status) {
            return $this->failValidationErrors($branch->message);
        }

        return $this->respond(['message' => 'Branch berhasil diupdate']);
    }

    public function delete($id = null)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT)) {
            return $this->failValidationErrors('ID Branch tidak valid');
        }
        
        $branch = $this->branchModel->delete_branch($id);
        if (!$branch){
            return $this->failServerError('Branch Gagal di hapus/sudah terhapus');
        }
        return $this->respondDeleted(['message' => 'Branch berhasil di hapus']);
    }
}
