<?php if (!defined('BASEPATH')) exit("No direct access allowed.");
/**
 * "Model with schema" field class
 *
 * @author Dennis Slade
 * @since  2011-06-09
 */


class ModelSchemaField
{
	const CHARGE_ITEM_COUPON           = 'Coupon';
	const CHARGE_ITEM_DISCOUNT         = 'Discount';
	const CUSTOM_ADDITIONAL_CHARGE     = '-- custom additional charge --';
	const CUSTOM_CHARGE_MISSING        = 'Other';
	const CUSTOM_PAYMENT_METHOD        = '-- custom payment method --';
	const CUSTOM_PAYMENT_MISSING       = 'Other';
	const CUSTOM_RENTAL_CHARGE         = '-- custom rental charge --';
	const CUSTOM_RENTAL_CHARGE_MISSING = 'Other';
	const DEFAULT_TIMESTAMP_FORMAT     = 'Y-m-d H:i:s T';
	const HELPER_TEXT_TIMESTAMP        = 'Example shorthand values: 10 years, 1 year, 6 months, 2 weeks, 1 day, yesterday, tomorrow';
	const INTERNAL_RELATIVE            = 'internal_relative';
	const LAST_INSERTED_VALUE          = 'last_inserted_value';
	const LIST_DELIMITER               = ',';
	const LIST_DELIMITER_PLACEHOLDER   = '>>>';
	const NO_VALIDATION                = 'no_validation';
	const SORT_ASCENDING               = 1;
	const SORT_DESCENDING              = -1;
	const TIMESTAMP_IN_MILLISECONDS    = false;	// false = timestamps in seconds
	
	const BOOLEAN      = 'boolean';
	const CHECKBOX     = 'checkbox';
	const GUID         = 'guid';
	const INTERNAL     = 'internal';
	const DB_ID        = '_id';
	const NUMBER       = 'number';
	const PASSWORD     = 'password';
	const TEXT         = 'text';
	const TIMESTAMP    = 'timestamp';
	const _ARRAY       = 'array';
	const _LIST        = 'list';
	const _NUMBER_LIST = 'number_list';
	const _OBJECT      = 'object';
	
    private $name  = "";
    private $type  = "";
	private $label = "";
	private $value = "";
	
	private $autocomplete      = FALSE;
	private $autofocus         = FALSE;
	private $db_filter         = FALSE;
	private $default_value     = NULL;
	private $display_as        = FALSE;
	private $display_link      = FALSE;
	private $display_on_forms  = TRUE;
	private $display_size      = array( 70, 0 );
	private $helper_text       = FALSE;
	private $is_nullable       = FALSE;
	private $multifield_filter = FALSE;
	private $options_lookup    = NULL;
	private $options           = NULL;
	private $primary_key       = FALSE;
	private $readonly          = FALSE;
	private $required          = FALSE;
	private $sort_using        = FALSE;
	private $validation_rules  = FALSE;
    
	private $sort_order_descending = FALSE;
	
	private static $adapter = NULL;
	
	
	public function __construct( $name="", $type="", $label="", $is_nullable = FALSE, $default_value = NULL )
	{
		$this->define( $name, $type, $label, $is_nullable, $default_value );
	}
    
	
    public function define( $name, $type, $label = "", $is_nullable = FALSE, $default_value = NULL )
    {
    	if (empty( $name ))
    		return false;
    		
    	if (empty( $label ))
    		$label = self::name_2_label( $name );
    		
    	$this->set_name( $name );
    	$this->set_type( $type );
    	$this->set_label( $label );
    	$this->set_is_nullable( $is_nullable );
    	$this->default_value( $default_value );
    	
    	$this->sort_using( "COLLECTION.$name" );
    	
    	return $name;
    }
    
    
	public function primary_key( $new_primary_key = TRUE )
	{
		$this->primary_key = !empty( $new_primary_key );
	    
		return $this;
	}
        

	public function is_primary_key()
	{
		return $this->primary_key;
	}
        

