<?php

declare(strict_types=1);

namespace pocketmine\plugin;

use pocketmine\thread\ThreadSafeClassLoader;
use function file_exists;
use function is_dir;
use function rtrim;
use function yaml_parse_file;
use const DIRECTORY_SEPARATOR;

class FolderPluginLoader implements PluginLoader{

	public function __construct(private ThreadSafeClassLoader $loader){}

	public function canLoadPlugin(string $path) : bool{
		return is_dir($path) && file_exists($path . DIRECTORY_SEPARATOR . "plugin.yml");
	}

	public function loadPlugin(string $file) : void{
		$description = $this->getPluginDescription($file);
		if($description === null){
			throw new PluginException("Invalid plugin.yml");
		}

		$file = rtrim($file, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		$srcNamespacePrefix = $description->getSrcNamespacePrefix();
		if($srcNamespacePrefix !== ""){
			$this->loader->addPath($srcNamespacePrefix, $file . "src");
		}else{
			$this->loader->addPath("", $file . "src");
		}
	}

	public function getPluginDescription(string $file) : ?PluginDescription{
		$pluginYml = rtrim($file, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "plugin.yml";
		if(!file_exists($pluginYml)){
			return null;
		}

		$yaml = @yaml_parse_file($pluginYml);
		if($yaml === false || !is_array($yaml)){
			return null;
		}

		try{
			return new PluginDescription($yaml);
		}catch(PluginDescriptionParseException $e){
			return null;
		}
	}

	public function getAccessProtocol() : string{
		return "";
	}
}
