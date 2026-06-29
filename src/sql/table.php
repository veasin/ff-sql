<?php
declare(strict_types=1);
namespace ff\helpers\sql;

use ff\helpers\sql;

/**
 * @method sql select(array|string|expr|null $fields = [], array $options = [])
 * @method sql insert(array $fields = [], array $options = [])
 * @method sql update($fields = [], array $options = [])
 * @method sql delete(array $options = [])
 * @method sql join(table|sql $table, mixed $on = null, array $options = [])
 * @method sql where(mixed ...$conditions)
 * @method sql limit(int $rows, int $offset = 0)
 * @method sql page(int $page, int $max = 20)
 * @method sql sort(array|string|expr|null $fields = null, string $direction = 'ASC')
 * @method sql group(array|string|expr|null $fields = [], string $direction = 'ASC')
 * @method sql having(mixed ...$conditions)
 */
class table implements \ArrayAccess{
	use alias;

	public function __construct(protected(set) string $name, protected(set) string $primary = 'id'){
		[$this->name, $this->alias] = explode(' ', $name, 2) + ['', null];
	}
	public function __call($name, $arguments): sql{
		return new sql($this)->$name(...$arguments);
	}
	public function __toString(): string{
		return builder::table($this->name, sql::$current?->hasJoin() ? $this->alias : null, sql::$current?->dialect);
	}
	public function offsetGet(mixed $offset): field{
		if(null === $offset) $offset = $this->primary;
		return new field($offset, $this);
	}
	public function offsetSet($offset, $value): void{}
	public function offsetExists($offset): bool{ return false; }
	public function offsetUnset($offset): void{}
	public function __debugInfo(): ?array{
		$f =$this->name.($this->alias ?"($this->alias)" : '')."[$this->primary]";
		return ['table' => $f];
	}
}
