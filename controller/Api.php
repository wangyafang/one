<?php
declare (strict_types=1);
namespace app\controller;
use app\BaseController;
use app\middleware\ApiAuth;
use app\model\QueryRecord;
use app\model\User;
use think\db\exception\DbException;
use Yurun\Util\HttpRequest;
use think\response\Json;
use think\Request;
use think\Response;
$API_URL = '';
$sk = '';
class Api extends BaseController 
{
	protected array $middleware = [ApiAuth::class];
	public function miss(): Json 
	{
		return show(250, 'error', '您可真是个小机灵鬼！');
	}
	public function gateway(): Json 
	{
		$sk='';
		$aid=getConfig('Access_ID');
		$API_KEY = getConfig('Secret_Key');
		$post_info = $this->request->post();
		$user_info = User::where('id', '=', $this->request->UserId)->find();
		if($post_info['channel'] === 'user_balance')
		{
			return show(200, 'success', '查询成功', $user_info['balance']);
		}
		if(empty($post_info['mobile']))
		{
			return show(500, 'error', '请输入查询号码');
		}
		if(empty($post_info['channel']))
		{
			return show(500, 'error', '请输入渠道编码');
		}
		if (($user_info['balance'] * 100) < ($user_info['channel_price'][$post_info['channel']] * 100)) 
		{
			return show(500, 'error', '账户余额不足，请先充值！');
		}
		$token = 'XxxreN2TDC1HzbTfzZuP9fFxAZtKoHSZ';
		switch ($post_info['channel'])
		{
			case 'unicom_balance'://联通余额查询
$API_URL='https://teamuuid.top/api/gateway';
				$get_post_data = array( 'channelCode' => 'CUCC', 'query' => $post_info['mobile'], 'token' => $token, 'extend' => '' );
				$cmboile=$post_info['mobile'];
			break;
			case 'telecom_balance'://电信余额查询
$API_URL='http://teamuuid.top/api/gateway';
				$get_post_data = array( 'channelCode' => 'CTCC', 'query' => $post_info['mobile'], 'token' => $token, 'extend' => '' );
				$cmboile=$post_info['mobile'];
			break;
			case 'mobile_balance'://移动余额查询
$API_URL='http://teamuuid.top/api/gateway';
				$get_post_data = array( 'channelCode' => 'CMCCBILL', 'query' => $post_info['mobile'], 'token' => $token, 'extend' => '' );
				$cmboile=$post_info['mobile'];
			break;
			case 'electricity_balance'://电费户号查询
$API_URL='http://teamuuid.top/api/gateway';
				$get_post_data = array( 'channelCode' => 'SGCC2', 'query' => $post_info['mobile'], 'token' => $token, 'extend' => '' );
				$cmboile=$post_info['mobile'];
			break;
			case 'electricity_balance_query'://电费户号查询
$API_URL='http://teamuuid.top/api/gateway';
				$get_post_data = array( 'channelCode' => 'SGCC', 'query' => $post_info['mobile'], 'token' => $token, 'extend' => $post_info['electricity_are'] );
				$cmboile=$post_info['mobile'];
			break;
			case 'detection_mnp'://携号转网
$API_URL='http://teamuuid.top/api/gateway';
				$get_post_data = array( 'channelCode' => 'ISP', 'query' => $post_info['mobile'], 'token' => $token, 'extend' => '' );
				$cmboile=$post_info['mobile'];
			break;
			default: return show(404, 'error', '请求失败');
		}
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $API_URL);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $get_post_data);
		curl_setopt($curl, CURLOPT_REFERER, $API_URL);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, 120);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array( 'Content-Type: multipart/form-data', 'Cache-Control: no-cache', 'Connection:keep-alive' ));
		$response = curl_exec($curl);
		curl_close($curl);
		$result = json_decode($response);
		$data = [ 'id' => $user_info['access_id'], 'key' => $user_info['secret_key'], 'channel' => $post_info['channel'], 'number' => $cmboile, ];
		$order_number = date('Ymd') . randomkeys(5, 'number');
