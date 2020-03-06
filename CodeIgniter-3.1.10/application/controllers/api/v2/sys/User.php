<?php
defined('BASEPATH') or exit('No direct script access allowed');

use \Firebase\JWT\JWT;
use chriskacerguis\RestServer\RestController;

class User extends RestController
{

    function __construct()
    {
        parent::__construct();
        $this->load->model('Base_model');
        $this->load->model('User_model');
        // $this->config->load('config', true);
    }

    public function index_get()
    {
        $this->load->view('login_view');
    }

    //签发Token
    public function issue_get()
    {
        var_dump(JWT::$leeway);
        $key = '344'; //key
        $time = time(); //当前时间
        $payload = [
            'iss' => 'http://www.helloweba.net', //签发者 可选
            'aud' => 'http://www.helloweba.net', //接收该JWT的一方，可选
            'iat' => $time, //签发时间
            'nbf' => $time, //(Not Before)：某个时间点后才能访问，比如设置time+30，表示当前时间30秒后才能使用
            'exp' => $time, //过期时间,这里设置2个小时
            'data' => [ //自定义信息，不要定义敏感信息
                'userid' => 2,
                'username' => '李小龙'
            ]
        ];
        echo JWT::encode($payload, $key); //输出Token
    }

    public function verification_get()
    {
        $key = '344'; //key要和签发的时候一样

        //签发的Token header.payload.signature 前两部分可以base64解密
        $jwt = "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOlwvXC93d3cuaGVsbG93ZWJhLm5ldCIsImF1ZCI6Imh0dHA6XC9cL3d3dy5oZWxsb3dlYmEubmV0IiwiaWF0IjoxNTc3NjY4MDk0LCJuYmYiOjE1Nzc2NjgwOTQsImV4cCI6MTU3NzY2ODA5NCwiZGF0YSI6eyJ1c2VyaWQiOjIsInVzZXJuYW1lIjoiXHU2NzRlXHU1YzBmXHU5Zjk5In19.EM9G8aW7DCpRYW7L0vjTgTt7UevwIyocVaouq0rdn0I";
//        $arr = explode('.', $jwt);
//        var_dump($arr);
//        var_dump(base64_decode($arr[1]));
//        $object = json_decode(base64_decode($arr[1]));
//        var_dump($object->data);
//        // var_dump($object->data->username);
//        return;
        try {
            $decoded = JWT::decode($jwt, $key, ['HS256']); //HS256方式，这里要和签发的时候对应
            $arr = (array)$decoded;
            print_r($arr);
        } catch (\Firebase\JWT\SignatureInvalidException $e) {  //签名不正确
            echo $e->getMessage();
        } catch (\Firebase\JWT\BeforeValidException $e) {  // 签名在某个时间点之后才能用
            echo $e->getMessage();
        } catch (\Firebase\JWT\ExpiredException $e) {  // token过期
            echo $e->getMessage();
        } catch (Exception $e) {  //其他错误
            echo $e->getMessage();
        }
        //Firebase定义了多个 throw new，我们可以捕获多个catch来定义问题，catch加入自己的业务，比如token过期可以用当前Token刷新一个新Token
    }

    public function testapi_get()
    {
        phpinfo();
        echo "test api ok...";

        echo APPPATH . "\n";
        echo SELF . "\n";
        echo BASEPATH . "\n";
        echo FCPATH . "\n";
        echo SYSDIR . "\n";
        var_dump($this->config->item('rest_language'));
        var_dump($this->config->item('language'));

        var_dump($this->config);

//        $message = [
//            "code" => 20000,
//            "data" => [
//                "__FUNCTION__" =>  __FUNCTION__,
//                "__CLASS__" => __CLASS__,
//                "uri" => $this->uri
//            ],
//
//        ];
//        "data": {
//            "__FUNCTION__": "router_get",
//            "__CLASS__": "User",
//            "uri": {
//                    "keyval": [],
//              "uri_string": "api/v2/user/router",
//              "segments": {
//                        "1": "api",
//                "2": "v2",
//                "3": "user",
//                "4": "router"
//              },
    }

    public function phpinfo_get()
    {
        phpinfo();
    }

    public function testdb_get()
    {
        $this->load->database();
        $query = $this->db->query("show tables");
        var_dump($query);
        var_dump($query->result());
        var_dump($query->row_array());
//         有结果表明数据库连接正常 reslut() 与 row_array 结果有时不太一样
//        一般加载到时model里面使用。
    }


