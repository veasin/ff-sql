<?php
declare(strict_types=1);
namespace ff\helpers\sql;

use ff\helpers\sql;

class field extends expr{
	public function __construct(readonly protected string $name, readonly protected ?table $table = null){}
	public function __toString(): string{
		$table = $this->table && sql::$current?->hasJoin() ? $this->table->alias ?? $this->table->name : null;
		return builder::field($this->name, $table, $this->alias, sql::$current?->dialect);
	}
	public function __debugInfo(): ?array{
		$f = ($this->table?->name ? $this->table?->name . "." : '') . $this->name . ($this->alias ? "($this->alias)" : '');
		return ['field' => $f];
	}
}
