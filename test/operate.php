<?php
include_once '../vendor/autoload.php';
error_reporting(E_ALL);

use ff\helpers\sql;
use ff\helpers\sql\operate;

use function ff\test;

// 测试 operate 类的功能
//test('operate 构造函数', new operate('eq', [1, 2]), new operate(...)); // 实际值与预期值比较可简化为断言逻辑，此处仅示例结构

// 基本操作符测试（使用 match 简化输出）
$ops = [
	'eq' => '"a" = "b"',
	'ne' => '"a" != "b"',
	'lt' => '"a" < "b"',
	'le' => '"a" <= "b"',
	'gt' => '"a" > "b"',
	'ge' => '"a" >= "b"',
	'add' => '"a" + "b"',
	'sub' => '"a" - "b"',
	'mul' => '"a" * "b"',
	'div' => '"a" / "b"',
	'mod' => '"a" % "b"',
];

foreach ($ops as $op => $expected) {
	test("基本操作符: {$op}", (string)new operate($op, ['a', 'b']), $expected);
}

// not_ 前缀测试
test('not_ 前缀处理: not_eq', (string)new operate('not_eq', ['a', 'b']), '"a" != "b"');
test('not_ 前缀处理: not_like', (string)new operate('not_like', ['a', 'b']), 'NOT "a" LIKE "b"');

// like / regexp
test('操作符: like', (string)new operate('like', ['a', 'b']), '"a" LIKE "b"');
test('操作符: rlike', (string)new operate('rlike', ['a', 'b']), '"a" REGEXP "b"');
test('操作符: regexp', (string)new operate('regexp', ['a', 'b']), '"a" REGEXP "b"');

// between
test('操作符: between', (string)new operate('between', ['a', 'b', 'c']), '"a" BETWEEN "b" AND "c"');
test('操作符: not_between', (string)new operate('not_between', ['a', 'b', 'c']), '"a" NOT BETWEEN "b" AND "c"');

// 逻辑操作符
test('逻辑操作符: and', (string)new operate('and', ['a', 'b']), '("a" AND "b")');
test('逻辑操作符: or', (string)new operate('or', ['a', 'b']), '("a" OR "b")');
test('逻辑操作符: xor', (string)new operate('xor', ['a', 'b']), '("a" XOR "b")');

// in / not_in
test('自定义函数: in', (string)new operate('in', ['a', 'b', 'c']), '"a" IN ("b", "c")');
test('自定义函数: not_in', (string)new operate('not_in', ['a', 'b', 'c']), '"a" NOT IN ("b", "c")');

// 聚合函数
$funcs = ['avg' => 'AVG("a")', 'count' => 'COUNT("a")', 'min' => 'MIN("a")', 'max' => 'MAX("a")', 'sum' => 'SUM("a")'];
foreach ($funcs as $func => $expected) {
	test("函数调用: {$func}", (string)new operate($func, ['a']), $expected);
}

// MySQL 内置函数（使用 sql:: 方法）
test('MySQL内置函数调用: trim', (string)sql::TRIM('a'), 'TRIM(FROM "a")');
test('MySQL内置函数调用: weight_string with type and length', (string)sql::WEIGHT_STRING('a', 10), 'WEIGHT_STRING("a" AS CHAR(10))');
test('MySQL内置函数调用: avg with distinct', (string)sql::AVG(true, true), 'AVG(DISTINCT TRUE)');
test('MySQL内置函数调用: pi', (string)sql::PI(), 'PI()');