    /* Helper Methods */
    /**
     * 生成 token
     * @param
     * @return string 40个字符
     */
    private function _generate_token()
    {
        do {
            // Generate a random salt
            $salt = base_convert(bin2hex($this->security->get_random_bytes(64)), 16, 36);

            // If an error occurred, then fall back to the previous method
            if ($salt === false) {
                $salt = hash('sha256', time() . mt_rand());
            }

            $new_key = substr($salt, 0, config_item('rest_key_length'));
        } while ($this->_token_exists($new_key));

        return $new_key;
    }

    /* Private Data Methods */

    private function _token_exists($token)
    {
        return $this->rest->db
            ->where('token', $token)
            ->count_all_results('sys_user_token') > 0;
    }

    private function _insert_token($token, $data)
    {
        $data['token'] = $token;

        return $this->rest->db
            ->set($data)
            ->insert('sys_user_token');
    }

    private function _update_token($token, $data)
    {
        return $this->rest->db
            ->where('token', $token)
            ->update('auth', $data);
    }

    // 查
    function view_post()
    {
        $parms = $this->post();
        //  $type = $parms['type'];
        $filters = $parms['filters'];
        $sort = $parms['sort'];
        $page = $parms['page'];
        $pageSize = $parms['pageSize'];

        $UserArr = $this->User_model->getUserList($filters, $sort, $page, $pageSize);

        $total = $this->User_model->getUserListCnt($filters);

        // 遍历该用户所属角色信息
        foreach ($UserArr as $k => $v) {
            $UserArr[$k]['role'] = [];
            $RoleArr = $this->User_model->getUserRoles($v['id']);
            foreach ($RoleArr as $kk => $vv) {
                array_push($UserArr[$k]['role'], $vv['id']);
            }
        }
        $message = [
            "code" => 20000,
            "data" => [
                'items' => $UserArr,
                'total' => intval($total)
            ]
        ];
        $this->response($message, RestController::HTTP_OK);
    }

    function getroleoptions_get()
    {
        $Token = $this->input->get_request_header('X-Token', true);
        $jwt_object = $this->permission->parseJWT($Token);

        $RoleArr = $this->User_model->getRoleOptions($jwt_object->user_id);
        // string to boolean
        foreach ($RoleArr as $k => $v) {
            $v['isDisabled'] === 'true' ? ($RoleArr[$k]['isDisabled'] = true) : ($RoleArr[$k]['isDisabled'] = false);
        }

        $message = [
            "code" => 20000,
            "data" => $RoleArr,
        ];
        $this->response($message, RestController::HTTP_OK);
    }

    // 增
    function add_post()
    {
        $parms = $this->post();  // 获取表单参数，类型为数组

        // 参数数据预处理
        $RoleArr = $parms['role'];
        unset($parms['role']);    // 剔除role数组
        // 加入新增时间
        $parms['create_time'] = time();
        $parms['password'] = md5($parms['password']);

        $user_id = $this->Base_model->_insert_key('sys_user', $parms);
        if (!$user_id) {
            $message = [
                "code" => 20000,
                "type" => 'error',
                "message" => $parms['username'] . ' - 用户新增失败'
            ];
            $this->response($message, RestController::HTTP_OK);
        }

        $failed = false;
        $failedArr = [];
        foreach ($RoleArr as $k => $v) {
            $arr = ['user_id' => $user_id, 'role_id' => $v];
            $ret = $this->Base_model->_insert_key('sys_user_role', $arr);
            if (!$ret) {
                $failed = true;
                array_push($failedArr, $arr);
            }
        }

        if ($failed) {
            $message = [
                "code" => 20000,
                "type" => 'error',
                "message" => '用户关联角色失败 ' . json_encode($failedArr)
            ];
            $this->response($message, RestController::HTTP_OK);
        }

        $message = [
            "code" => 20000,
            "type" => 'success',
            "message" => $parms['username'] . ' - 用户新增成功'
        ];
        $this->response($message, RestController::HTTP_OK);
    }

