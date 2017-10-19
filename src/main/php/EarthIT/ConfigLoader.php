<?php

class EarthIT_ConfigLoader_JSONDecodeError extends Exception {}

class EarthIT_ConfigLoader_JSON {
	public static function jsonDecodeMessage( $code ) {
		switch( $code ) {
		case JSON_ERROR_NONE             : return "No error";
		case JSON_ERROR_DEPTH            : return "Maximum stack depth exceeded";
		case JSON_ERROR_STATE_MISMATCH   : return "Underflow or the modes mismatch";
		case JSON_ERROR_CTRL_CHAR        : return "Unexpected control character found";
		case JSON_ERROR_SYNTAX           : return "Syntax error, malformed JSON";
		case JSON_ERROR_UTF8             : return "Malformed UTF-8 characters, possibly incorrectly encoded";
		default                          : return "json_last_error() = $code";
		}
	}

	protected static function lastJsonErrorMessage() {
		return function_exists('json_last_error_msg') ?
			json_last_error_msg() :
			self::jsonDecodeMessage(json_last_error());
	}
	
	public static function decode( $thing, $sourceLocation='' ) {
		$fromStr = $sourceLocation ? " from $sourceLocation" : "";
		
		if( !is_string($thing) ) {
			throw new EarthIT_ConfigLoader_JSONDecodeError("Attempted to JSON-decode{$fromStr} non-string: ".gettype($thing));
		}
		
		$thing = trim($thing);
		if( $thing == '' ) throw new EarthIT_ConfigLoader_JSONDecodeError("Attempted to JSON-decode empty string{$fromStr}.");
		if( $thing == 'null' ) return null;
		$value = json_decode($thing, true);
		if( $value === null ) {
			$report_thing = strlen($thing) < 256 ? $thing : substr($thing,0,253)."...";
			$message = self::lastJsonErrorMessage();
			throw new EarthIT_ConfigLoader_JSONDecodeError("Error parsing JSON{$fromStr}: $message; JSON: $report_thing");
		}
		return $value;
	}
}

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
		if( is_array($v1) && is_array($v2) ) return array_replace_recursive($v1, $v2);
		return $v2;
	}

	protected function merge(array $path, $value) {
		$arr =& $this->loaded;
		$prePath = $path;
		$lastComponent = array_pop($prePath);
		foreach( $prePath as $p ) {
			if( !isset($arr[$p]) ) $arr[$p] = array();
			$arr =& $arr[$p];
		}
		$value = !isset($arr[$lastComponent]) ? $value : self::mergeValues($arr[$lastComponent], $value);
		$arr[$lastComponent] = $value;
		//echo "Merged ".implode('/',$path)." = ".var_export($value,true)."\n";
		//echo "Loaded: "; print_r($this->loaded);
	}
	
	protected function maybeLoadFromFile(array $path, $fn) {
		//echo "Look for file $fn...";
		if( file_exists($fn) ) {
			//echo "reading as ".implode('/',$path)."\n";
			$this->merge($path, EarthIT_ConfigLoader_JSON::decode(file_get_contents($fn), $fn));
		} else {
			//echo "nope\n";
		}
	}

	protected function maybeLoadRecursivelyFromDir(array $path, $fn) {
		//echo "Looking for dir $fn...";
		if( is_dir($fn) ) {
			//echo "reading as ".implode('/',$path)."\n";
			$dh = opendir($fn);
			if( $dh === false ) throw new Exception("Failed to opendir('$fn')");
			while( ($n = readdir($dh)) !== false ) {
				if( $n[0] == '.' ) continue;
				$sp = "${fn}/${n}";
				if( is_dir($sp) ) {
					$subPath = $path;
					$subPath[] = $n;
					$this->maybeLoadRecursivelyFromDir($subPath, $sp);
				} else if( preg_match('/^(.*)\.json$/',$n, $bif) ) {
					$subPath = $path;
					$subPath[] = $bif[1];
					$this->maybeLoadFromFile($subPath, $sp);
				}
			}
			closedir($dh);
		} else {
			//echo "nope\n";
		}
	}

	protected function maybeLoadRecursivelyFromEnv(array $path, $prefix) {
		$prefixLen = strlen($prefix);
		foreach( $this->env as $k=>$v ) {
			if( $k == $prefix ) {
				$this->merge($path, $v);
			} else if( substr($k, 0, $prefixLen) == $prefix ) {
				$postfix = substr($k, $prefixLen);
				if( $postfix[0] != '_' ) continue;
				$postfix = substr($postfix, 1);
				$postfixParts = explode('_', $postfix);
				if( $postfixParts[count($postfixParts)-1] == 'json' ) {
					array_pop($postfixParts);
					$v = EarthIT_ConfigLoader_JSON::decode($v, "environment variable '$k'");
				}
				$this->merge(array_merge($path, $postfixParts), $v);
			}
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
		$parts = explode('/', $path);
		$traversedParts = array();
		foreach( $parts as $p ) {
			$traversedParts[] = $p;
			$this->maybeLoadFromFile($traversedParts, $this->configDir.'/'.implode('/',$traversedParts).'.json');
			$this->maybeLoadFromEnv($traversedParts, implode('_',$traversedParts));
		}
		$this->maybeLoadRecursivelyFromDir($parts, $this->configDir.'/'.implode('/',$parts));
		$this->maybeLoadRecursivelyFromEnv($parts, implode('_',$parts));
	}
	
	protected $cache = [];
	public function get( $path, array $options=array() ) {
		$this->load($path);
		$c = $this->loaded;
		$parts = explode('/', $path);
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