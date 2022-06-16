<?php
/**
 * 数据处理
 */

namespace Scpzc\HyperfDb;


use Hyperf\Utils\ApplicationContext;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Scpzc\HyperfDb\Event\FailToExecute;


class DbCore
{

    private $operateData = [   //要操作的数据
        'table'       => null,  //要查询的表
        'field'       => ' * ',   //查询的字段
        'where'       => '',    //查询条件
        'limit'       => '',    //查询限制
        'update_data' => '',//更新数据
        'insert_data' => '',//插入数据
        'order_by'    => '',   //排序
    ];
    private $resetData ;  //重置数据
    private $sql = '';//执行sql语句
    private $params = [];//绑定的参数
    private $sqlAndParams = []; //执行的SQL语句和参数
    private $transNum = 0; //事务嵌套次数
    private $db;  //数据库连接资源
    private $debug = 0; //是否开启调试模式,如果开启将不运行，直接输出sql语句

    /**
     * @var ContainerInterface
     */
    protected $container;


    /**
     * @var null|EventDispatcherInterface
     */
    protected $dispatcher;


    public function __construct($name)
    {
        $this->db = \Hyperf\DbConnection\Db::connection($name);
        $this->resetData = $this->operateData;

        $container = ApplicationContext::getContainer();
        if ($container->has(EventDispatcherInterface::class)) {
            $this->dispatcher = $container->get(EventDispatcherInterface::class);
        }
    }


    /**
     * 开启调试模式
     */
    public function debug(){
        $this->debug = 1;
        return $this;
    }


    /**
     * 重置参数
     */
    private function resetParams(){
        $this->operateData = $this->resetData;
        $this->params = [];
    }


    /**
     * 异常处理
     */
    private function exception($throwable){
        $this->dispatcher && $this->dispatcher->dispatch(new FailToExecute($throwable));
    }


    /**
     * 设置表名
     * @param $table
     *
     * @return $this
     */
    public function table($table){
        $this->operateData['table'] = ' `'.$table.'` ';
        return $this;
    }




    /**
     * 设置要查询的字段
     * @param string $field
     *
     * @return $this
     */
    public function field($field = ' * '){
        if(is_array($field) && !empty($field)){
            $field = ' '.join(',',$field).' ';
        }
        $field = !empty($field) ? $field : '*';
        $this->operateData['field'] = ' '.$field.' ';
        return $this;
    }

    /**
     * 排序
     * @param $orderBy
     *
     * @return $this
     */
    public function order($orderBy){
        $sort = [];
        if(is_array($orderBy)){
            foreach($orderBy as $key=>$val){
                $sort[] = $key.' '.$val;
            }
            $this->operateData['order_by'] = ' ORDER BY '.join(',',$sort).' ';
        }elseif(is_string($orderBy)){
            $this->operateData['order_by'] = ' ORDER BY '.$orderBy.' ';
        }
        return $this;
    }


    /**
     * 查询有限的记录
     * @param int $offset   //偏移量
     * @param int $limit    //条数
     * @return $this
     */
    public function limit(int $offset = 0, int $limit = 0){
        if(!empty($offset) && empty($limit)){
            $this->operateData['limit'] = ' LIMIT '.$offset;
        }else{
            $this->operateData['limit'] = ' LIMIT '.$offset.','.$limit;
        }
        return $this;
    }