    // 改
    function edit_post()
    {
        $parms = $this->post();  // 获取表单参数，类型为数组

        // 参数检验/数据预处理
        // 超级管理员角色不允许修改
        if ($parms['id'] == 1) {
            $message = [
                "code" => 20000,
                "type" => 'error',
                "message" => $parms['username'] . ' - 超级管理员用户不允许修改'
            ];
            $this->response($message, RestController::HTTP_OK);
        }

        $id = $parms['id'];
        $RoleArr = [];
        foreach ($parms['role'] as $k => $v) {
            $RoleArr[$k] = ['user_id' => $id, 'role_id' => $v];
        }

        unset($parms['role']);  // 剔除role数组
        unset($parms['id']);    // 剔除索引id
        unset($parms['password']);    // 剔除密码

        $where = ["id" => $id];

        if (!$this->Base_model->_update_key('sys_user', $parms, $where)) {
            $message = [
                "code" => 20000,
                "type" => 'error',
                "message" => $parms['username'] . ' - 用户更新错误'
            ];
            $this->response($message, RestController::HTTP_OK);
        }

        $RoleSqlArr = $this->User_model->getRolesByUserId($id);

        $AddArr = $this->permission->array_diff_assoc2($RoleArr, $RoleSqlArr);
        // var_dump('------------只存在于前台传参 做添加操作-------------');
        // var_dump($AddArr);
        $failed = false;
        $failedArr = [];
        foreach ($AddArr as $k => $v) {
            $ret = $this->Base_model->_insert_key('sys_user_role', $v);
            if (!$ret) {
                $failed = true;
                array_push($failedArr, $v);
            }
        }
        if ($failed) {
            $message = [
                "code" => 20000,
                "type" => 'error',
                "message" => '用户关联角色失败 ' . json_encode($failedArr)
            ];
            $this->response($message, RestController::HTTP_OK);
        }

        $DelArr = $this->permission->array_diff_assoc2($RoleSqlArr, $RoleArr);
        // var_dump('------------只存在于后台数据库 删除操作-------------');
        // var_dump($DelArr);
        $failed = false;
        $failedArr = [];
        foreach ($DelArr as $k => $v) {
            $ret = $this->Base_model->_delete_key('sys_user_role', $v);
            if (!$ret) {
                $failed = true;
                array_push($failedArr, $v);
            }
        }
        if ($failed) {
            $message = [
                "code" => 20000,
                "type" => 'error',
                "message" => '用户关联角色失败 ' . json_encode($failedArr)
            ];
            $this->response($message, RestController::HTTP_OK);
        }

        $message = [
            "code" => 20000,
            "type" => 'success',
            "message" => $parms['username'] . ' - 用户更新成功'
        ];
        $this->response($message, RestController::HTTP_OK);
    }

    // 删
    function del_post()
    {
        $parms = $this->post();  // 获取表单参数，类型为数组
        // var_dump($parms['path']);

        // 参数检验/数据预处理
        // 超级管理员角色不允许删除
        if ($parms['id'] == 1) {
            $message = [
                "code" => 20000,
                "type" => 'error',
                "message" => $parms['username'] . ' - 超级管理员不允许删除'
            ];
            $this->response($message, RestController::HTTP_OK);
        }

        // 删除外键关联表 sys_user_role
        $this->Base_model->_delete_key('sys_user_role', ['user_id' => $parms['id']]);

        // 删除基础表 sys_user
        if (!$this->Base_model->_delete_key('sys_user', $parms)) {
            $message = [
                "code" => 20000,
                "type" => 'error',
                "message" => $parms['username'] . ' - 用户删除错误'
            ];
            $this->response($message, RestController::HTTP_OK);
        }

        $message = [
            "code" => 20000,
            "type" => 'success',
            "message" => $parms['username'] . ' - 用户删除成功'
        ];
        $this->response($message, RestController::HTTP_OK);

    }

