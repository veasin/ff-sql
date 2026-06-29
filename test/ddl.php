<?php

use ff\helpers\ddl\table;
use function ff\test;

include_once __DIR__ . '/../vendor/autoload.php';
error_reporting(E_ALL);
// alter
test('alter.重命名', (string)new table('user')->alter(['rename' => 'users']), 'ALTER TABLE `user` RENAME TO `users`;');
test('alter.注释', (string)new table('user')->alter(['comment' => '用户表']), 'ALTER TABLE `user` COMMENT = `用户表`;');
test('alter.引擎', (string)new table('user')->alter(['engine' => 'InnoDB']), 'ALTER TABLE `user` ENGINE = INNODB;');
test('alter.字符集', (string)new table('user')->alter(['charset' => 'utf8mb4']), 'ALTER TABLE `user` DEFAULT CHARSET = utf8mb4;');
test('alter.自增', (string)new table('user')->alter(['autoIncrement' => 1000]), 'ALTER TABLE `user` AUTO_INCREMENT = 1000;');
test('alter.复合配置',
	(string)new table('user')->alter(['engine' => 'InnoDB', 'charset' => 'utf8mb4', 'comment' => '用户表']),
	'ALTER TABLE `user` COMMENT = `用户表`, ENGINE = INNODB, DEFAULT CHARSET = utf8mb4;'
);
// create
test('create.基本', (string)new table('user')->create(['id' => ['type' => 'INT'], 'name' => ['type' => 'VARCHAR(100)']]), "CREATE TABLE `user` (\n  `id` INT,\n  `name` VARCHAR(100)\n);");
test('create.非空',
	(string)new table('user')->create(['id' => ['type' => 'INT', 'null' => false], 'name' => ['type' => 'VARCHAR(100)', 'null' => false]]),
	"CREATE TABLE `user` (\n  `id` INT NOT NULL,\n  `name` VARCHAR(100) NOT NULL\n);"
);
test('create.默认值',
	(string)new table('user')->create(['id' => ['type' => 'INT', 'default' => 0], 'status' => ['type' => 'INT', 'default' => 1]]),
	"CREATE TABLE `user` (\n  `id` INT DEFAULT 0,\n  `status` INT DEFAULT 1\n);"
);
test('create.注释',
	(string)new table('user')->create(['id' => ['type' => 'INT', 'comment' => '主键'], 'name' => ['type' => 'VARCHAR(100)', 'comment' => '姓名']]),
	"CREATE TABLE `user` (\n  `id` INT COMMENT `主键`,\n  `name` VARCHAR(100) COMMENT `姓名`\n);"
);
test('create.自增主键',
	(string)new table('user')->create(['id' => ['type' => 'INT', 'auto' => true], 'name' => ['type' => 'VARCHAR(100)']]),
	"CREATE TABLE `user` (\n  `id` INT AUTO_INCREMENT,\n  `name` VARCHAR(100),\n  PRIMARY KEY (`id`)\n);"
);
test('create.首位',
	(string)new table('user')->create(['id' => ['type' => 'INT'], 'name' => ['type' => 'VARCHAR(100)', 'pos' => 'first']]),
	"CREATE TABLE `user` (\n  `id` INT,\n  `name` VARCHAR(100) FIRST\n);"
);
test('create.位置',
	(string)new table('user')->create(['id' => ['type' => 'INT'], 'name' => ['type' => 'VARCHAR(100)', 'pos' => 'id'], 'email' => ['type' => 'VARCHAR(200)', 'pos' => 'name']]),
	"CREATE TABLE `user` (\n  `id` INT,\n  `name` VARCHAR(100) AFTER `id`,\n  `email` VARCHAR(200) AFTER `name`\n);"
);
test('create.自增类型',
	(string)new table('user')->create(['id' => ['type' => 'AUTO_INCREMENT'], 'name' => ['type' => 'VARCHAR(100)']]),
	"CREATE TABLE `user` (\n  `id` AUTO_INCREMENT,\n  `name` VARCHAR(100),\n  PRIMARY KEY (`id`)\n);"
);
// fields
test('fields.添加', (string)new table('user')->fields(['email' => ['type' => 'VARCHAR(200)']], [], []), "ALTER TABLE `user` ADD COLUMN `email` VARCHAR(200);");
test('fields.添加首位', (string)new table('user')->fields(['email' => ['type' => 'VARCHAR(200)', 'pos' => 'first']], [], []), "ALTER TABLE `user` ADD COLUMN `email` VARCHAR(200) FIRST;");
test('fields.添加位置', (string)new table('user')->fields(['email' => ['type' => 'VARCHAR(200)', 'pos' => 'id']], [], []), "ALTER TABLE `user` ADD COLUMN `email` VARCHAR(200) AFTER `id`;");
test('fields.修改', (string)new table('user')->fields([], ['name' => ['type' => 'VARCHAR(200)', 'null' => false]], []), "ALTER TABLE `user` MODIFY COLUMN `name` VARCHAR(200) NOT NULL;");
test('fields.删除', (string)new table('user')->fields([], [], ['email', 'phone']), "ALTER TABLE `user` DROP COLUMN `email`,\n  DROP COLUMN `phone`;");
test('fields.混合操作',
	(string)new table('user')->fields(['age' => ['type' => 'INT']], ['name' => ['type' => 'VARCHAR(200)']], ['phone']),
	"ALTER TABLE `user` ADD COLUMN `age` INT,\n  MODIFY COLUMN `name` VARCHAR(200),\n  DROP COLUMN `phone`;"
);
// fields 命名参数
test('fields.命名参数-添加', (string)new table('user')->fields(add: ['email' => ['type' => 'VARCHAR(200)']]), "ALTER TABLE `user` ADD COLUMN `email` VARCHAR(200);");
test('fields.命名参数-修改', (string)new table('user')->fields(modify: ['name' => ['type' => 'VARCHAR(200)', 'null' => false]]), "ALTER TABLE `user` MODIFY COLUMN `name` VARCHAR(200) NOT NULL;");
test('fields.命名参数-删除', (string)new table('user')->fields(drop: ['email', 'phone']), "ALTER TABLE `user` DROP COLUMN `email`,\n  DROP COLUMN `phone`;");
test('fields.命名参数-添加修改',
	(string)new table('user')->fields(add: ['age' => ['type' => 'INT']], modify: ['name' => ['type' => 'VARCHAR(200)']]),
	"ALTER TABLE `user` ADD COLUMN `age` INT,\n  MODIFY COLUMN `name` VARCHAR(200);"
);
test('fields.命名参数-修改删除',
	(string)new table('user')->fields(modify: ['name' => ['type' => 'VARCHAR(200)']], drop: ['phone']),
	"ALTER TABLE `user` MODIFY COLUMN `name` VARCHAR(200),\n  DROP COLUMN `phone`;"
);
test('fields.命名参数-全部',
	(string)new table('user')->fields(add: ['age' => ['type' => 'INT']], modify: ['name' => ['type' => 'VARCHAR(200)']], drop: ['phone']),
	"ALTER TABLE `user` ADD COLUMN `age` INT,\n  MODIFY COLUMN `name` VARCHAR(200),\n  DROP COLUMN `phone`;"
);
// index
test('index.普通索引', (string)new table('user')->index(['idx_name' => ['columns' => ['name'], 'type' => 'INDEX']], [], []), "ALTER TABLE `user` ADD INDEX `idx_name` (`name`);");
test('index.唯一索引', (string)new table('user')->index(['uk_email' => ['columns' => ['email'], 'type' => 'UNIQUE']], [], []), "ALTER TABLE `user` ADD UNIQUE INDEX `uk_email` (`email`);");
test('index.主键索引', (string)new table('user')->index(['pk_id' => ['columns' => ['id'], 'type' => 'PRIMARY']], [], []), "ALTER TABLE `user` ADD PRIMARY KEY (`id`);");
test('index.全文索引', (string)new table('user')->index(['ft_content' => ['columns' => ['content'], 'type' => 'FULLTEXT']], [], []), "ALTER TABLE `user` ADD FULLTEXT INDEX `ft_content` (`content`);");
test('index.外键索引',
	(string)new table('user')->index(['fk_user' => ['columns' => ['user_id'], 'type' => 'FOREIGN', 'refs' => ['users', 'id']]], [], []),
	"ALTER TABLE `user` ADD FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);"
);
test('index.多列索引',
	(string)new table('user')->index(['idx_name_status' => ['columns' => ['name', 'status'], 'type' => 'INDEX']], [], []),
	"ALTER TABLE `user` ADD INDEX `idx_name_status` (`name`, `status`);"
);
test('index.修改索引',
	(string)new table('user')->index([], ['idx_name' => ['columns' => ['name', 'email'], 'type' => 'INDEX']], []),
	"ALTER TABLE `user` DROP INDEX `idx_name`, ADD INDEX `idx_name` (`name`, `email`);"
);
test('index.修改主键', (string)new table('user')->index([], ['pk_id' => ['columns' => ['id', 'uuid'], 'type' => 'PRIMARY']], []), "ALTER TABLE `user` DROP PRIMARY KEY, ADD PRIMARY KEY (`id`, `uuid`);");
test('index.删除索引', (string)new table('user')->index([], [], ['idx_name', 'uk_email']), "ALTER TABLE `user` DROP INDEX `idx_name`,\n  DROP INDEX `uk_email`;");
test('index.删除主键', (string)new table('user')->index([], [], ['PRIMARY']), "ALTER TABLE `user` DROP PRIMARY KEY;");
// drop
test('drop.基本', (string)new table('user')->drop(), 'DROP TABLE `user`;');
test('drop.条件删除', (string)new table('user')->drop(true), 'DROP TABLE IF EXISTS `user`;');
test('drop.级联删除', (string)new table('user')->drop(false, true), 'DROP TABLE `user` CASCADE;');
test('drop.条件级联', (string)new table('user')->drop(true, true), 'DROP TABLE IF EXISTS `user` CASCADE;');
// truncate
test('truncate.基本', (string)new table('user')->truncate(), 'TRUNCATE TABLE `user`;');
test('truncate.重置自增', (string)new table('user')->truncate(true), 'TRUNCATE TABLE `user` RESTART IDENTITY;');
// 链式操作
test('chain.建表后修改',
	(string)new table('user')->create(['id' => ['type' => 'INT', 'auto' => true]])->alter(['comment' => '用户表']),
	"CREATE TABLE `user` (\n  `id` INT AUTO_INCREMENT,\n  PRIMARY KEY (`id`)\n);\nALTER TABLE `user` COMMENT = `用户表`;"
);
test('chain.建表后添加字段',
	(string)new table('user')->create(['id' => ['type' => 'INT']])->fields(['name' => ['type' => 'VARCHAR(100)']], [], []),
	"CREATE TABLE `user` (\n  `id` INT\n);\nALTER TABLE `user` ADD COLUMN `name` VARCHAR(100);"
);
test('chain.建表后添加索引',
	(string)new table('user')->create(['id' => ['type' => 'INT'], 'email' => ['type' => 'VARCHAR(100)']])->index(['uk_email' => ['columns' => ['email'], 'type' => 'UNIQUE']], [], []),
	"CREATE TABLE `user` (\n  `id` INT,\n  `email` VARCHAR(100)\n);\nALTER TABLE `user` ADD UNIQUE INDEX `uk_email` (`email`);"
);
