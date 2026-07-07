# ff-sql

SQL query builder, DDL generator, and database migration tool for ff framework (PHP 8.4+).

```
composer require veasin/ff-sql
```

## Query Builder

Chainable SQL query builder with automatic parameter binding.

```php
use ff\helpers\sql;

// SELECT
echo sql::table('user')->select();                   // SELECT * FROM `user`
echo sql::table('user')->select(['id', 'name']);     // SELECT `id`, `name` FROM `user`

// WHERE
sql::table('user')->where(['id' => 1, 'status' => 1])->select();
// SELECT * FROM `user` WHERE `id` = ? AND `status` = ?

// JOIN
$info = sql::table('info i');
sql::table('user')
    ->join($info, ['id' => 'id'])
    ->where($info['status']->equal(1))
    ->select();
// SELECT `user`.* FROM `user` LEFT JOIN `info` `i` ON (`i`.`id` = `user`.`id`) WHERE `i`.`status` = ?

// INSERT / UPDATE / DELETE
sql::table('user')->insert(['name' => 'vea', 'age' => 30]);
sql::table('user')->where(['id' => 1])->update(['name' => 'new name']);
sql::table('user')->where(['id' => 1])->delete();

// Expressions
sql::table('user')->select([sql::COUNT('*')->as('total')]);
// SELECT COUNT(*) `total` FROM `user`

// OR / AND grouping
$t = sql::table('article');
$t->where($t['status']->eq(1), $t['id']->eq(4)->or($t['id']->eq(5)))->select();
// SELECT * FROM `article` WHERE `status` = ? AND (`id` = ? OR `id` = ?)
```

Parameters are collected in `$sql->params`:

```php
$sql = sql::table('user')->where(['id' => 1])->select();
$sql->params; // [1]
```

## DDL Generator

Generate MySQL DDL statements programmatically.

```php
use ff\helpers\ddl\table;

// CREATE TABLE
echo new table('user')->create([
    'id'   => ['type' => 'INT', 'auto' => true],
    'name' => ['type' => 'VARCHAR(100)', 'null' => false, 'comment' => '姓名'],
    'email'=> ['type' => 'VARCHAR(200)', 'unique' => true],
]);
/*
CREATE TABLE `user` (
  `id` INT AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL COMMENT '姓名',
  `email` VARCHAR(200),
  UNIQUE INDEX `uk_email` (`email`),
  PRIMARY KEY (`id`)
);
*/

// ALTER TABLE — add / modify / drop columns
echo new table('user')->fields(
    add: ['age' => ['type' => 'INT']],
    modify: ['name' => ['type' => 'VARCHAR(200)']],
    drop: ['phone'],
);
// ALTER TABLE `user` ADD COLUMN `age` INT, MODIFY COLUMN `name` VARCHAR(200), DROP COLUMN `phone`;

// Index management
echo new table('user')->index(
    add: ['idx_name' => ['columns' => ['name'], 'type' => 'INDEX']],
    modify: ['PRIMARY' => ['columns' => ['id', 'uuid'], 'type' => 'PRIMARY']],
    drop: ['uk_email'],
);

// Schema round-trip
$schema = new table('user')->create([...])->toSchema();           // table → array
$tables = table::fromSql("CREATE TABLE `user` (...)");           // SQL → table[]
```

## Migration Tool

Track and apply database schema changes over time.

```bash
# 初始化
php tools/migrate.php init

# 修改数据库后保存版本
php tools/migrate.php save -m "add users table"

# 查看状态
php tools/migrate.php check
php tools/migrate.php list
php tools/migrate.php diff

# 部署到另一台机器
php tools/migrate.php apply

# 回滚未提交变更
php tools/migrate.php clean

# 从头重建
php tools/migrate.php reset
```

See [tools/migrate.md](tools/migrate.md) for full documentation.

