<?php
defined('BASEPATH') or exit('No direct script access allowed');

use chriskacerguis\RestServer\RestController;
use Nette\Utils\Arrays;
use Nette\Utils\Strings;
use Nette\Utils\Random;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\ValidationException;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Using Medoo namespace
use Medoo\Medoo;

use Activiti\Client\ModelFactory;
use Activiti\Client\ObjectSerializer;
use Activiti\Client\ServiceFactory;
use Activiti\Client\Model\User\UserQuery;
use Activiti\Client\Model\User\UserCreate;
use Activiti\Client\Model\User\UserUpdate;
use GuzzleHttp\Client;
use Activiti\Client\Model\Group\GroupQuery;
use Activiti\Client\Exception as ActivitiException; // 解决与 PHPMailer\PHPMailer\Exception 同名冲突

use Activiti\Client\Model\ProcessInstance\ProcessInstanceCreate;
use Activiti\Client\Model\ProcessInstance\ProcessInstanceQuery;
// use Activiti\Client\Model\VariableCreate;
// use Activiti\Client\Model\VariableUpdate;
use Activiti\Client\Service\ProcessInstanceService;

class Article extends RestController
{
    private $Medoodb;

    function __construct()
    {
        parent::__construct();
        // Initialize
        $this->Medoodb = new Medoo(config_item('medoodb'));
    }

    // DROP TABLE IF EXISTS `article`;
    // CREATE TABLE `article` (
    //   `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '文章id',
    //   `author` varchar(32) NOT NULL,
    //   `title` varchar(32) NOT NULL,
    //   `content` varchar(512) NOT NULL,
    //   `createTime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    //   PRIMARY KEY (`id`)
    // ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

    // restful post
    public function articles_post()
    {
        // var_dump($this->post());
        $parms = $this->post();

        // $parms = [
        //     'author' => 'pocoyo',
        //     'title' => 'hello world'
        // ]
        $data = $this->Medoodb->insert('article', $parms); // 返回PDOStatement

        // // Returns the ID of the last inserted row
        // var_dump($this->Medoodb->id());

        // // update(), insert() and delete() method will return the PDO::Statement object
        // echo $data->rowCount(); // The number of affected row
        // echo $data->errorInfo(); // Fetch extended error information for this query
        // // Read more: http://php.net/manual/en/class.pdostatement.php

        // 捕获错误信息
        $err = $this->Medoodb->error();
        // array(3) => ["42S02", 1146, "Table 'vueadminv2.articlex' doesn't exist"]
        if ($err[1]) { // 如果出错 否则为空
            // var_dump($err[2]);
            // var_dump($this->Medoodb->log());
            $message = [
                "code" => 20400,
                "data" => $err[2]
            ];
            $this->response($message, RestController::HTTP_BAD_REQUEST);
        } else { // 成功
            $message = [
                "code" => 20000,
                "data" => $parms
            ];
            $this->response($message, RestController::HTTP_CREATED); // CREATED (201) being the HTTP response code
        }
    }

