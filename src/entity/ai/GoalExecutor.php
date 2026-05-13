<?php

declare(strict_types=1);

namespace pocketmine\entity\ai;

use pocketmine\entity\Living;
use function usort;

final class GoalExecutor{
	/** @var Sensor[] */
	private array $sensors;
	/** @var Goal[] */
	private array $goals;
	/** @var array<string, mixed> */
	private array $memory = [];

	/**
	 * @param Sensor[] $sensors
	 * @param Goal[]   $goals
	 */
	public function __construct(array $sensors, array $goals){
		$this->sensors = $sensors;
		$this->goals = $goals;
		usort($this->goals, static fn(Goal $a, Goal $b) => $b->getPriority() <=> $a->getPriority());
	}

	public function tick(Living $entity, int $tickDiff = 1) : void{
		foreach($this->sensors as $sensor){
			$sensor->collect($entity, $this->memory);
		}

		foreach($this->goals as $goal){
			if($goal->canRun($entity, $this->memory)){
				$goal->tick($entity, $this->memory, $tickDiff);
				return;
			}
		}
	}

	/**
	 * @param array<string, mixed> $memory
	 */
	public function setMemory(array $memory) : void{
		$this->memory = $memory;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getMemory() : array{
		return $this->memory;
	}
}

