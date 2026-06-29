<?php

use ff\helpers\sql;
use function ff\test;

include_once __DIR__ . '/../vendor/autoload.php';
error_reporting(E_ALL);

$user = sql::table('user');
$info = sql::table('info i');

// work: select with options
test(
	'work',
	(string)$user->select([123, 'id', 'name'], [sql::OPTS_DISTINCT, sql::OPTS_HIGH_PRIORITY, sql::OPTS_SQL_NO_CACHE]),
	'SELECT DISTINCT HIGH_PRIORITY SQL_NO_CACHE ?, `id`, `name` FROM `user`'
);

// join clone
test(
	'join clone',
	(string)(clone $user)
		->select([123, 'id', 'name'], [sql::OPTS_DISTINCT])
		->join($info, ['id' => 'id'], [])
		->join($info->as('a')->select(), ['user_id' => 'id'])
		->join($info->as('b')->select(['id', 'name']), 'id')
		->join($info->as('c')->select('c'))
		->select(null),
	'SELECT `a`.*, `b`.`id`, `b`.`name`, `c`.`c` FROM `user` LEFT JOIN `info` `i` ON (`i`.`id` = `user`.`id`) LEFT JOIN `info` `a` ON (`a`.`user_id` = `user`.`id`) LEFT JOIN `info` `b` ON (`b`.`id` = `user`.`id`) LEFT JOIN `info` `c` ON (`c`.`id` = `user`.`id`)'
);

// join + params
$user = sql::table('user');
$info = sql::table('info i');
$sql = $user->join($info->select(['b']), ['id', 'num' => 123])->select()->where($info['c']->equal(567));
test('join', (string)$sql, 'SELECT `user`.*, `i`.`b` FROM `user` LEFT JOIN `info` `i` ON (`i`.`id` = `user`.`id` AND `i`.`num` = ?) WHERE `i`.`c` = ?');
test('join.params', $sql->params, [123, 567]);

// update
$user = sql::table('user');
$info = sql::table('info i');

$sql = $user->update(['id' => 1, 'name' => 'vea'], []);
test('update.basic', (string)$sql, 'UPDATE `user` SET `id` = ?, `name` = ?');
test('update.params', $sql->params, [1, 'vea']);

$sql->update(['id' => 1, 'name' => 'vea']);
test('update.chain', (string)$sql, 'UPDATE `user` SET `id` = ?, `name` = ?');
test('update.chain.params', $sql->params, [1, 'vea']);

$sql->where()->update(['id' => 1, 'name' => 'vea'])->sort(['id' => 'desc'])->limit(1);
test('update.full', (string)$sql, 'UPDATE `user` SET `id` = ?, `name` = ? ORDER BY `id` DESC LIMIT 1');
test('update.full.params', $sql->params, [1, 'vea']);

$sql = $info->update(['id' => 1, 'password' => $info['salt']->md5()]);
test('update.func', (string)$sql, 'UPDATE `info` SET `id` = ?, `password` = MD5(`salt`)');
test('update.func.params', $sql->params, [1]);

$sql->where($info['id']->equal(123))->update(['id' => 1, 'password' => $info['salt']->MD5()]);
test('update.where.func', (string)$sql, 'UPDATE `info` SET `id` = ?, `password` = MD5(`salt`) WHERE `id` = ?');
test('update.where.func.params', $sql->params, [1, 123]);

// insert
$user = sql::table('user');
$info = sql::table('info i');

$sql = $user->insert(['id' => 1, 'name' => 'vea'], []);
test('insert.one', (string)$sql, 'INSERT INTO `user` (`id`, `name`) VALUES (?, ?)');
test('insert.one.params', $sql->params, [1, 'vea']);

$sql = $info->insert([['id' => 1, 'name' => 'vea'], ['id' => 2, 'name' => 'f0']]);
test('insert.multi', (string)$sql, 'INSERT INTO `info` (`id`, `name`) VALUES (?, ?), (?, ?)');
test('insert.multi.params', $sql->params, [1, 'vea', 2, 'f0']);

// where
$user = sql::table('user');
$sql = $user->where('123')->select();
test('where.string', (string)$sql, 'SELECT * FROM `user` WHERE `id` = ?');
test('where.string.params', $sql->params, ['123']);

$sql = $user->where(['id' => 1])->select();
test('where.kv', (string)$sql, 'SELECT * FROM `user` WHERE `id` = ?');
test('where.kv.params', $sql->params, [1]);

$info = sql::table('info i');
$sql = $info
	->where($info['createdAt']->TIMESTAMP($info['1'])->YEAR()->equal("3"), $info['id']->equal(4))
	->select();
test('where.func.part', (string)$sql, 'SELECT * FROM `info` WHERE YEAR(TIMESTAMP(`createdAt`, `1`)) = ? AND `id` = ?');
test('where.func.part.params', $sql->params, ["3", 4]);

$article = sql::table('article a');
$sql = $article
	->where($article['status']->equal(1), $article['id']->equal(4)->or($article['id']->equal(5)))
	->select();
test('where.or', (string)$sql, 'SELECT * FROM `article` WHERE `status` = ? AND (`id` = ? OR `id` = ?)');
test('where.or.params', $sql->params, [1, 4, 5]);

$article = sql::table('info a');
$sql = $article
	->where($article['status']->equal(1), $article['id']->equal(4)->or($article['id']->equal(5))->and($article['id']->equal(6)))
	->select();
test('where.or.and', (string)$sql, 'SELECT * FROM `info` WHERE `status` = ? AND ((`id` = ? OR `id` = ?) AND `id` = ?)');
test('where.or.and.params', $sql->params, [1, 4, 5, 6]);

$article = sql::table('info2 a');
$sql = $article
	->where($article['status']->equal(1), $article['id']->equal(4)->or($article['id']->equal(5)->and($article['id']->equal(6))))
	->select();
test('where.or.and.group', (string)$sql, 'SELECT * FROM `info2` WHERE `status` = ? AND (`id` = ? OR (`id` = ? AND `id` = ?))');
test('where.or.and.group.params', $sql->params, [1, 4, 5, 6]);

// where in / between
$user = sql::table('user');

$sql = $user->where(['id' => [1, 2, 3]])->select();
test('where.in', (string)$sql, 'SELECT * FROM `user` WHERE `id` IN (?,?,?)');
test('where.in.params', $sql->params, [1, 2, 3]);

$sql = $user->where(['id' => [1, 2, 3, 'fn' => 'in']])->select();
test('where.in.explicit', (string)$sql, 'SELECT * FROM `user` WHERE `id` IN (?, ?, ?)');
test('where.in.explicit.params', $sql->params, [1, 2, 3]);

$sql = $user->where(['id' => [1, 2, 3, 'fn' => 'not_in']])->select();
test('where.not_in', (string)$sql, 'SELECT * FROM `user` WHERE `id` NOT IN (?, ?, ?)');
test('where.not_in.params', $sql->params, [1, 2, 3]);

$sql = $user->where(['id' => [1, 2, 'fn' => 'between']])->select();
test('where.between', (string)$sql, 'SELECT * FROM `user` WHERE `id` BETWEEN ? AND ?');
test('where.between.params', $sql->params, [1, 2]);

// delete
$user = sql::table('user');
$sql = $user->where($user['id']->equal(1))->delete();
test('delete', (string)$sql, 'DELETE FROM `user` WHERE `id` = ?');
test('delete.params', $sql->params, [1]);