    // restful get
    public function articles_get()
    {
        // TODO: 指定条件 $filters, 分页查询 user_model.php
        // nginx proxy 获取实际地址？？
        // config/routes.php 里可以重新定义路由规则，根据控制器中获取的参数 将不规则的路由 重新定义成规则的路由
        // Example 4 , api/example/users/3 转换成 api/example/users/id/3 的标准形式
        // $route['api/v2/article/articles/(:num)'] = 'api/v2/article/articles/id/$1'; 
        // var_dump($this->get('id'));  // 参数带id
        // var_dump($this->get('blah'));  // http://www.cirest.com:8889/api/article/articles/id/2/blah/3 可以传多个参数 可获取id,blah参数
        // 测试 $this->get() 获取的参数完全包含 $this->query() 的参数，后者参数只是 get url？后面的值

        // www.cirest.com:8890/api/v2/article/articles/id/222/blah/333?color=red&seats=<2&sort=-manufactorer,+model&fields=manufacturer,model,id,color&offset=10&limit=5
        // var_dump($this->get());
        // array(8) {
        //     ["id"]=>
        //     string(3) "222"
        //     ["blah"]=>
        //     string(3) "333"
        //     ["color"]=>
        //     string(3) "red"
        //     ["seats"]=>
        //     string(2) "<2"
        //     ["sort"]=>
        //     string(20) "-manufactorer, model"
        //     ["fields"]=>
        //     string(27) "manufacturer,model,id,color"
        //     ["offset"]=>
        //     string(2) "10"
        //     ["limit"]=>
        //     string(1) "5"
        //   }

        // var_dump($this->query()); // =>
        // array(6) {
        //     ["color"]=>
        //         string(3) "red"
        //     ["seats"]=>
        //         string(2) "<2" 
        //     ["sort"]=>
        //         string(20) "-manufactorer, model"
        //     ["fields"]=>
        //         string(27) "manufacturer,model,id,color"
        //     ["offset"]=>
        //         string(2) "10"
        //     ["limit"]=>
        //         string(1) "5"
        //     }

        // var_dump($this->get());
        // GET /articles?offset=1&limit=30&sort=-id&author=888&title=&fields=id,title,author&query=~author,title&author=888&title=world
        // 分页参数配置
        $limit = $this->get('limit') ? $this->get('limit') : 10;
        $offset = $this->get('offset') ?  ($this->get('offset') - 1) *  $limit : 0; // 第几页
        $where = [
            "LIMIT" => [$offset, $limit]
        ];
        // 分页参数配置结束

        // GET /articles?offset=1&limit=30&sort=-id&author=888&title=&fields=id,title,author&query=~author,title&author=888&title=world
        // 存在排序参数则 获取排序参数 加入 $where，否则不添加ORDER条件
        $sort = $this->get('sort');
        if ($sort) {
            $where["ORDER"] = [];
            $sortArr = explode(",", $sort);
            foreach ($sortArr as $k => $v) {
                if (Strings::startsWith($v, '-')) { // true DESC
                    $key = Strings::substring($v, 1); //  去 '-'
                    $where["ORDER"][$key] = "DESC";
                } else {
                    $key = Strings::substring($v, 1); //  去 '+'
                    $where["ORDER"][$key] = "ASC";
                }
            }
        }
        // 排序参数结束

        // GET /articles?offset=1&limit=30&sort=-id&author=888&title=&fields=id,title,author&query=~author,title&author=888&title=world
        // fields: 显示字段参数过滤配置,不设置则为全部
        $fields = $this->get('fields');
        $fields ? $columns = explode(",", $fields) : $columns = "*";

        // 显示字段过滤配置结束

        // GET /articles?offset=1&limit=30&sort=-id&author=888&title=&fields=id,title,author&query=~author,title&author=888&title=world
        // 指定条件模糊或搜索查询,author like %pocoyo%, status=1 此时 total $wherecnt 条件也要发生变化
        // var_dump($this->get('author')); var_dump($this->get('title'));
        // 查询字段及字段值获取
        // 如果存在query 参数以,分隔，且每个参数的有值才会增加条件
        $wherecnt = []; // 计算total使用条件，默认为全部
        $query = $this->get('query');
        if ($query) { // 存在才进行过滤,否则不过滤
            $queryArr = explode(",", $query);
            foreach ($queryArr as $k => $v) {
                if (Strings::startsWith($v, '~')) { // true   query=~username&status=1 以~开头表示模糊查询
                    $tmpKey = Strings::substring($v, 1); // username

                    $tmpValue = $this->get($tmpKey);
                    if (!is_null($tmpValue)) {
                        $where[$tmpKey . '[~]'] = $tmpValue;
                        $wherecnt[$tmpKey . '[~]'] = $tmpValue;
                    }
                } else {
                    $tmpValue = $this->get($v);
                    if (!is_null($tmpValue)) {
                        $where[$v] = $tmpValue;
                        $wherecnt[$v] = $tmpValue;
                    }
                }
            }
        }
        // 查询字段及字段值获取结束

        $data = $this->Medoodb->select(
            "article",
            $columns,
            $where
        );

        // var_dump($this->Medoodb->log());
        // var_dump($this->Medoodb->error());

        // 捕获错误信息
        $err = $this->Medoodb->error();
        // array(3) => ["42S02", 1146, "Table 'vueadminv2.articlex' doesn't exist"]
        if ($err[1]) { // 如果出错 否则为空
            // var_dump($err[2]);
            // var_dump($this->Medoodb->log());
            $message = [
                "code" => 20400,
                "data" => $err[2]
            ];
            $this->response($message, RestController::HTTP_BAD_REQUEST); // BAD_REQUEST (400) being the HTTP response code
        }

        $total = $this->Medoodb->count("article",  $wherecnt);
        $message = [
            "code" => 20000,
            "data" => [
                "items" => $data,
                "total" => $total
            ]
        ];
        $this->response($message, RestController::HTTP_OK);
        return;




        $id = $this->get('id');

        if ($id === NULL) { // id === NULL 查询全部
            $data = $this->Medoodb->select(
                'article',
                '*'
            ); // 返回 array
        } else if ($id <= 0) { // Validate the id.
            // Set the response and exit
            $message = [
                "code" => 20400,
                "data" => '400 BAD_REQUEST'
            ];
            $this->response($message, RestController::HTTP_BAD_REQUEST); // BAD_REQUEST (400) being the HTTP response code
        } else { // 根据 id 查询
            $data = $this->Medoodb->select(
                'article',
                '*',
                ['id' => $id]
            ); // 返回 array
        }

        // 查询复杂时，也可以使用query 可以直接使用SQL。
        // $data = $this->Medoodb->query("SELECT * FROM article")->fetchAll(PDO::FETCH_ASSOC);
        // PDO::FETCH_ASSOC	关联数组形式。
        // PDO::FETCH_NUM	数字索引数组形式。
        // PDO::FETCH_BOTH	两者数组形式都有，这是默认的。

        // 捕获错误信息
        $err = $this->Medoodb->error();
        // array(3) => ["42S02", 1146, "Table 'vueadminv2.articlex' doesn't exist"]
        if ($err[1]) { // 如果出错 否则为空
            // var_dump($err[2]);
            // var_dump($this->Medoodb->log());
            $message = [
                "code" => 20400,
                "data" => $err[2]
            ];
            $this->response($message, RestController::HTTP_BAD_REQUEST); // BAD_REQUEST (400) being the HTTP response code
        } else if (empty($data)) {
            $message = [
                "code" => 20404,
                "data" => [
                    "items" => $data,
                    "total" => count($data)
                ]
            ];
            $this->response($message, RestController::HTTP_NOT_FOUND);  // NOT_FOUND (404) being the HTTP response code

        } else { // 成功
            $message = [
                "code" => 20000,
                "data" => [
                    "items" => $data,
                    "total" => count($data)
                ]
            ];
            $this->response($message, RestController::HTTP_OK);
        }
    }

