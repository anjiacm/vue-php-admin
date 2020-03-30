<?php
defined('BASEPATH') or exit('No direct script access allowed');

use chriskacerguis\RestServer\RestController;
use Nette\Utils\Arrays;

// Using Medoo namespace
use Medoo\Medoo;

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
        // config/routes.php 里可以重新定义路由规则，根据控制器中获取的参数 将不规则的路由 重新定义成规则的路由
        // Example 4 , api/example/users/3 转换成 api/example/users/id/3 的标准形式
        // $route['api/v2/article/articles/(:num)'] = 'api/v2/article/articles/id/$1'; 
        // var_dump($this->get('id'));  // 参数带id
        // var_dump($this->get('blah'));  // http://www.cirest.com:8889/api/article/articles/id/2/blah/3 可以传多个参数 可获取id,blah参数
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
                "code" => 200400,
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
    public function articles_delete()
    {
        // var_dump($this->delete()); // ['id' => 22]
        $parms = $this->delete();

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
} // class Article end
