<?php
/**
* ========================================================
* Spicy Yaml parser
* this library tuned oroginal spyc http://code.google.com/p/spyc/
*
* @author Yoshiaki Sugimoto <neo.yoshiaki.sugimoto@gmail.com>
* @license http://www.opensource.org/licenses/mit-license.php MIT License
* ========================================================
*/

class Spicy
{
	private static $path;
	private static $strlen;
	private static $delayedPath = array();
	private static $result      = array();
	private static $savedGroups = array();
	
	// Dymnamic group static status
	private static $groupAnchor = FALSE;
	private static $groupAlias  = FALSE;
	
	// Some formats array
	private static $trulyChars    = array('true', 'on', '+', 'yes', 'y');
	private static $falsyChars    = array('false', 'off', '-', 'no', 'n');
	private static $escapedQuotes = array ('\\"' => '"', '\'\'' => '\'', '\\\'' => '\'');
	
	// String/Sequence/Map regex
	private static $strRegex      = '/(?:(")|(?:\'))((?(1)[^"]+|[^\']+))(?(1)"|\')/';
	private static $sequenceRegex = '/\[([^{}\[\]]+)\]/U';
	private static $mapRegex      = '/{([^\[\]{}]+)}/U';
	
	/*
	// Group parse regex
	private static $groupAnchorRefRegex       = '/^(&[A-z0-9_\-]+)/';
	private static $groupAnchorContainRegex   = '/(&[A-z0-9_\-]+)$/';
	private static $groupAliasRefRegex        = '/^(\*[A-z0-9_\-]+)/';
	private static $groupAliasContainRegex    = '/(\*[A-z0-9_\-]+)$/';
	private static $groupColonFirstGroupRegex = '#^\s*<<\s*:\s*(\*[^\s]+).*$#';
	*/
	// Placefolders constant
	const PLACEHOLDER          = '__SPICYYAML__';
	const ZEROKEY              = '__SPICYZERO__';
	const SEQUENCE_PLACEHOLDER = '__SPICYSEQUENCE__';
	const MAP_PLACEHOLDER      = '__SPICYMAP__';
	const STRING_PLACEHOLDER   = '__SPICYSTRING__';
	
	// Double/Single quotes
	const DOUBLE_QUOTE         = '"';
	const SINGLE_QUOTE         = '\'';
	
	public static $dump;
	
	public static function loadFile($file)
	{
		if ( ! file_exists($file) )
		{
			throw new InvalidArgumentException('Yaml file is not exists.');
		}
		
		return self::_parseString(file($file));
	}
	
	public static function loadString($string)
	{
		$lines = explode("\n", $string);
		return self::_parseString(lines);
	}
	
