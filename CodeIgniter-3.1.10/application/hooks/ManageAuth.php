<?php
defined('BASEPATH') or exit('No direct script access allowed');

use \Firebase\JWT\JWT;
use chriskacerguis\RestServer\RestController;
use Nette\Utils\Arrays;
use Nette\Utils\Strings;

class ManageAuth
{
    private $CI;

    function __construct()
    {
        $this->CI = &get_instance();  //获取CI对象
    }

    //token及权限认证
    public function auth()
    {
        // var_dump(uri_string()); // => api/v2/sys/user/login , api/example/users

        $in_whiteList = Arrays::some(config_item('jwt_white_list'), function ($value) : bool {
            // 白名单里的某一项 eg. '/sys/user/testapi' 包含于 uri_string() => 'api/v2/sys/user/testapi' 中则立即返回true, 所有项都不包含于才返回false
            return Strings::contains(uri_string(), $value);
        });

        if (!$in_whiteList) { // 不在白名单里需要校验 token
            $headers = $this->CI->input->request_headers();
            $Token = $headers['X-Token'];

            try {
                $decoded = JWT::decode($Token, config_item('jwt_key'), ['HS256']); //HS256方式，这里要和签发的时候对应
                $userId = $decoded->user_id;
                $retPerm = $this->CI->permission->HasPermit($userId, uri_string());
                if ($retPerm['code'] != 50000) {
                    $this->CI->response($retPerm, RestController::HTTP_OK);
                }
            } catch (\Firebase\JWT\ExpiredException $e) {  // access_token过期
                $message = [
                    "code" => 50014,
                    "message" => $e->getMessage()
                ];
                $this->CI->response($message, RestController::HTTP_UNAUTHORIZED);
            } catch (Exception $e) {  //其他错误
                $message = [
                    "code" => 50015,
                    "message" => $e->getMessage()
                ];
                $this->CI->response($message, RestController::HTTP_UNAUTHORIZED);
            }
        }
    } // auth() end
}