    function login_post()
    {
        $username = $this->post('username'); // POST param
        $password = $this->post('password'); // POST param

        $result = $this->User_model->validate($username, md5($password));

        // 用户名密码正确 生成token 返回
        if ($result['success']) {
//            $Token = $this->_generate_token();
//            $create_time = time();
//            $expire_time = $create_time + 2 * 60 * 60;  // 2小时过期
//
//            $data = [
//                'user_id' => $result['userinfo']['id'],
//                'expire_time' => $expire_time,
//                'create_time' => $create_time
//            ];
//
//            if (!$this->_insert_token($Token, $data)) {
//                $message = [
//                    "code" => 20000,
//                    "message" => 'Token 创建失败, 请联系管理员.'
//                ];
//                $this->response($message, RestController::HTTP_OK);
//            }
            $userInfo = $result['userinfo'];

            $time = time(); //当前时间

            // 公用信息
            $payload = [
                'iat' => $time, //签发时间
                'nbf' => $time, //(Not Before)：某个时间点后才能访问，比如设置time+30，表示当前时间30秒后才能使用
                'user_id' => $userInfo['id'], //自定义信息，不要定义敏感信息, 一般只有 userId 或 username
            ];

            $access_token = $payload;
            $access_token['scopes'] = 'role_access'; //token标识，请求接口的token
            $access_token['exp'] = $time + config_item('jwt_access_token_exp'); //access_token过期时间,这里设置2个小时

            $refresh_token = $payload;
            $refresh_token['scopes'] = 'role_refresh'; //token标识，刷新access_token
            $refresh_token['exp'] = $time + config_item('jwt_refresh_token_exp'); //refresh_token,这里设置30天
            $refresh_token['count'] = 0; // 刷新TOKEN计数, 在刷新token期间多次请求刷新token则表示活跃,可以重新生成刷新token以免刷新token过期后登录

            $message = [
                "code" => 20000,
                "data" => [
                    "token" => JWT::encode($access_token, config_item('jwt_key')), //生成access_tokenToken,
                    "refresh_token" => JWT::encode($refresh_token, config_item('jwt_key')) //生成refresh_token,
                ]
            ];
            $this->response($message, RestController::HTTP_OK);
        } else {
            $message = [
                "code" => 60204,
                "message" => 'Account and password are incorrect.'
            ];
            $this->response($message, RestController::HTTP_OK);
        }
    }
    function githubauth_get()
    {
        $code = $this->get('code');
        $state = $this->get('state');

        // 需要正确配置github client ID, Secret, redirect_uri
        // $client_id = 'xxxxxx';
        // $client_secret = 'xxxxxx';
        // $redirect_uri ='http://localhost:9527/auth-redirect';
        $client_id = '94aae05609c96ffb7d3b';  // #gitignore
        $client_secret = '02e962159c91e76bfc18548f7c90c52bc18b1cc6';  // #gitignore
        $redirect_uri = 'http://localhost:9527/auth-redirect';   // #gitignore
        
        // composer 安装 oauth2-client 包
        // composer require league/oauth2-client
        $provider = new \League\OAuth2\Client\Provider\GenericProvider([
            'clientId' => $client_id,    // The client ID assigned to you by the provider
            'clientSecret' => $client_secret,   // The client password assigned to you by the provider
            'redirectUri' => $redirect_uri,
            'urlAuthorize' => 'https://github.com/login/oauth/authorize',
            'urlAccessToken' => 'https://github.com/login/oauth/access_token',
            'urlResourceOwnerDetails' => 'https://api.github.com/user'
        ]);

        // If we don't have an authorization code then get one
        if (!isset($code)) {
             // 没有 code 参数, 生成授权链接 AuthorizationUrl 前返回前端
              //  https://github.com/login/oauth/authorize?state=137caabc2b409f0cccd14834fc848041&response_type=code&approval_prompt=auto&redirect_uri=http://localhost:9527/auth-redirect&client_id=94aae05609c96ffb7d3b
                // Fetch the authorization URL from the provider; this returns the
                // urlAuthorize option and generates and applies any necessary parameters
                // (e.g. state).
            $authorizationUrl = $provider->getAuthorizationUrl();
            
                // Get the state generated for you and store it to the session.
            $_SESSION['oauth2state'] = $provider->getState();
            
                // Redirect the user to the authorization URL.
            // header('Location: ' . $authorizationUrl);
            // exit;
            $message = [
                "code" => 20000,
                "data" => ['auth_url' => $authorizationUrl],
            ];
            $this->response($message, RestController::HTTP_OK);

            // Check given state against previously stored one to mitigate CSRF attack
        } elseif (empty($state) || (isset($_SESSION['oauth2state']) && $state !== $_SESSION['oauth2state'])) {

            if (isset($_SESSION['oauth2state'])) {
                unset($_SESSION['oauth2state']);
            }

            exit('Invalid state');

        } else {
            try {
                // Try to get an access token using the authorization code grant.
                $accessToken = $provider->getAccessToken('authorization_code', [
                    'code' => $code
                ]);
               
                // We have an access token, which we may use in authenticated
                // requests against the service provider's API.
                // echo 'Access Token: ' . $accessToken->getToken() . "<br>";
                $resourceOwner = $provider->getResourceOwner($accessToken);
                // var_export($resourceOwner->toArray());
                // var_dump($resourceOwner->toArray()['email']);

                $userInfo = $resourceOwner->toArray();

                $user = $this->User_model->getUserInfoByTel($userInfo["email"]); // 结合业务逻辑
                if (!empty($user)) {

                    $time = time(); //当前时间
                    // 公用信息
                    $payload = [
                        'iat' => $time, //签发时间
                        'nbf' => $time, //(Not Before)：某个时间点后才能访问，比如设置time+30，表示当前时间30秒后才能使用
                        'user_id' => $user[0]['id'], //自定义信息，不要定义敏感信息, 一般只有 userId 或 username
                    ];

                    $access_token = $payload;
                    $access_token['scopes'] = 'role_access'; //token标识，请求接口的token
                    $access_token['exp'] = $time + config_item('jwt_access_token_exp'); //access_token过期时间,这里设置2个小时

                    $refresh_token = $payload;
                    $refresh_token['scopes'] = 'role_refresh'; //token标识，刷新access_token
                    $refresh_token['exp'] = $time + config_item('jwt_refresh_token_exp'); //refresh_token,这里设置30天
                    $refresh_token['count'] = 0; // 刷新TOKEN计数, 在刷新token期间多次请求刷新token则表示活跃,可以重新生成刷新token以免刷新token过期后登录

                    $message = [
                        "code" => 20000,
                        "data" => [
                            "token" => JWT::encode($access_token, config_item('jwt_key')), //生成access_tokenToken,
                            "refresh_token" => JWT::encode($refresh_token, config_item('jwt_key')) //生成refresh_token,
                        ]
                    ];
                    $this->response($message, RestController::HTTP_OK);
                } else {
                    $message = [
                        "code" => 60206,
                        "data" => ["status" => 'fail', "msg" => "此github邮箱账号(" . $userInfo['email'] . ")没有与系统账号关联,请联系系统管理员!"],
                        "message" => "此github邮箱账号(" . $userInfo['email'] . ")没有与系统账号关联,请联系系统管理员!"
                    ];
                    $this->response($message, RestController::HTTP_OK);
                }
            } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {              
                // Failed to get the access token or user details.
                exit($e->getMessage());
            }
        }

    } // function githubauth_get() end

