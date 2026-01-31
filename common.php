<?php

// 应用公共文件
use app\model\AdminLog;
use app\model\BalanceRecord;
use app\model\Cache as CacheModel;
use app\model\Config as ConfigModel;

use app\model\User;
use app\model\UserLog;
use app\model\QueryRecord;

use ip2region\Ip2Region;
use think\db\exception\DbException;
use think\facade\Request;
use think\facade\Session;
use think\response\Json;
use Yurun\Util\HttpRequest;

/**
 * Json接口统一输出
 * @param int $code 状态码
 * @param string $status 状态信息: "success" or "error"
 * @param string $message 返回内容
 * @param mixed $data 返回的数据
 * @param int $httpStatus HTTP状态码
 * @return Json
 */
function show(int $code = 200, string $status = 'success', string $message = "嗯", Mixed $data = null, int $httpStatus = 200): Json
{
    return json([
        "code"    => $code,
        "status"  => $status,
        "message" => $message,
        "data"    => $data,
    ], $httpStatus);
}

/**
 * API返回信息统一输出
 * @param int $code 状态码
 * @param string $status 状态信息: "success" or "error"
 * @param string $message 返回内容
 * @param mixed $data 返回的数据
 * @return array
 */
function apishow(int $code = 200, string $status = 'success', string $message = "嗯", Mixed $data = null): array
{
    return [
        "code"    => $code,
        "status"  => $status,
        "message" => $message,
        "data"    => $data,
    ];
}

/**
 * 字符过滤器（防XSS）
 * @param mixed $string 内容
 * @return mixed
 */
function daddslashes(mixed $string): mixed
{
    if (is_array($string)) {
        foreach ($string as $key => $val) {
            $string[$key] = daddslashes($val);
        }
    } else {
        if (empty($string)) {
            return '';
        }
        $string = addslashes($string);
    }
    return $string;
}

/**
 * 向数据库插入操作日志
 * @param string $controller 操作区域：admin & user
 * @param string $msg 操作内容
 * @param mixed $data 详细数据
 * @param int|null $uid 操作者ID（可选）
 */
function inlog(string $controller, string $msg, mixed $data = null, int|null $uid = null): void
{
    $model = ($controller === 'admin') ? new AdminLog() : new UserLog();
    $operation_id = $uid ?? (($controller === 'admin') ? Session::get('admin.id') : Session::get('user.id'));
    $model->save([
        'uid'  => (int)$operation_id,
        'account'  => User::where('id', (int)$operation_id)->value('account'),
        'msg'  => $msg,
        'ip'   => Request::ip(),
        'data' => $data
    ]);
}

/**
 * 随机生成字符
 * @param int $length 字符长度
 * @param string $method 方法：text or number
 * @return string
 */
function randomkeys(int $length, string $method = 'text'): string
{
    $key = '';
    if ($method === 'number') {
        $pattern = '1234567890';
        $max = 9;
    } else {
        $pattern = '1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLOMNOPQRSTUVWXYZ';
        $max = 62;
    }
    for ($i = 0; $i < $length; $i++) {
        // 生成php随机数
        $key .= $pattern[random_int(0, $max)];
    }
    return $key;
}

/**
 * 随机生成汉字
 * @param int $num 字符长度
 * @return string
 */
function randomchinese(int $num): string
{
    $b = '';
    for ($i = 0; $i < $num; $i++) {
        // 使用chr()函数拼接双字节汉字，前一个chr()为高位字节，后一个为低位字节
        $a = chr(random_int(0xB0, 0xD0)) . chr(random_int(0xA1, 0xF0));
        // 转码
        $b .= iconv('GB2312', 'UTF-8', $a);
    }
    return $b;
}

/**
 * 替换字符串的中间部分
 * @param string $str 输入字符串
 * @param string $replaceChar 要替换的单个字符
 * @param int $leftLen 左边保留正常显示的长度
 * @param int $rightLen 右面保留正常显示的长度
 * @param bool $notEnoughReplace 计算后要替换的字符串长度不足时，输入的字符串是否进行整体替换
 * @return string
 */