    // restful put
    public function articles_put()
    {
        $parms = $this->put();

        // 参数数据预处理
        $where = ['id' => Arrays::pick($parms, 'id')]; // nette/utils/Arrays 杀鸡用牛刀?  $parms['id'] 即可
        unset($parms['id']);    // 剔除 id 元素

        $has = $this->Medoodb->has('article', $where); // 记录是否存在 感觉此处判断多余，即使不存在 update/delete 也不会出错， 不精确的情况下不必要用此逻辑
        if (!$has) { // 记录不存在
            $message = [
                "code" => 20404,
                "data" => $where
            ];
            $this->response($message, RestController::HTTP_NOT_FOUND);
        }

        $data = $this->Medoodb->update(
            'article',
            $parms, //  ['author' => 'nightfury'],
            $where  //  ['id' => 1]
        ); // 返回PDOStatement
        // var_dump($data);
        // object(PDOStatement)#27 (1) {
        //     [
        //       "queryString"
        //     ]=>
        //     string(74) "UPDATE "article" SET "author" = :MeDoO_0_mEdOo WHERE "id" = :MeDoO_1_mEdOo"
        //   }

        // 捕获错误信息
        $err = $this->Medoodb->error();
        // array(3) => ["42S02", 1146, "Table 'vueadminv2.articlex' doesn't exist"]
        if ($err[1]) { // 如果出错 否则为空
            var_dump($err);
            var_dump($err[2]);
            var_dump($this->Medoodb->log());
        } else { // 成功
            $message = [
                "code" => 20000,
                "data" => $data
            ];
            $this->response($message, RestController::HTTP_OK);
        }
    }