    function refreshtoken_post()
    {
        // 此处 $Token 应为refresh token 在前端 request 拦截器中做了修改
        // 刷新token接口需要在控制器内作权限验证,比较特殊,不能使用hook ManageAuth来验证
        $Token = $this->input->get_request_header('X-Token', true);
        try {
            $decoded = JWT::decode($Token, config_item('jwt_key'), ['HS256']); //HS256方式，这里要和签发的时候对应

            // $decoded = JWT::decode($Token, config_item('jwt_key'), ['HS256']); //HS256方式，这里要和签发的时候对应
            //            stdClass Object
            //            (
            //                [iss] => http://www.helloweba.net
            //                [aud] => http://www.helloweba.net
            //                [iat] => 1577668094
            //                [nbf] => 1577668094
            //                [exp] => 1577668094
            //                [user_id] => 2
            //                [count] => 0
            //            )

            $time = time(); //当前时间
            // 公用信息
            $payload = [
                'iat' => $time, //签发时间
                'nbf' => $time, //(Not Before)：某个时间点后才能访问，比如设置time+30，表示当前时间30秒后才能使用
                'user_id' => $decoded->user_id, //自定义信息，不要定义敏感信息, 一般只有 userId 或 username
            ];

            $access_token = $payload;
            $access_token['scopes'] = 'role_access'; //token标识，请求接口的token
            $access_token['exp'] = $time + config_item('jwt_access_token_exp'); //access_token过期时间,这里设置2个小时
            $new_access_token = JWT::encode($access_token, config_item('jwt_key')); //生成access_tokenToken
            //        {
            //          "iss": "http://pocoyo.org",
            //          "aud": "http://emacs.org",
            //          "iat": 1577757920,
            //          "nbf": 1577757920,
            //          "user_id": "1",
            //          "scopes": "role_refresh",
            //          "exp": 1577758100,
            //          "count": 0
            //        }

            $count = $decoded->count + 1;
            if ($count > config_item('jwt_refresh_count')) { // 在刷新token期间 {多次} 请求刷新token则表示活跃,可以重新生成刷新token以免刷新token过期后登录
                $refresh_token = $payload;
                $refresh_token['scopes'] = 'role_refresh'; //token标识，刷新access_token
                $refresh_token['exp'] = $time + config_item('jwt_refresh_token_exp');
                $refresh_token['count'] = 0; // 重置刷新TOKEN计数
                $new_refresh_token = JWT::encode($refresh_token, config_item('jwt_key')); // 这里可以根据需要重新生成 refresh_token
            } else { // 保持refresh_token过期时间及其他共公用信息,仅自增计数器
                $decoded->count++;
                $new_refresh_token = JWT::encode($decoded, config_item('jwt_key'));
            }

            $message = [
                "code" => 20000,
                "data" => [
                    "token" => $new_access_token,
                    "refresh_token" => $new_refresh_token
                ]
            ];
            $this->response($message, RestController::HTTP_OK);
        } catch (\Firebase\JWT\ExpiredException $e) {  // access_token过期
            $message = [
                "code" => 50015,
                "message" => 'refresh_token过期, 请重新登录'
            ];
            $this->response($message, RestController::HTTP_UNAUTHORIZED);
        } catch (Exception $e) {  //其他错误
            $message = [
                "code" => 50015,
                "message" => $e->getMessage()
            ];
            $this->response($message, RestController::HTTP_UNAUTHORIZED);
        }

    }

