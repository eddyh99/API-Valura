<?php

namespace App\Controllers\V1;

use App\Models\Mdl_bank;
use App\Models\Mdl_bank_settlement;
use App\Controllers\BaseApiController;

class Bank extends BaseApiController
{
    // protected $modelName = Mdl_bank::class;
    protected $format    = 'json';
    protected $bankModel;
    protected $bankSettlementModel;

    public function __construct()
    {
        $this->bankModel = new Mdl_bank();
        $this->bankSettlementModel = new Mdl_bank_settlement();
    }

    public function show_all_banks()
    {
        $banks = $this->bankModel->getAllBanksRaw($this->tenantId);

        return $this->respond([
            'status' => true,
            'data' => $banks
        ]);
    }

    public function showBank_ByID($id = null)
    {
        $bank = $this->bankModel->getBankByIdRaw($this->tenantId, $id);

        return $this->respond([
            'status' => true,
            'data' => $bank
        ]);
    }

    public function create()
    {
        $validation = $this->validation;
        $validation->setRules([
            'name' => [
                'label'  => 'Nama Bank',
                'rules'  => 'required|trim|max_length[100]|is_unique[banks.name,tenant_id,' . $this->tenantId . ']',
                'errors' => [
                    'required'   => '{field} wajib diisi.',
                    'max_length' => '{field} maksimal 100 karakter.',
                    'is_unique'  => '{field} sudah terdaftar.',
                ]
            ],
            'account_no' => [
                'label'  => 'Nomor Rekening',
                'rules'  => 'required|trim|numeric|max_length[50]|is_unique[banks.account_no,tenant_id,' . $this->tenantId . ']',
                'errors' => [
                    'required'   => '{field} wajib diisi.',
                    'numeric'    => '{field} harus berupa angka.',
                    'max_length' => '{field} maksimal 50 karakter.',
                    'is_unique'  => '{field} sudah terdaftar.',
                ]
            ],
            'branch' => [
                'label'  => 'Cabang Bank',
                'rules'  => 'required|trim|max_length[100]',
                'errors' => [
                    'required'   => '{field} wajib diisi.',
                    'max_length' => '{field} maksimal 100 karakter.',
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

        $bank = $this->bankModel->setContext(current_context())->insert_bank($data);
        if (!$bank->status) {
            return $this->failValidationErrors($bank->message);
        }

        return $this->respondCreated(['message' => 'Bank berhasil ditambahkan']);
    }

    public function update($id = null)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT)) {
            return $this->failValidationErrors('ID Bank tidak valid');
        }

        $validation = $this->validation;
        $validation->setRules([
            'name' => [
                'label'  => 'Nama Bank',
                'rules'  => 'required|trim|max_length[100]|is_unique[banks.name,tenant_id,' . $this->tenantId . ']',
                'errors' => [
                    'required'   => '{field} wajib diisi.',
                    'max_length' => '{field} maksimal 100 karakter.',
                    'is_unique'  => '{field} sudah terdaftar.',
                ]
            ],
            'account_no' => [
                'label'  => 'Nomor Rekening',
                'rules'  => 'required|trim|numeric|max_length[50]|is_unique[banks.account_no,tenant_id,' . $this->tenantId . ']',
                'errors' => [
                    'required'   => '{field} wajib diisi.',
                    'numeric'    => '{field} harus berupa angka.',
                    'max_length' => '{field} maksimal 50 karakter.',
                    'is_unique'  => '{field} sudah terdaftar.',
                ]
            ],
            'branch' => [
                'label'  => 'Cabang Bank',
                'rules'  => 'required|trim|max_length[100]',
                'errors' => [
                    'required'   => '{field} wajib diisi.',
                    'max_length' => '{field} maksimal 100 karakter.',
                ]
            ],
        ]);

        $data = $this->request->getJSON(true);

        $bank = $this->bankModel->setContext(current_context())->update_bank($id, $data);
        if (!$bank->status) {
            return $this->failValidationErrors($bank->message);
        }

        return $this->respond(['message' => 'Bank berhasil diupdate']);
    }

    public function delete($id = null)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT)) {
            return $this->failValidationErrors('ID Bank tidak valid');
        }
        
        $bank = $this->bankModel->setContext(current_context())->delete_bank($id);
        if (!$bank){
            return $this->failServerError('Bank gagal dihapus/sudah terhapus');
        }
        return $this->respondDeleted(['message' => 'Bank berhasil dihapus']);
    }

    // Batas Bank & Bank-Settlement

    public function create_settlement()
    {
        $data = $this->request->getJSON(true);

        // Tambahan otomatis dari sistem
        $data['tenant_id']   = auth_tenant_id();
        $data['created_by']  = auth_user_id();
        $data['created_at']  = date('Y-m-d H:i:s');

        // Validasi input
        $rules = [
            'currency_id'     => 'required|integer',
            'bank_name'       => 'required|string',
            'account_number'  => 'required|string',
            'amount_foreign'  => 'required|numeric|greater_than[0]',
            'rate_used'       => 'required|numeric|greater_than[0]',
            'transaction_date'=> 'required|valid_date[Y-m-d]'
        ];

        if (! $this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        if (! $this->bankSettlementModel->insert($data)) {
            return $this->failServerError('Gagal menambahkan data settlement.');
        }

        return $this->respondCreated(['message' => 'Settlement berhasil ditambahkan']);
    }

}