    /**
     * where条件处理
     * @param $where    //where条件
     * @param array $params   //where条件对应的参数
     * @return $this
     * @throws \Exception
     */
    public function where($where,$params = []){
        if(empty($where)) return $this;   //如果没有传where参数直接返回
        if(is_string($where)){   //字符串
            $this->operateData['where'] = ' WHERE '.$where.' ';
            $this->params = array_merge($this->params,$params);
        }elseif(is_array($where)){  //数组
            $whereTemp = [];
            $count = 1;
            foreach($where as $key=>$whereItem){
                if(is_array($whereItem)){        //[['id','=',1]]
                    $whereCount = count($whereItem);
                    if($whereCount == 3){
                        $sign = trim(strtoupper($whereItem[1]));
                        if(in_array($sign,['IN','NOT IN'])){    //[['id','in',[1,2]]] 查询指定数据
                            if(!is_array($whereItem[2]))  throw new \Exception('in值应该传一个数组');
                            if(!empty($whereItem[2])){
                                $keysTemp = array_keys($whereItem[2]);
                                $valuesTemp = array_values($whereItem[2]);
                                $inArr = array_map(function($v) use ($whereItem,$count) {return $whereItem[0].$count.'_'.$v;}, $keysTemp);
                                $whereTemp[] = '`'.$whereItem[0].'` '.$sign.' (:'.join(',:',$inArr).')';
                                $this->params = array_merge($this->params,array_combine($inArr,$valuesTemp));
                            }else{   //[['id','in',[]]]  没有就查不出数据
                                $whereTemp[] = '`'.$whereItem[0].'` '.$sign.' ( null )';
                            }
                        }else{   //[['id','>','121']]等
                            $whereTemp[] = '`'.$whereItem[0].'` '.$sign.' '.':'.$whereItem[0].$count;
                            $this->params[$whereItem[0].$count] = $whereItem[2];
                        }
                    }elseif($whereCount == 1){
                        foreach($whereItem as $key2=>$whereItem2){      //[['id'=>1]]
                            $whereTemp[] = '`'.$key2.'` = :'.$key2.$count;
                            $this->params[$key2.$count] = $whereItem2;
                        }
                    }else{
                        throw new \Exception('where的写法有误');
                    }
                }else{     //['id'=>1]
                    $whereTemp[] = '`'.$key.'` = :'.$key.$count;
                    $this->params[$key.$count] = $whereItem;
                }
                $count++;
            }
            if(!empty($whereTemp)){
                $this->operateData['where'] = ' WHERE '.join(' AND ',$whereTemp).' ';
            }
        }
        return $this;
    }


    /**
     * 处理params针对 IN这种，传的是一个数组
     * author: scpzc
     * date: 2022/3/3 9:23
     * @param $params
     */
    private function params($params){
        foreach($params as $field => $paramItem){
            if(is_array($paramItem)){
                $tempKeys = [];
                foreach($paramItem as $key=>$param){
                    $tempKeys[] = ":".$field."_".$key;
                    $params[$field."_".$key] = $param;
                }
                unset($params[$field]);
                $this->sql = str_replace(":".$field,join(",",$tempKeys),$this->sql);
            }
        }
        return $params;
    }


    /**
     * 插入数据处理
     * @param $dataArray    //要操作的数据
     * @return $this
     * @throws \Exception
     */
    private function insertData($dataArray){
        if(empty($dataArray)){
            throw new \Exception('data参数不能为空');
        }
        $batchData = is_array($dataArray[0]??null)?$dataArray:[$dataArray];
        $insertValues = [];
        foreach($batchData as $dataKey1=>$data){
            $dataKeys = [];
            foreach($data as $dataKey2=>$dataValue) {
                $dataKey = 'data_'.$dataKey1.'_'.$dataKey2;  //参数KEY
                $dataKeys[] = ':'.$dataKey;   //绑定KEY
                $this->params[$dataKey] = $dataValue;
            }
            $insertValues[] = '(' . join(',', $dataKeys) . ')';
        }
        //插入的字段
        $insertFields = array_keys($batchData[0]);
        $this->operateData['insert_data'] = ' ( `' . join('`,`', $insertFields) . '` ) VALUES '.join(',',$insertValues);
        return $this;
    }



