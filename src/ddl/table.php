<?php
declare(strict_types=1);
namespace ff\helpers\ddl;
use function ff\db;

class table{
	private array $queue = [];
	public function __construct(public readonly string $name){}
	public function alter(array $config): self{
		$this->queue[] = ['type' => 'alter', 'config' => $config];
		return $this;
	}
	public function create(array $fields, ?array $indexes = null): self{
		$this->queue[] = ['type' => 'create', 'fields' => $fields, 'indexes' => $indexes];
		return $this;
	}
	public function fields(array $add = [], array $modify = [], array $drop = []): self{
		$this->queue[] = ['type' => 'fields', 'add' => $add, 'modify' => $modify, 'drop' => $drop];
		return $this;
	}
	public function index(array $add, array $modify, array $drop): self{
		$this->queue[] = ['type' => 'index', 'add' => $add, 'modify' => $modify, 'drop' => $drop];
		return $this;
	}
	public function drop(bool $ifExists = false, bool $cascade = false): self{
		$this->queue[] = ['type' => 'drop', 'ifExists' => $ifExists, 'cascade' => $cascade];
		return $this;
	}
	public function truncate(bool $resetAuto = false): self{
		$this->queue[] = ['type' => 'truncate', 'resetAuto' => $resetAuto];
		return $this;
	}
	public function __toString(): string{
		$SQLs = $this->buildAllSQL();
		return $SQLs ? implode(";\n", $SQLs) . ';' : '';
	}
	public function toSchema(): ?array{
		if(!$this->queue) return null;
		$columns = [];
		$indexes = [];
		foreach($this->queue as $op){
			match ($op['type']) {
				'create' => self::resolveCreateSchema($columns, $indexes, $op['fields'], $op['indexes']),
				'fields' => self::resolveFieldsSchema($columns, $op['add'], $op['modify'], $op['drop']),
				'index' => self::resolveIndexSchema($indexes, $op['add'], $op['modify'], $op['drop']),
				'drop' => ($columns = $indexes = []),
				default => null,
			};
		}
		return $columns ? ['columns' => $columns, 'indexes' => $indexes] : null;
	}
	private static function resolveCreateSchema(array &$columns, array &$indexes, array $fields, ?array $idx): void{
		$columns = $fields;
		$indexes = $idx ?? [];
		self::resolveCreateIndexes($indexes, $fields);
	}
	private static function resolveFieldsSchema(array &$columns, array $add, array $modify, array $drop): void{
		foreach($add as $col => $def) $columns[$col] = $def;
		foreach($modify as $col => $def) $columns[$col] = $def;
		foreach($drop as $col) unset($columns[$col]);
	}
	private static function resolveIndexSchema(array &$indexes, array $add, array $modify, array $drop): void{
		foreach($add as $name => $def) $indexes[$name] = $def;
		foreach($modify as $name => $def) $indexes[$name] = $def;
		foreach($drop as $name) unset($indexes[$name]);
	}
	private function buildAllSQL(): array{
		return array_filter(array_map(fn($op) => $this->buildSQL($op), $this->queue));
	}
	private function buildSQL(array $op): ?string{
		$table = "`{$this->name}`";
		return match ($op['type']) {
			'alter' => $this->buildAlter($table, $op['config']),
			'create' => $this->buildCreate($table, $op['fields'], $op['indexes']),
			'fields' => $this->buildFields($table, $op['add'], $op['modify'], $op['drop']),
			'index' => $this->buildIndex($table, $op['add'], $op['modify'], $op['drop']),
			'drop' => "DROP TABLE " . ($op['ifExists'] ? "IF EXISTS " : "") . $table . ($op['cascade'] ? " CASCADE" : ""),
			'truncate' => "TRUNCATE TABLE $table" . ($op['resetAuto'] ? " RESTART IDENTITY" : ""),
			default => null,
		};
	}
	private function buildAlter(string $table, array $config): string{
		$parts = [];
		if(isset($config['rename'])) $parts[] = "RENAME TO `{$config['rename']}`";
		if(isset($config['comment'])) $parts[] = "COMMENT = '" . str_replace("'", "''", $config['comment']) . "'";
		if(isset($config['engine'])) $parts[] = "ENGINE = " . strtoupper($config['engine']);
		if(isset($config['charset'])) $parts[] = "DEFAULT CHARSET = " . strtolower($config['charset']);
		if(isset($config['autoIncrement'])) $parts[] = "AUTO_INCREMENT = " . $config['autoIncrement'];
		return $parts ? "ALTER TABLE $table " . implode(", ", $parts) : '';
	}
	private function buildCreate(string $table, array $fields, ?array $indexes): string{
		$indexes = $indexes ?? [];
		self::resolveCreateIndexes($indexes, $fields);
		$lines = [];
		foreach($fields as $col => $def) $lines[] = self::buildFieldDef($col, $def);
		foreach($indexes as $name => $def){
			$cols = self::quoteList($def['columns']);
			$lines[] = match ($def['type']) {
				'PRIMARY' => "PRIMARY KEY ($cols)",
				'UNIQUE' => "UNIQUE INDEX `$name` ($cols)",
				'FULLTEXT' => "FULLTEXT INDEX `$name` ($cols)",
				default => "INDEX `$name` ($cols)",
			};
		}
		return "CREATE TABLE $table (\n  " . implode(",\n  ", $lines) . "\n)";
	}
	private static function resolveCreateIndexes(array &$indexes, array $fields): void{
		foreach($fields as $col => $def){
			if(($def['primary'] ?? false) && !isset($indexes['PRIMARY'])) $indexes['PRIMARY'] = ['type' => 'PRIMARY', 'columns' => [$col]];
			foreach(['fulltext', 'unique', 'index'] as $k){
				$v = $def[$k] ?? false;
				if($v === false) continue;
				$name = is_string($v) ? $v : match ($k) {'fulltext' => "ft_$col", 'unique' => "uk_$col", default => "idx_$col"};
				if(!isset($indexes[$name])) $indexes[$name] = ['type' => strtoupper($k), 'columns' => [$col]];
			}
			if(!isset($indexes['PRIMARY']) && (($def['auto'] ?? false) || ($def['type'] ?? '') === 'AUTO_INCREMENT')) $indexes['PRIMARY'] = ['type' => 'PRIMARY', 'columns' => [$col]];
		}
	}
	private function buildFields(string $table, array $add, array $modify, array $drop): string{
		$SQLs = [];
		foreach($add as $col => $def) $SQLs[] = "ADD COLUMN " . self::buildFieldDef($col, $def);
		foreach($modify as $col => $def) $SQLs[] = "MODIFY COLUMN " . self::buildFieldDef($col, $def);
		foreach($drop as $col) $SQLs[] = "DROP COLUMN " . self::quote($col);
		return $SQLs ? "ALTER TABLE $table " . implode(",\n  ", $SQLs) : '';
	}
	public static function buildFieldDef(string $column, array $def): string{
		$type = self::fieldType($def);
		$sql = self::quote($column) . " $type";
		if(!($def['null'] ?? true)) $sql .= " NOT NULL";
		if(array_key_exists('default', $def)) $sql .= " DEFAULT " . self::formatValue($def['default']);
		if(isset($def['comment'])) $sql .= " COMMENT '" . str_replace("'", "''", $def['comment']) . "'";
		if($def['auto'] ?? false) $sql .= " AUTO_INCREMENT";
		$pos = $def['pos'] ?? null;
		if($pos === 'first') $sql .= " FIRST";
		elseif(is_string($pos)) $sql .= " AFTER " . self::quote($pos);
		return $sql;
	}
	public static function fieldType(array $def): string{
		return strtoupper($def['type'] ?? 'VARCHAR(255)');
	}
	public static function formatValue(mixed $value): string{
		return match (true) {
			is_null($value) => 'NULL',
			is_bool($value) => $value ? 'TRUE' : 'FALSE',
			is_int($value) || is_float($value) => (string)$value,
			is_string($value) && str_starts_with(strtoupper($value), 'CURRENT_') => $value,
			is_string($value) => "'" . str_replace("'", "''", $value) . "'",
			default => (string)$value,
		};
	}
	public static function quote(string $name): string{
		return "`$name`";
	}
	public static function quoteList(array $columns): string{
		return implode(', ', array_map(fn($c) => self::quote($c), $columns));
	}
	public static function fromSql(string $sql): array{
		preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?\s*\(([\s\S]*?)\)\s*(?:ENGINE\s*=|COLLATE\s*=|DEFAULT\s+CHARSET|;|$)/i', $sql, $matches);
		$tables = [];
		foreach($matches[1] as $i => $name){
			$fields = [];
			$indexes = [];
			foreach(explode(',', preg_replace('/\n\s*/', ' ', trim($matches[2][$i]))) as $part){
				$part = trim($part);
				if($part === '') continue;
				if(preg_match('/PRIMARY\s+KEY\s*\(([^)]+)\)/i', $part, $m)){
					preg_match_all('/`?(\w+)`?/', $m[1], $cms);
					$indexes['PRIMARY'] = ['type' => 'PRIMARY', 'columns' => $cms[1]];
					continue;
				}
				if(preg_match('/^(UNIQUE\s+(?:INDEX|KEY)\s+`?(\w+)`?|INDEX\s+`?(\w+)`?|KEY\s+`?(\w+)`?|FULLTEXT\s+(?:INDEX|KEY)\s+`?(\w+)`?)\s*\(([^)]+)\)/i', $part, $m)){
					preg_match_all('/`?(\w+)`?/', $m[6], $cms);
					$idxName = $m[2] ?: $m[3] ?: $m[4] ?: $m[5];
					$indexes[$idxName] = ['type' => preg_match('/UNIQUE/i', $part) ? 'UNIQUE' : (preg_match('/FULLTEXT/i', $part) ? 'FULLTEXT' : 'INDEX'), 'columns' => $cms[1]];
					continue;
				}
				if(preg_match('/^`?(\w+)`?\s+/', $part, $m)){
					$col = $m[1];
					$rest = trim(substr($part, strlen($m[0])));
					preg_match('/^(\w+(?:\([^)]+\))?(?:\s+\w+)*?)\s*/i', $rest, $tm);
				$def = ['type' => strtoupper(trim($tm[1] ?? 'VARCHAR(255)'))];
				if(preg_match('/NOT\s+NULL/i', $part)) $def['null'] = false;
					if(preg_match('/DEFAULT\s+(\S+)/i', $part, $dm)) $def['default'] = self::parseDefault($dm[1]);
					if(preg_match('/COMMENT\s+[\'"]([^\'"]+)[\'"]/i', $part, $cm)) $def['comment'] = $cm[1];
					if(preg_match('/AUTO_INCREMENT/i', $part)){
						$def['auto'] = true;
						$indexes['PRIMARY'] ??= ['type' => 'PRIMARY', 'columns' => [$col]];
					}
					$fields[$col] = $def;
				}
			}
			$t = new static($name);
			$t->queue[] = ['type' => 'create', 'fields' => $fields, 'indexes' => $indexes ?: null];
			$tables[] = $t;
		}
		return $tables;
	}
	private static function parseDefault(string $value): mixed{
		if($value === 'NULL') return null;
		if(preg_match('/^\'(.*)\'$/s', $value, $m)) return str_replace("''", "'", $m[1]);
		if(is_numeric($value)) return str_contains($value, '.') ? (float)$value : (int)$value;
		return $value;
	}
	private function buildIndex(string $table, array $add, array $modify, array $drop): string{
		$SQLs = [];
		foreach($add as $idx => $def){
			$cols = self::quoteList($def['columns']);
			$SQLs[] = match ($def['type']) {
				'PRIMARY' => "ADD PRIMARY KEY ($cols)",
				'UNIQUE' => "ADD UNIQUE INDEX `$idx` ($cols)",
				'FULLTEXT' => "ADD FULLTEXT INDEX `$idx` ($cols)",
				'FOREIGN' => "ADD FOREIGN KEY (`{$def['columns'][0]}`) REFERENCES `{$def['refs'][0]}` (`{$def['refs'][1]}`)",
				default => "ADD INDEX `$idx` ($cols)",
			};
		}
		foreach($modify as $idx => $def){
			$cols = self::quoteList($def['columns']);
			$SQLs[] = match ($def['type']) {
				'PRIMARY' => "DROP PRIMARY KEY, ADD PRIMARY KEY ($cols)",
				default => "DROP INDEX `$idx`, ADD INDEX `$idx` ($cols)",
			};
		}
		foreach($drop as $idx) $SQLs[] = $idx === 'PRIMARY' ? "DROP PRIMARY KEY" : "DROP INDEX `$idx`";
		return $SQLs ? "ALTER TABLE $table " . implode(",\n  ", $SQLs) : '';
	}
}