    // 根据token拉取用户信息 get
    function info_get()
    {
        // $result = $this->some_model();
        $result['success'] = true;
        // /sys/user/info 不用认证但是需要提取出 access_token 中的 user_id 来拉取用户信息
        $Token = $this->input->get_request_header('X-Token', true);
        $jwt_obj = $this->permission->parseJWT($Token);

        //    $decoded = JWT::decode($Token, config_item('jwt_key'), ['HS256']); //HS256方式，这里要和签发的时候对应
        //     print_r($decoded);
        //            stdClass Object
        //            (
        //                [iss] => http://pocoyo.org
        //    [aud] => http://emacs.org
        //    [iat] => 1577348490
        //    [nbf] => 1577348490
        //    [data] => stdClass Object
        //            (
        //                [user_id] => 1
        //            [username] => admin
        //        )
        //
        //    [scopes] => role_access
        //            [exp] => 1577355690
        //)
        $MenuTreeArr = $this->permission->getPermission($jwt_obj->user_id, 'menu', false);
        $asyncRouterMap = $this->permission->genVueRouter($MenuTreeArr, 'id', 'pid', 0);
        $CtrlPerm = $this->permission->getMenuCtrlPerm($jwt_obj->user_id);

        // 获取用户信息成功
        if ($result['success']) {
            $info = [
                "roles" => ["admin", "editor"],
                "introduction" => "I am a super administrator",
                "avatar" => "https://wpimg.wallstcn.com/f778738c-e4f8-4870-b634-56703b4acafe.gif",
                "name" => "Super Admin",
                "identify" => "410000000000000000",
                "phone" => "13633838282",
                "ctrlperm" => $CtrlPerm,
//                "ctrlperm" => [
//                    [
//                        "path" => "/sys/menu/view"
//                    ],
//                    [
//                        "path" => "/sys/menu/add"
//                    ],
//                    [
//                        "path" => "/sys/menu/download"
//                    ]
//                ],
                "asyncRouterMap" => $asyncRouterMap
//                "asyncRouterMap" => [
//                [
//                    "path" => '/sys',
//                    "name" => 'sys',
//                    "meta" => [
//                        "title" => "系统管理",
//                        "icon" => "sysset2"
//                    ],
//                    "component" => 'Layout',
//                    "redirect" => '/sys/menu',
//                    "children" => [
//                        [
//                            "path" => '/sys/menu',
//                            "name" => 'menu',
//                            "meta" => [
//                                "title" => "菜单管理",
//                                "icon" => "menu1"
//                            ],
//                            "component" => 'sys/menu/index',
//                            "redirect" => '',
//                            "children" => [
//
//                            ]
//                        ],
//                        [
//                            "path" => '/sys/user',
//                            "name" => 'user',
//                            "meta" => [
//                                "title" => "用户管理",
//                                "icon" => "user"
//                            ],
//                            "component" => 'pdf/index',
//                            "redirect" => '',
//                            "children" => [
//
//                            ]
//                        ],
//                        [
//                            "path" => '/sys/icon',
//                            "name" => 'icon',
//                            "meta" => [
//                                "title" => "图标管理",
//                                "icon" => "icon"
//                            ],
//                            "component" => 'svg-icons/index',
//                            "redirect" => '',
//                            "children" => [
//
//                            ]
//                        ]
//                    ]
//                ],
//                    [
//                        "path" => '/sysx',
//                        "name" => 'sysx',
//                        "meta" => [
//                            "title" => "其他管理",
//                            "icon" => "plane"
//                        ],
//                        "component" => 'Layout',
//                        "redirect" => '',
//                        "children" => [
//
//                        ]
//                    ]
//                ]
            ];

            $message = [
                "code" => 20000,
                "data" => $info,
                "_SERVER" => $_SERVER,
                "_GET" => $_GET
            ];
            $this->response($message, RestController::HTTP_OK);
        } else {
            $message = [
                "code" => 50008,
                "message" => 'Login failed, unable to get user details.'
            ];

            $this->response($message, RestController::HTTP_OK);
        }

    }