    /**
     * 更新的数据处理
     * @param $dataArray    //要操作的数据
     * @return $this
     * @throws \Exception
     */
    private function updateData($dataArray){
        if(empty($dataArray)){
            throw new \Exception('data参数不能为空');
        }
        $updateFields = [];
        foreach($dataArray as $dataKey=>$dataValue){
            $updateFields[] = '`'.$dataKey.'` = :'.'data_'.$dataKey;  //要更新的字段
            $this->params['data_'.$dataKey] = $dataValue;  //要更新的参数
        }
        $this->operateData['update_data'] = ' SET '.join(',',$updateFields);  //要更新的字段拼接成字符串
        return $this;
    }

    /**
     * 分页获取数据
     * @param $pageSize  //每页取的条数
     * @param callable $callback  //回调
     */
    public function chunk($pageSize, callable $callback){
        $page = 1;
        do{
            $operateData = $this->operateData;
            $runParams = $this->params;
            if(empty($operateData['order_by'])){
                throw new \Exception('请先使用order排序');
            }
            $result = $this->fetchByPage(null,null,null,$page,$pageSize);
            $resultCount = count($result['list']);
            if(empty($resultCount)) break;
            $callback($result['list'],$result['page']);
            unset($result);
            $page++;
            $this->operateData = $operateData;
            $this->params = $runParams;
        }while($resultCount == $pageSize);
        $this->resetParams();

    }

    /**
     * 获取单个值，可以使用原生
     *
     * @param array  $where
     * @param array  $params
     * @param string $fields
     *
     * @return mixed|null
     * @throws \Exception
     */
    public function fetchOne($where = null, $params = [], $fields = ''){
        $result = $this->fetchRow($where,$params,$fields);
        $result = is_array($result)?array_shift($result):null;
        return $result;
    }

    /**
     * 查询一条记录，可以使用原生
     *
     * @param array|string  $where
     * @param array  $params
     * @param string $fields
     *
     * @return mixed
     * @throws \Exception
     */
    public function fetchRow($where = null, $params = [], $fields = ''){
        $this->selectOperate($where,$params,$fields,'fetchRow');
        $this->sqlAndParams[] = ['sql'=>$this->sql,'params'=>$this->params];
        $result = $this->execute($this->sql,$this->params);
        $this->resetParams();
        $result = $this->objectToArray($result[0]??null);
        return $result;
    }


    /**
     * 查询符合条件的所有记录，可以使用原生
     *
     * @param array|string  $where
     * @param array  $params
     * @param string $fields
     *
     * @return array
     * @throws \Exception
     */
    public function fetchAll($where = null, $params = [], $fields = ''){
        $this->selectOperate($where,$params,$fields,'fetchAll');
        $this->sqlAndParams[] = ['sql'=>$this->sql,'params'=>$this->params];
        $result = $this->execute($this->sql, $this->params);
        $this->resetParams();
        return (array)$result;
    }


    /**
     * 分页取出数据
     * @param null $where
     * @param array $params
     * @param string $fields
     * @param int $page 当前页码
     * @param int $pageSize 每页的记录数
     * @return array
     */
    public function fetchByPage($where = null, $params = [], $fields = '',$page = 1,$pageSize = 20){
        $page = (int) $page;
        $pageSize = (int) $pageSize;
        if(empty($page)) $page = 1;
        if(empty($pageSize)) $pageSize = 20;
        $operateData = $this->operateData;
        $runParams = $this->params;
        $totalCount = $this->fetchOne($where,$params??[],'count(*)');
        $totalPage = ceil($totalCount / $pageSize);
        $page = max(min($totalPage,$page),1);
        $this->operateData = $operateData;
        $this->params = $runParams;
        //使用原生查询
        if(is_string($where) && strpos(strtoupper($where),'SELECT') !== false ) $where.= ' LIMIT '.($page-1)*$pageSize.','.$pageSize;
        $list = $this->limit(($page-1)*$pageSize,$pageSize)->fetchAll($where,$params??[],$fields??'');
        return [
            'total_count' => $totalCount,  //总记录
            'total_page'  => $totalPage,  //总页数
            'page'        => $page,   //当前页
            'page_size'   => $pageSize,  //每页条数
            'list'        => $list,  //查出的数据
        ];
    }


