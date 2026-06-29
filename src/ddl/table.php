<?php
declare(strict_types=1);
namespace ff\helpers\ddl;
use function ff\db;

class table{
	private array $queue = [];
	public function __construct(private readonly string $name){}
	public function alter(array $config): self{
		$this->queue[] = ['type' => 'alter', 'config' => $config];
		return $this;
	}
	public function create(array $fields): self{
		$this->queue[] = ['type' => 'create', 'fields' => $fields];
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
	public function execute(): array{
		if(!function_exists('ff\db')) throw new \RuntimeException('The ff\db() function is required to execute DDL operations.');
		$SQLs = $this->buildAllSQL();
		if(!$SQLs) return [];
		try{
			db('BEGIN');
			foreach($SQLs as $sql) db($sql);
			db('COMMIT');
		}catch(\Throwable $e){
			db('ROLLBACK');
			throw $e;
		}
		return $SQLs;
	}
	public function __toString(): string{
		$SQLs = $this->buildAllSQL();
		return $SQLs ? implode(";\n", $SQLs) . ';' : '';
	}
	private function buildAllSQL(): array{
		return array_filter(array_map(fn($op) => $this->buildSQL($op), $this->queue));
	}
	private function buildSQL(array $op): ?string{
		$table = "`{$this->name}`";
		return match ($op['type']) {
			'alter' => $this->buildAlter($table, $op['config']),
			'create' => $this->buildCreate($table, $op['fields']),
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
		if(isset($config['comment'])) $parts[] = "COMMENT = `{$config['comment']}`";
		if(isset($config['engine'])) $parts[] = "ENGINE = " . strtoupper($config['engine']);
		if(isset($config['charset'])) $parts[] = "DEFAULT CHARSET = " . strtolower($config['charset']);
		if(isset($config['autoIncrement'])) $parts[] = "AUTO_INCREMENT = " . $config['autoIncrement'];
		return $parts ? "ALTER TABLE $table " . implode(", ", $parts) : '';
	}
	private function buildCreate(string $table, array $fields): string{
		$lines = $pk = [];
		foreach($fields as $col => $def){
			$line = $this->buildFieldDef($col, $def);
			$lines[] = $line;
			if(($def['auto'] ?? false) || ($def['type'] ?? '') === 'AUTO_INCREMENT') $pk[] = $col;
		}
		if($pk) $lines[] = "PRIMARY KEY (" . $this->quoteList($pk) . ")";
		return "CREATE TABLE $table (\n  " . implode(",\n  ", $lines) . "\n)";
	}
	private function buildFields(string $table, array $add, array $modify, array $drop): string{
		$SQLs = [];
		foreach($add as $col => $def) $SQLs[] = "ADD COLUMN " . $this->buildFieldDef($col, $def);
		foreach($modify as $col => $def) $SQLs[] = "MODIFY COLUMN " . $this->buildFieldDef($col, $def);
		foreach($drop as $col) $SQLs[] = "DROP COLUMN `$col`";
		return $SQLs ? "ALTER TABLE $table " . implode(",\n  ", $SQLs) : '';
	}
	private function buildFieldDef(string $column, array $def): string{
		$type = $this->fieldType($def);
		$sql = "`$column` $type";
		if(!($def['null'] ?? true)) $sql .= " NOT NULL";
		if(array_key_exists('default', $def)) $sql .= " DEFAULT " . $this->formatValue($def['default']);
		if(isset($def['comment'])) $sql .= " COMMENT `{$def['comment']}`";
		if($def['auto'] ?? false) $sql .= " AUTO_INCREMENT";
		$pos = $def['pos'] ?? null;
		if($pos === 'first') $sql .= " FIRST";
		elseif(is_string($pos)) $sql .= " AFTER `$pos`";
		return $sql;
	}
	private function fieldType(array $def): string{
		return strtoupper($def['type'] ?? 'VARCHAR(255)');
	}
	private function formatValue(mixed $value): string{
		return match (true) {
			is_null($value) => "NULL",
			is_bool($value) => $value ? "TRUE" : "FALSE",
			is_int($value) || is_float($value) => (string)$value,
			is_string($value) && str_starts_with(strtoupper($value), 'CURRENT_') => $value,
			default => "`$value`",
		};
	}
	private function quoteList(array $columns): string{
		return implode(', ', array_map(fn($c) => "`$c`", $columns));
	}
	private function buildIndex(string $table, array $add, array $modify, array $drop): string{
		$SQLs = [];
		foreach($add as $idx => $def){
			$cols = $this->quoteList($def['columns']);
			$SQLs[] = match ($def['type']) {
				'PRIMARY' => "ADD PRIMARY KEY ($cols)",
				'UNIQUE' => "ADD UNIQUE INDEX `$idx` ($cols)",
				'FULLTEXT' => "ADD FULLTEXT INDEX `$idx` ($cols)",
				'FOREIGN' => "ADD FOREIGN KEY (`{$def['columns'][0]}`) REFERENCES `{$def['refs'][0]}` (`{$def['refs'][1]}`)",
				default => "ADD INDEX `$idx` ($cols)",
			};
		}
		foreach($modify as $idx => $def){
			$cols = $this->quoteList($def['columns']);
			$SQLs[] = match ($def['type']) {
				'PRIMARY' => "DROP PRIMARY KEY, ADD PRIMARY KEY ($cols)",
				default => "DROP INDEX `$idx`, ADD INDEX `$idx` ($cols)",
			};
		}
		foreach($drop as $idx) $SQLs[] = $idx === 'PRIMARY' ? "DROP PRIMARY KEY" : "DROP INDEX `$idx`";
		return $SQLs ? "ALTER TABLE $table " . implode(",\n  ", $SQLs) : '';
	}
}