    //    async router test get
    function router_get()
    {
//        $result = $this->some_model();
        $result['success'] = true;

        // 获取用户信息成功
        if ($result['success']) {
//            $info = [
//                "roles" => ["admin", "editor"],
//                "introduction" => "I am a super administrator",
//                "avatar" => "https://wpimg.wallstcn.com/f778738c-e4f8-4870-b634-56703b4acafe.gif",
//                "name" => "Super Admin",
//                "identify" => "410000000000000000",
//                "phone" => "13633838282",
//                "asyncRouterMap" => [
//
//                ]
//            ];

            $message = [
                "code" => 20000,
                "data" => [
                    "asyncRouterMap" => [
                        [
                            "path" => '/sys',
                            "name" => 'sys',
                            "meta" => [
                                "title" => "系统管理",
                                "icon" => "nested"
                            ],
                            "component" => 'Layout',
                            "children" => [
                                [
                                    "path" => '/sys/menu',
                                    "name" => 'menu',
                                    "meta" => [
                                        "title" => "菜单管理",
                                        "icon" => "nested"
                                    ],
                                    "component" => 'index',
                                    "children" => []
                                ]
                            ]
                        ],
                        [
                            "path" => '/sysx',
                            "name" => 'sysx',
                            "meta" => [
                                "title" => "其他管理",
                                "icon" => "nested"
                            ],
                            "component" => 'Layout',
                            "children" => []
                        ]
                    ],
                    "__FUNCTION__" => __FUNCTION__,
                    "__CLASS__" => __class__,
                    "uri" => $this->uri
                ],

            ];
            $this->response($message, RestController::HTTP_OK);
        } else {
            $message = [
                "code" => 50008,
                "message" => 'Login failed, unable to get user details.'
            ];

            $this->response($message, RestController::HTTP_OK);
        }

    }

    function logout_post()
    {
        $message = [
            "code" => 20000,
            "data" => 'success'
        ];
        $this->response($message, RestController::HTTP_OK);
    }

    function list_get()
    {
//        $result = $this->some_model();
        $result['success'] = true;

        if ($result['success']) {
            $List = array(
                array('order_no' => '201805138451313131', 'timestamp' => 'iphone 7 ', 'username' => 'iphone 7 ', 'price' => 399, 'status' => 'success'),
                array('order_no' => '300000000000000000', 'timestamp' => 'iphone 7 ', 'username' => 'iphone 7 ', 'price' => 399, 'status' => 'pending'),
                array('order_no' => '444444444444444444', 'timestamp' => 'iphone 7 ', 'username' => 'iphone 7 ', 'price' => 399, 'status' => 'success'),
                array('order_no' => '888888888888888888', 'timestamp' => 'iphone 7 ', 'username' => 'iphone 7 ', 'price' => 399, 'status' => 'pending'),
            );

            $message = [
                "code" => 20000,
                "data" => [
                    "total" => count($List),
                    "items" => $List
                ]
            ];
            $this->response($message, RestController::HTTP_OK);
        } else {
            $message = [
                "code" => 50008,
                "message" => 'Login failed, unable to get user details.'
            ];

            $this->response($message, RestController::HTTP_OK);
        }

    }

}