	public function autofocus( $new_autofocus = FALSE )
	{
		$this->autofocus = !empty( $new_autofocus );
	    
		return $this;
	}
        

	public function has_autofocus()
	{
		return $this->autofocus;
	}
        

	public function readonly( $new_readonly = FALSE )
	{
		$this->readonly = !empty( $new_readonly );
	    
		return $this;
	}
        

	public function is_readonly()
	{
		return $this->readonly;
	}
        

	public function required( $new_required = FALSE )
	{
		$this->required = !empty( $new_required );
	    
		return $this;
	}
        

	public function is_required()
	{
		return $this->required;
	}
        

	public function validation_rules( $new_validation_rules = FALSE )
	{
		$this->validation_rules = $new_validation_rules;
	    
		return $this;
	}
        

	public function get_validation_rules()
	{
		return $this->validation_rules;
	}
        

	public function set_sort_order_descending()
	{
		$this->sort_order_descending = TRUE;
		
		return $this;
	}
        

	public function is_sort_order_descending()
	{
		return $this->sort_order_descending;
	}
        

	public function default_value( $new_default )
	{
		$this->default_value = $new_default;
	    	
		return $this;
	}
        

	public function get_default_value( $namespace = "" )
	{
		if ($this->default_value == self::LAST_INSERTED_VALUE)
			return $this->get_saved_last_inserted_value( $namespace );
		else
			return $this->get_default_value_from_model();
	}
	
	
    public function get_default_value_from_model()
	{
		return $this->default_value;
	}
	
	
    public function save_last_inserted_value( $value = NULL, $namespace = "" )
	{
		if (!isset( $value ))
			$value = $this->get_value();
		
		return setcookie( $this->last_inserted_value_cookie_name( $namespace ),
		                  $value,
		                  strtotime('+10 years'),
		                  '/',
		                  $_SERVER['HTTP_HOST'] );
	}
	
	
    public function get_saved_last_inserted_value( $namespace = "")
	{
		return extract_value( $_COOKIE, $this->last_inserted_value_cookie_name( $namespace ));
	}
	
	
    private function last_inserted_value_cookie_name( $namespace = "" )
	{
		if (empty( $namespace ))
			$namespace = get_class( $this );
		
		return $namespace
		       . "_" . $this->get_name()
		       . "_" . self::LAST_INSERTED_VALUE;
	}
	
	
    public function set_options_from_options_lookup()
    {
		$options_lookup = $this->get_options_lookup();
		
		if (empty( $options_lookup ))
			return;
		
		$options = ($this->get_type() === self::TEXT )
		               ? array( "" => ((empty( $this->db_filter )) ? '-- none selected --' : '-- all --') )
		               : array();
		
		if ($options_lookup[0])
		{
			log_message_info( __METHOD__, "Loading " . $options_lookup[0] . "..." );
			
			$lookup_object = $options_lookup[0];
				
			if (is_string( $lookup_object ) && class_exists( $lookup_object, TRUE ))
			{
				$lookup_object = new $lookup_object( FALSE );
			}
			
			if (empty( $lookup_object ))
			{
				log_message_error( __METHOD__, "Couldn't load class for model " . $options_lookup[0] . ", aborting..." );
				return;
			}
			
			if (method_exists( $lookup_object, 'has_errors') && !$lookup_object->has_errors())
			{
				$value_column = $options_lookup[1];
				$show_column  = $options_lookup[2];
				$conditions   = $options_lookup[3];
				$sortBy       = $options_lookup[4];
				
				/**
				 * Lookup the options
				 */
		
				$params = array( 'where'   => $conditions,
				                 'columns' => "$value_column, $show_column",
				                 'orderby' => $sortBy );
				
				$list = $lookup_object->find( $params );
				
				if ($list && count( $list ) > 0)
				{
					foreach( $list as $item )
						$options[ $item[ $value_column ]] = $item[ $show_column ];
				
					$this->options( $options );
				}
			}
			
			unset( $lookup_object );
		}
		elseif ($options_lookup[1])
		{
			$values  = $options_lookup[1];
			$display = $options_lookup[2];

			foreach( $values as $key => $value )
			{
				$options[ $value ] =  isset( $display[$key] )
										? $display[$key]
										: $value;
			}
			
			$this->options( $options );
		}
    }
    
    
    /**
     * Used during initial field setup
     *
     * @param Mixed $lookup_object ModelSchema-derived object, or a string containing the name of a ModelSchema-derived class
     * @param String $value_column
     * @param String $show_column
     * @param Array $lookup_conditions
     * @param String $sortBy
     */
	public function options_lookup( $lookup_object, $value_column, $show_column = "", $lookup_conditions = "", $sortBy = 'show_column' )
	{
		if (empty( $show_column ))
			$show_column = $value_column;
			
		if (empty( $lookup_conditions ))
			$lookup_conditions = "";
			
		if ((empty( $sortBy ) || ($sortBy == 'show_column')) && is_string( $show_column ))
			$sortBy = $show_column;
		    			
    	$this->options_lookup = array( $lookup_object, $value_column, $show_column, $lookup_conditions, $sortBy );
    	
    	return $this;
    }
    
	
	public function get_options_lookup()
	{
		return $this->options_lookup;
	}


