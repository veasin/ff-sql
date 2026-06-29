<?php
declare(strict_types=1);
namespace ff\helpers\sql;

use ff\helpers\sql;

class operate extends expr{
	protected bool $negate = false;
	public function __construct(protected(set) string $name, protected(set) array $args){
		foreach($this->args as &$arg){
			if(!$arg instanceof expr) $arg = sql::value($arg);
		}
	}
	public function __toString(): string{
		$func = $this->name;
		if(str_starts_with($func, 'not_')){
			$func = substr($func, 4); // 移除 'not_' 前缀
			$this->negate = !$this->negate;
		}
		if($func==='operate'){
			$_count =count($this->args);
			if($_count>1){
				$func =$this->args[$_count-1];
				if($func instanceof expr) $func =$func->value;
				unset($this->args[$_count-1]);
			} else $func = '=';
		}
		$map = [
			'add' => '+', 'sub' => '-', 'mul' => '*', 'div' => '/', 'mod' => '%',
			'eq' => '=', 'ne' => '!=', 'lt' => '<', 'le' => '<=', 'gt' => '>', 'ge' => '>=', 'nullsafe_eq' => '<=>',
			'equal'=>'=',//兼容
			'and' => 'AND', 'or' => 'OR', 'xor' => 'XOR',
			'like' => 'LIKE', 'rlike' => 'REGEXP', 'regexp' => 'REGEXP',
			'between' => 'BETWEEN','in' =>'IN',
		];
		return builder::operate($map[$func] ?? strtoupper($func), $this->args, $this->negate, $this->alias, sql::$current?->dialect);
	}
	public function __debugInfo(): ?array{
		$i =['operate'=>$this->name. ($this->negate ?" (!)":"")];
		$this->args && $i['args'] = $this->args;
		return $i;
	}
}