function strReplaceMiddle(string $str, string $replaceChar = '*', int $leftLen = 3, int $rightLen = 3, bool $notEnoughReplace = true): string
{
    $len = mb_strlen($str);
    $replaceLen = $len - $leftLen - $rightLen;

    if ($replaceLen > 0) {
        $replaceStr = str_repeat($replaceChar, $replaceLen);
    } else {
        // 计算后要替换的字符串长度不足时，$replaceLen = $len - $frontLen - $backLen;
        $replaceStr = str_repeat($replaceChar, $len);
        return $notEnoughReplace ? $replaceStr : $str;
    }
    return mb_substr($str, 0, $leftLen) . $replaceStr . mb_substr($str, $leftLen + $replaceLen);
}

/**
 * 通过IP地址获取地理位置
 * @param string $ip IP地址
 * @return string 地理位置
 */
function getIpCity(string $ip = '127.0.0.1'): string
{
    try {
        // 无视IPv6地址
        if (Request::isValidIP($ip, 'ipv6')) {
            return '无法识别IPv6地理位置';
        }
        // 取得 国家|区域|省份|城市|ISP
        $citydata = Ip2Region::newWithVectorIndex()->search($ip);
        // 将地址信息根据|分割为数组并剔除空值与重复值
        $citydata = array_unique(array_filter(explode('|', $citydata)));
        $hello = explode(' ', implode(' ', $citydata));

        return $hello[1] . ' - ' . $hello[2];
    } catch (Exception $e) {
        return $e->getMessage();
    }
}
/**
 * 获取系统配置信息
 * @param null|string $name 字段名（英文），为空则以数组的形式返回全部
 * @return array|string|bool 内容
 */
function getConfig(null|string $name = null): array|string|bool
{
    // 如果没有向函数传入字段名，则读取缓存并直接以数组形式输出全部配置信息
    if (!empty($name)) {
        // 以字段名查找并输出对应的值，如不存在对应的值，则直接输出字符串空值
        return ConfigModel::where('k', '=', $name)->value('v', '');
    }
    // 从数据库缓存表内读取配置缓存，并通过tp框架自带缓存引擎缓存结果10秒
    $configCache = CacheModel::where('k', 'config')->value('v');
    // 如果缓存为空
    if (empty($configCache)) {
        // 从站点配置表内读取全部配置信息
        $result = ConfigModel::column('k,v');
        // 如果读取配置信息失败，返回false
        if (!$result || !is_array($result)) {
            return false;
        }
        $cache = [];
        foreach ($result as $row) {
            $cache[$row['k']] = $row['v'];
        }
        // 对站点配置信息进行序列化存储
        $results = serialize($cache);
        CacheModel::create([
            'k'      => 'config',
            'v'      => $results,
            'expire' => 0
        ], ['k', 'v', 'expire'], true);
        // 重新读取一遍缓存信息，并通过tp框架自带缓存引擎缓存结果10秒
        $configCache = CacheModel::where('k', 'config')->cache(10)->value('v');
    }
    // 对系统缓存站点配置信息进行反序列化
    return unserialize($configCache);
}



/**
 * 根据手机号获取运营商以及地理归属信息（国内）
 * @param int|string $phone 手机号
 * @return array|bool
 */
function phone_info(int|string $phone): array|bool
{
    // API接口地址
    // $api = "https://www.weisms.com/api/detection";
    // // 參数数组
    // $data = array (
    //     'id' => '3138034364', // Access ID
    //     'key' => 'HKOH30gON7CuE0R2FI50L19ZynRt2J32', // Secret Key
    //     'phone' => '18888888888' // 本次要检测的号码
    // );
    
    // $ch = curl_init ();
    // // print_r($ch);
    // curl_setopt ( $ch, CURLOPT_URL, $api);
    // curl_setopt ( $ch, CURLOPT_POST, 1 );
    // curl_setopt ( $ch, CURLOPT_HEADER, 0 );
    // curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
    // curl_setopt ( $ch, CURLOPT_POSTFIELDS, $data );
    // $return = curl_exec ( $ch );
    // curl_close ( $ch );
    // print_r($return);

    // 初始化请求
    $http = HttpRequest::newSession();
    $params = [
        'id'        => '3138034364',
        'key'       => 'HKOH30gON7CuE0R2FI50L19ZynRt2J32',
        'phone'     => $phone,
    ];
    // 提交POST请求
    $response = $http->post('https://www.weisms.com/api/phone', $params);
    // 如果请求返回的Http状态码不是200,则报错
    if ($response->httpCode() !== 200) {
        return false;
    }
    // 获取返回值并将Json内容格式化
    $result = $response->json(true);
    // 如果Sms基类返回的状态为success则返回结果
    return $result['data'];

}