	private static function _parseString($lines)
	{
		self::$path        = array();
		self::$result      = array();
		self::$delayedPath = array();
		self::$savedGroups = array();
		self::$groupAlias  = FALSE;
		self::$groupAnchor = FALSE;
		$count             = count($lines);
		
		for ( $i = 0; $i < $count; ++$i )
		{
			$line   = $lines[$i];
			$indent = $idt = strlen($line) - strlen(ltrim($line));
			
			// getParentPathByIndent extracts inline
			if ( $indent === 0 )
			{
				$tmpPath = array();
			}
			else
			{
				$tmpPath = self::$path;
				do
				{
					end($tmpPath);
					$lastIndentPath = key($tmpPath);
					if ( $indent <= $lastIndentPath )
					{
						array_pop($tmpPath);
					}
				}
				while ( $indent <= $lastIndentPath );
			}
			
			if ( $indent === -1 )
			{
				$idt = strlen($line) - strlen(ltrim($line));
			}
			$line = substr($line, $idt);
			// line string is commented or empty section?
			if ( trim($line) === ''
			     || $line[0] === '#'
			     || trim($line, " \r\n\t") === '---' )
			{
				continue;
			}
			self::$path        = $tmpPath;
			$lastChar          = substr(trim($line), -1);
			$literalBlockStyle = ( ($lastChar !== '>' && $lastChar !== '|') || preg_match('#<.*?>$#', $line) ) ? FALSE : $lastChar;//self::_getLiteralBlock($line);*/
			if ( $literalBlockStyle )
			{
				$literalBlock       = '';
				$line               = rtrim($line, $literalBlockStyle . " \n") . self::PLACEHOLDER;
				$literalBlockIndent = strlen($lines[++$i]) - strlen(ltrim($lines[$i--]));
				while ( ++$i < $count //&& self::_literalBlockContinues($lines[$i], $indent) )
				       && ( ! trim($lines[$i]) || (strlen($lines[$i]) - strlen(ltrim($lines[$i]))) > $indent ) )
				{
					//$literalBlock = self::_addLiteralLine($literalBlock, $lines[$i], $literalBlockStyle, $literalBlockIndent);
					
					$tmpLine = $lines[$i];
					$_indent = ( $literalBlockIndent === -1 ) ? (strlen($tmpLine) - strlen(ltrim($tmpLine))) : $literalBlockIndent;
					$tmpLine = substr($tmpLine, $_indent);
					
					if ( $literalBlockStyle !== '|' )
					{
						$_indent = strlen($tmpLine) - strlen(ltrim($tmpLine));
						$tmpLine = substr($tmpLine, $_indent);
					}
					$tmpLine = rtrim($tmpLine, "\r\n\t ") ."\n";
					if ( $literalBlockStyle === '|' )
					{
						$literalBlock = $literalBlock . $tmpLine;
					}
					else if ( $tmpLine == "\n" && $literalBlockStyle === '>' )
					{
						$literalBlock = rtrim($literalBlock, " \t") . "\n";
					}
					else if ( strlen($tmpLine) === 0 )
					{
						$literalBlock = rtrim($literalBlock, ' ') . "\n";
					}
					else
					{
						if ( $tmpLine !== "\n" )
						{
							$tmpLine = trim($tmpLine, "\r\n ") . " ";
						}
						$literalBlock = $literalBlock . $tmpLine;
					}
					//$literalBlock = self::_addLiteralLine($literalBlock, $lines[$i], $literalBlockStyle, $literalBlockIndent);
					
				}
				$i--;
			}
			
			while ( ++$i < $count )// && self::_greedilyNeedNextLine($line) )
			{
				$tmpLine = trim($line);
				if ( ! strlen($tmpLine) || substr($tmpLine, -1, 1) === ']' )
				{
					break;
				}
				if ( $tmpLine[0] === '[' || preg_match ('#^[^:]+?:\s*\[#', $tmpLine))
				{
					$line = rtrim($line, " \n\t\r") . ' ' . ltrim($lines[$i], " \t");
					continue;
				}
				break;
				
				//$line = rtrim($line, " \n\t\r") . ' ' . ltrim($lines[$i], " \t");
			}
			$i--;
			
			if ( strpos($line, '#') )
			{
				if ( strpos($line, self::DOUBLE_QUOTE) === FALSE && strpos($line, self::SINGLE_QUOTE) === FALSE )
				{
					$line = preg_replace('/\s+#(.+)$/', '', $line);
				}
			}
			
			$lineArray = self::_parseLine($line, $indent);
			if ( $literalBlockStyle )
			{
				$lineArray = self::_revertLiteralPlaceHolder($lineArray, $literalBlock);
			}
			
			self::_addArray($lineArray, $indent);
			$delayed = self::$delayedPath;
			foreach ( $delayed as $idt => $delayedPath )
			{
				self::$path[$idt] = $delayedPath;
			}
			
			self::$delayedPath = array();
		}
		return self::$result;
	}
	
