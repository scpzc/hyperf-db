#hyperf轻量级DB

##1.基本用法
###1.1查询
```phpregexp
$count = Db::table('user')->count($where,$params); #查询记录数
$userInfo = Db::table('user')->fetchRow($where,$params,$fields); #查询单条
$userList = Db::table('user')->fetchAll($where,$params,$fields); #查询多条
$username = Db::table('user')->fetchOne($where,$params,$fields); #查询某列
$result = Db::table('user')->fetchByPay($where,$params,$fields,$page,$pageSize); #查询分页
```
以上语句支持原生写法，如：查询单条
```phpregexp
$userInfo = Db::fetchRow('SELECT username * FROM user_id=:user_id',['user_id'=>1]);
```
###1.2新增
```phpregexp
$data = ['username'=>'张三','age'=>10];
$id = Db::table('user')->insert($data); #返回自增ID，只支持单条
```
###1.3修改
```phpregexp
$data = ['username'=>'张三','age'=>10];
$where = ['user_id'=>1];
$result = Db::table('user')->update($data,$where); #返回修改记录数，失败返回false，修改0条返回0
```
###1.4删除
```phpregexp
$result = Db::table('user')->delete($where); #返回删除记录数
```
###1.5原生操作
```phpregexp
Db::table('user')->execute($sql,$params);
```
###1.6选择不同的数据库连接
```phpregexp
Db::connect('log')->table('user')->insert($data);
```

##2.WHERE用法
###2.1 数组查询
```phpregexp
$where = ['user_id'=>1];
$where = [['user_id','=',1]];
$where = [
    ['user_id','>',1],
    ['user_id','<',10],
];
$where = [
    ['user_id','in',[1,2]],
    ['user_id','not in',[3,4]],
];
```
###2.2 绑定参数
```phpregexp
$where = "user_id = :user_id and age > :age";
$params = ['user_id'=>1,'age'=>10];
```
###2.3 直接使用原生+绑定参数
```phpregexp
$sql = "SELECT username FROM user WHERE user_id = :user_id";
$params = ['user_id'=>1];
```

##3.异常处理
增，删，改，都不会抛出异常，如果需要处理异常，请在config\autoload\exceptions.php
配置异常处理，如下：
```phpregexp
return [
    'handler' => [
        'http' => [
            ...
        ],
    ],
    ...
    'db'=>App\Exception\Handler\DefaultExceptionHandler::class,
];

```

##4.SQL语句调试
###4.1 输出sql语句
```phpregexp
Db::table('user')->fetchRow($where); 
var_dump(Db::getSql()); #SQL语句会执行，如果有语法错误，可能不能输出
```
###4.2调式sql语句
```phpregexp
Db::table('user')->debug()->fetchRow($where); #SQL语句不会执行，会直接在控制台输出SQL语句
```

##5.left join等查询
对于两表以上的联表查询，只能自己写原生语句

##6.事务
```phpregexp
Db::startTrans()  #开始事务
Db::commit()  #提交事务
Db::rollBack()  #回滚事务
```