/**
 * 记录数据到BalanceRecord
 * @param int $uid 用户ID
 * @param int $action 0增加/1减少
 * @param int $money 交易金额
 * @param int $trade_no 交易单号
 * @param int $remarks 备注内容
 * @return string
 */
function BalanceRecord(string|int $uid, string|int $action, string|int|float $money, string|int|null $trade_no = null, string|int|null $remarks = null): string
{
    $user = User::find($uid);

    if ($action === 1) {
        $oldmoney = $user['balance'] + $money;
    } else {
        $oldmoney = $user['balance'] - $money;
    }
    BalanceRecord::create([
        'uid'      => $uid,
        'action'   => $action,
        'money'    => $money,
        'oldmoney' => $oldmoney,
        'newmoney' => $user['balance'],
        'trade_no' => $trade_no,
        'remarks' => $remarks,
    ]);
    return false;
}


/**
 * 记录数据到query_data
 * @param int $uid 用户id
 * @param int $channel 通道名称
 * @param int $time 日期时间
 * @return string
 */
function query_data(string|int $uid, string $channel, string $time): string
{
    if(!empty($uid)){
        $par[] = ['uid', '=', $uid];
    }
    $par[] = ['channel', '=', $channel];

    if($channel === 'unicom_record'){
        if($time === 'jr'){
            $query_data = QueryRecord::where($par)->whereDay('create_time', date('Y-m-d'))->count();
        }
        if($time === 'zr'){
            $query_data = QueryRecord::where($par)->whereDay('create_time', date('Y-m-d',strtotime(-1 . 'day')))->count();
        }
        if($time === 'qb'){
            $query_data = QueryRecord::where($par)->count();
        }
    }
    if($channel === 'unicom_balance'){
        if($time === 'jr'){
            $query_data = QueryRecord::where($par)->whereDay('create_time', date('Y-m-d'))->count();
        }
        if($time === 'zr'){
            $query_data = QueryRecord::where($par)->whereDay('create_time', date('Y-m-d',strtotime(-1 . 'day')))->count();
        }
        if($time === 'qb'){
            $query_data = QueryRecord::where($par)->count();
        }
    }
    if($channel === 'telecom_balance'){
        if($time === 'jr'){
            $query_data = QueryRecord::where($par)->whereDay('create_time', date('Y-m-d'))->count();
        }
        if($time === 'zr'){
            $query_data = QueryRecord::where($par)->whereDay('create_time', date('Y-m-d',strtotime(-1 . 'day')))->count();
        }
        if($time === 'qb'){
            $query_data = QueryRecord::where($par)->count();
        }
    }
    if($channel === 'mobile_balance'){
        if($time === 'jr'){
            $query_data = QueryRecord::where($par)->whereDay('create_time', date('Y-m-d'))->count();
        }
        if($time === 'zr'){
            $query_data = QueryRecord::where($par)->whereDay('create_time', date('Y-m-d',strtotime(-1 . 'day')))->count();
        }
        if($time === 'qb'){
            $query_data = QueryRecord::where($par)->count();
        }
    }
    if($channel === 'detection_mnp'){
        if($time === 'jr'){
            $query_data = QueryRecord::where($par)->whereDay('create_time', date('Y-m-d'))->count();
        }
        if($time === 'zr'){
            $query_data = QueryRecord::where($par)->whereDay('create_time', date('Y-m-d',strtotime(-1 . 'day')))->count();
        }
        if($time === 'qb'){
            $query_data = QueryRecord::where($par)->count();
        }
    }
    if($channel === 'electricity_balance'){
        if($time === 'jr'){
            $query_data = QueryRecord::where($par)->whereDay('create_time', date('Y-m-d'))->count();
        }
        if($time === 'zr'){
            $query_data = QueryRecord::where($par)->whereDay('create_time', date('Y-m-d',strtotime(-1 . 'day')))->count();
        }
        if($time === 'qb'){
            $query_data = QueryRecord::where($par)->count();
        }
    }
     if($channel === 'electricity_balance_query'){
        if($time === 'jr'){
            $query_data = QueryRecord::where($par)->whereDay('create_time', date('Y-m-d'))->count();
        }
        if($time === 'zr'){
            $query_data = QueryRecord::where($par)->whereDay('create_time', date('Y-m-d',strtotime(-1 . 'day')))->count();
        }
        if($time === 'qb'){
            $query_data = QueryRecord::where($par)->count();
        }
    }
    return $query_data;
}