	private static function _parseLine($line, $indent)
	{
		if ( ! $line )
		{
			return array();
		}
		$line = trim($line);
		if ( ! $line )
		{
			return array();
		}
		$ret    = array();
		$groups = FALSE;
		// nodeContailsGroup inline
		if ( strpos($line, '&') === FALSE && strpos($line, '*') === FALSE )
		{
			$groups = FALSE;
		}
		else if ( $line[0] === '&'
		     && preg_match('/^(&[A-z0-9_\-]+)/', $line, $match) )
		{
			$groups = $match[1];
		}
		else if ( $line[0] === '*'
		     && preg_match('/^(\*[A-z0-9_\-]+)/', $line, $match) )
		{
			$groups = $match[1];
		}
		else if ( preg_match('/(&[A-z0-9_\-]+)$/', $line, $match) )
		{
			$groups = $match[1];
		}
		else if ( preg_match('/(\*[A-z0-9_\-]+)$/', $line, $match) )
		{
			$groups = $match[1];
		}
		else if ( preg_match ('#^\s*<<\s*:\s*(\*[^\s]+).*$#', $line, $match) )
		{
			$groups = $match[1];
		}
		//$groups = self::_nodeContainsGroup($line);
		if ( $groups )
		{
			// Add group
			if ( $groups[0] === '&' )
			{
				self::$groupAnchor = substr($groups, 1);
			}
			else if ( $groups[0] === '*' )
			{
				self::$groupAlias = substr($groups, 1);
			}
			// self::_addGroup($line, $groups);
			$line = trim(str_replace($groups, '', $line));
		}
		$last = substr($line, -1, 1);
		
		// Mapped sequence
		if ( $line[0] === '-' && $last === ':' )
		{
			$key               = self::_unquote(trim(substr($line, 1, -1)));
			$ret[$key]         = array();
			$delayedKey        = strpos($line, $key) + $indent;
			self::$delayedPath = array($delayedKey => $key);
			return array($ret);
		}
		// Mapped value
		if ( $last === ':' )
		{
			$key       = self::_unquote(trim(substr($line, 0, -1)));
			$ret[$key] = '';
			return $ret;
		}
		// Array element
		if ( $line
		     && $line[0] === '-'
		     && ! (strlen($line) > 3 && substr($line, 0, 3) === '---') )
		{
			if ( strlen($line) <= 1 )
			{
				$ret = array(array());
			}
			else
			{
				$val   = trim(substr($line, 1));
				$val   = self::_toType($val);
				$ret[] = $val;
			}
			return $ret;
		}
		// Plain array
		if ( $line[0] === '[' && $last === ']' )
		{
			return self::_toType($line);
		}
		
		// getKeyValuePair inline
		$ret = array();
		$key = '';
		if ( strpos($line, ':') )
		{
			if ( ($line[0] === self::DOUBLE_QUOTE || $line[0] === self::SINGLE_QUOTE)
			     && preg_match('#^(["\'](.*)["\'](\s)*:)#', $line, $match) )
			{
				$val = trim(str_replace($match[1], '', $line));
				$key = $match[2];
			}
			else
			{
				$point = strpos($line, ':');
				$key   = trim(substr($line, 0, $point));
				$val   = trim(substr($line, ++$point));
			}
			
			$val = self::_toType($val);
			if ( $key === '0' )
			{
				$key = self::ZEROKEY;
			}
			$ret[$key] = $val;
		}
		else
		{
			$ret = array($line);
		}
		return $ret;
		
		//return self::_getKeyValuePair($line);
	}
	
	/*
	private static function _greedilyNeedNextLine($line)
	{
		$line = trim($line);
		if ( ! strlen($line) )
		{
			return FALSE;
		}
		if ( substr($line, -1, 1) === ']' )
		{
			return FALSE;
		}
		if ( $line[0] === '[' )
		{
			return TRUE;
		}
		if ( preg_match ('#^[^:]+?:\s*\[#', $line) )
		{
			return TRUE;
		}
		return FALSE;
	}
	
	/*
	private static function _literalBlockContinues($line, $indent)
	{
		if ( ! trim($line) || (strlen($line) - strlen(ltrim($line))) > $indent )
		{
			return TRUE;
		}
		return FALSE;
	}
	 * */
	
	private static function _unquote($str)
	{
		if ( $str && (string)$str === $str )
		{
			if ( $str[0] === self::SINGLE_QUOTE )
			{
				return trim($str, self::SINGLE_QUOTE);
			}
			if ( $str[0] === self::DOUBLE_QUOTE )
			{
				return trim($str, self::DOUBLE_QUOTE);
			}
		}
		return $str;
	}
	
	/*
	private static function _getKeyValuePair($line)
	{
		$ret = array();
		$key = '';
		if ( strpos($line, ':') )
		{
			if ( ($line[0] === self::DOUBLE_QUOTE || $line[0] === self::SINGLE_QUOTE)
			     && preg_match('#^(["\'](.*)["\'](\s)*:)#', $line, $match) )
			{
				$val = trim(str_replace($match[1], '', $line));
				$key = $match[2];
			}
			else
			{
				$point = strpos($line, ':');
				//$exp = explode(':', $line, 2);
				$key = trim(substr($line, 0, $point));
				$val = trim(substr($line, ++$point));
			}
			
			$val = self::_toType($val);
			if ( $key === '0' )
			{
				$key = self::ZEROKEY;
			}
			$ret[$key] = $val;
		}
		else
		{
			$ret = array($line);
		}
		return $ret;
	}
	 */
	
