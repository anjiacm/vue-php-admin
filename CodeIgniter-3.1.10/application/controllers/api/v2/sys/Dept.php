<?php
defined('BASEPATH') or exit('No direct script access allowed');

use chriskacerguis\RestServer\RestController;

class Dept extends RestController
{

    function __construct()
    {
        parent::__construct();
        $this->load->model('Base_model');
        $this->load->model('Dept_model');
        // $this->config->load('config', true);
    }

    // 增
    function add_post()
    {
        $parms = $this->post();  // 获取表单参数，类型为数组

        if ($this->Base_model->_key_exists('sys_dept', ['name' => $parms['name']])) {
            $message = [
                "code" => 20000,
                "type" => 'error',
                "message" => $parms['name'] . ' - 机构名称重复'
            ];
            $this->response($message, RestController::HTTP_OK);
        }

        $dept_id = $this->Base_model->_insert_key('sys_dept', $parms);
        if (!$dept_id) {
            $message = [
                "code" => 20000,
                "type" => 'error',
                "message" => $parms['name'] . ' - 机构新增失败'
            ];
            $this->response($message, RestController::HTTP_OK);
        }

        // 生成该部门对应的权限: sys_perm, 权限类型为: dept, 生成唯一的 perm_id
        $perm_id = $this->Base_model->_insert_key('sys_perm', ['perm_type' => 'dept', "r_id" => $dept_id]);
        if (!$perm_id) {
            var_dump($this->uri->uri_string . ' 生成该部门对应的权限: sys_perm, 失败...');
            var_dump(['perm_type' => 'role', "r_id" => $dept_id]);
            return;
        }

        // 超级管理员角色自动拥有该权限 perm_id
        $role_perm_id = $this->Base_model->_insert_key('sys_role_perm', ["role_id" => 1, "perm_id" => $perm_id]);
        if (!$role_perm_id) {
            var_dump($this->uri->uri_string . ' 超级管理员角色自动拥有该权限 perm_id, 失败...');
            var_dump(["role_id" => 1, "perm_id" => $perm_id]);
            return;
        }

        $message = [
            "code" => 20000,
            "type" => 'success',
            "message" => $parms['name'] . ' - 机构新增成功'
        ];
        $this->response($message, RestController::HTTP_OK);
    }

    // 删
    function del_post()
    {
        $parms = $this->post();  // 获取表单参数，类型为数组
        // var_dump($parms['path']);

        // 参数检验/数据预处理
        // 含有子节点不允许删除
        $hasChild = $this->Dept_model->hasChildDept($parms['id']);
        if ($hasChild) {
            $message = [
                "code" => 20000,
                "type" => 'error',
                "message" => $parms['name'] . ' - 存在子节点不能删除'
            ];
            $this->response($message, RestController::HTTP_OK);
        }

        // 删除外键关联表 sys_role_perm , sys_user_dept, sys_perm, sys_dept
        // 1. 根据sys_dept id及'dept' 查找 perm_id
        // 2. 删除sys_role_perm中perm_id记录
        // 3. 删除sys_perm中 perm_type='role' and r_id = role_id 记录,即第1步中获取的 perm_id， 一一对应
        // 4. 删除sys_user_dept中 dept_id = $parms['id']) 的记录
        $where = 'perm_type="dept" and r_id=' . $parms['id'];
        $arr = $this->Base_model->_get_key('sys_perm', '*', $where);
        if (empty($arr)) {
            var_dump($this->uri->uri_string . ' 未查找到 sys_perm 表中记录');
            var_dump($where);
            return;
        }

        $perm_id = $arr[0]['id']; // 正常只有一条记录
        $this->Base_model->_delete_key('sys_role_perm', ['perm_id' => $perm_id]); // 必须删除权限id 因为超级管理员角色自动拥有该权限否则会造成删除关联错误
        $this->Base_model->_delete_key('sys_perm', ['id' => $perm_id]);

        $this->Base_model->_delete_key('sys_user_dept', ['dept_id' => $parms['id']]);

        // 删除基础表 sys_dept
        if (!$this->Base_model->_delete_key('sys_dept', ['id' => $parms['id']])) {
            $message = [
                "code" => 20000,
                "type" => 'error',
                "message" => $parms['name'] . ' - 机构删除失败'
            ];
            $this->response($message, RestController::HTTP_OK);
        }

        $message = [
            "code" => 20000,
            "type" => 'success',
            "message" => $parms['name'] . ' - 机构删除成功'
        ];
        $this->response($message, RestController::HTTP_OK);
    }

    // 改
    function edit_post()
    {
        $parms = $this->post();  // 获取表单参数，类型为数组
        // var_dump($parms['path']);

        // 参数检验/数据预处理
        $id = $parms['id'];
        unset($parms['id']); // 剃除索引id
        unset($parms['children']); // 剃除传递上来的子节点信息

        if ($id == $parms['pid']) {
            $message = [
                "code" => 20000,
                "type" => 'error',
                "message" => $parms['name'] . ' - 上级机构不能为自己'
            ];
            $this->response($message, RestController::HTTP_OK);
        }

        $where = ["id" => $id];

        if (!$this->Base_model->_update_key('sys_dept', $parms, $where)) {
            $message = [
                "code" => 20000,
                "type" => 'error',
                "message" => $parms['name'] . ' - 机构更新错误'
            ];
            $this->response($message, RestController::HTTP_OK);
        }

        $message = [
            "code" => 20000,
            "type" => 'success',
            "message" => $parms['name'] . ' - 机构更新成功'
        ];
        $this->response($message, RestController::HTTP_OK);
    }



    // 查
    function view_post()
    {
        $DeptArr = $this->Dept_model->getDeptList();
        $DeptTree = $this->permission->genDeptTree($DeptArr, 'id', 'pid', 0);

        $message = [
            "code" => 20000,
            "data" => $DeptTree,
        ];
        $this->response($message, RestController::HTTP_OK);
    }
}