    // restful delete
    public function articles_delete($id)
    {
        // https://github.com/yurychika/codeIgniter-RESTServer-demo
        // 对于PUT、GET、POST等HTTP请求动词，可以通过以下方法来获取参数：
        // $this->get('blah'); // GET param  可以获取多个参数 api/user/id/2/blah/3  
        // $this->post('blah'); // POST param
        // $this->put('blah'); // PUT param
        // The HTTP spec for DELETE requests precludes the use of parameters. For delete requests, you can add items to the URL
        // 而对于DELETE请求，则只能通过在方法中添加参数，然后通过URL传入参数，来进行访问：

        // DELETE http://www.cirest.com:8890/api/v2/article/articles/22
        var_dump($id); // $id => 22

        $parms = ['id' => $id];
        $data = $this->Medoodb->delete(
            'article',
            $parms // ['id' => 15]
        ); // 返回 PDOStatement
        // var_dump($data);
        // object(PDOStatement)#27 (1) {
        //     [
        //       "queryString"
        //     ]=>
        //     string(49) "DELETE FROM "article" WHERE "id" = :MeDoO_0_mEdOo"
        //   }

        // 捕获错误信息
        $err = $this->Medoodb->error();
        // array(3) => ["42S02", 1146, "Table 'vueadminv2.articlex' doesn't exist"]

        if ($err[1]) { // 如果出错 否则为空
            var_dump($err);
            var_dump($err[2]);
            var_dump($this->Medoodb->log());
        } else { // 成功
            $message = [
                "code" => 20000,
                "data" => $data
            ];
            $this->response($message, RestController::HTTP_OK);
        }
    }

    // excel 上传入库测试
    public function upload_post()
    {
        // set excel 上传目录
        $uploadDir = FCPATH . 'uploads/excel/';
        $storage = new \Upload\Storage\FileSystem($uploadDir);

        $file = new \Upload\File('file', $storage); // 其中 file 前端传递的 file 参数,表单 name = 'file'

        // Optionally you can rename the file on upload
        $new_filename = uniqid();
        $file->setName($new_filename); // => name => 5e8b3bfe95302
        // Validate file upload
        // MimeType List => http://www.iana.org/assignments/media-types/media-types.xhtml
        $file->addValidations([
            //You can also add multi mimetype validation
            new \Upload\Validation\Mimetype([
                'application/vnd.ms-excel', //.xls
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', //.xlsx
                'application/zip' // 前端xlsx导出的 xlsx mime type是 zip类型 https://github.com/SheetJS/sheetjs/issues/1402
            ]),
            // Ensure file is no larger than 5M (use "B", "K", M", or "G")
            new \Upload\Validation\Size('5M')

        ]);

        // Access data about the file that has been uploaded
        $data = array(
            'name'       => $file->getNameWithExtension(),
            'extension'  => $file->getExtension(),
            'mime'       => $file->getMimetype(),
            'size'       => $file->getSize(),
            'md5'        => $file->getMd5(),
            'dimensions' => $file->getDimensions()
        );

        // var_dump($data);

        // Try to upload file
        try {
            // Success!
            $file->upload();

            // phpoffice/phpspreadsheet read excel
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(TRUE);
            $spreadsheet = $reader->load($uploadDir . $data['name']); //载入excel表格

            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = $worksheet->getHighestRow(); // 总行数 索引以 1 开头
            // $highestColumn = $worksheet->getHighestColumn(); // 总列数

            $lines = $highestRow - 2;
            if ($lines <= 0) {
                exit('Excel表格中没有数据');
            }

            $sql = "INSERT INTO `article` (`title`, `author`, `pageviews`, `display_time`) VALUES ";
            // 循环读取指定 2 3 4 5 列 数据
            for ($row = 3; $row <= $highestRow; ++$row) {
                $title = $worksheet->getCellByColumnAndRow(2, $row)->getValue(); //title
                $author = $worksheet->getCellByColumnAndRow(3, $row)->getValue(); //author
                $pageviews = $worksheet->getCellByColumnAndRow(4, $row)->getValue(); //pageviews
                $display_time = $worksheet->getCellByColumnAndRow(5, $row)->getValue(); //display_time

                $sql .= "('$title','$author','$pageviews','$display_time'),";
            }
            $sql = rtrim($sql, ","); //去掉最后一个,号
            // phpoffice/phpspreadsheet read excel end

            // 入库
            $this->Medoodb->query($sql); // 执行sql语句
            $err = $this->Medoodb->error(); // 捕获错误信息

            $message = [
                "code" => 20000,
                "message" => '上传入库成功',
                "url" => base_url('uploads/excel/') . $data['name'],
                "data" => $data,
                "sqlInfo" => ['sql' => $this->Medoodb->log(), 'errInfo' => $err]
            ];
            $this->response($message, RestController::HTTP_OK);
        } catch (\Exception $e) {
            // Fail!
            $errors = $file->getErrors();
            $errMsg = implode(',', $errors); // $errors是数组
            $message = [
                "code" => 50015,
                "message" => $errMsg
            ];
            $this->response($message, RestController::HTTP_OK);
        }
    }

