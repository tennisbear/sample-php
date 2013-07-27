<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Class for working with jsons
 * 
 * @author Dennis Slade
 * @since  2011-06-14
 */

class Json
{
	const STATUS_OK    = 200;
	const STATUS_ERROR = 500;
	
	private $text = "";
	private $data = NULL;
	private $status = self::STATUS_OK;
	
	
    public function __construct( $encoded_text = "" )
    {
    	$this->decode( $encoded_text );
    }
    
    
    public function decode( $encoded_text = "" )
	{
		$this->text = $encoded_text;
		$this->data = json_decode( $encoded_text );
		
		$this->status = ($this->data === NULL) ? self::STATUS_ERROR
		                                       : self::STATUS_OK;
		
		return $this->data;
	}
	
	
	public static function encode( $data = array(), $pretty_print = TRUE )
	{
		/**
		 * @todo When the JSON_UNESCAPED_SLASHES option finally surfaces, add the option to the json_encode() and then remove the str_replace()
		 * @link https://bugs.php.net/bug.php?id=49366  
		 */
		$text = json_encode( $data );
		$text = str_replace( '\\/', '/', $text );
		
		if ($pretty_print)
			$text = self::prettify( $text );
		
		return $text;
	}
	
	
	public function encode_for_diff()
	{
		$data = $this->force_into_array_recursive( $this->get_data() );
		
		if (!empty( $data ))
		{
			foreach( $data as $key => $row )
			{
				$data[ $key ] = $this->sort_for_export( $row );
			}
		}
		
		return $this->encode( $data );
	}
	
	
	public static function array_to_simple_list( array $array )
	{
		if (empty( $array ))
		{
			return "[]";
		}
		else
		{
			return '["'
			       . implode( '","', $array ) 
			       . '"]';
		}
	}
	
	
	/**
	 * Takes JSON encoded text and makes it human readable
	 * 
	 * @author Michael Maclean (https://github.com/mgdm; he stole it from http://recursive-design.com/blog/2008/03/11/format-json-with-php/)
	 * @link   https://gist.github.com/906036
	 * @param  String $encoded_json_text
	 */
	public static function prettify( $encoded_json_text )
	{
		$result      = '';
		$pos         = 0;
		$strLen      = strlen( $encoded_json_text );
		$indentStr   = "\t";
		$newLine     = "\n";
		$prevChar    = '';
		$outOfQuotes = true;
	
		for( $i=0; $i <= $strLen; $i++ )
		{
			// Grab the next character in the string.
			$char = substr($encoded_json_text, $i, 1);
	
			// Put spaces in front of :
			if ($outOfQuotes && $char == ':' && $prevChar != ' ') {
				$result .= ' ';
			}
	
			if ($outOfQuotes && $char != ' ' && $prevChar == ':') {
				$result .= ' ';
			}
	
			// Are we inside a quoted string?
			if ($char == '"' && $prevChar != '\\') {
				$outOfQuotes = !$outOfQuotes;
	
				// If this character is the end of an element, 
				// output a new line and indent the next line.
			} else if(($char == '}' || $char == ']') && $outOfQuotes) {
				$result .= $newLine;
				$pos --;
				for ($j=0; $j<$pos; $j++) {
					$result .= $indentStr;
				}
			}
	
			// Add the character to the result string.
			$result .= $char;
	
			// If the last character was the beginning of an element, 
			// output a new line and indent the next line.
			if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
				$result .= $newLine;
				if ($char == '{' || $char == '[') {
					$pos ++;
				}
	
				for ($j = 0; $j < $pos; $j++) {
					$result .= $indentStr;
				}
			}
	
			$prevChar = $char;
		}
	
		return $result;
	}
	
	
	/**
	 * WARNING: Recursive. Don't mess with the recursion unless you know exactly what you're doing. :)
	 * 
	 * @param  Mixed $array
	 * @author Dennis Slade
	 */
	public static function sort_for_export( $array )
	{
		/**
		 * Only proceed if we were given a non-empty array
		 */
		if (!empty( $array ) && is_array( $array ))
		{
			foreach( $array as $key => $row )
			{
				/**
				 * Process the row if it's an array
				 */
				if (is_array( $row ))
				{
					foreach( $row as $field_key => $field )
					{
						/**
						 * Only recurse for fields which are numeric arrays
						 */
						if (is_array( $field ) && isset( $field[0] ))
							$row[ $field_key ] = self::sort_for_export( $field );
					}
					
					/**
					 * Sort the row array by key if-and-only-if it has fields (ie, it is not a numerically keyed array)
					 */
					if (!isset( $row[0] ))
					{
						if (ksort( $row ))
							$array[$key] = $row;
					}
				}
			}
		}
		
		return $array;
	}
        
    
	/**
	 * @access private
	 * @param  Mixed $anything Anything at all!
	 * @since  2011-09-02
	 */
	private function force_into_array( $anything )
	{
		if (is_scalar( $anything ))
		{
			return array( $anything );
		}
		elseif (is_object( $anything ))
		{
			return get_object_vars( $anything );
		}
		elseif (empty( $anything ))
		{
			return array();
		}
		else
		{
			return $anything;
		}
	}

	
	/**
	 * @access private
	 * @param  Mixed $anything Anything at all!
	 * @since  2011-09-02
	 */
	private static function force_into_array_recursive( $anything )
	{
		if (is_scalar( $anything ))
			return $anything;
			
		if (is_object( $anything ))
			$anything = get_object_vars( $anything );
			
		if (is_array( $anything ))
		{
			foreach( $anything as $key => $one_thing )
			{
				$anything[ $key ] = self::force_into_array_recursive( $one_thing );
			}
		}
		
		return $anything;
	}

	
	public function get_data()
	{
		return $this->data;
	}
	
	
	public function has_data()
	{
		return isset( $this->data );
	}
	
	
	public function has_errors()
	{
		return (!$this->has_data()
		        || $this->status !== self::STATUS_OK);
	}
	
	
	public function get_errors()
	{
		if (!$this->has_data())
		{
			return "Server didn't return a valid json. Please try again.";
		}	
		elseif (isset( $this->data, $this->data->alerts, $this->data->alerts[0] ))
		{
			$alert = $this->data->alerts[0];
			
			if ($alert->type == "error") 
				return $alert->message; 
		}
		else
		{
			return "";
		}
	}
	
	
	public function get_guid()
	{
		if (isset( $this->data, $this->data->data, $this->data->data->guid ))
		{
			return $this->data->data->guid; 
		}
		else
		{
			return "";
		}
	}
	
	
}

/* End of file Json.php */
/* Location: ./application/libraries/Json.php */