	private static function _toType($str)
	{
		if ( $str === '' )
		{
			return NULL;
		}
		$first    = $str[0];
		$last     = substr($str, -1, 1);
		$isQuoted = FALSE;
		
		do
		{
			if ( ! $str
			    || ($first !== self::DOUBLE_QUOTE && $first !== self::SINGLE_QUOTE)
				|| ($last  !== self::DOUBLE_QUOTE && $last  !== self::SINGLE_QUOTE) )
			{
				break;
			}
			$isQuoted = TRUE;
		}
		while ( 0 );
		
		if ( $isQuoted === TRUE )
		{
			return strtr(substr($str, 1, -1), self::$escapedQuotes);
		}
		
		if ( strpos($str, ' #') !== FALSE && ! $isQuoted )
		{
			$str = preg_replace('/\s+#(.+)$/', '', $str);
		}
		
		if ( ! $isQuoted )
		{
			$str = str_replace('\n', "\n", $str);
		}
		
		if ( $first === '[' && $last === ']' )
		{
			$inner = trim(substr($str, 1, -1));
			if ( $inner === '' )
			{
				return array();
			}
			$exp = self::_inlineEscape($inner);
			$ret = array();
			foreach ( $exp as $v )
			{
				$ret[] = self::_toType($v);
			}
			return $ret;
		}
		
		if ( strpos($str, ': ') !== FALSE && $first !== '{' )
		{
			$point = strpos($str, ': ');//explode(': ', $str, 2);
			$key   = trim(substr($str, 0, $point));
			$val   = self::_toType(trim(substr($str, ++$point)));
			return array($key => $val);
		}
		
		if ( $first === '{' && $last === '}' )
		{
			$inner = trim(substr($str, 1, -1));
			if ( $inner === '' )
			{
				return array();
			}
			$exp = self::_inlineEscape($inner);
			$ret = array();
			foreach ( $exp as $v )
			{
				$sub = self::_toType($v);
				if ( empty($sub) )
				{
					continue;
				}
				if ( is_array($sub) )
				{
					$ret[key($sub)] = $sub[key($sub)];
					continue;
				}
				$ret[] = $sub;
			}
			return $ret;
		}
		
		if ( $str === 'null' || $str === 'NULL' || $str === 'Null'
		     || $str === '' || $str === '~' )
		{
			return NULL;
		}
		
		if ( is_numeric($str) && preg_match('/^(-|)[1-9]+[0-9]*$/', $str) )
		{
			$int = (int)$str;
			if ( $int != PHP_INT_MAX )
			{
				$str = $int;
			}
			return $str;
		}
		
		$lower = strtolower($str);
		if ( in_array($lower, self::$trulyChars) )
		{
			return TRUE;
		}
		
		if ( in_array($lower, self::$falsyChars) )
		{
			return FALSE;
		}
		
		if ( is_numeric($str) )
		{
			if ( $str === '0' )
			{
				return 0;
			}
			if ( rtrim($str, 0) === $str )
			{
				$str = (float)$str;
			}
			return $str;
		}
		
		return $str;
	}
	
	private static function _inlineEscape($val)
	{
		$sequences = array();
		$maps      = array();
		$saved     = array();
		
		if ( preg_match_all(self::$strRegex, $val, $strings) )
		{
			$saved = $strings[0];
			$val   = preg_replace(self::$strRegex, self::STRING_PLACEHOLDER, $val);
		}

		$i = 0;
		do
		{
			while ( preg_match(self::$sequenceRegex, $val, $matchseq) )
			{
				$sequences[] = $matchseq[0];
				$val         = preg_replace(self::$sequenceRegex, (self::SEQUENCE_PLACEHOLDER . (count($sequences) - 1) . 's'), $val, 1);
			}
			
			while ( preg_match(self::$mapRegex, $val, $matchmap) )
			{
				$maps[] = $matchmap[0];
				$val    = preg_replace(self::$mapRegex, (self::MAP_PLACEHOLDER . (count($maps) - 1) . 's'), $val, 1);
			}
			
			if ( $i++ >= 10 )
			{
				break;
			}
		}
		while ( strpos($val, '[') !== FALSE || strpos($val, '{') !== FALSE );
		
		$exp  = explode(', ', $val);
		$stri = 0;
		$i    = 0;
		
		while ( TRUE )
		{
			if ( ! empty($sequences) )
			{
				foreach ( $exp as $k => $v )
				{
					if ( strpos($v, self::SEQUENCE_PLACEHOLDER) !== FALSE )
					{
						foreach ( $sequences as $kk => $vv )
						{
							$exp[$k] = str_replace((self::SEQUENCE_PLACEHOLDER . $kk . 's'), $vv, $v);
							$v = $exp[$k];
						}
					}
				}
			}
			
			if ( ! empty($maps) )
			{
				foreach ( $exp as $k => $v )
				{
					if ( strpos($v, self::MAP_PLACEHOLDER) !== FALSE )
					{
						foreach ( $maps as $kk => $vv )
						{
							$exp[$k] = str_replace((self::MAP_PLACEHOLDER . $kk . 's'), $vv, $v);
							$v = $exp[$k];
						}
					}
				}
			}
			
			if ( ! empty($saved) )
			{
				foreach ( $exp as $k => $v )
				{
					while ( strpos($v, self::STRING_PLACEHOLDER) !== FALSE )
					{
						$exp[$k] = preg_replace('/' . self::STRING_PLACEHOLDER . '/', $saved[$stri], $v, 1);
						unset($saved[$stri]);
						++$stri;
						$v = $exp[$k];
					}
				}
			}
			
			$finished = TRUE;
			foreach ( $exp as $k => $v )
			{
				if ( strpos($v, self::SEQUENCE_PLACEHOLDER) !== FALSE
				     || strpos($v, self::MAP_PLACEHOLDER) !== FALSE
				     || strpos($v, self::STRING_PLACEHOLDER) !== FALSE )
				{
					$finished = FALSE;
					break;
				}
			}
			if ( $finished || ++$i > 10 )
			{
				break;
			}
		}
		
		return $exp;
	}
	
