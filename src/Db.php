<?php

/**
 * 数据库操作工具类，只能在hyperf中使用
 */

namespace Scpzc\HyperfDb;


use Hyperf\Utils\Context;

/**
 * @method static DbCore table(string $table)  要操作的数据表
 * @method static DbCore field($field = '')  要操作的字段
 * @method static DbCore where($where,$params = [])  要操作的条件
 * @method static array fetchRow($where = [], $params = [], $fields = '') 查询一条记录，可以使用原生
 * @method static array fetchAll($where = [], $params = [], $fields = '') 查询符合条件的所有记录，可以使用原生
 * @method static array fetchByPage($where = null, $params = [], $fields = '',$page = 1,$pageSize = 10) 分页查询
 * @method static string fetchOne($where = [], $params = [], $fields = '') 获取单个值，可以使用原生
 * @method static mixed execute(string $sql = null, array $params = []) 执行原生SQL
 * @method static  startTrans() 开始事务
 * @method static  commit() 提交事务
 * @method static  rollBack()  回滚事务
 * @method static  getSql()  查询执行的SQL和参数
 */

class Db
{

    public static function connect($pool = 'default')
    {
        $keyName = 'DB___'.$pool;
        $dbCore = Context::get($keyName);
        if (empty($dbCore)) {
            $dbCore = new DbCore($pool);
            Context::set($keyName,$dbCore);
        }
        return $dbCore;
    }

    public static function __callStatic($method, $args)
    {
        return self::connect()->$method(...$args);
    }

}
