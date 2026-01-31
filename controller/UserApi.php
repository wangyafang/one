<?php

declare (strict_types=1);

namespace app\controller;
use app\middleware\UserAuth;

use app\model\User as UserModel;
use app\model\RechargeRecord;

use okv5\Config;
use okv5\FundingApi;
use epay\lib\EpayCore;

use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use think\App;
use think\db\exception\DbException;
use think\facade\Cookie;
use think\facade\Session;
use think\facade\Validate;
use think\Request;
use think\response\Json;
use think\response\Redirect;
use Yurun\Util\HttpRequest;
use yzh52521\filesystem\facade\Filesystem;

class UserApi
{
    /**
     * Request实例
     * @var Request
     */
    protected Request $request;

    /**
     * 应用实例
     * @var App
     */
    protected App $app;

    protected mixed $user_info;
    protected string|array|bool $config = [];
    protected array $middleware = [UserAuth::class];

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $this->app->request;
        // 将当前登录用户信息写入至私有属性
        $this->user_info = $this->request->session('user');
        $this->config = getConfig();
    }


    public function key(string $action): ?Json
    {
        $post_info = $this->request->post();
        $user_info = UserModel::find($this->user_info['id']);
        switch ($action) {
            case 'create':
                $user_info->secret_key = randomkeys(32);
                $user_info->save();
                return show(200, 'success', '创建成功');
            case 'reset':
                $user_info->secret_key = randomkeys(32);
                $user_info->save();
                return show(200, 'success', '重置成功');
            case 'api_auth_ip':
                if(!empty($post_info['auth_ip'])){
                    $ips = explode("\n", $post_info['auth_ip']); // 通过 "|" 分割多个 IP 地址
                    $hasInvalidIp = false;
                    foreach ($ips as $ip) {
                        $ip = trim($ip);
                        // 验证 IP 地址的有效性
                        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                            $hasInvalidIp = true;
                            return show(500, 'error', $ip.'不是有效的 IP 地址');
                        }
                    }
                    $user_info->api_auth_ip = $post_info['auth_ip'];
                }else{
                    $user_info->api_auth_ip = '*';
                }
                $user_info->save();
                return show(200, 'success', '保存成功');
            default:
                return show(500, 'error', '请求失败，请联系平台客服');
        }
    }


    public function balance_recharge(string $action): ?Json
    {
        $post_info = $this->request->post();
        switch ($action) {
            case 'submit':
                if(empty($post_info['recharge_amount'])){
                    return show(500, 'error', '请输入充值金额');
                }
                if (!preg_match('/^\d+(\.\d{1,2})?$/', $post_info['recharge_amount'])) {
                    return show(500, 'error', '输入的金额不正确，请精确到分。');
                }
                if(empty($post_info['payment_amount'])){
                    return show(500, 'error', '支付金额有误');
                }
                $order_number = date('Ymd') . randomkeys(5, 'number');

                $create_time = date('Y-m-d H:i:s', strtotime('-16 minutes', strtotime(date('Y-m-d H:i:s'))));// 获取16分钟内的数据
                $RechargeRecord = RechargeRecord::where('status', '=', 0)->where('create_time', '>=', $create_time)->select();
                $payment_amount = number_format(floatval($post_info['recharge_amount']) / 6.85, 3);
                foreach ($RechargeRecord as $record) {
                    $paymentAmount = floatval($record['payment_amount']);
                    if ($paymentAmount == $payment_amount) {
                        $payment_amount += 0.001;
                        $payment_amount = number_format($payment_amount, 3);
                    }
                }

                RechargeRecord::create([
                    'uid'  => $this->user_info['id'],
                    'recharge_amount' => $post_info['recharge_amount'],
                    'payment_amount' => $payment_amount,
                    'trade_no' => $order_number
                ]);

                $config=[
                    "apiKey"=> $this->config['okx_apiKey'],
                    "apiSecret"=> $this->config['okx_apiSecret'],
                    "passphrase"=> $this->config['okx_passphrase'],
                ];

                // 获取欧意收款信息
                $obj = new FundingApi($config);
                $res = $obj->getDepositAddress('USDT');

                $data = json_decode($res, true);


                foreach ($data['data'] as $item) {
                    if ($item['chain'] === "USDT-TRC20") {
                        $addr = $item['addr'];
                    }
                }

                return show(200, 'success', '创建成功',  [
                    'trade_no' => $order_number,
                    'addr' => $addr,
                    'amount' => $payment_amount,
                ]);

            case 'amount':
                $amount = number_format(floatval($post_info['recharge_amount']) / 6.85, 3);
                $create_time = date('Y-m-d H:i:s', strtotime('-16 minutes', strtotime(date('Y-m-d H:i:s'))));// 获取16分钟内的数据
                $RechargeRecord = RechargeRecord::where('status', '=', 0)->where('create_time', '>=', $create_time)->select();
                foreach ($RechargeRecord as $record) {
                    $paymentAmount = floatval($record['payment_amount']);
                    if ($paymentAmount == $amount) {
                        $amount += 0.001;
                        $amount = number_format($amount, 3);
                    }
                }
                return show(200, 'success', '获取成功', $amount);
            case 'getStatus':
                $RechargeRecord_info = RechargeRecord::where('trade_no', '=', $post_info['trade_no'])->find();
                if($RechargeRecord_info['status'] === 1){
                    return show(200, 'success', '支付成功！');
                }
                return show(500, 'error', '未支付或未到账');
            default:
                return show(500, 'error', '请求失败，请联系平台客服');
        }
    }


    public function balance_recharge1(string $action): ?Json
    {
        $epay_pid=$this->config['epay_apiKey'];
        $epay_key=$this->config['epay_apiSecret'];
        $epay_apiurl=$this->config['epay_passphrase'];
        //$epay_apiurl="http://116.196.67.18:88/";
        $post_info = $this->request->post();
        switch ($action) {
            case 'submit':
                if(empty($post_info['recharge_amount'])){
                    return show(500, 'error', '请输入充值金额');
                }
                if (!preg_match('/^\d+(\.\d{1,2})?$/', $post_info['recharge_amount'])) {
                    return show(500, 'error', '输入的金额不正确，请精确到分。');
                }
                if(empty($post_info['payment_amount'])){
                    return show(500, 'error', '支付金额有误');
                }
                $order_number = date('Ymd') . randomkeys(5, 'number');

                $create_time = date('Y-m-d H:i:s', strtotime('-16 minutes', strtotime(date('Y-m-d H:i:s'))));// 获取16分钟内的数据
                $RechargeRecord = RechargeRecord::where('status', '=', 0)->where('create_time', '>=', $create_time)->select();
                $payment_amount = number_format(floatval($post_info['recharge_amount']) / 6.85, 3);
                foreach ($RechargeRecord as $record) {
                    $paymentAmount = floatval($record['payment_amount']);
                    if ($paymentAmount == $payment_amount) {
                        $payment_amount += 0.001;
                        $payment_amount = number_format($payment_amount, 3);
                    }
                }

                RechargeRecord::create([
                    'uid'  => $this->user_info['id'],
                    'recharge_amount' => $post_info['recharge_amount'],
                    'payment_amount' => $payment_amount,
                    'trade_no' => $order_number
                ]);

                $config=[
                    "pid"=> $epay_pid,
                    "key"=> $epay_key,
                    "apiurl"=> $epay_apiurl,
                ];

                // 获取易支付收款信息
                $request = request();
                $domain = $request->host();
                $protocol = $request->isSsl() ? 'https://' : 'http://';
                $obj = new EpayCore($config);
                $notify_url = $protocol.$domain."/notify_url.html";
                $return_url = $protocol.$domain.url('user/balance_recharge');
                $parameter = array(
                	"pid" => $config['pid'],
                	"type" => $post_info['paytype'],
                	"notify_url" => $notify_url,
                	"return_url" => $return_url,
                	"out_trade_no" => $order_number,
                	"name" => "充值(UID:".$this->user_info['id'].")",
                	"money"	=> $post_info['recharge_amount'],
                );
                $paylink = $obj->getPayLink($parameter);

                return show(200, 'success', '创建成功',  [
                    'trade_no' => $order_number,
                    'paylink' => $paylink,
                ]);

            case 'amount':
                $amount = number_format(floatval($post_info['recharge_amount']) / 6.85, 3);
                $create_time = date('Y-m-d H:i:s', strtotime('-16 minutes', strtotime(date('Y-m-d H:i:s'))));// 获取16分钟内的数据
                $RechargeRecord = RechargeRecord::where('status', '=', 0)->where('create_time', '>=', $create_time)->select();
                foreach ($RechargeRecord as $record) {
                    $paymentAmount = floatval($record['payment_amount']);
                    if ($paymentAmount == $amount) {
                        $amount += 0.001;
                        $amount = number_format($amount, 3);
                    }
                }
                return show(200, 'success', '获取成功', $amount);
            case 'getStatus':
                $RechargeRecord_info = RechargeRecord::where('trade_no', '=', $post_info['trade_no'])->find();
                if($RechargeRecord_info['status'] === 1){
                    return show(200, 'success', '支付成功！');
                }
                return show(500, 'error', '未支付或未到账');
            default:
                return show(500, 'error', '请求失败，请联系平台客服');
        }
    }


    public function account(string $action): ?Json
    {
        $post_info = $this->request->post();
        $user_info = UserModel::find($this->user_info['id']);
        switch ($action) {
            case 'submit':
                $salt = randomkeys(4);

                $user_info->phone = $post_info['phone'];
                $user_info->email = $post_info['email'];
                if(!empty($post_info['password'])){
                    $user_info->password = password_hash(($post_info['password'] . $salt), PASSWORD_BCRYPT);
                    $user_info->salt = $salt;
                }
                $user_info->save();
                return show(200, 'success', '保存成功');
            default:
                return show(500, 'error', '请求失败，请联系平台客服');
        }
    }


    public function online_query(): ?Json
    {
        $post_info = $this->request->post();
        $user_info = UserModel::find($this->user_info['id']);
        if(empty($post_info['channel'])){
            return show(500, 'error', '查询渠道不可为空');
        }
        if(empty($post_info['mobile'])){
            return show(500, 'error', '查询号码不可为空');
        }
        if (($user_info['balance'] * 100) < ($user_info['channel_price'][$post_info['channel']] * 100)) {
            return show(500, 'error', '账户余额不足，请先充值！');
        }

        $http = HttpRequest::newSession();
        $params = [
            'id'        => $user_info['access_id'],
            'channel'   => $post_info['channel'],
            'mobile'    => $post_info['mobile'],
            'electricity_are'    => $post_info['electricity_are'],
            'api_type'  => 'pt',
            'key' => $user_info['secret_key'],
        ];

        $response = $http->post((string)url('/api/gateway')->domain(true), $params);
//        echo '<pre>';print_r($response->httpCode());echo '</pre>';exit();
//        $response = $http->post('http://222.217.95.171:88/api/gateway', $params);
        //return show(500, 'error', '查询成功');
        if ($response->httpCode() !== 200) {
            return show(500, 'error', '接口请求失败');
        }
        $result = $response->json(true);
//        echo '<pre>';print_r($response);echo '</pre>';exit();
        $data = $result['data'] ?? $result;
        return show(200, 'success', '查询成功', $data);




    }


    // 用户退出登录
    public function logout(): Redirect
    {
        Session::delete('user');
        return redirect((string)url('/login'));
    }
}
?>
