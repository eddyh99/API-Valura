<?php

namespace App\Controllers\V1;

use App\Models\Mdl_client;
use App\Controllers\BaseApiController;

class Client extends BaseApiController
{
    // protected $modelName = Mdl_client::class;
    protected $format    = 'json';
    protected $clientModel;

    public function __construct()
    {
        $this->clientModel = new Mdl_client();
    }

    public function show_all_clients()
    {
        $clients = $this->clientModel->getAllClientsRaw($this->tenantId);

        return $this->respond([
            'status' => true,
            'data'   => $clients
        ]);
    }

    public function showClient_ByID($id = null)
    {
        $client = $this->clientModel->getClientByIdRaw($this->tenantId, $id);

        return $this->respond([
            'status' => true,
            'data'   => $client
        ]);
    }

    public function create()
    {
        $validation = $this->validation;
        $validation->setRules([
            'name' => [
                'label'  => 'Nama Lengkap',
                'rules'  => 'required|trim|max_length[100]|alpha_numeric_space',
                'errors' => [
                    'required'             => '{field} wajib diisi.',
                    'max_length'           => '{field} maksimal 100 karakter.',
                    'alpha_numeric_space'  => '{field} hanya boleh berisi huruf, angka, dan spasi.',
                ]
            ],
            'id_type' => [
                'label'  => 'Tipe Identitas',
                'rules'  => 'required|trim|max_length[20]|alpha_numeric_space',
                'errors' => [
                    'required'             => '{field} wajib diisi.',
                    'max_length'           => '{field} maksimal 20 karakter.',
                    'alpha_numeric_space'  => '{field} hanya boleh berisi huruf, angka, dan spasi.',
                ]
            ],
            'id_number' => [
                'label'  => 'Nomor Identitas',
                'rules'  => 'required|trim|max_length[50]|alpha_numeric',
                'errors' => [
                    'required'     => '{field} wajib diisi.',
                    'max_length'   => '{field} maksimal 50 karakter.',
                    'alpha_numeric'=> '{field} hanya boleh berisi huruf dan angka.',
                ]
            ],
            'country' => [
                'label'  => 'Negara',
                'rules'  => 'required|trim|max_length[30]|alpha_numeric_space',
                'errors' => [
                    'required'             => '{field} wajib diisi.',
                    'max_length'           => '{field} maksimal 30 karakter.',
                    'alpha_numeric_space'  => '{field} hanya boleh berisi huruf, angka, dan spasi.',
                ]
            ],
            'phone' => [
                'label'  => 'Nomor Telepon',
                'rules'  => 'required|regex_match[/^((\+62|62|0)8[1-9][0-9]{6,9}|0[2-9][0-9]{1,3}[0-9]{5,8})$/]',
                'errors' => [
                    'required'     => '{field} wajib diisi.',
                    'regex_match'  => '{field} tidak valid. Masukkan nomor HP atau telepon rumah yang benar.',
                ]
            ],
            'email' => [
                'label'  => 'Email',
                'rules'  => 'required|trim|valid_email|max_length[100]',
                'errors' => [
                    'required'     => '{field} wajib diisi.',
                    'valid_email'  => '{field} tidak valid.',
                    'max_length'   => '{field} maksimal 100 karakter.',
                ]
            ],
            'address' => [
                'label'  => 'Alamat',
                'rules'  => 'required|trim|max_length[255]|alpha_numeric_space',
                'errors' => [
                    'required'            => '{field} wajib diisi.',
                    'max_length'          => '{field} maksimal 255 karakter.',
                    'alpha_numeric_space' => '{field} hanya boleh berisi huruf, angka, dan spasi.',
                ]
            ],
            'job' => [
                'label' => 'Pekerjaan',
                'rules' => 'required|trim|max_length[30]|alpha_numeric_space',
                'errors' => [
                    'required'             => '{field} wajib diisi.',
                    'max_length'           => '{field} maksimal 30 karakter.',
                    'alpha_numeric_space'  => '{field} hanya boleh berisi huruf, angka, dan spasi.',
                ]
            ],
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $data = $this->request->getJSON(true);

        $data['tenant_id'] = $this->tenantId;
        $data['is_active'] = 1;

        $client = $this->clientModel->setContext(current_context())->insert_client($data);
        if (!$client->status) {
            return $this->failValidationErrors($client->message);
        }

        return $this->respondCreated(['message' => 'Client berhasil ditambahkan']);
    }

    public function update($id = null)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT)) {
            return $this->failValidationErrors('ID Client tidak valid');
        }