    /**
     * 获取总数，可以使用原生
     *
     * @param array|string $where
     * @param array  $params
     *
     * @return array|bool|mixed
     * @throws \Exception
     */
    public function count($where = null,$params = []){
        $this->selectOperate($where,$params,null,'count');
        $this->sqlAndParams[] = ['sql'=>$this->sql,'params'=>$this->params];
        $result = $this->execute($this->sql, $this->params);
        $this->resetParams();
        $result = $this->objectToArray($result[0]??null);
        $result = array_pop($result);
        return $result;
    }


    /**
     * 查询处理
     *
     *
     * @param        $where  //查询条件
     * @param        $params   //查询参数
     * @param        $fields   //查询字段
     * @param        $selectType  //查询类型fetchAll fetchRow count
     *
     * @return bool
     * @throws \Exception
     */
    private function selectOperate($where, $params, $fields, $selectType){
        if($fields == 'count(*)' && is_string($where)){
            $where = preg_replace('#SELECT(.*?)FROM#is','SELECT count(*) FROM',$where);
        }
        $sqlLower = '';
        if(is_string($where)) $sqlLower = trim(strtolower($where));  //如果是字符串转换一下
        //异常传值
        if (is_string($where) && (
                strpos($sqlLower, 'insert') === 0 ||
                strpos($sqlLower, 'update') === 0 ||
                strpos($sqlLower, 'delete') === 0
            )) {
            throw new \Exception('请使用execute');
        }
        //where传的是原生sql语句，如select * from table where ... 、 show tables ...
        if(is_string($where) && (
                strpos($sqlLower, 'select') === 0 ||
                strpos($sqlLower, 'show') === 0
            )) {
            $this->sql = $where;
            $this->params = $params;
        }else{
            //sqlOrWhere是where条件如['id'=>1]、'id = :id'
            $this->where($where,$params);
            if(!empty($fields)) $this->field($fields);
            if($selectType == 'fetchAll'){
                $this->sql = 'SELECT '.$this->operateData['field'].' FROM '.$this->operateData['table'].$this->operateData['where'].$this->operateData['order_by'].$this->operateData['limit'];
            }elseif($selectType == 'fetchRow'){
                $this->sql = 'SELECT '.$this->operateData['field'].' FROM '.$this->operateData['table'].$this->operateData['where'].$this->operateData['order_by'].' LIMIT 1';
            }elseif($selectType == 'count'){
                $this->sql = 'SELECT '.'count(*) FROM '.$this->operateData['table'].$this->operateData['where'].' LIMIT 1';
            }
        }
        $this->params = $this->params($this->params);
        return true;
    }


    /**
     * 删除数据
     *
     * @param null  $where  //删除条件
     * @param array $params  //删除参数
     *
     * @return array|int|mixed
     * @throws \Exception
     */
    public function delete($where = null,$params = []){
        $this->where($where,$params);
        try{
            $this->sql = 'DELETE FROM'.$this->operateData['table'].$this->operateData['where'];
            $this->sqlAndParams[] = ['sql'=>$this->sql,'params'=>$this->params];
            $result = $this->execute($this->sql, $this->params);
        }catch(\Throwable $e){
            $this->exception($e);
            $result = 0;
        }
        $this->resetParams();
        return $result;
    }


    /**
     * 插入数据
     * @param array $data
     *
     * @return int
     */
    public function insert($data = []){
        $this->insertData($data);
        try{
            $this->sql = 'INSERT INTO'.$this->operateData['table'].$this->operateData['insert_data'];
            $this->sqlAndParams[] = ['sql'=>$this->sql,'params'=>$this->params];
            $result = $this->execute($this->sql, $this->params);
        }catch(\Throwable $e){
            $this->exception($e);
            $result = false;
        }
        $this->resetParams();
        return $result;
    }