    public function arraydiff_post()
    {
        // permission->array_diff_assoc2 _权限设计时使用了二维数组 其实可以前后端均使用一维即可，比较起来比较方便 且使用php原生array_diff即可完成_
        // [
        //  ['role_id'=> 1, 'perm_id'=>1]，
        //  ['role_id'=> 1, 'perm_id'=>2]，
        // ]
        // charliekassel/array-diff 测试 一维数组必须带key比较 比较局限

        $old = [1, 2, 3];

        $new = $this->Medoodb->select(
            "sys_role_perm",
            "perm_id",
            ["role_id" => 2]
        );
        // print_r(array_diff($old, $new));
        // print_r(array_diff($new, $old));

        // print_r($new);


        // 菜单树生成
        // bluem/tree 复杂强大，对象方式， chastephp/array2tree _简单方便_
        // https://github.com/BlueM/Tree
        // 数据库取出为string类型，强制类型转换成整形，方便前端使用 medoo使用 [Int] 强制转换
        // medoo使用Data Mapping 自定义输出 字段格式 eg. 'meta'
        $menuArr = $this->Medoodb->select(
            "sys_menu",
            [
                'id[Int]', 'pid[Int]', 'name', 'path',  'component', 'type[Int]',
                // 'title',  'icon',
                // Customize output data construction - Data Mapping
                'meta' => [
                    'title',
                    'icon'
                ],
                'redirect', 'hidden[Int]', 'status[Int]', 'condition', 'listorder[Int]', 'create_time', 'update_time'
            ]
        );
        // var_dump($menuArr); // var_dump可显示变量类型
        $tree = new BlueM\Tree(
            $menuArr,
            ['rootId' => 0, 'id' => 'id', 'parent' => 'pid']
        );
        $asynRoute = $this->_dumpBlueMTreeNodes($tree->getRootNodes());

        // var_dump($asynRoute);
        $message = [
            "code" => 20000,
            "data" => $asynRoute
        ];
        $this->response($message, RestController::HTTP_OK);
    }

    // 遍历 BlueM\Tree 树对象，生成符合 vue-router 结构的路由树或菜单树
    private function _dumpBlueMTreeNodes($node)
    {
        $tree = array();

        foreach ($node as $k => $v) {
            $valArr = $v->toArray();

            unset($valArr['parent']); // BlueM\Tree 对象 多余可去除

            // // 构造 vue-admin 路由结构 meta 可使用 medoo data Map 功能 直接构造完成 meta
            // $valArr['meta'] = [
            //     'title' => $valArr['title'],
            //     'icon' => $valArr['icon']
            // ];
            // unset($valArr['title']);
            // unset($valArr['icon']);

            if ($v->hasChildren()) { // 存在 children 则构造 children key，否则不添加
                $valArr['children'] = $this->_dumpBlueMTreeNodes($v->getChildren());
            }

            $tree[] = $valArr;     // 循环数组添加元素 属于同一层级
        }

        return $tree;
    }