        $validation = $this->validation;
        $validation->setRules([
            'name' => [
                'label'  => 'Nama Lengkap',
                'rules'  => 'required|trim|max_length[100]|alpha_numeric_space',
                'errors' => [
                    'required'             => '{field} wajib diisi.',
                    'max_length'           => '{field} maksimal 100 karakter.',
                    'alpha_numeric_space'  => '{field} hanya boleh berisi huruf, angka, dan spasi.',
                ]
            ],
            'id_type' => [
                'label'  => 'Tipe Identitas',
                'rules'  => 'required|trim|max_length[20]|alpha_numeric_space',
                'errors' => [
                    'required'             => '{field} wajib diisi.',
                    'max_length'           => '{field} maksimal 20 karakter.',
                    'alpha_numeric_space'  => '{field} hanya boleh berisi huruf, angka, dan spasi.',
                ]
            ],
            'id_number' => [
                'label'  => 'Nomor Identitas',
                'rules'  => 'required|trim|max_length[50]|alpha_numeric',
                'errors' => [
                    'required'     => '{field} wajib diisi.',
                    'max_length'   => '{field} maksimal 50 karakter.',
                    'alpha_numeric'=> '{field} hanya boleh berisi huruf dan angka.',
                ]
            ],
            'country' => [
                'label'  => 'Negara',
                'rules'  => 'required|trim|max_length[30]|alpha_numeric_space',
                'errors' => [
                    'required'             => '{field} wajib diisi.',
                    'max_length'           => '{field} maksimal 30 karakter.',
                    'alpha_numeric_space'  => '{field} hanya boleh berisi huruf, angka, dan spasi.',
                ]
            ],
            'phone' => [
                'label'  => 'Nomor Telepon',
                'rules'  => 'required|regex_match[/^((\+62|62|0)8[1-9][0-9]{6,9}|0[2-9][0-9]{1,3}[0-9]{5,8})$/]',
                'errors' => [
                    'required'     => '{field} wajib diisi.',
                    'regex_match'  => '{field} tidak valid. Masukkan nomor HP atau telepon rumah yang benar.',
                ]
            ],
            'email' => [
                'label'  => 'Email',
                'rules'  => 'required|trim|valid_email|max_length[100]',
                'errors' => [
                    'required'     => '{field} wajib diisi.',
                    'valid_email'  => '{field} tidak valid.',
                    'max_length'   => '{field} maksimal 100 karakter.',
                ]
            ],
            'address' => [
                'label'  => 'Alamat',
                'rules'  => 'required|trim|max_length[255]|alpha_numeric_space',
                'errors' => [
                    'required'            => '{field} wajib diisi.',
                    'max_length'          => '{field} maksimal 255 karakter.',
                    'alpha_numeric_space' => '{field} hanya boleh berisi huruf, angka, dan spasi.',
                ]
            ],
            'job' => [
                'label' => 'Pekerjaan',
                'rules' => 'required|trim|max_length[30]|alpha_numeric_space',
                'errors' => [
                    'required'             => '{field} wajib diisi.',
                    'max_length'           => '{field} maksimal 30 karakter.',
                    'alpha_numeric_space'  => '{field} hanya boleh berisi huruf, angka, dan spasi.',
                ]
            ],
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->failValidationErrors($validation->getErrors());
        }

        $data = $this->request->getJSON(true);

        $client = $this->clientModel->setContext(current_context())->update_client($id, $data);
        if (!$client->status) {
            return $this->failValidationErrors($client->message);
        }

        return $this->respond(['message' => 'Client berhasil diupdate']);
    }

    public function delete($id = null)
    {
        if (!filter_var($id, FILTER_VALIDATE_INT)) {
            return $this->failValidationErrors('ID Client tidak valid');
        }
        
        $client = $this->clientModel->setContext(current_context())->delete_client($id);
        if (!$client){
            return $this->failServerError('Client gagal dihapus/sudah terhapus');
        }
        return $this->respondDeleted(['message' => 'Client berhasil dihapus']);
    }
}
