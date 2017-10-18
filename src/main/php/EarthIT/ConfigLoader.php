<?php

class EarthIT_ConfigLoader
{
	const ON_MISSING_VALUE = 'onMissingValue';
	const ON_MISSING_VALUE_RETURN_NULL = 'returnNull';
	const ON_MISSING_VALUE_THROW_EXCEPTION = 'throwException';
	
	protected $configDir;
	protected $env;
	public function __construct( $configDir, array $envVars=array() ) {
		$this->configDir = $configDir;
		$this->env = $envVars;
	}
	
	protected $loaded;
	
	protected static function mergeValues($v1, $v2) {
		// Do we really want array_replace_recursive,
		// or something more like a hybrid of that and array_merge_recursive,
		// where numeric keys are renumbered to not collide?
		if( is_array($v1) && is_value($v2) ) return array_replace_recursive($v1, $v2);
		return $v2;
	}
	
	protected function merge(array $path, $value) {
		$arr =& $this->loaded;
		$lastComponent = array_pop($path);
		foreach( $path as $p ) {
			if( !isset($arr[$p]) ) $arr[$p] = array();
			$arr =& $arr[$p];
		}
		if( !isset($arr[$lastComponent]) ) {
			$arr[$lastComponent] = $value;
		} else {
			$arr[$lastComponent] = self::mergeValues($arr[$lastComponent], $value);
		}
	}
	
	protected function maybeLoadFromFile(array $path, $fn) {
		if( file_exists($fn) ) {
			$this->merge($path, EarthIT_JSON::decode(file_get_contents($fn)));
		}
	}
	
	protected function maybeLoadFromEnv(array $path, $prefix) {
		if( isset($this->env[$prefix]) ) {
			$this->merge($path, $this->env[$prefix]);
		} else if( isset($this->env["{$prefix}_json"]) ) {
			$this->merge($path, EarthIT_JSON::decode($this->env["{$prefix}_json"]));
		}
	}
	
	protected function load( $path ) {
		$parts = explode('/', $name);
		$traversedParts = array();
		foreach( $parts as $p ) {
			$traversedParts[] = $p;
			$this->maybeLoadFromFile($traversedParts, implode('/',$traversedParts),'.json');
			$this->maybeLoadFromEnv($traversedParts, implode('_',$traversedParts));
		}
		$this->maybeLoadRecursivelyFromDir($parts, implode('/',$parts));
		$this->maybeLoadRecursivelyFromEnv($parts, implode('_',$parts));
	}
	
	protected $cache = [];
	public function get( $name, array $options=array() ) {
		$this->load($path);
		$parts = explode('/', $name);
		$traversedParts = array();
		foreach( $parts as $p ) {
			$traversedParts[] = $p;
			if( isset($c[$p]) ) {
				$c = $c[$p];
			} else {
				$failMode = isset($options[self::ON_MISSING_VALUE]) ?
					$options[self::ON_MISSING_VALUE] :
					self::ON_MISSING_VALUE_THROW_EXCEPTION;
				switch( $failMode ) {
				case self::ON_MISSING_VALUE_RETURN_NULL: return null;
				case self::ON_MISSING_VALUE_THROW_EXCEPTION:
					throw new Exception("No such config variable: ".implode('/',$traversedParts));
				default:
					throw new Exception("Invalid onMissingValue value: {$failMode}");
				}
			}
		}
		
		return $c;
	}

}