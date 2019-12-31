<?php

use \Firebase\JWT\JWT;

defined('BASEPATH') OR exit('No direct script access allowed');

// TODO: Hook 里不知道使用 REST_Controller 出错, 暂以原始方法替代
//use Restserver\Libraries\REST_Controller;
//require_once APPPATH . 'libraries/REST_Controller.php';
//require_once APPPATH . 'libraries/Format.php';

class ManageAuth
{
    private $CI;

    function __construct()
    {
        $this->CI = &get_instance();  //获取CI对象
    }

    // var_dump(uri_string()); => api/v2/sys/user/login

    //token及权限认证
    public function auth()
    {
        $uri_no_prefix = str_replace(config_item('jwt_api_prefix'), '', uri_string());  // /sys/user/login 不带 api/v2 前缀

        if (!in_array($uri_no_prefix, config_item('jwt_white_list'))) { // 不在白名单里需要校验 token
            $headers = $this->CI->input->request_headers();
            $Token = $headers['X-Token'];

            try {
                $decoded = JWT::decode($Token, config_item('jwt_key'), ['HS256']); //HS256方式，这里要和签发的时候对应
                $userId = $decoded->user_id;
                $retPerm = $this->CI->permission->HasPermit($userId, uri_string());
                if ($retPerm['code'] != 50000) {
                    set_status_header(200);
                    echo json_encode($retPerm);
                    die(); // 必须加die 否则会继续执行钩子后面的控制器方法
                }
            } catch (\Firebase\JWT\ExpiredException $e) {  // access_token过期
                $message = [
                    "code" => 50014,
                    "message" => $e->getMessage()
                ];
                set_status_header(401);
                echo json_encode($message);
                die(); // 必须加die 否则会继续执行钩子后面的控制器方法
            } catch (Exception $e) {  //其他错误
                $message = [
                    "code" => 50015,
                    "message" => $e->getMessage()
                ];
                set_status_header(401);
                echo json_encode($message);
                die(); // 必须加die 否则会继续执行钩子后面的控制器方法
            }
        }
    }
}