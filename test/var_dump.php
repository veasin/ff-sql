<?php

use ff\helpers\sql;

include_once '../vendor/autoload.php';
error_reporting(E_ALL);


$value =sql::value(123)->as('v');
$table =sql::table('user u 1');
$field =$table[null]->as('id');
$sql =$table->select(['*', $table['id']])->where($field->eq($value));

var_dump($value, $field, $table, $sql);

var_dump((string)$value, (string)$field, (string)$table, (string)$sql, $sql->params);