	public function options( $options = array() )
	{
    	$this->options = $options;
    	
    	return $this;
    }
    
	
	public function get_options()
	{
		return $this->options;
	}


	public function has_options()
	{
		return !empty( $this->options );
	}


	public static function prepare_list( $list, $type = self::TEXT )
	{
		if (!is_array( $list ))
		{
			$list = str_replace( '\\'.self::LIST_DELIMITER, self::LIST_DELIMITER_PLACEHOLDER, $list );
			
			$list = ($list === "") ? array()
			                       : explode( self::LIST_DELIMITER, $list );
		}
		
		if (!empty( $list ))
		{
			$prep_function = ($type == self::NUMBER) ? 'prepare_number'
			                                         : 'prepare_string';
			                                         
			foreach( $list as $key => $item )
			{
				$list[$key] = self::$prep_function( $item );
			}
		}

		return $list;
	}
	
	
	public function prepare_array( $value )
	{
		return $value;
	}
	
	
	public static function prepare_object( $value )
	{
		return $value;
	}
	
	
	public static function prepare_number( $value )
	{
		$ret = (ctype_digit( $value )) ? intval( $value )
		                               : floatval( $value );
		return $ret;
	}
	
	
	public static function prepare_boolean( $value )
	{
		if (is_string( $value ))
		{
			return (in_array(strtoupper($value), array('TRUE','T','1','YES','Y')));
		}
		else
		{
			return (!empty( $value ));
		}
	}
	
	
	public static function prepare_password( $value )
	{
		return $value;
	}
	
	
	public static function prepare_timestamp( $value )
	{
		if (is_float( $value ))
			$time = $value;				// Still a float from the db read operation
		else
		{
			$factor = self::timestamp_factor();
			 
			if (is_int( $value ))
				$time = $value * $factor;				// Probably a server-side epoch time; adjust to send back to the client
			else
				$time = strtotime( $value ) * $factor;	// Assume must be string at this point
		}
		
		return date( self::DEFAULT_TIMESTAMP_FORMAT, $time );
	}
	
	
	public function prepare_string( $value )
	{
		$value = trim( $value );
		
		if ($this->is_nullable())
		{
			if ($value === "")
			{
				$value = NULL;
			}
		}

		return $value;
	}
	
	
	public function display_form_html( $input_name, $value )
	{
		$extra = 'id="' . $this->get_input_id() . '"';
		
		if ($this->is_required())
			$extra .= " required";
			
		$options = $this->get_options();
		
		list( $width,
		      $height ) = $this->get_display_size();
		
		if ($this->type === self::PASSWORD)
		{
			$ret = $this->display_form_html_password( $input_name, $value );
		}
		elseif ($this->display_as === self::CHECKBOX)
		{
			$ret = $this->display_form_html_checkbox( $input_name, $value );
		}
		elseif ($this->is_readonly())
		{
			$ret = form_hidden( $input_name, $value )
			     . div( $value, array( 'class' => 'readonly_display' ) );
		}
		elseif (!empty( $options ))
		{
			$ret = form_dropdown( $input_name, $options, $value, $extra );
		}
		elseif (!empty( $height ))
		{
			$attributes = array( 'name' => $input_name,
			                     'rows' => $height );

			if (!empty( $width ))
				$attributes['cols'] = $width;
			
			$ret = form_textarea( $attributes, $value, $extra );
		}
		else
		{
			if (!empty( $width ))
				$extra = " size=\"$width\"";
			
			$ret = form_input( $input_name, $value, $extra );
		}
		      		
		return $ret;
	}
	
	
	private function display_form_html_checkbox( $input_name, $value )
	{
		$options = $this->get_options();
		
		list( $width,
		      $height ) = $this->get_display_size();

		if ($this->type === self::BOOLEAN)
		{
			$checked = ($value === 'true');
			$value   = 'true';
		}
		else
		{
			/**
			 * WARNING: This case might not actually work. Do test it!
			 */
			$checked = !empty( $value );
		}
		
		$extra = ($this->is_readonly())
		             ? "disabled"
		             : ('id="' . $this->get_input_id() . '"');
		
		if ($this->is_required())
			$extra .= " required";
			
		$ret = form_checkbox( $input_name, $value, $checked, $extra );
		
		return $ret;
	}
	
	
	private function display_form_html_password( $input_name, $value )
	{
		$options = $this->get_options();
		list( $width,
		      $height ) = $this->get_display_size();
		
		if ($this->is_readonly())
		{
			$ret = form_hidden( $input_name, $value )
			     . div( $this->password_string( $value ),
			            array( 'class' => 'readonly_display' ) );
		}
		else
		{
			$extra = 'id="' . $this->get_input_id() . '"';
			
			if (!empty( $width ))
				$extra .= " size=\"$width\"";
			
			if ($this->is_required())
				$extra .= " required";
			
			$ret = form_password( $input_name, $value, $extra );
		}
		
		return $ret;
	}
	
	
	public function display_string( $value )
	{
		switch( $this->get_type() )
		{
			case self::BOOLEAN:
				$value = self::boolean_string( $value );
				break;
				
			case self::PASSWORD:
				break;
				
			case self::TIMESTAMP:
				$value = self::timestamp_string( $value, $this->get_display_as() );
				break;
				
			default:
				if ($value === FALSE)
					$value = $this->get_default_value();
				break;
		}
		
		return $value;
	}
	
	
	public static function boolean_string( $value )
	{
		return (empty( $value )) ? "false" : "true";
	}
	

