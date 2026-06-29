<?php
use ff\helpers\sql;

include_once '../vendor/autoload.php';
error_reporting(E_ALL);

use function ff\test;

$table = sql::table('user');
$info = sql::table('info i');
$sql = $table->join($info, ['id' => $table['id']]);
$sql->where($info['id']->eq(1));
$sql->select();

test('join on', (string)$sql, 'SELECT `user`.* FROM `user` LEFT JOIN `info` `i` ON (`i`.`id` = `user`.`id`) WHERE `i`.`id` = ?');
test('join params', $sql->params, [1]);

$table = sql::table('user');
$info = sql::table('info a');
$sql = $table->join($info->select(), ['user_id' => 'id'])->where([
	$table['id']->eq(1),
	$info['id']->operate(2, '>'),
])->select(null);

test('join on', (string)$sql, 'SELECT `a`.* FROM `user` LEFT JOIN `info` `a` ON (`a`.`user_id` = `user`.`id`) WHERE `user`.`id` = ? AND `a`.`id` > ?');
test('join params', $sql->params, [1, 2]);

$content = sql::table('article');
$sql = $content->select(['id', 'content_id', 'url', 'title', 'desc', 'image']);
$user_course = sql::table('user_course');
$user_count = sql::COUNT($user_course['id'])->as('count');
$sql->join($user_course->select($user_count), ['content_id' => 'content_id'])->group('content_id')->sort($user_count, 'desc');

test('content', (string)$sql, 'SELECT `article`.`id`, `article`.`content_id`, `article`.`url`, `article`.`title`, `article`.`desc`, `article`.`image`, COUNT(`user_course`.`id`) `count` FROM `article` LEFT JOIN `user_course` ON (`user_course`.`content_id` = `article`.`content_id`) GROUP BY `article`.`content_id` ORDER BY `count` DESC');

$pp = sql::table('payment_pay');
$py = sql::table('payment p');
$sql = $pp->join($py, ['id' => $pp['id']]);
$sql->where($py['id']->eq(1));
$sql->select();

test('pay', (string)$sql, 'SELECT `payment_pay`.* FROM `payment_pay` LEFT JOIN `payment` `p` ON (`p`.`id` = `payment_pay`.`id`) WHERE `p`.`id` = ?');
test('pay params', $sql->params, [1]);

$table = sql::table('user_c');
$article = sql::table('article');
$word = sql::table('word');
$user = sql::table('user');
$sql = $table->join($article->select($article['title']->as('course_name')), ['id' =>'content_id'])
	->join($word->select($word['name']->as('word_name')), ['id' => $article['word_id']])
	->join($user->select(['name', 'mobile']), ['id' => 'user_id']);
$where[] = $article['title']->operate("%" . 'course_name' . "%", 'LIKE');
$where[] = $word['name']->operate('word_name', '=');
$where[] = $user['name']->operate('nickname', '=');
$where[] = $user['mobile']->operate('mobile', '=');
$sql->where($where);
$sql->select();

test('select', (string)$sql, 'SELECT `user_c`.*, `article`.`title` `course_name`, `word`.`name` `word_name`, `user`.`name`, `user`.`mobile` FROM `user_c` LEFT JOIN `article` ON (`article`.`id` = `user_c`.`content_id`) LEFT JOIN `word` ON (`word`.`id` = `article`.`word_id`) LEFT JOIN `user` ON (`user`.`id` = `user_c`.`user_id`) WHERE `article`.`title` LIKE ? AND `word`.`name` = ? AND `user`.`name` = ? AND `user`.`mobile` = ?');
test('select params', $sql->params, ['%course_name%', 'word_name', 'nickname', 'mobile']);

$table = sql::table('user');
$sql = $table->select([
	sql::COUNT($table['xx'], true),
	sql::AVG($table['xx'], true),
	sql::MIN($table['xx'], true),
	sql::MAX($table['xx'], true),
	sql::SUM($table['xx'], true),
]);

test('select', (string)$sql, 'SELECT COUNT(DISTINCT `xx`), AVG(DISTINCT `xx`), MIN(DISTINCT `xx`), MAX(DISTINCT `xx`), SUM(DISTINCT `xx`) FROM `user`');

$serviceTable = sql::table('service');
$table = sql::table('corp_service');
$conditions['corp_id'] = 1;
$conditions['deleted_at'] = 0;
$sql = $table->where($conditions);
$sql2 = $serviceTable->select();
$sql->join($sql2, ['id' => 'service_id', 'deleted_at' => 0], ['INNER']);
$sql->group($serviceTable['id']);
$sql->select(sql::COUNT('*')->as('COUNT'));

test('select', (string)$sql, 'SELECT COUNT(*) `COUNT`, `service`.* FROM `corp_service` INNER JOIN `service` ON (`service`.`id` = `corp_service`.`service_id` AND `service`.`deleted_at` = ?) WHERE `corp_service`.`corp_id` = ? AND `corp_service`.`deleted_at` = ? GROUP BY `service`.`id`');

$sql->sort('id', 'DESC');
$sql->page(1, 10);
$sql->select(['corp_id', 'state_enable']);
$sql->group($serviceTable['id']);
$sql2->select($serviceTable['*']);

test('select', (string)$sql, 'SELECT `corp_service`.`corp_id`, `corp_service`.`state_enable`, `service`.* FROM `corp_service` INNER JOIN `service` ON (`service`.`id` = `corp_service`.`service_id` AND `service`.`deleted_at` = ?) WHERE `corp_service`.`corp_id` = ? AND `corp_service`.`deleted_at` = ? GROUP BY `service`.`id` ORDER BY `corp_service`.`id` DESC LIMIT 10');

$table = sql::table('test');
$sql = $table->select([]);
$sql->where([]);

test('where empty', (string)$sql, 'SELECT * FROM `test`');