	/*
	private static function _nodeContainsGroup($line)
	{
		if ( strpos($line, '&') === FALSE
		     && strpos($line, '*') === FALSE )
		{
			return FALSE;
		}
		if ( $line[0] === '&'
		     && preg_match('/^(&[A-z0-9_\-]+)/', $line, $match) )
		{
			return $match[1];
		}
		if ( $line[0] === '*'
		     && preg_match('/^(\*[A-z0-9_\-]+)/', $line, $match) )
		{
			return $match[1];
		}
		if ( preg_match('/(&[A-z0-9_\-]+)$/', $line, $match) )
		{
			return $match[1];
		}
		if ( preg_match('/(\*[A-z0-9_\-]+)$/', $line, $match) )
		{
			return $match[1];
		}
		if ( preg_match ('#^\s*<<\s*:\s*(\*[^\s]+).*$#', $line, $match) )
		{
			return $match[1];
		}
		return FALSE;
	}
	
	private static function _addGroup($line, $group)
	{
		if ( $group[0] === '&' )
		{
			self::$groupAnchor = substr($group, 1);
		}
		if ( $group[0] === '*' )
		{
			self::$groupAlias = substr($group, 1);
		}
	}
	*/
	
	private static function _addArray($lineArray, $indent)
	{
		if ( count($lineArray) > 1 )
		{
			// addArrayInline inline
			$groupPath = self::$path;
			foreach ( $lineArray as $k => $v )
			{
				self::_addArray(array($k => $v), $indent);
				self::$path = $groupPath;
			}
			return;
			//return self::_addArrayInline($lineArray, $indent);
		}
		
		$key = key($lineArray);
		$val = ( isset($lineArray[$key]) ) ? $lineArray[$key] : NULL;
		if ( $key === self::ZEROKEY )
		{
			$key = '0';
		}
		
		if ( $indent == 0 && ! self::$groupAlias && ! self::$groupAnchor)
		{
			if ( $key || $key === '' || $key === '0' )
			{
				self::$result[$key] = $val;
			}
			else
			{
				self::$result[] = $val;
				end(self::$result);
				$key = key(self::$result);
			}
			self::$path[$indent] = $key;
			return;
		}
		
		$history   = array();
		$history[] = $_arr = self::$result;
		foreach ( self::$path as $path )
		{
			$history[] = $_arr = $_arr[$path];
		}
		
		if ( self::$groupAlias )
		{
			//$val = self::_referenceAlias(self::$groupAlias);
			
			do
			{
				if ( ! isset(self::$savedGroups[self::$groupAlias]) )
				{
					throw new LogicException('Bad group name:' . self::$groupAlias . '.');
					break;
				}
				//$groupPath = self::$savedGroups[self::$groupAlias];
				$val = self::$result;
				foreach ( self::$savedGroups[self::$groupAlias] as $g )
				{
					$val = $val[$g];
				}
			}
			while ( FALSE );
			self::$groupAlias = FALSE;
		}
		
		if ( (string)$key === $key && $key === '<<' )
		{
			if ( ! is_array($_arr) )
			{
				$_arr = array();
			}
			$_arr = array_merge($_arr, $val);
		}
		else if ( $key || $key === '' || $key === '0' )
		{
			if ( ! is_array($_arr) )
			{
				$_arr = array($key => $val);
			}
			else
			{
				$_arr[$key] = $val;
			}
		}
		else
		{
			if ( ! is_array($_arr) )
			{
				$_arr = array($val);
				$key  = 0;
			}
			else
			{
				$_arr[] = $val;
				end($_arr);
				$key = key($_arr);
			}
		}
		$reversePath       = array_reverse(self::$path);
		$reverseHistory    = array_reverse($history);
		$reverseHistory[0] = $_arr;
		$count             = count($reverseHistory) - 1;
		for ( $i = 0; $i < $count; ++$i )
		{
			$reverseHistory[$i + 1][$reversePath[$i]] = $reverseHistory[$i];
		}
		self::$result        = $reverseHistory[$count];
		self::$path[$indent] = $key;
		
		if ( self::$groupAnchor )
		{
			self::$savedGroups[self::$groupAnchor] = self::$path;
			if ( is_array($val) )
			{
				$k = key($val);
				if ( (int)$k !== $k/*! is_int($k)*/ )
				{
					self::$savedGroups[self::$groupAnchor][$indent + 2] = $k;
				}
			}
			self::$groupAnchor = FALSE;
		}
	}
	