	public static function password_string( $value )
	{
		return (empty( $value ))
		          ? '<i>(empty)</i>'
		          : '&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;';
	}
	

	public static function timestamp_string( $value, $format = "" )
	{
		if (!is_numeric( $value ))
			return $value;

		if (empty( $format ))
			$format = self::DEFAULT_TIMESTAMP_FORMAT;
		
		return date( $format, intval( $value / self::timestamp_factor() ));
	}
	
	
	public static function extract_list_string( $hash, $value, $default = "" )
	{
		$list = get_posted_value( $hash, $value );
		
		if (empty( $list ))
			$list = $default;

		if (is_scalar( $list ))
			return "$list";
			
		if (is_array( $list ))
			return self::list_string_from_array( $list );
		
		return "<pre>$list</pre>\n";
	}
	
	
    public static function list_string_from_array( $list )
    {
		if (empty( $list ) || !is_array( $list ))
		{
			return "";
		}
		else
		{
			return implode( self::LIST_DELIMITER." ", $list);
		}
    }
	
	
    public function get_class_name()
    {
    	return ucfirst( $this->name );
    }
    
    
    public function get_name()
    {
    	return $this->name;
    }
    
    
    public function set_name( $name )
    {
    	$this->name = $name;
    }
    
    
    public function get_type()
    {
    	return $this->type;
    }

    
    public function set_type( $type )
    {
    	$this->type = $type;
    }
    
    
    public function is_nullable()
    {
    	return $this->is_nullable;
    }

    
    public function set_is_nullable( $is_nullable )
    {
    	$this->is_nullable = $is_nullable;
    	
    	return $this;
    }
    
    
    public function get_label( $in_edit_or_create_mode = FALSE )
    {
    	if ($this->required && $in_edit_or_create_mode)
	    	return $this->label . " *";
    	else
    		return $this->label;
    }

    
    public function get_label_raw()
	{
		return $this->label;
	}

    
	public function set_label( $label )
    {
    	$this->label = $label;
    	
    	return $this;
    }
    
    
    public function get_value()
    {
    	return $this->value;
    }

    
    public function set_value( $value )
    {
    	$this->value = $value;
    }
    

