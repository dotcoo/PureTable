<?php
// 定义常量
define("APP_MYSQL", empty($argv[1]) ? true : ($argv[1] === "true" || $argv[1] === "yes"));

// 断言
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_WARNING, 1);
assert_options(ASSERT_BAIL, 1);

// 数据库表结构
$database_init = <<< SQL
DROP TABLE IF EXISTS `table_user`;
CREATE TABLE `table_user` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`username` varchar(45) NOT NULL,
`password` varchar(45) NOT NULL,
`nickname` varchar(45) NOT NULL,
`r` tinyint(4) NOT NULL,
PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `table_blog`;
CREATE TABLE `table_blog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(45) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SQL;

// 初始化
header("Content-Type: text/html; charset=utf-8");

// 引入Table类
require __DIR__ . "/Table.php";

// 配置连接信息
Table::$__dsn 		= "mysql:host=%s;dbname=%s;charset=%s;";
Table::$__host 		= "127.0.0.1";
Table::$__username 	= "root";
Table::$__password 	= "123456";
Table::$__dbname 	= "purephp_test";
Table::$__charset 	= "utf8";

// 表前缀
Table::$__prefix 	= "table_";

// 创建实体对象
$userTable = new Table("user", "id");
$blogTable = new Table("blog", "id");

// 设置数据库类型
$userTable->is_mysql(APP_MYSQL);
$blogTable->is_mysql(APP_MYSQL);

// 设置别名
$userTable->alias("u");
$blogTable->alias("b");

// Table类是连接数据库是懒惰模式 传入true初始化PDO
$userTable->pdo(true);

// 初始化数据库
Table::$__pdo->exec($database_init);

// sql查询
$sql = "SELECT * FROM table_user WHERE id > ? AND id < ?";
$stmt = $userTable->query($sql, 10, 20);
assert(!empty($stmt) && $stmt->queryString === $sql && $stmt->executeResult === true);
$stmt->fetchAll();

// 插入数据
$user = array(
	"username" => "admin1",
	"password" => "admin1",
	"nickname" => "管理员1",
	"r" => 1,
);
$result = $userTable->insert($user);
if ($userTable->is_mysql()) {
	assert($userTable->_sql === "INSERT INTO `table_user` SET `username` = ?, `password` = ?, `nickname` = ?, `r` = ?");
} else {
	assert($userTable->_sql === "INSERT INTO `table_user` (`username`, `password`, `nickname`, `r`) VALUES (?, ?, ?, ?)");
}
assert(array_equal($userTable->_params, array("admin1", "admin1", "管理员1", 1)));
assert($result->rowCount() === 1);
assert($userTable->lastInsertId() === "1");

// 批量插入数据
$fields = array("username","password","nickname","r");
$rows = array();
for ($i = 2; $i <= 10; $i++) {
	$rows[] = array("admin$i", "admin$i", "管理员$i", $i % 3);
}
$userTable->batchInsert($fields, $rows);
assert($userTable->_sql === "INSERT INTO `table_user` (`username`,`password`,`nickname`,`r`) VALUES (?,?,?,?),(?,?,?,?),(?,?,?,?),(?,?,?,?),(?,?,?,?),(?,?,?,?),(?,?,?,?),(?,?,?,?),(?,?,?,?)");
assert(array_equal($userTable->_params, array("admin2", "admin2", "管理员2", 2, "admin3", "admin3", "管理员3", 0, "admin4", "admin4", "管理员4", 1, "admin5", "admin5", "管理员5", 2, "admin6", "admin6", "管理员6", 0, "admin7", "admin7", "管理员7", 1, "admin8", "admin8", "管理员8", 2, "admin9", "admin9", "管理员9", 0, "admin10", "admin10", "管理员10", 1)));

// 修改数据
$user = array(
	"username" => "admin4-1",
	"password" => "admin4-1",
	"nickname" => "管理员4-1",
	"r" => 5,
);
$result = $userTable->where("id = ?", 4)->update($user);
assert($userTable->_sql === "UPDATE `table_user` SET `username` = ?, `password` = ?, `nickname` = ?, `r` = ? WHERE id = ?");
assert(array_equal($userTable->_params, array("admin4-1", "admin4-1", "管理员4-1", 5, 4)));
assert($result->rowCount() === 1);

// replace数据
$user = array(
	"id" => 4,
	"username" => "admin4",
	"password" => "admin4",
	"nickname" => "管理员4",
	"r" => 4,
);
$result = $userTable->replace($user);
assert($userTable->_sql === "REPLACE INTO `table_user` SET `id` = ?, `username` = ?, `password` = ?, `nickname` = ?, `r` = ?");
assert(array_equal($userTable->_params, array(4, "admin4", "admin4", "管理员4", 4)));
assert($result->rowCount() === 2);

// 删除数据
$result = $userTable->where("id = ?", 4)->delete();
assert($userTable->_sql === "DELETE FROM `table_user` WHERE id = ?");
assert(array_equal($userTable->_params, array(4)));
assert($result->rowCount() === 1);

// 查询数据
$userTable->select()->fetchAll(); // 获取所有数据
assert($userTable->_sql === "SELECT * FROM `table_user`");
assert(array_equal($userTable->_params, array()));
$userTable->select()->fetch(); // 获取一行数据
$userTable->select()->fetchColumn(); // 获取第一行第一列数据
$userTable->select()->fetchColumn(1); // 获取第一行第二列数据

// 多where条件
$userTable->where("id > ?", 4)->where("id IN (?)", array(5,7,9))->select()->fetchAll();
assert($userTable->_sql === "SELECT * FROM `table_user` WHERE id > ? AND id IN (?,?,?)");
assert(array_equal($userTable->_params, array(4, 5, 7, 9)));

// 分组 过滤
$userTable->group("r")->having("c BETWEEN ? AND ?", 2, 4)->having("c > ?", 1)->select("*, r, count(*) AS c")->fetchAll();
assert($userTable->_sql === "SELECT *, r, count(*) AS c FROM `table_user` GROUP BY r HAVING c BETWEEN ? AND ? AND c > ?");
assert(array_equal($userTable->_params, array(2, 4, 1)));

// 排序
$userTable->order("username, id DESC")->select()->fetchAll();
assert($userTable->_sql === "SELECT * FROM `table_user` ORDER BY username, id DESC");
assert(array_equal($userTable->_params, array()));

// 限制行数
$userTable->limit(3)->offset(3)->select()->fetchAll();
assert($userTable->_sql === "SELECT * FROM `table_user` LIMIT ? OFFSET ?");
assert(array_equal($userTable->_params, array(3, 3)));

// 分页
$userTable->page(3, 3)->select()->fetchAll();
assert($userTable->_sql === "SELECT * FROM `table_user` LIMIT ? OFFSET ?");
assert(array_equal($userTable->_params, array(3, 6)));

// 条件 分页 总行数
$userTable->calcFoundRows()->where("r = ?", 2)->order("id DESC")->page(2, 3)->select()->fetchAll();
if ($userTable->is_mysql()) {
	assert($userTable->_sql === "SELECT SQL_CALC_FOUND_ROWS * FROM `table_user` WHERE r = ? ORDER BY id DESC LIMIT ? OFFSET ?");
	assert(array_equal($userTable->_params, array(2, 3, 3)));
} else {
	assert($userTable->_sql === "SELECT * FROM `table_user` WHERE r = ? ORDER BY id DESC LIMIT ? OFFSET ?");
	assert(array_equal($userTable->_params, array(2, 3, 3)));
}

// count
assert($userTable->count() === 3);
if ($userTable->is_mysql()) {
	assert($userTable->_sql === "SELECT FOUND_ROWS()");
	assert(array_equal($userTable->_params, array()));
} else {
	assert($userTable->_sql === "SELECT count(*) FROM `table_user` WHERE r = ?");
	assert(array_equal($userTable->_params, array(2)));
}

// 复杂查询
$userTable->where("id > ?", 0)->where("id < ?", 100)
	->group("r")->having("c BETWEEN ? AND ?", 1, 100)->having("c > ?", 1)
	->order("c DESC")->page(2, 3)->select("*, count(*) AS c")->fetchAll();
assert($userTable->_sql === "SELECT *, count(*) AS c FROM `table_user` WHERE id > ? AND id < ? GROUP BY r HAVING c BETWEEN ? AND ? AND c > ? ORDER BY c DESC LIMIT ? OFFSET ?");
assert(array_equal($userTable->_params, array(0, 100, 1, 100, 1, 3, 3)));

// 联合查询
$blogTable->join("user AS u", "b.user_id = u.id")->where("b.id < ?", 20)->select("b.*, u.username")->fetchAll();
assert($blogTable->_sql === "SELECT b.*, u.username FROM `table_blog` AS `b` LEFT JOIN `table_user` AS `u` ON b.user_id = u.id WHERE b.id < ?");
assert(array_equal($blogTable->_params, array(20)));

// 根据主键查询数据
$userTable->find(4);
assert($userTable->_sql === "SELECT * FROM `table_user` WHERE `id` = ?");
assert(array_equal($userTable->_params, array(4)));

// 添加数据
$user = array(
	"username" => "admin9998",
	"password" => "admin9998",
	"nickname" => "管理员9998",
	"r" => 0,
);
$result = $userTable->add($user);
if ($userTable->is_mysql()) {
	assert($userTable->_sql === "INSERT INTO `table_user` SET `username` = ?, `password` = ?, `nickname` = ?, `r` = ?");
} else {
	assert($userTable->_sql === "INSERT INTO `table_user` (`username`, `password`, `nickname`, `r`) VALUES (?, ?, ?, ?)");
}
assert(array_equal($userTable->_params, array("admin9998", "admin9998", "管理员9998", 0)));
assert($result->rowCount() === 1);
assert($userTable->lastInsertId() === "11");

// 根据主键修改数据
$user = array(
	"id" => 11,
	"username" => "admin9998-1",
	"password" => "admin9998-1",
	"nickname" => "管理员9998-1",
	"r" => 0,
);
$userTable->edit($user);
assert($userTable->_sql === "UPDATE `table_user` SET `username` = ?, `password` = ?, `nickname` = ?, `r` = ? WHERE `id` = ?");
assert(array_equal($userTable->_params, array("admin9998-1", "admin9998-1", "管理员9998-1", 0, 11)));
assert($result->rowCount() === 1);

// 根据主键删除数据
$userTable->del(11);
assert($userTable->_sql === "DELETE FROM `table_user` WHERE `id` = ?");
assert(array_equal($userTable->_params, array(11)));
assert($result->rowCount() === 1);

// 保存 修改
$user = array("id" => 3, "nickname" => "管理员3-3");
$result = $userTable->save($user);
assert($userTable->_sql === "UPDATE `table_user` SET `nickname` = ? WHERE `id` = ?");
assert(array_equal($userTable->_params, array("管理员3-3", 3)));
assert($result->rowCount() === 1);

// 保存 添加
$user = array(
	"username" => "admin9999",
	"password" => "admin9999",
	"nickname" => "管理员9999",
	"r" => 0,
);
$result = $userTable->save($user);
if ($userTable->is_mysql()) {
	assert($userTable->_sql === "INSERT INTO `table_user` SET `username` = ?, `password` = ?, `nickname` = ?, `r` = ?");
} else {
	assert($userTable->_sql === "INSERT INTO `table_user` (`username`, `password`, `nickname`, `r`) VALUES (?, ?, ?, ?)");
}
assert(array_equal($userTable->_params, array("admin9999", "admin9999", "管理员9999", 0)));
assert($result->rowCount() === 1);
assert($userTable->lastInsertId() === "12");

// 加一
$userTable->where("`id` = ?", 2)->plus("r");
assert($userTable->_sql === "UPDATE `table_user` SET `r` = `r` + ? WHERE `id` = ?");
assert(array_equal($userTable->_params, array(1, 2)));

// 减一
$userTable->where("`id` = ?", 2)->plus("r", -1);
assert($userTable->_sql === "UPDATE `table_user` SET `r` = `r` + ? WHERE `id` = ?");
assert(array_equal($userTable->_params, array(-1, 2)));

// 多列
$userTable->where("`id` = ?", 2)->plus("r", 1, "r", -1);
assert($userTable->_sql === "UPDATE `table_user` SET `r` = `r` + ?, `r` = `r` + ? WHERE `id` = ?");
assert(array_equal($userTable->_params, array(1, -1, 2)));

// 加一
$userTable->where("`id` = ?", 2)->incr("r");
assert($userTable->_sql === "UPDATE `table_user` SET `r` = last_insert_id(`r` + ?) WHERE `id` = ?");
assert(array_equal($userTable->_params, array(1, 2)));

// 减一
$userTable->where("`id` = ?", 2)->incr("r", -1);
assert($userTable->_sql === "UPDATE `table_user` SET `r` = last_insert_id(`r` + ?) WHERE `id` = ?");
assert(array_equal($userTable->_params, array(-1, 2)));

// 生成外键测试数据
$users = $userTable->select("id")->fetchAll();
$id = 0;
foreach ($users as $user) {
	for ($i=0; $i<10; $i++) {
		$id++;
		$blog = array(
				"user_id" => $user["id"],
				"title" => "blog$id",
		);
		$blogTable->insert($blog);
	}
}

// 外键
$blogs = $blogTable->where("id IN (?)", array(1, 12, 23, 34, 45, 56, 67, 78, 89, 99))->select()->fetchAll(); // 获取主表数据
assert($blogTable->_sql === "SELECT * FROM `table_blog` WHERE id IN (?,?,?,?,?,?,?,?,?,?)");
assert(array_equal($blogTable->_params, array(1, 12, 23, 34, 45, 56, 67, 78, 89, 99)));
$userTable->foreignKey($blogs, "user_id", "*, id")->fetchAll(PDO::FETCH_UNIQUE); // 获取外表数据 关联数据
assert($userTable->_sql === "SELECT *, id FROM `table_user` WHERE `id` IN (?,?,?,?,?,?,?,?,?,?)");
assert(array_equal($userTable->_params, array(1, 2, 3, 5, 6, 7, 8, 9, 10, 12)));
$userTable->foreignKey($blogs, "user_id", "id, username")->fetchAll(PDO::FETCH_KEY_PAIR); // 获取外表数据 键值数据
assert($userTable->_sql === "SELECT id, username FROM `table_user` WHERE `id` IN (?,?,?,?,?,?,?,?,?,?)");
assert(array_equal($userTable->_params, array(1, 2, 3, 5, 6, 7, 8, 9, 10, 12)));

// PDO fetch 示例
$userTable->select("*, id")->fetchAll(PDO::FETCH_UNIQUE); // 获取映射数据
$userTable->select("nickname")->fetchAll(PDO::FETCH_COLUMN); // 获取数组
$userTable->select("id, nickname")->fetchAll(PDO::FETCH_KEY_PAIR); // 获取键值对
$userTable->select("r, id, nickname")->fetchAll(PDO::FETCH_GROUP); // 获取数据分组
$userTable->select("r, id")->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_COLUMN); // 获取数据分组
$userTable->select("r, nickname")->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_KEY_PAIR); // 获取数据分组
$userTable->select()->fetchAll(PDO::FETCH_OBJ); // 获取对象 指定获取方式，将结果集中的每一行作为一个属性名对应列名的对象返回。
$userTable->select()->fetchAll(PDO::FETCH_CLASS); // 获取对象 指定获取方式，返回一个所请求类的新实例，映射列到类中对应的属性名。 Note: 如果所请求的类中不存在该属性，则调用 __set() 魔术方法
$userTable->select()->fetchAll(PDO::FETCH_FUNC, function($id, $username, $password, $r){ // 获取自定义行
	return array("id"=>$id, "name"=>"$username - $password - $r");
});
$userTable->select()->fetchAll(PDO::FETCH_FUNC, function($id, $username, $password, $r){ //  获取单一值
	return "$id - $username - $password - $r";
});

function array_equal($a, $b) {
	return serialize($a) === serialize($b);
}
