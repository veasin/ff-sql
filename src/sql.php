<?php
declare(strict_types=1);
namespace ff\helpers;

use ff\helpers\sql\{alias, builder, expr, operate, table, value};

class sql implements \ArrayAccess{
	use alias;
	const string
		OPTS_DISTINCT = 'DISTINCT', OPTS_HIGH_PRIORITY = 'HIGH_PRIORITY', OPTS_STRAIGHT_JOIN = 'STRAIGHT_JOIN', OPTS_SQL_SMALL_RESULT = 'SQL_SMALL_RESULT', OPTS_SQL_BIG_RESULT = 'SQL_BIG_RESULT', OPTS_SQL_BUFFER_RESULT = 'SQL_BUFFER_RESULT', OPTS_SQL_NO_CACHE = 'SQL_NO_CACHE', OPTS_SQL_CALC_FOUND_ROWS = 'SQL_CALC_FOUND_ROWS';
	const string
		JOIN_INNER = 'INNER', JOIN_CROSS = 'CROSS', JOIN_STRAIGHT = 'STRAIGHT', JOIN_LEFT = 'LEFT', JOIN_RIGHT = 'RIGHT', JOIN_NATURAL = 'NATURAL';
	protected(set) ?string $dialect=null;
	protected(set) array $params = [];//执行参数
	protected(set) mixed $select = null;
	protected array $where = [], $join = [], $having = [], $set = [], $options = [];
	protected ?array $limit = null, $sort = null, $group = null;
	protected(set) string $action = 'select';
	public static ?sql $current = null;
	protected \WeakMap|null $joinSQL = null;
	public function __construct(protected(set) table $table){}
	public function collectParam(mixed $value): ?string{
		if(!self::$current) return null;
		$this->params[] = $value;
		return '?';
	}
	public function hasJoin(): bool{
		return !empty($this->join);
	}
	public static function table(string $name, string $primary = 'id'): table{
		return new table($name, $primary);
	}
	public static function value($any): value{
		return new value($any);
	}
	public function insert(array $fields = [], array $options = []): static{
		$this->action = 'insert';
		$this->set = $fields;
		$this->options = $options;
		return $this;
	}
	public function update(array $fields = [], array $options = []): static{
		$this->action = 'update';
		$this->set = $fields;
		$this->options = $options;
		return $this;
	}
	public function delete(array $options = []): static{
		$this->action = 'delete';
		$this->options = $options;
		return $this;
	}
	public function select(array|string|expr|null $fields = [], array $options = []): static{
		$this->action = 'select';
		$this->select = $fields;
		$this->options = $options;
		return $this;
	}
	public function join(table|sql $table, mixed $on = null, array $options = []): static{
		if($table instanceof sql){
			$this->joinSQL ??= new \WeakMap();
			$this->joinSQL[$table->table] = $table;
			$table = $table->table;
		}
		$this->join[] = [$table, $on ?: [$table->primary => $this->table->primary], $options ?: ['LEFT']];
		return $this;
	}
	public function where(mixed ...$conditions): static{
		$this->where = $conditions;
		return $this;
	}
	public function limit(int $rows, int $offset = 0): static{
		$this->limit = [$rows, $offset];
		return $this;
	}
	public function page(int $page, int $max = 20): static{
		$this->limit = [$max, ($page - 1) * $max];
		return $this;
	}
	public function sort(array|string|expr|null $fields = null, string $direction = 'ASC'): static{
		$this->sort = [$fields, $direction];
		return $this;
	}
	public function group(array|string|expr|null $fields = [], string $sort = 'ASC'): static{
		$this->group = [$fields, $sort];
		return $this;
	}
	public function having(mixed ...$conditions): static{
		$this->having = $conditions;
		return $this;
	}
	public function __toString(): string{
		self::$current ??= $this;
		$this->params = [];
		$data = match ($this->action) {
			'select' => [
				'fields' => $this->prepareFields($this->table, $this->select),
				'where' => $this->prepareWhere($this->where),
				'sort' => $this->prepareSort($this->sort),
				'group' => $this->prepareSort($this->group),
				'having' => $this->prepareWhere($this->having),
				'limit' => $this->prepareLimit($this->limit),
			],
			'update' => [
				'set' => $this->prepareSet($this->set),
				'where' => $this->prepareWhere($this->where),
				'sort' => $this->prepareSort($this->sort),
				'limit' => $this->prepareLimit($this->limit),
			],
			'delete' => [
				'where' => $this->prepareWhere($this->where),
				'sort' => $this->prepareSort($this->sort),
				'limit' => $this->prepareLimit($this->limit),
			],
			'insert' => [
				'insert' => $this->prepareInsert($this->set),
			],
			default => [],
		};
		if($this->action === 'select'){
			[$data['join'], $joinSelect] = $this->prepareJoin($this->join);
			$data['fields'] = [...$data['fields'], ...$joinSelect];
		}
		$sql = builder::sql($this->action, ['table' => $this->table, 'options' => $this->options,] + $data, $this->alias, $this->table->db?->dialect ?? 'mysql');
		self::$current = null;
		return $sql;
	}
	protected function prepareInsert($set): array{
		if(!is_array($set) || empty($set)) return [[], []];
		$cols = current($set);
		if(!is_array($cols)){
			$cols = $set;
			$_set = [$set];
		}
		else $_set = $set;
		$_cols = [];
		foreach($cols as $col => $value) $_cols[] = $this->table[$col];
		$params = [];
		foreach($_set as $index => $values){
			$param = [];
			foreach($values as $val) $param[] = sql::value($val);
			$params[$index] = $param;
		}
		return [$_cols, $params];
	}
	protected function prepareJoin(array $joins): array{
		$joinSet = [];
		$joinSelect = [];
		foreach($joins as [$table2, $on, $options]){
			if(!is_array($on)) $on = [$on => $on];
			$conditions = [];
			foreach($on as $k => $v){
				$left = match (true) {
					$k instanceof expr => $k,
					is_string($k) => $table2[$k],//['id'=>]
					default => $table2[null],// ['id', ...]
				};
				$conditions[] = $left->eq(is_string($v) ? $this->table[$v] : $v);//[=>'id', =>expr, =>1]
			}
			if($this->joinSQL && isset($this->joinSQL[$table2])){
				$_sql = $this->joinSQL[$table2];
				if($_sql?->alias) $table2 = $_sql;// join (select ...) as
				else $joinSelect = [...$joinSelect, ...$this->prepareFields($table2, $_sql->select)];
			}
			$joinSet[] = ['join' => $table2, 'on' => $conditions, 'options' => $options];
		}
		return [$joinSet, $joinSelect];
	}
	protected function prepareWhere(array $where): array{
		if(!$where) return [];
		$_conditions = [];
		foreach($where as $cond){
			// ->where(1) ->where(expr)
			if(!is_iterable($cond)) $cond = [$cond instanceof expr ? $cond : $this->table[$this->table->primary]->eq($cond)];
			// ->where(['id'=>1, 'status'=>2, expr])
			foreach($cond as $field => $value){// int|str|expr => expr|array|mixed
				$_f = match (true) {
					//$field instanceof expr => $field,// expr =>
					is_string($field) => $this->table[$field],// str =>
					default => null,// 0 => expr OR Error
				};
				if($value instanceof expr) $_conditions[] = null === $_f ? $value : $_f->eq($value);
				else{
					if(null === $_f) throw new \InvalidArgumentException();
					if(is_array($value)){// ['fn'=>'', ...]  => fn (...) |  IN (...)
						$fn = $value['fn'] ?? null;
						unset($value['fn']);
						$_conditions[] = $fn ? $_f->$fn(...$value) : $_f->in($value);
					}
					else $_conditions[] = $_f->eq($value);
				}
			}
		}
		return $_conditions;
	}
	protected function prepareFields(table $table, $select = null): array{
		if(null === $select) return [];
		$select = is_array($select) ? $select : [$select];
		if(empty($select)) return [$table['*']];
		$_select = [];
		foreach($select as $value){
			$_select[] = match (true) {
				$value instanceof expr => $value,
				is_string($value) => $table[$value],// field
				default => static::value($value),
			};
		}
		return $_select;
	}
	protected function prepareSet(array $set): array{
		if(!$set) return [];
		$params = [];
		foreach($set as $field => $value){
			$params[] = $this->table[$field]->eq($value);
		}
		return $params;
	}
	protected function prepareSort(?array $sort): array{
		if(empty($sort)) return [];
		[$_field, $asc] = $sort;
		$sorts = [];
		if(!is_iterable($_field)){
			if($_field instanceof expr){
				$fields =new \WeakMap();
				$fields[$_field] =$asc;
			} else $fields = [$_field => $asc];
		} else $fields =$_field;
		foreach($fields as $f => $s){
			$field = match (true) {
				$f instanceof expr => $f,
				is_string($f) => $this->table[$f],
				is_numeric($f) => $this->table[$s],
				default => static::value($f),
			};
			$sortDN = fn($str) => strtoupper($str[0] ?? 'A') === 'A';
			$direction = match (true) {
				is_bool($s) =>$s,
				is_string($s) => $sortDN($s),
				default => is_bool($asc) ? $asc : (is_string($asc) ? $sortDN($asc) : true),
			};
			$sorts[] = [$field, $direction];
		}
		return $sorts;
	}
	protected function prepareLimit(?array $limit): ?array{
		return $limit ? ($limit[1] ? [$limit[1], $limit[0]] : [$limit[0], null]) : null;
	}
	public static function __callStatic($name, $arguments): operate{
		return new operate($name, $arguments);
	}
	public function offsetSet($offset, $value): void{}
	public function offsetExists($offset): bool{ return false; }
	public function offsetUnset($offset): void{}
	public function offsetGet(mixed $offset): ?table{
		if(!is_string($offset)) return null;
		if($offset === $this->table->name || $offset === $this->table->alias) return $this->table;
		return array_find($this->join, fn($table) => $offset === $table->name || $offset === $table->alias);
	}
	public function __debugInfo(): array{
		$i =['table' => "{$this->table->name}({$this->table->alias})",'action' => $this->action,];
		$this->params && $i['params'] = $this->params;
		$this->select && $i['select'] = $this->select;
		$this->join && $i['join'] = $this->join;
		$this->where && $i['where'] = $this->where;
		$this->group && $i['group'] = $this->group;
		$this->sort && $i['sort'] = $this->sort;
		$this->limit && $i['limit'] = $this->limit;
		return $i;
	}
}
