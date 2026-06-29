<?php
declare(strict_types=1);
namespace ff\helpers\sql;

use ff\helpers\sql;

class value extends expr{
	public function __construct(protected(set) mixed $value){}
	public function __toString(): string{
		if($this->value === '*') return '*';
		if($this->value === '\*') $this->value = '*';
		return sql::$current
			? (is_array($this->value) ? join(',', array_map(sql::$current->collectParam(...), $this->value)) : sql::$current->collectParam($this->value))
			: builder::value($this->value,
				$this->alias,
				sql::$current?->dialect
			);
	}
	public function __debugInfo(): ?array{
		$i =['value'=>$this->value];
		$this->alias && $i['alias'] = $this->alias;
		return $i;
	}
}