	public function autocomplete()
	{
		$this->autocomplete = func_get_args();
		
		return $this;
	}
    
    
	public function get_autocomplete()
	{
		return $this->autocomplete;
	}
    
    
    public function has_autocomplete()
	{
		return !empty( $this->autocomplete );
	}
	
	
	public function has_autocomplete_validation()
	{
		return $this->has_autocomplete()
		       && (extract_value( $this->autocomplete, 5) !== self::NO_VALIDATION);
	}
	
	
	private function lookup_autocomplete_value( $string )
	{
		log_message_info( __METHOD__, 'BEGIN' );
		
		$ret        = NULL;
		$DESC_FIELD = 'DESCRIPTOR';
		
		list( $model,
		      $column_name,
		      $descriptor) = $this->get_autocomplete();
		
		if (!empty( $model ))
		{
			$accessor = new $model( FALSE );
		
			if (!empty( $accessor ))
			{
				if (empty( $column_name ))
					$column_name = $this->get_name();
				
				$string = ModelSchema::safe_string( $string );
				$params = array( 'columns' => "$descriptor AS $DESC_FIELD, $column_name",
				                 'having'  => "$DESC_FIELD = '$string'" );
				
				$values = $accessor->find( $params );
				
				log_message_info( __METHOD__, "Values ="  );
				log_message_info( __METHOD__, $values  );
				
				if ($values
				    && (count( $values ) > 0)
				    && ($first_row = reset( $values ))
				    && isset( $first_row[ $column_name ] ))
				{
					$ret = $first_row[ $column_name ];
				}
				
				unset( $accessor );
			}
		}
		
		log_message_info( __METHOD__, 'END' );
		
		return $ret;
	}
	
	
	public function lookup_autocomplete_string( $value )
	{
		log_message_info( __METHOD__, 'BEGIN' );
		
		$ret        = NULL;
		$DESC_FIELD = 'DESCRIPTOR';
		
		list( $model,
		      $column_name,
		      $descriptor) = $this->get_autocomplete();
		
		if (empty( $column_name ))
			$column_name = $this->get_name();
		
		$accessor = new $model( FALSE );
		$value    = $accessor->safe_string( $value );
		 
		$params = array( 'columns' => "$column_name, $descriptor as $DESC_FIELD",
		                 'where'   => "$column_name = '$value'" );
		
		$values = $accessor->find( $params );
		
		if ($values
		    && (count( $values ) > 0)
		    && ($first_row = reset( $values ))
		    && isset( $first_row[ $DESC_FIELD ] ))
		{
			$ret = $first_row[ $DESC_FIELD ];
		}
		
		log_message_info( __METHOD__, 'END' );
		unset( $accessor );
		
		return $ret;
	}
	
	
	/**
	 * @param Integer $width_aka_columns Number of columns, for use when rendered
	 * @param Integer $height_aka_rows Number of rows, for use when rendered
	 */
	public function set_display_size( $width_aka_columns, $height_aka_rows = 0 )
	{
		$this->display_size = array( $width_aka_columns, $height_aka_rows );
		
		return $this;
	}
    
    
	public function get_display_size()
	{
		return $this->display_size;
	}
	
	
	public function display_as( $display_type = FALSE )
	{
		$this->display_as = $display_type;
		
		return $this;
	}
    
    
	public function get_display_as()
	{
		return $this->display_as;
	}
    
    
	public function display_link( $display_link = FALSE )
	{
		$this->display_link = $display_link;
		
		return $this;
	}
    
    
	public function get_display_link()
	{
		return $this->display_link;
	}
    
    
	public function display_on_forms( $new_display_on_forms = TRUE )
	{
		$this->display_on_forms = $new_display_on_forms;
		
		return $this;
	}
    
    
	public function is_displaying_on_forms()
	{
		return $this->display_on_forms;
	}
    
    
	public function sort_using( $sort_using = FALSE )
	{
		$this->sort_using = $sort_using;
		
		return $this;
	}
    
    
	public function get_sort_using()
	{
		return $this->sort_using;
	}
    
    
	public function helper_text( $new_helper_text )
	{
		$this->helper_text = $new_helper_text;
		
		return $this;
	}
    
    
	public function get_helper_text()
	{
		return $this->helper_text;
	}
	
	
	public function get_input_id()
	{
		$input_id = $this->get_name();
		
		if ($this->has_autocomplete())
			$input_id = "autocomplete_".$input_id;
		
		return $input_id;
	}
	
	
	public function set_db_filter( $new_db_filter )
	{
		$this->db_filter = $new_db_filter;
		
		return $this;
	}
    
    
	public function db_filter( $value = "" )
	{
		log_message_info( __METHOD__, $this->db_filter." => ".$value );
		
		if ($this->has_autocomplete())
		{
			$lookup_value = $this->lookup_autocomplete_value( $value );
			
			if ($lookup_value !== NULL)
			{
				$value = $lookup_value;
			}
			elseif ($this->get_type() == ModelSchemaField::NUMBER)
			{
				$value = -1;
			}
		}
		
		$value = ModelSchema::safe_string( $value );
		
		/**
		 * New "multifield" functionality
		 */
		if ($this->is_multifield_filter())
		{
			$value_pieces = explode( ',', $value );
			
			foreach( $value_pieces as $index => $piece )
			{
				if (strpos( $piece, '%' ) === FALSE)
					$value_pieces[ $index ] = '%'.$piece.'%';
			}
			
			$value = implode( ',', $value_pieces );
			
			log_message_info( __METHOD__, $this->db_filter." >>> being applied to >>> $value" );
		}
		
		return str_replace( "%s", "$value", $this->db_filter );
	}
	    
    
    public function has_db_filter()
	{
		return !empty( $this->db_filter );
	}
	
	
	public function multifield_filter( $new_multifield_filter = TRUE )
	{
		$this->multifield_filter = $new_multifield_filter;
		
		return $this;
	}
    
    
	public function is_multifield_filter()
	{
		return !empty( $this->multifield_filter );
	}
	
	
	public static function name_2_label( $name )
	{
		if (!is_string( $name ))
			return $name;
		
		$name  = end( explode( ".", $name ));
		$label = "";
		
		for( $i=0; $i < strlen($name); $i++ )
		{
			$char = $name[$i];
			
			if ($char == 'P' && $i > 0 && $name[$i-1] == 'X')	// special case: XP => xp
				$label .= strtolower($char);
			elseif (ctype_upper( $char ))
				$label .= " " . strtolower($char);
			elseif ($char == "_")
				$label .= " ";
			else
				$label .= $char;
		}
		
		return ucfirst( $label );
	}
    
	
	public static function to_timestamp( $time_string = "" )
	{
		$time = ($time_string === "") ? time()
		                              : strtotime( $time_string );
		
		return $time * self::timestamp_factor();
	}
	
	
	private static function timestamp_factor()
	{
		return (self::TIMESTAMP_IN_MILLISECONDS) ? 1000.00 : 1.00;
	}
	
}

/* End of file modelschemafield.php */
/* Location ./application/models/modelschemafield.php */