    public function monolog_post()
    {
        // Create the logger
        $logger = new Logger(basename(__FILE__)); // 以当前文件名作为 channel-name 可自定义用于过滤使用
        // Now add some handlers, eg. StreamHandler 用来保存日志到文件
        $logger->pushHandler(new StreamHandler(APPPATH . 'logs/monolog.log', Logger::DEBUG));

        // You can now use your logger
        // 第一个参数 ，第二个参数(array), 额外数据 => 需要使用 pushProcessor 来处理
        $logger->info('Adding a new user');

        $logger->error('delete roles failed Array', [
            'failedArr' => [
                ['role_id' => 1, 'perm_id' => 2],
                ['role_id' => 1, 'perm_id' => 3]
            ],
            'uri' => uri_string() . '/' . Strings::lower($_SERVER['REQUEST_METHOD'])
        ]);

        // 附加额外数据
        $logger->pushProcessor(function ($entry) {
            $entry['extra']['data'] = 'Hello world!';
            return $entry;
        });
        $logger->warning('User registered', ['username' => 'pocoyo']);
        // [2020-04-26T15:25:11.655317+08:00] Article.php.INFO: Adding a new user [] []
        // [2020-04-26T15:25:11.656317+08:00] Article.php.ERROR: delete roles failed Array {"failed":[{"role_id":1,"perm_id":2},{"role_id":1,"perm_id":3}],"uri":"api/v2/article/arraydiff/post"} []
        // [2020-04-26T15:25:11.657317+08:00] Article.php.WARNING: User registered {"username":"pocoyo"} {"data":"Hello world!"}

        // var_dump($asynRoute);
        $message = [
            "code" => 20000,
            "message" => 'monolog create success!!!'
        ];
        $this->response($message, RestController::HTTP_OK);
    }

    // respect/validation 校验测试
    public function validation_post()
    {

        try {
            // 使用check 来捕获异常信息 https://respect-validation.readthedocs.io/en/2.0/rules/AnyOf/
            // $usernameValidator->check('alganetgagag11111');
            v::keySet(
                // key 3 params false 非必须字段
                v::key('name', v::notEmpty()->alnum('#', '%')->noWhitespace()->lowercase()->length(5, 30), false), //  alnum Validates alphanumeric characters from a-Z and 0-9. 可额外包含 # % 字符
                v::key('id', v::intVal()->notEmpty()->between(10, 100)), // 必须带有 id参数 且参数为整数并且在10,100之间 [10,100]
                // 在某个取值区间内
                v::key('method', v::in(['GET', 'POST',])),
                // age校验
                v::key('age', v::intVal()->between(10, 80)),
                v::key('list', v::arrayType()->notEmpty(), false),

                // 测试可用至少8位 大小写，数字，指定特殊字符 @$!%*?& 均至少一位
                // v::key('password', v::regex('/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/')),
                // 至少8位，必含有大小写，数字， 同时可包含非空白字符 \S
                v::key('password', v::regex('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[\S]{8,}$/')),

                v::key('password_confirmation', v::notEmpty(), false),

                // email校验
                v::key('email', v::email()),
                // ip地址或者domain 嵌套oneOf
                v::key('host', v::oneOf(
                    v::ip(),
                    v::domain()
                ))
            )->check($this->post());

            // 修改密码时检验密码强度与一致性
            // v::regex('/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/')->check($this->post()['password']);       
            // keyValue不能在 keySet 里使用因此需要分开来进行校验
            v::keyValue('password_confirmation', 'equals', 'password')->check($this->post());
        } catch (ValidationException $e) {
            $message = [
                "code" => 20000,
                "message" => $e->getMessage()
            ];
            $this->response($message, RestController::HTTP_OK);
        }

        $message = [
            "code" => 20000,
            "message" => '校验无异常'
        ];
        $this->response($message, RestController::HTTP_OK);
    }

