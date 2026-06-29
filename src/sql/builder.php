<?php
declare(strict_types=1);
namespace ff\helpers\sql;
class builder{
	const array QUOTE = [
		'mysql' => '`',
		'sqlite' => '"',
	];
	public static function sql(string $action, array $data, ?string $alias, ?string $dialect = 'mysql'): ?string{
		$q = static::QUOTE[$dialect ?? 'mysql'] ?? static::QUOTE['mysql'];
		$opts = array_intersect([
			'DISTINCT',
			'DISTINCTROW',
			'HIGH_PRIORITY',
			'STRAIGHT_JOIN',
			'SQL_SMALL_RESULT',
			'SQL_BIG_RESULT',
			'SQL_BUFFER_RESULT',
			'SQL_NO_CACHE',
			'SQL_CALC_FOUND_ROWS',
		], $data['options'] ?? []);
		$sql = strtoupper($action) . ($opts ? ' ' . implode(' ', $opts) : '');
		if('insert' === $action){
			$cols = implode(', ', $data['insert'][0]);
			$values = implode(', ', array_map(fn($v) => '(' . implode(', ', array_map(fn($val) => self::value($val, null, $dialect), $v)) . ')', $data['insert'][1]));
			$sql = "$sql INTO {$data['table']} ($cols) VALUES $values";
		}
		else{
			if('select' === $action){
				$fields = implode(', ', $data['fields'] ?? []);
				$joinStr = '';
				foreach($data['join'] ?? [] as $j){
					$joinType = implode(' ', array_intersect(['NATURAL', 'INNER', 'CROSS', 'LEFT', 'RIGHT'], $j['options']));
					$keyword = in_array('STRAIGHT', $j['options']) ? 'STRAIGHT_JOIN' : 'JOIN';
					$on = implode(' AND ', $j['on']);
					$joinStr .= " $joinType $keyword {$j['join']} ON ($on)";
				}
			}
			elseif('update' === $action){
				$set = implode(', ', array_map(fn($s) => (string)$s, $data['set']));
			}
			$where = $data['where'] ? ' WHERE ' . implode(' AND ', $data['where']) : '';
			if('select' === $action){
				$group = $data['group'] ? ' GROUP BY ' . implode(', ', array_map(fn($g) => ($g[0]->alias ? "$q{$g[0]->alias}$q" : $g[0]) . ($g[1] ? '' : " DESC"), $data['group'])) : '';
				$having = $data['having'] ? ' HAVING ' . implode(' AND ', $data['having']) : '';
			}
			$sort = $data['sort'] ? " ORDER BY " . implode(', ', array_map(fn($s) => ($s[0]->alias ? "$q{$s[0]->alias}$q" : $s[0]) . ($s[1] ? '' : " DESC"), $data['sort'])) : '';
			$limit = $data['limit'] ? " LIMIT " . implode(', ', array_filter($data['limit'])) : '';
			$sql = match ($action) {
				'select' => "$sql $fields FROM {$data['table']}$joinStr$where$group$having$sort$limit",
				'update' => "$sql {$data['table']} SET $set$where$sort$limit",
				'delete' => "$sql FROM {$data['table']}$where$sort$limit",
			};
		}
		return $alias ? "($sql) $q$alias$q" : $sql;
	}
	public static function value(mixed $value, ?string $alias, ?string $dialect = 'mysql'): string{
		$q = static::QUOTE[$dialect ?? 'mysql'] ?? static::QUOTE['mysql'];
		return match (true) {
				is_string($value) => "\"$value\"",
				is_bool($value) => $value ? 'TRUE' : 'FALSE',
				is_null($value) => 'NULL',
				is_array($value) => join(',', $value),
				default => (string)$value,
			} . ($alias ? " $q$alias$q" : '');
	}
	public static function field(string $name, ?string $table_name, ?string $alias, ?string $dialect = 'mysql'): string{
		$q = static::QUOTE[$dialect ?? 'mysql'] ?? static::QUOTE['mysql'];
		if('*' === $name) $field = '*';
		else $field = "$q$name$q";
		if(null !== $table_name) $field = "$q$table_name$q.$field";
		return $alias ? "$field $q$alias$q" : $field;
	}
	public static function table(string $name, ?string $alias, ?string $dialect = 'mysql'): string{
		$q = static::QUOTE[$dialect ?? 'mysql'] ?? static::QUOTE['mysql'];
		return "$q$name$q" . ($alias ? " $q$alias$q" : '');
	}
	public static function operate(string $name, array $args, bool $negate, ?string $alias, ?string $dialect = 'mysql'): string{
		$q = static::QUOTE[$dialect ?? 'mysql'] ?? static::QUOTE['mysql'];
		$negateMap = ['=' => '!=', '!=' => '=', '<>' => '=', '<' => '>=', '>' => '<=', '>=' => '<', '<=' => '>',];
		if($negate && ($negateMap[$name] ?? false)) $name = $negateMap[$name];
		$not = $negate ? 'NOT ' : '';
		$result = match ($name) {
			'&', '&&', '|', '||',
			'+', '-', '*', '/', '%',
			'=', '!=', '<>', '<', '>', '<=', '>=', '<=>' => "$args[0] $name $args[1]",
			'LIKE', 'REGEXP' => "$not$args[0] $name $args[1]",
			'AND', 'OR', 'XOR' => "($args[0] $name $args[1])",
			'BETWEEN' => "$args[0] $not$name $args[1] AND $args[2]",
			'IN' => "$args[0] $not$name (" . implode(', ', array_slice($args, 1)) . ")",
			'AVG', 'COUNT', 'MIN', 'MAX', 'SUM' => "$name(" . ((!empty($args[1]) && $args[1]->value) ? 'DISTINCT ' : '') . "$args[0])",
			default => null,
		};
		if(null === $result){
			switch($name){
				case 'TRIM':
					$side = strtoupper(($args[2] ?? null) ? ($args[2]->value.' ' ?? '') : '');
					$rem = ($args[1] ?? null) ? "$args[1] " : '';
					$result = "TRIM($side{$rem}FROM $args[0])";
					break;
				case 'WEIGHT_STRING':
					$type = strtoupper(($args[2] ?? null) ? ($args[2]->value ?? '') : 'char');
					$n = ($args[1] ?? null) ? ($args[1]->value ?? '') : '';
					$result = "WEIGHT_STRING($args[0] AS $type($n))";
					break;
				default:
					if($name === 'NOT' && $negate) $name = "";
					$result = "$name(" . implode(', ', $args) . ")";
			}
		}
		$AS = $alias ? " $q$alias$q" : "";
		return $negate && !in_array($name, ['IN', 'BETWEEN', 'LIKE', 'REGEXP', '=', '!=', '<', '>', '<=', '>=', '<=>']) ? "NOT ($result)$AS" : "$result$AS";
	}
}