	/*
	private static function _addArrayInline($lineArray, $indent)
	{
		$groupPath = self::$path;
		if ( empty($lineArray) )
		{
			return FALSE;
		}
		foreach ( $lineArray as $k => $v )
		{
			self::_addArray(array($k => $v), $indent);
			self::$path = $groupPath;
		}
		return TRUE;
	}
	 */ 
	/*
	private static function _referenceAlias($alias)
	{
		do
		{
			if ( ! isset(self::$savedGroups[$alias]) )
			{
				throw new LogicException('Bad group name:' . $alias . '.');
				break;
			}
			$groupPath = self::$savedGroups[$alias];
			$val = self::$result;
			foreach ( $groupPath as $g )
			{
				$val = $val[$g];
			}
		}
		while ( FALSE );
		return $val;
	}
	 * */
	
	private static function _revertLiteralPlaceHolder($lineArray, $literalBlock)
	{
		foreach ( $lineArray as $key => $val )
		{
			if ( is_array($val) )
			{
				// recursively
				$lineArray[$key] = self::_revertLiteralPlaceHolder($val, $literalBlock);
			}
			else if ( substr($val, -1 * strlen(self::PLACEHOLDER)) == self::PLACEHOLDER )
			{
				$lineArray[$key] = rtrim($literalBlock, " \r\n");
			}
		}
		return $lineArray;
	}
	
	/*
	private static function _addLiteralLine($literalBlock, $line, $literalBlockStyle, $indent = -1)
	{
		//echo $line . '| ';
		if ( $indent === -1 )
		{
			$idt  = strlen($line) - strlen(ltrim($line));
		}
		else
		{
			$idt = $indent;
		}
		$line = substr($line, $idt);
		
		if ( $literalBlockStyle !== '|' )
		{
			$idt  = strlen($line) - strlen(ltrim($line));
			$line = substr($line, $idt);
		}
		$line = rtrim($line, "\r\n\t ") ."\n";
		if ( $literalBlockStyle === '|' )
		{
			return $literalBlock . $line;
		}
		if ( $line == "\n" && $literalBlockStyle === '>' )
		{
			return rtrim($literalBlock, " \t") . "\n";
		}
		if ( strlen($line) === 0 )
		{
			return rtrim($literalBlock, ' ') . "\n";
		}
		if ( $line !== "\n" )
		{
			$line = trim($line, "\r\n ") . " ";
		}
		return $literalBlock . $line;
	}
	
	private static function _getLiteralBlock($line)
	{
		$last = substr(trim($line), -1);
		if ( $last !== '>' && $last !== '|' )
		{
			return FALSE;
		}
		if ( $last === '|' )
		{
			return $last;
		}
		if ( preg_match('#<.*?>$#', $line) )
		{
			return FALSE;
		}
		return $last;
	}
	 
	
	private static function _getParentPathByIndent($indent)
	{
		$linePath = self::$path;
		do
		{
			end($linePath);
			$lastIndentPath = key($linePath);
			if ( $indent <= $lastIndentPath )
			{
				array_pop($linePath);
			}
		}
		while ( $indent <= $lastIndentPath );
		
		return $linePath;
	}
	*/
}