    // phpmailer/phpmailer 测试
    public function phpmailer_post()
    {
        $parms = $this->post();
        try {
            // 使用check 来捕获异常信息 https://respect-validation.readthedocs.io/en/2.0/rules/AnyOf/
            v::keySet(
                // key 3 params false 非必须字段
                v::key('name', v::notEmpty()), //  alnum Validates alphanumeric characters from a-Z and 0-9. 可额外包含 # % 字符
                // email校验
                v::key('email', v::email()),

            )->check($parms);
        } catch (ValidationException $e) {
            $message = [
                "code" => 20000,
                "message" => $e->getMessage()
            ];
            $this->response($message, RestController::HTTP_OK);
        }

        // 校验 email 与 username 名， 存在则继续
        $has = $this->Medoodb->has(
            'sys_user',
            [
                'username' => $parms['name'],
                'email' =>    $parms['email']
            ]
        );

        if (!$has) {
            $message = [
                "code" => 20400,
                "type" => 'error',
                "message" => 'username/email不正确'
            ];
            $this->response($message, RestController::HTTP_OK); // BAD_REQUEST (400) being the HTTP response code
        }

        // 生成随机密码 md5 后, 根据 email 更新入库
        $new_passwd = Random::generate(12, '0-9a-zA-Z!@#$%^&*');
        // $this->Medoodb->update(
        //     'sys_user',
        //     ['password' => md5($parms['new_passwd'])],
        //     ['username' => $parms['name']]
        // );

        // Instantiation and passing `true` enables exceptions
        $mail = new PHPMailer(true);

        try {
            //Server settings
            // $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      // Enable verbose debug output
            $mail->isSMTP();                                            // Send using SMTP
            $mail->Host       = 'smtp.163.com';                    // Set the SMTP server to send through
            $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
            $mail->Username   = 'ctthawg@163.com';                     // SMTP username
            $mail->Password   = 'ctthawg1';                               // SMTP password
            // $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
            $mail->Port       = 25;                                    // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above

            //Recipients
            $mail->setFrom('ctthawg@163.com', 'Vue-PHP-Admin');
            // $mail->addAddress('joe@example.net', 'Joe User');     // Add a recipient
            $mail->addAddress($parms['email']);               // Name is optional
            // $mail->addReplyTo('info@example.com', 'Information');
            // $mail->addCC('cc@example.com');
            // $mail->addBCC('bcc@example.com');

            // Content
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->Subject = '找回密码';

            $mail->Body    = '用户名: ' .  $parms['name'] .  '<br>密码: ' . $new_passwd;

            $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

            $mail->send();
            echo 'Message has been sent';
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    }

    public function activiti_post()
    {
        $client = new Client([
            'base_uri' => 'http://localhost:8080/activiti-rest/service/',
            'auth' => [
                'admin', 'test',
            ],
        ]);

        $serviceFactory = new ServiceFactory($client, new ModelFactory(), new ObjectSerializer());
        $service = $serviceFactory->createUserService();

        $query = new UserQuery();
        $query->setSize(10); // 设置分页 setSize setStart

        do {
            $users = $service->getUsersList($query);

            foreach ($users as $i => $user) {
                vprintf("%d. %s %s (%s) <%s>\n", [
                    $query->getStart() + $i + 1,
                    $user->getFirstName(),
                    $user->getLastName(),
                    $user->getId(),
                    $user->getEmail(),
                ]);
            }

            $query->setStart($query->getStart() + $query->getSize());
        } while ($users->getTotal() > $query->getStart());

        $serviceFactory = new ServiceFactory($client, new ModelFactory(), new ObjectSerializer());
        $service = $serviceFactory->createGroupService();

        $query = new GroupQuery();
        $query->setSize(5); // 分页 setSize setStart

        do {
            $groups = $service->getGroupList($query);
            foreach ($groups as $i => $group) {
                printf("%s (%s)\n", $group->getName(), $group->getType());
            }

            $query->setStart($query->getStart() + $query->getSize());
            var_dump($query->getStart());
        } while ($groups->getTotal() > $query->getStart());

        // // 创建group
        // $serviceFactory = new ServiceFactory($client, new ModelFactory(), new ObjectSerializer());
        // try {
        //     $group = $serviceFactory->createGroupService()->createGroup('Group A', 'Group a', 'group-a'); //  ID_, Name_, TYPE_
        // } catch (ActivitiException\ActivitiException $e) {
        //     var_dump($e->getMessage());
        // }

        // // 删除group
        // $serviceFactory = new ServiceFactory($client, new ModelFactory(), new ObjectSerializer());
        // try {
        //     $group = $serviceFactory->createGroupService()->deleteGroup('Group A'); //  ID_, Name_, TYPE_
        //     var_dump('删除group success!');
        // } catch (ActivitiException\ActivitiException $e) {
        //     var_dump($e->getMessage());
        // }

        // // 更新group
        // $serviceFactory = new ServiceFactory($client, new ModelFactory(), new ObjectSerializer());
        // try {
        //     $group = $serviceFactory->createGroupService()->updateGroup('Group A', 'new GroupName', 'New Type'); //  ID_, Name_, TYPE_
        //     var_dump('更新group success!');
        // } catch (ActivitiException\ActivitiException $e) {
        //     var_dump($e->getMessage());
        // }

        // // 创建 user
        // $serviceFactory = new ServiceFactory($client, new ModelFactory(), new ObjectSerializer());
        // try {
        //     $data = new UserCreate();
        //     $data->setId('testuser');
        //     $data->setFirstName('testName');
        //     // $data->setLastName('McDonald');
        //     $data->setEmail('no-reply@activiti.org'); // 必须配置邮箱 否则 activiti-app 不能登录成功
        //     $data->setPassword('testuser');

        //     $user = $serviceFactory->createUserService()->createUser($data);
        //     var_dump('创建 用户成功');
        // } catch (ActivitiException\ActivitiException $e) {
        //     var_dump($e->getMessage());
        // }

        // // 删除 user
        // $serviceFactory = new ServiceFactory($client, new ModelFactory(), new ObjectSerializer());
        // try {
        //     $user = $serviceFactory->createUserService()->deleteUser('testuser');
        //     var_dump('删除用户成功');
        // } catch (ActivitiException\ActivitiException $e) {
        //     var_dump($e->getMessage());
        // }

        // // 更新 user
        // $serviceFactory = new ServiceFactory($client, new ModelFactory(), new ObjectSerializer());
        // try {
        //     $data = new UserUpdate();
        //     $data->setFirstName('testName');
        //     // $data->setLastName('McDonald');
        //     // $data->setEmail('no-reply@activiti.org');
        //     $data->setPassword('testuser');
        //     $group = $serviceFactory->createUserService()->updateUser('testuser', $data);

        //     var_dump('更新group success!');
        // } catch (ActivitiException\ActivitiException $e) {
        //     var_dump($e->getMessage());
        // }

        // // 添加 group memmber addMember 
        // $serviceFactory = new ServiceFactory($client, new ModelFactory(), new ObjectSerializer());
        // try {
        //     $group = $serviceFactory->createGroupService()->addMember('user','testuser');
        //     var_dump('addMember success');
        // } catch (ActivitiException\ActivitiException $e) {
        //     var_dump($e->getMessage());
        // }

        // // 删除 group memmber deleteMember => act_id_membership
        // $serviceFactory = new ServiceFactory($client, new ModelFactory(), new ObjectSerializer());
        // try {
        //     $group = $serviceFactory->createGroupService()->deleteMember('user','testuser');
        //     var_dump('deleteMember success');
        // } catch (ActivitiException\ActivitiException $e) {
        //     var_dump($e->getMessage());
        // }

        // $serviceFactory = new ServiceFactory($client, new ModelFactory(), new ObjectSerializer());
        // $processInstanceId = 5120;

        // $processInstance = $serviceFactory->ProcessInstanceService->createClient(new Response(200, [], $expected));
        // $actual = $this
        //     ->createProcessInstanceService($client)
        //     ->getDiagram($processInstanceId);

        // $this->assertRequestMethod('GET');
        // $this->assertRequestUri('runtime/process-instances/' . $processInstanceId . '/diagram');
        // $this->assertEquals($expected, $actual);
    }
} // class Article end