    /**
     * 更新数据
     *
     * @param array $data   //要更新的数据
     * @param null  $where   //更新的条件
     * @param null  $params  //更新的参数
     *
     * @return int|mixed
     */
    public function update($data = [],$where = null, $params = []){
        $this->updateData($data);
        $this->where($where,$params);
        if(empty($this->operateData['where'])) throw new \Exception('where条件不能为空');
        try{
            $this->sql = 'UPDATE'.$this->operateData['table'].$this->operateData['update_data'].$this->operateData['where'];
            $this->sqlAndParams[] = ['sql'=>$this->sql,'params'=>$this->params];
            $result = $this->execute($this->sql,$this->params);
        }catch(\Throwable $e){
            $this->exception($e);
            $result = false;
        }
        $this->resetParams();
        return $result;
    }



    /**
     * 执行原生SQL
     *
     * @param string $sql  SQL语句
     * @param array  $params SQL参数
     *
     * @return mixed
     */
    public function execute(string $sql = null, array $params = [])
    {
        if($this->debug == 1){
            var_dump($this->getSql());
        }else {
            $sql      = trim($sql);
            $sqlArray = explode(' ', $sql);
            switch (strtoupper(current($sqlArray))) {
                case 'SELECT':
                    $result = $this->db->select($sql, $params);
                    $result = !empty($result) ? $this->objectToArray($result) : [];
                    break;
                case 'SHOW':
                    $result = $this->db->select($sql, $params);
                    $result = !empty($result) ? $this->objectToArray($result) : [];
                    break;
                case 'INSERT':
                    //如果是批量插入，返回影响行数
                    $result = $this->db->affectingStatement($sql, $params);
                    //如果是插入一条，直接返回ID
                    if($result == 1) $result = $this->db->getPdo()->lastInsertId();
                    break;
                case 'UPDATE':
                    $result = $this->db->update($sql, $params);
                    break;
                case 'DELETE':
                    $result = $this->db->delete($sql, $params);
                    break;
                default:
                    $result = $this->db->statement($sql, $params);
                    break;
            }
            return $result;
        }
    }



    /**
     * 查询执行的SQL和参数方便排查问题
     */
    public function getSql(){
        foreach($this->sqlAndParams as $key=>$item){
            $sqlAndParams = $item['sql'];
            if(!empty($item['params'])){
                $item['params'] = array_reverse($item['params']);
                foreach($item['params'] as $paramKey=>$paramValue){
                    $sqlAndParams = str_replace(':'.$paramKey,"'".$paramValue."'",$sqlAndParams);
                }
            }
            if(!in_array($item['sql'],['begin transaction','commit','rollback'])){
                $this->sqlAndParams[$key]['sql_and_params'] = $sqlAndParams;
            }
        }
        return $this->sqlAndParams;
    }


    /**
     * 开启事务（支持事务嵌套）
     */
    public function startTrans(){
        $this->transNum++;
        if($this->transNum == 1){
            $this->sqlAndParams[] = ['sql'=>'begin transaction'];
            $this->db->beginTransaction();
        }
    }

    /**
     * 提交事务（支持事务嵌套）
     */
    public function commit(){
        if($this->transNum == 1) {
            $this->sqlAndParams[] = ['sql' => 'commit'];
            $this->db->commit();
        }
        $this->transNum--;
    }

    /**
     * 回滚事务（支持事务嵌套）
     */
    public function rollBack(){
        if($this->transNum == 1) {
            $this->sqlAndParams[] = ['sql' => 'rollback'];
            $this->db->rollBack();
        }
        $this->transNum--;
    }

    /**
     * 对象转化成数组
     * @param $object
     *
     * @return mixed
     */
    private function objectToArray($object){
        return json_decode(json_encode($object),true);
    }


}
