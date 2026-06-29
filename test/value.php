<?php
include_once '../vendor/autoload.php';
error_reporting(E_ALL);

use ff\helpers\sql;

use function ff\test;

// 测试 value 类型处理
test('测试字符串值', (string)sql::value('hello'), '"hello"');
test('测试数字值', (string)sql::value(123), '123');
test('测试布尔值 true', (string)sql::value(true), 'TRUE');
test('测试布尔值 false', (string)sql::value(false), 'FALSE');
test('测试 null 值', (string)sql::value(null), 'NULL');
test('测试数组值', (string)sql::value([1, 2, 3]), '1,2,3');
test('测试星号值', (string)sql::value('*'), '*');
test('测试转义星号值', (string)sql::value('\*'), '"*"');
test('测试带别名的值', (string)(sql::value('test'))->as('alias'), '"test" `alias`');

// 测试 value 在 SQL 上下文中的行为
$sql = new sql(sql::table('test'));
$result = (string)sql::value('hello');
test('测试 SQL 上下文中字符串值', $result, '"hello"'); // 注意：这里会根据实际实现有所不同

// 测试带参数收集的值
sql::$current = new sql(sql::table('test'));
$value = sql::value('test');
$param = $value->__toString();
sql::$current = null;
test('测试 SQL 上下文中参数收集', $param, '?');

// 测试带别名的值（重复项已保留原样）
test('测试带别名的字符串值', (string)(sql::value('hello'))->as('alias'), '"hello" `alias`');
test('测试带别名的数字值', (string)(sql::value(123))->as('alias'), '123 `alias`');
test('测试带别名的布尔值', (string)(sql::value(true))->as('alias'), 'TRUE `alias`');
test('测试带别名的 null 值', (string)(sql::value(null))->as('alias'), 'NULL `alias`');
test('测试带别名的数组值', (string)(sql::value([1, 2, 3]))->as('alias'), '1,2,3 `alias`');