//		echo '<pre>';print_r(1111);echo '</pre>';
//		echo '<pre>';print_r($result->data);echo '</pre>';exit;

		if (isset($result->code) && ((string)$result->code === '200' || (string)$result->code === '1'))
		{
//			switch ($post_info['channel'])
//			{
//				case 'unicom_balance'://联通余额查询
//					$return_data = $result->data;
//					$API_URL='https://teamuuid.top/api/gateway';
//					$get_post_data = array( 'channelCode' => 'CUCC', 'query' => $post_info['mobile'], 'token' => $token, 'extend' => '' );
//					$cmboile=$post_info['mobile'];
//					break;
//				case 'telecom_balance'://电信余额查询
//					$return_data = $result->data;
//					$API_URL='http://teamuuid.top/api/gateway';
//					$get_post_data = array( 'channelCode' => 'CTCC', 'query' => $post_info['mobile'], 'token' => $token, 'extend' => '' );
//					$cmboile=$post_info['mobile'];
//					break;
//				case 'mobile_balance'://移动余额查询
//					$return_data = $result->data->curFee;
//					$API_URL='http://teamuuid.top/api/gateway';
//					$get_post_data = array( 'channelCode' => 'CMCCBILL', 'query' => $post_info['mobile'], 'token' => $token, 'extend' => '' );
//					$cmboile=$post_info['mobile'];
//					break;
//				case 'electricity_balance'://电费户号查询
//					$return_data = $result->data;
//					$get_post_data = array( 'channelCode' => 'SGCC2', 'query' => $post_info['mobile'], 'token' => $token, 'extend' => '' );
//					$cmboile=$post_info['mobile'];
//					break;
//				case 'electricity_balance_query'://电费户号余额
//					$API_URL='http://teamuuid.top/api/gateway';
//					$get_post_data = array( 'channelCode' => 'SGCC', 'query' => $post_info['mobile'], 'token' => $token, 'extend' => $post_info['electricity_are'] );
//					$cmboile=$post_info['mobile'];
//					break;
//				case 'detection_mnp'://携号转网
//					$return_data = $result->data;
//					$API_URL='http://teamuuid.top/api/gateway';
//					$get_post_data = array( 'channelCode' => 'ISP', 'query' => $post_info['mobile'], 'token' => $token, 'extend' => '' );
//					$cmboile=$post_info['mobile'];
//					break;
//				default: return show(404, 'error', '请求失败');
//			}
			$money = $user_info['channel_price'][$post_info['channel']];
			$user_info->balance -= $money;
			$user_info->save();
			if($post_info['channel']=='electricity_balance_query')
			{
				if($result->data->balance==null || $result->data->balance=='null')
				{
					$result->data->balance=0;
				}
				if($result->data->owedBalance==null || $result->data->owedBalance=='null')
				{
					$result->data->owedBalance=0;
				}
				if($result->data->availableBalance==null || $result->data->availableBalance=='null')
				{
					$result->data->availableBalance=0;
				}
				$result->data->balance= $result->data->balance;
				$result->data->owedBalance= $result->data->owedBalance;
				$result->data->availableBalance= $result->data->availableBalance;
			}
			if($post_info['channel']=='electricity_balance')
			{
				if($result->data->minPayBalance==null || $result->data->minPayBalance=='null')
				{
					$result->data->minPayBalance=0;
				}
				$result->data->minPayBalance= $result->data->minPayBalance;
			}
			BalanceRecord($user_info['id'], 1, $money, $order_number, '查询扣费');
			QueryRecord::create([ 'uid' => $user_info['id'], 'mobile' => $post_info['mobile'], 'channel' => $post_info['channel'], 'extend' => $result->data, 'ip' => $_SERVER['REMOTE_ADDR']??null, 'trade_no' => $order_number, 'money' => $money, ]);
//			echo '<pre>';print_r($result->data);echo '</pre>';exit;
			return show(200, 'success', '查询成功' ,$result->data);
		}
		return show(400, 'error', '查询成功' ,$result);
	}
}
?>
