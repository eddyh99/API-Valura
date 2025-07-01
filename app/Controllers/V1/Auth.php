<?php

namespace App\Controllers\V1;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;

class Auth extends BaseController
{
    use ResponseTrait;

    public function __construct()
    {
		$this->member  = model('App\Models\V1\Mdl_member');        
    }

    public function postRegister()
    {
        //do Register
    }

    public function postLogin()
    {

        $validation = $this->validation;
        $validation->setRules([
            'username' => [
                'rules'  => 'required',
                'errors' => [
                    'required' => 'Username is required',
                ]
            ],
            'password' => [
                'rules'  => 'required',
                'errors' =>  [
                    'required'     => 'Password is required',
                ]
            ],
            'domain' => [
                'rules'  => 'required',
                'errors' => [
                    'required'     => 'Domain is required',
                ]
            ],
            'ip_address' => [
                'rules'  => 'required|valid_ip',
                'errors' => [
                    'required'  => 'IP Address is required',
                    'valid_ip'  => 'Invalid IP address format'
                ]
            ]
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->fail($validation->getErrors());
        }

        $data    = $this->request->getJSON();

        $pending_order = $this->signal->getBuy_pending();

        if ($pending_order->code != 200) {
            return $this->respond(error_msg(400, "order", '01', 'Failed check previous order, try again'), 400);
        }

        if (!empty($pending_order->message)) {
            return $this->respond(error_msg(400, "binance", '02', 'Previous buy still pending'), 400);
        }

        $deposit  = $this->deposit->rebalanceMemberPosition($data->type);

        if (@$deposit->code != 200) {
            return $this->respond(error_msg(400, "signal", '01', $deposit->message), 400);
        }

        if($data->type == 'BUY A') {
            $btc = $this->getAssets("BTC");
            $asset_btc = ($btc->free + $btc->locked);
            $this->setting->store(['asset_btc' => $asset_btc ]);
        } 
        
        // else {
        //     $asset_btc = $this->setting->get('asset_btc')->message;
        // }

        $trade_balance = $deposit->message;
        // dd($trade_balance);
        $order = $this->limit_order('BUY', $trade_balance, $data->limit);      

        $mdata = [
            'admin_id' => $data->admin_id,
            'ip_addr'  => $data->ip_address,
            'type'     => $data->type,
            'order_id' => $order->orderId ?? null,
            'entry_price' => $data->limit
        ];
        $signal = $this->signal->add($mdata);
        if (@$signal->code != 201) {
            return $this->respond(error_msg(400, "signal", '01', $signal->message), 400);
        }

        $result = [
            'text' => $signal->message,
            'id'   => $signal->id
        ];

        if (!isset($order->orderId) || !isset($order->origQty)) {
            $result['text'] = 'Order Failed.';
            return $this->respond(error_msg(400, "order", '01', $result), 400);
        }
        $this->member->update_data($deposit->member_positions);
        
        // $cost = floor(($order->origQty * $data->limit) * 100) / 100;

        // if($order->status == 'FILLED') {
        //     $cost = $order->cummulativeQuoteQty;
        // }
        
        $member = $this->getBTC_member($data->type, $signal->id, $deposit->member_ids);
        $member_signal = $this->member_signal->addOrUpdate($member);
        if (@$member_signal->code != 201) {
            $result['text'] =  $member_signal->message;
            return $this->respond(error_msg(400, "signal", '01', $result), 400);
        }

        return $this->respond(error_msg(201, "order", null, $result), 201);

    }

    private function getBtc_member($type, $signal_id, $member_ids)
    {

        // function convertBTC($number, $precision = 6) {
        //     $factor = pow(10, $precision);
        //     return floor($number * $factor) / $factor;
        // }
        
        // $btc = $this->getAssets("BTC");
        // $amount_btc = convertBTC(($btc->free + $btc->locked));
        // log_message('info', 'BTC FROM ASSETS: ' .json_encode($amount_btc));
        // if($type == 'BUY A') {
        //     // $amount_btc -= ($asset_btc + 0);
        //     $this->setting->store(['asset_btc' => $asset_btc ]);
        // } else {
        //     $prev_signal = $this->signal->getPrev_signals($type)->message;
        //     $amount_btc -= ($asset_btc + $prev_signal->btc);
        //     log_message('info', 'BTC FROM PREV BUY: ' .json_encode($prev_signal));
        // }


        $member = $this->member->getby_ids($member_ids);
        $side_position = [
            'BUY A' => 'position_a',
            'BUY B' => 'position_b',
            'BUY C' => 'position_c',
            'BUY D' => 'position_d'
        ];
        $position = $side_position[$type];

        if ($member->code != 200) {
            return false;
        }

        $mdata = [];
        foreach ($member->message as $m) {

            $mdata[] = [
                'member_id' => $m['id'],
                'amount_usdt' => $m[$position],
                'amount_btc' => 0,
                'sinyal_id' => $signal_id
            ];
        }
        return $mdata;
    }

    public function getAssets($coin)
    {
        $url = BINANCEAPI . "/account";

        $response = binanceAPI($url, []);
        if (isset($response->code)) {
            return false;
        }

		$btc = array_values(array_filter($response->balances, function ($bal) use ($coin){
			return $bal->asset === $coin;
		}));
        return $btc[0];
    }

    public function postLimit_sell()
    {

        $validation = $this->validation;
        $validation->setRules([
            'type' => [
                'rules'  => 'required|in_list[SELL A,SELL B,SELL C,SELL D]',
                'errors' => [
                    'required' => 'Type is required',
                    'in_list'  => 'Invalid type, allowed types: SELL A, SELL B, SELL C, SELL D'
                ]
            ],
            'id_signal' => [
                'rules'  => 'required|is_numeric',
                'errors' => [
                    'required' => 'Type is required',
                ]
            ],
            'limit' => [
                'rules'  => 'required|numeric|greater_than[0]',
                'errors' =>  [
                    'required'     => 'Limit is required',
                    'numeric'      => 'Limit must be a number',
                    'greater_than' => 'Limit must be greater than 0'
                ]
            ],
            'admin_id' => [
                'rules'  => 'required|integer',
                'errors' => [
                    'required'     => 'Admin ID is required',
                    'integer'      => 'Admin ID must be an integer',
                ]
            ],
            'ip_address' => [
                'rules'  => 'required|valid_ip',
                'errors' => [
                    'required'  => 'IP Address is required',
                    'valid_ip'  => 'Invalid IP address format'
                ]
            ]
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->fail($validation->getErrors());
        }

        $data    = $this->request->getJSON();
        $signal  = $this->signal->getBtc_bySignal($data->id_signal);

        if (@$signal->code != 200) {
            return $this->respond(error_msg(400, "signal", '01', $signal->message), 400);
        }

        if (@$signal->message->status != 'filled') {
            return $this->respond(error_msg(400, "signal", '01', 'Order is ' . $signal->message->status), 400);
        }

        $order = $this->limit_order('SELL', $signal->btc, $data->limit);
        
        $mdata = [
            'admin_id' => $data->admin_id,
            'ip_addr'  => $data->ip_address,
            'type'     => $data->type,
            'order_id' => $order->orderId,
            'entry_price' => $data->limit,
            'pair_id'   => $data->id_signal
        ];
        $signal = $this->signal->add($mdata);
        if (@$signal->code != 201) {
            return $this->respond(error_msg(400, "signal", '01', $signal->message), 400);
        }

        $result = [
            'text' => $signal->message,
            'id'   => $signal->id
        ];

        if (!isset($order->orderId) || !isset($order->origQty)) {
            $result['text'] = 'Order Failed.';
            return $this->respond(error_msg(400, "order", '01', $result), 400);
        }

        return $this->respond(error_msg(201, "sell", null, $result), 201);
    }

    public function limit_order($side, $amount, $limit)
    {

        $stepSize = 0.00001000;
        $btc = $amount / $limit;
        $quantityBTC = floor(($side == 'BUY' ? $btc : $amount) / $stepSize) * $stepSize;

        $params =  [
            "symbol"      => "BTCUSDT",
            "side"        => $side,
            "type"        => "LIMIT",
            "timeInForce" => "GTC",
            "quantity"    => $quantityBTC,
            "price"       => $limit,
            "newClientOrderId" => 'order_' . bin2hex(random_bytes(10))
        ];

        $url = BINANCEAPI . '/order';
        $result = binanceAPI($url, $params, 'POST');
        return $result;
    }

    public function postFill_order()
    {
        $id = filter_var($this->request->getVar('id_signal'), FILTER_SANITIZE_NUMBER_INT);
        $result = $this->signal->fill_order($id);
        if (@$result->code != 201) {
            return $this->respond(error_msg($result->code, "order", '01', $result->message), $result->code);
        }

        return $this->respond(error_msg(201, "order", null, $result->message), 201);
    }

    public function getGet_all()
    {
        $result = $this->signal->get_all();
        if (@$result->code != 200) {
            return $this->respond(error_msg($result->code, "order", '01', $result->message), $result->code);
        }

        return $this->respond(error_msg(200, "order", null, $result->message), 200);
    }

    public function get_proxies()
    {
        $result = $this->proxy->get_all();

        if (@$result->code != 200) {
            return false;
        }

        // filter proksi yang aktif
        $active_proxies = $this->get_active_proxies($result->message);

        if (empty($active_proxies)) {
            return false;
        }

        return $active_proxies;
    }

    private function get_active_proxies($proxies)
    {
        $mh = curl_multi_init();
        $handles = [];

        foreach ($proxies as $proxy) {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, "https://1.1.1.1/cdn-cgi/trace");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_PROXY, "{$proxy->ip_address}:{$proxy->port}");
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);

            if (!empty($proxy->username) && !empty($proxy->password)) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, "{$proxy->username}:{$proxy->password}");
            }

            curl_multi_add_handle($mh, $ch);
            $handles[$proxy->ip_address . ':' . $proxy->port] = ['ch' => $ch, 'proxy' => $proxy];
        }

        // Eksekusi semua request secara paralel
        do {
            $status = curl_multi_exec($mh, $active);
        } while ($status === CURLM_CALL_MULTI_PERFORM || $active);

        // Ambil hanya proxy yang aktif
        $active_proxies = [];
        foreach ($handles as $key => $data) {
            $ch = $data['ch'];
            $proxy = $data['proxy'];

            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            if ($http_code === 200 && empty($error)) {
                $active_proxies[] = $proxy; // Simpan hanya proxy yang aktif
            }

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        curl_multi_close($mh);

        return $active_proxies; // Hanya mengembalikan proxy yang aktif
    }

    // cancel order
    public function getDelete()
    {
        // Get signal ID from request
        $id_signal = filter_var($this->request->getVar('id_signal'), FILTER_SANITIZE_NUMBER_INT);
    
        // Fetch the signal order detail
        $order = $this->signal->get_order($id_signal);
    
        // If the signal order is not valid or not in pending status
        if ($order->code != 200) {
            return $this->respond(error_msg($order->code, "signal", null, $order->message), $order->code);
        }
    
        // Get the Binance order ID from the signal data
        $id_order = $order->message->order_id;
    
        if(!is_null($id_order)) {
            // Prepare Binance API endpoint and parameters
            $url = BINANCEAPI . "/order";
            $params = [
                "symbol" => "BTCUSDT",
                "orderId" => $id_order
            ];
        
            // Call Binance API to cancel the order
            $response = binanceAPI($url, $params, "DELETE");
        
            // If the API responds with an error code from binance
            if (isset($response->code)) {
                return $this->respond(error_msg(400, "binance", $response->code, $response->msg), 400);
            }
        
            // If the order status is not 'CANCELED', treat it as a failure
            if ($response->status != 'CANCELED') {
                return $this->respond(error_msg(400, "binance", null, 'Failed to cancel order'), 400);
            }
        }
    
        // Delete the signal
        $result = $this->signal->destroy($id_signal);
    
        if ($result->code != 201) {
            return $this->respond(error_msg($result->code, "signal", null, $result->message), $result->code);
        }
    
        // Success response
        return $this->respond(error_msg(200, "signal", null, $result->message), 200);
    }


    public function getlast_order($type)
    {
        $types = [];
        switch ($type) {
            case 'Buy B':
                $types = ['Buy A'];
                break;
            case 'Buy C':
                $types = ['Buy A', 'Buy B'];
                break;
            case 'Buy D':
                $types = ['Buy A', 'Buy B', 'Buy C'];
                break;
            default:
                return [];
                break;
        }
        $result = $this->signal->getlast_orderFilled($types);
        if (@$result->code != 200) {
            return false;
        }

        return $result->message;
    }

    //========= for debugging ===========
    public function getSell_all()
    {
        $qty = $this->request->getVar('qty');
        $url = BINANCEAPI . "/order";
        $params = [
            "symbol"      => "BTCUSDT",
            "side"        => 'SELL',
            "type"        => "MARKET",
            "quantity"    => $qty ?? 0,
        ];

        $response = binanceAPI($url, $params, 'POST');
        if (isset($response->code)) {
            return $this->respond(error_msg(400, "binance", $response->code, $response->msg), 400);
        }

        return $this->respond(error_msg(200, "binance", null, $response), 200);
    }

    public function getBalance()
    {
        $url = BINANCEAPI . "/account";

        $response = binanceAPI($url, []);
        if (isset($response->code)) {
            return $this->respond(error_msg(400, "binance", $response->code, $response->msg), 400);
        }

        return $this->respond(error_msg(200, "binance", null, $response), 200);
    }

    public function getStatus()
    {
        $order_id = $this->request->getVar('order_id');
        $url = BINANCEAPI . "/order";
        $params = [
            "symbol" => "BTCUSDT",
            "orderId" => $order_id
        ];

        $response = binanceAPI($url, $params);
        if (isset($response->code)) {
            return $this->respond(error_msg(400, "binance", $response->code, $response->msg), 400);
        }

        return $this->respond(error_msg(200, "binance", null, $response), 200);
    }

    public function postDelete_all()
    {
        $url = BINANCEAPI . "/openOrders";
        $params = [
            "symbol" => "BTCUSDT"
        ];
        $response = binanceAPI($url, $params, "DELETE");

        if (isset($response->code)) {
            return $this->respond(error_msg(400, "binance", $response->code, $response->msg), 400);
        }

        return $this->respond(error_msg(200, "binance", null, $response), 200);
    }

    public function getShow_all()
    {
        $url = BINANCEAPI . "/openOrders";
        $params = [
            "symbol" => "BTCUSDT"
        ];
        $response = binanceAPI($url, $params);

        if (isset($response->code)) {
            return $this->respond(error_msg(400, "binance", $response->code, $response->msg), 400);
        }

        return $this->respond(error_msg(200, "binance", null, $response), 200);
    }

    public function getTruncate_signals()
    {
        $result = $this->signal->truncate();

        if ($result->code !== 201) {
            return $this->respond(error_msg(400, "binance", $result->code, $result->message), 400);
        }

        return $this->respond(error_msg(201, "binance", null, $result->message), 201);
    }
}
