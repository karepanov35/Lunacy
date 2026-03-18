<?php

declare(strict_types=1);

namespace pocketmine\data\bedrock\block\convert\property;

use pocketmine\data\bedrock\block\BlockStateDeserializeException;
use pocketmine\data\bedrock\block\convert\BlockStateReader;
use pocketmine\data\bedrock\block\convert\BlockStateWriter;

/**
 * Property that serializes a fixed string and on deserialize reads it if present (e.g. for creative inventory),
 * otherwise does nothing (e.g. world save with empty states).
 */
final class OptionalDummyStringProperty implements Property{

	public function __construct(
		private string $name,
		private string $value
	){}

	public function getName() : string{
		return $this->name;
	}

	public function deserialize(object $block, BlockStateReader $in) : void{
		try{
			$in->readString($this->name);
		}catch(BlockStateDeserializeException){
			// optional: e.g. world save has no state
		}
	}

	public function serialize(object $block, BlockStateWriter $out) : void{
		$out->writeString($this->name, $this->value);
	}
}
