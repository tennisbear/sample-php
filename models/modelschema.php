<?php if (!defined('BASEPATH')) exit("No direct access allowed.");
/**
 * "Model with schema" class, based on ModelBaseMysql
 *
 * @author Dennis Slade
 * @since  2011-12-05
 */


class ModelSchema extends ModelBaseMysql
{
	const DEFAULT_TEXT_FIELD_WIDTH  = 60;
	const DEFAULT_TEXT_FIELD_HEIGHT = 10;
	
	protected $collection_name      = "";
    protected $default_sort_field   = NULL;
	protected $fields               = array();
	protected $one_to_manys         = array();
	protected $one_to_ones          = array();
	protected $schema               = array();
    
    protected static $adapters   = array();
    
    
	public function __construct( $load_full_schema = TRUE )
	{
		$log_prefix = "[".__METHOD__."]";
		
		log_message( 'info', "$log_prefix BEGIN " . strtoupper( get_class( $this )));
		
		parent::__construct();
	
		log_message( 'info', "$log_prefix Loading schema..." );
		
		$this->load_schema( $load_full_schema );
		
		log_message( 'info', "$log_prefix END " . strtoupper( get_class( $this )));
	}
    
    
	public static function extract_field_details_by_model( $schema, $model_name = "" )
	{
		if (empty( $model_name ))
		{
			$fields       = reset( $schema );
			$field_names  = next( $schema );
			$field_labels = next( $schema );
		}
		else
		{
			$fields       = $schema[ self::model_2_index_fields( $model_name ) ];
			$field_names  = $schema[ self::model_2_index_field_names( $model_name ) ];
			$field_labels = $schema[ self::model_2_index_field_labels( $model_name ) ];
		}
		
		return array( $fields, $field_names, $field_labels );
	}
	
	
	public function extract_data_for_insert( $input )
	{
		$input = $this->force_into_array( $input );
		$primary_key = $this->get_primary_key();
		
		foreach( $this->fields as $name => $one_field )
		{
			if ($name == $primary_key)
				continue;
				
			if (!array_key_exists( $name, $input ))
			{
				$input[$name] = $one_field->get_default_value();
			}
			
			switch( $one_field->get_type() )
			{
				case ModelSchemaField::_ARRAY:
					$class_name = ucfirst( $name );
					$new_array  = array();
					
					if (class_exists( $class_name, TRUE ))
					{
						/**
						 * @todo: encapsulate in a private function
						 */
						if ( $input[$name] === NULL )
						{
							echo "<p>Array parse warning. Instance of <span class=\"error\">\"$name\": null</span> should be <span class=\"notice\">\"$name\": []</span></p>";
						}
						elseif ( is_object( $input[$name] ))
						{
							echo "<p>Array parse warning. Instance of <span class=\"error\">\"$name\": { ... }</span> as object, should be array like <span class=\"notice\">\"$name\": [ { ... } ]</span>. "
							     . "<br/> => " . print_r( $input[$name], TRUE ) . "</p>";
						}
						elseif ( is_string( $input[$name] ))
						{
							echo "<p>Array parse warning. Instance of <span class=\"error\">\"$name\": \"{$input[$name]}\"</span> should be <span class=\"error\">\"$name\": [\"{$input[$name]}\"]</span></p>";
						}
						elseif ( is_bool( $input[$name] ))
						{
							$value_string = $one_field->boolean_string( $input[$name] );
							
							echo "<p>Array parse warning. Instance of <span class=\"error\">\"$name\": $value_string</span> is incorrect, perhaps you meant <span class=\"error\">\"$name\": []</span></p>";
						}
						elseif ( !is_array( $input[$name] ))
						{
							$value_string = $input[$name];
							
							echo "<p>Array parse warning. Instance of <span class=\"error\">\"$name\": $value_string</span>, should be <span class=\"error\">\"$name\": [$value_string]</span></p>";
						}
						else
						{
							foreach( $input[$name] as $key => $row )
							{
								$sub_object = new $class_name();
								$sub_object->extract_data_for_insert( $row );
								$data = $sub_object->get_data();
								unset( $sub_object );
							
								if (isset( $data[ ModelSchemaField::GUID ] ))
									unset( $data[ ModelSchemaField::GUID ] );
							
								$new_array[$key] = $data;
							}
						}
					}
					
					$this->$name = ModelSchemaField::prepare_array( $new_array );
					
					break;
					
				case ModelSchemaField::_OBJECT:
					$class_name = ucfirst( $name );
					$data       = array();

					if (class_exists( $class_name, TRUE ))
					{
						/**
						 * @todo: encapsulate in a private function
						 */
						if ($input[$name] === NULL)
						{
							echo "<p>Object parse warning. Instance of <span class=\"error\">\"$name\": null</span> should be <span class=\"notice\">\"$name\": { ... }</span></p>";
						}
						elseif (is_string( $input[$name] ))
						{
							echo "<p>Object parse warning. Instance of <span class=\"error\">\"$name\": \"{$input[$name]}\"</span> should be more like <span class=\"error\">\"$name\": { \"fieldName\": \"{$input[$name]}\" }</span></p>";
						}
						elseif (is_bool( $input[$name] ))
						{
							$value_string = $one_field->boolean_string( $input[$name] );
							
							echo "<p>Object parse warning. Instance of <span class=\"error\">\"$name\": $value_string</span> is incorrect, should be <span class=\"notice\">\"$name\": { ... }</span></p>";
						}
						elseif (is_array( $input[$name] ) && dk_html::in_edit_mode())
						{
							echo "<p>Object parse warning. Instance of <span class=\"error\">\"$name\": [ ... ]</span> as array, should be single object like <span class=\"notice\">\"$name\": { ... }</span>. "
							     . "<br/> => " . print_r( $input[$name], TRUE ) . "</p>";
						}
						elseif (!is_object( $input[$name] ) && dk_html::in_edit_mode())
						{
							$value_string = $input[$name];
							
							echo "<p>Object parse warning. Instance of <span class=\"error\">\"$name\": $value_string</span>, should be more like <span class=\"error\">\"$name\": { \"fieldName\": $value_string }</span></p>";
						}
						else
						{
							$sub_object = new $class_name();
							$sub_object->extract_data_for_insert( $input[$name] );
							$data = $sub_object->get_data();
							unset( $sub_object );
							
							if (isset( $data[ ModelSchemaField::GUID ] ))
								unset( $data[ ModelSchemaField::GUID ] );
						}
						
						$this->$name = $data;
					}
					
					break;
					
				case ModelSchemaField::_LIST:
					$this->$name = $one_field->prepare_list( $input[$name], ModelSchemaField::TEXT );
					break;
				
				case ModelSchemaField::_NUMBER_LIST:
					$this->$name = $one_field->prepare_list( $input[$name], ModelSchemaField::NUMBER );
					break;
					
					
				case ModelSchemaField::NUMBER:
					$this->$name = $one_field->prepare_number( $input[$name] );
					break;
					
				case ModelSchemaField::BOOLEAN:
					$this->$name = $one_field->prepare_boolean( $input[$name] );
					break;
					
				case ModelSchemaField::TIMESTAMP:
					$this->$name = $one_field->prepare_timestamp( $input[$name] );
					break;
					
				case ModelSchemaField::PASSWORD:
					$this->$name = $one_field->prepare_password( $input[$name] );
					break;
					
				default:
					$this->$name = $one_field->prepare_string( $input[$name] );
					break;
			}
		}
	}
	
	
	/**
	 * WARNING: Recursive. Don't mess with the recursion unless you know exactly what you're doing. :)
	 *
	 * @param  Array|String $input
	 * @param  String $model_name
	 * @author Dennis Slade
	 */
    public function extract_data_for_update( $input )
	{
		$input       = $this->force_into_array( $input );
		$primary_key = $this->get_primary_key();
		
		if (is_array( $input ))
		{
			foreach( $this->fields as $name => $one_field )
			{
				if (($name == $primary_key) && isset( $input[$name] ))
				{
					$this->primary_key_clause = "$name = '${input[$name]}'";
					continue;
				}
				
				if (!array_key_exists( $name, $input ))
				{
					$input[$name] = $one_field->get_default_value();
				}
				
				switch( $one_field->get_type() )
				{
					case ModelSchemaField::_ARRAY:
						$class_name = ucfirst( $name );
						$new_array  = array();
						
						if (class_exists( $class_name, TRUE ))
						{
							foreach( $input[$name] as $key => $row )
							{
								$sub_object = new $class_name();
								$sub_object->extract_data_for_update( $row );
								$new_array[$key] = $sub_object->get_data();

								unset( $sub_object );
							}
						}
						
						$this->$name = ModelSchemaField::prepare_array( $new_array );
						
						break;
						
					case ModelSchemaField::_OBJECT:
						$class_name = ucfirst( $name );

						if (class_exists( $class_name, TRUE ))
						{
							$sub_object = new $class_name();
							$sub_object->extract_data_for_update( $input[$name] );
							$this->$name = $sub_object->get_data();
						
							unset( $sub_object );
						}
						
						break;
						
					case ModelSchemaField::_LIST:
						$this->$name = $one_field->prepare_list( $input[$name], ModelSchemaField::TEXT );
						break;
						
					case ModelSchemaField::_NUMBER_LIST:
						$this->$name = $one_field->prepare_list( $input[$name], ModelSchemaField::NUMBER );
						break;
						
					case ModelSchemaField::NUMBER:
						$this->$name = $one_field->prepare_number( $input[$name] );
						break;
						
					case ModelSchemaField::BOOLEAN:
						$this->$name = $one_field->prepare_boolean( $input[$name] );
						break;
						
					case ModelSchemaField::PASSWORD:
						$this->$name = $one_field->prepare_password( $input[$name] );
						break;
						
					case ModelSchemaField::TIMESTAMP:
						$this->$name = $one_field->prepare_timestamp( $input[$name] );
						break;
						
					default:
						$this->$name = $one_field->prepare_string( $input[$name] );
						break;
				}
			}
		}
	}
	
	
	public function add_missing_guids()
	{
		$records = iterator_to_array( $this->find( array() ));
		
		foreach( $records as $key => $one_record )
		{
			if (!isset( $one_record[ ModelSchemaField::GUID ] ))
			{
				$one_record[ ModelSchemaField::GUID ] = $this->get_new_guid();
				$this->data = $one_record;
				$this->save();
			}
		}
	}
	
	
    public function get_new_guid()
    {
    	$prefix = strtolower(get_class( $this ));
    		
    	return $prefix
    	       . "." . date("YmdHis")
    	       . "." . random_string('numeric',8);
    }
    
    
	public function get_field( $field_name )
    {
    	foreach( $this->fields as $name => $one_field )
		{
			if ($name == $field_name)
				return $one_field;
		}

    	return NULL;
    }
    
    
	public function get_field_labels()
    {
    	$labels = array();
		foreach( $this->fields as $name => $one_field )
		{
			$labels[$name] = $one_field->get_label();
		}

		return $labels;
    }

    
    public function get_field_names()
    {
    	$names = array();
    	foreach( $this->fields as $name => $one_field )
		{
			$names[$name] = $one_field->get_type();
		}

    	return $names;
    }
    
    
	public function get_fields()
	{
		return $this->fields;
	}
    
    
    public static function get_model_instance( $model_name )
    {
    	$model_name = ucfirst( $model_name );
    	
		return (class_exists( $model_name, TRUE ))
		            ? new $model_name()
		            : NULL;
    }
    
    
    public static function get_class_map()
    {
    	return self::$class_map;
    }
    
    
    public function get_data()
    {
		return $this->data;
    }

    
    public function get_schema()
    {
		return $this->schema;
    }

    
    public function get_default_empty_object()
    {
    	$default_empty_object = array();

		foreach( $this->fields as $name => $one_field )
		{
			switch( $one_field->get_type() )
			{
				case ModelSchemaField::_ARRAY:
					$value = array();
										
					break;
					
				case ModelSchemaField::_OBJECT:
					$class_name = ucfirst( $name );

					if (class_exists( $class_name, TRUE ))
					{
						$sub_object = new $class_name();
						$value = $sub_object->get_default_empty_object();
						unset( $sub_object );
					}
					else
					{
						$value = NULL;
					}
					
					break;
					
				case ModelSchemaField::TEXT:
					$value = $one_field->get_default_value();
					
					if (($value === "") && $one_field->is_nullable())
						$value = NULL;
					break;
					
				case ModelSchemaField::_LIST:
				case ModelSchemaField::_NUMBER_LIST:
				case ModelSchemaField::NUMBER:
				case ModelSchemaField::BOOLEAN:
				case ModelSchemaField::PASSWORD:
				case ModelSchemaField::TIMESTAMP:
					$value = $one_field->get_default_value();
					break;
					
				default:
					$value = NULL;
					break;
			}
			
			$default_empty_object[ $name ] = $value;
		}
		
		return $default_empty_object;
    }
    
    
    protected function get_default_empty_object_for_field( $field_name )
	{
		$ret        = FALSE;
		$class_name = ucfirst( $field_name );
		
		if (class_exists( $class_name, TRUE ))
		{
			$object = new $class_name();
			$ret    = $object->get_default_empty_object();
			unset( $object );
		}
		
		return $ret;
	}


	protected function add_default_value_if_field_missing( &$input, $field_name )
	{
		if (!is_array($input) || isset( $input[ $field_name ] ))
			return;
		
		$field = $this->get_field( $field_name );
		
		if ($field)
		{
			switch ($field->get_type())
			{
				case ModelSchemaField::_OBJECT:
					$input[ $field_name ] = $this->get_default_empty_object_for_field( $field_name );
					break;
					
				case ModelSchemaField::_ARRAY:
					$input[ $field_name ][0] = $this->get_default_empty_object_for_field( $field_name );
					break;
					
				default:
					$input[ $field_name ] = $field->get_default_value();
					break;
			}
		}
	}


	protected function load_schema( $load_schema = TRUE )
	{
		$log_prefix = "[".__METHOD__."]";
		
		if (!$load_schema)
		{
			return $this->schema;
		}
		
		log_message( 'debug', "$log_prefix Adding names and labels..." );
		
		// First for the main model itself...
		$schema = $this->add_field_names_and_labels( array() );

		log_message( 'debug', "$log_prefix Traversing through the fields..." );
		
		// And now all the rest...
		
		foreach( $this->fields as $schema_field )
		{
			if (!in_array( $schema_field->get_type(), array( ModelSchemaField::_ARRAY,
			                                                 ModelSchemaField::_OBJECT )) )
			{
				continue;
			}
			
			$class_name = $schema_field->get_class_name();
			
			log_message( 'debug', "$log_prefix >>> $class_name" );
			
			if (class_exists( $class_name, TRUE ))
			{
				$next_model = new $class_name();
				$schema = $next_model->add_field_names_and_labels( $schema );
				
				log_message( 'debug', "$log_prefix >>> $class_name model loaded" );
				
				foreach( $next_model->get_field_names() as $field_name => $field_type )
				{
					if (in_array( $field_type, array( ModelSchemaField::_ARRAY,
					                                  ModelSchemaField::_OBJECT ))
					    && class_exists( $field_name, TRUE ))
					{
						$next_model = new $field_name();
						$schema = $next_model->add_field_names_and_labels( $schema );
					}
				}
			}
		}
		
		$this->schema = $schema;
		
		$this->schema['model_name'] = get_class( $this );
		
		return $this->schema;
	}
    
	
	public function add_field_names_and_labels( $previous_schemas )
    {
    	$this->lookup_field_options();
    	
		$previous_schemas[ self::model_2_index_fields( get_class($this) )       ] = $this->fields;
		$previous_schemas[ self::model_2_index_field_names( get_class($this) )  ] = $this->get_field_names();
		$previous_schemas[ self::model_2_index_field_labels( get_class($this) ) ] = $this->get_field_labels();
		
		return $previous_schemas;
    }
    
    
    protected function lookup_field_options()
    {
    	foreach( $this->fields as $field )
    	{
    		if (!$field->has_options())
    			$field->set_options_from_options_lookup();
    	}
    }
    
    
    public function save_sticky_insert_form_values( $inserted_data )
	{
		log_message_info( __METHOD__, 'BEGIN' );
		
		if (!empty( $this->fields ) && is_array( $this->fields ))
		{
			foreach( $this->fields as $field )
			{
	    		if ($field->get_default_value_from_model() === ModelSchemaField::LAST_INSERTED_VALUE)
	    		{
	    			$value = extract_value( $inserted_data, $field->get_name() );
	    			log_message_info( __METHOD__, $field->get_name() . ' = ' . $field->get_default_value_from_model() . ', value = ' . $value  );
	    			
	    			$field->save_last_inserted_value( $value, get_class( $this ));
	    		}
			}
		}
		
		log_message_info( __METHOD__, 'END' );
	}
	
	
    public function has_validation_rules()
    {
    	if (!empty( $this->fields ) && is_array( $this->fields ))
		{
			foreach( $this->fields as $field )
			{
				if ($field->get_validation_rules())
					return TRUE;
			}
		}
    	
		return FALSE;
    }

    
	/**
	 * JSON EXPORT METHODS
	 */
    
	public function get_all_for_json_export( $include_guids = FALSE )
	{
		$all_records = array_values( iterator_to_array( $this->find()->sort( array('identifier' => 1) )));
		
		foreach( $all_records as $key => $record )
		{
			unset( $all_records[ $key ][ ModelSchemaField::DB_ID ] );	// remove the mongo internal id's
			
			if (!$include_guids)
				unset( $all_records[ $key ][ ModelSchemaField::GUID ] );	// remove the cms-generated guid's
		}
				
		return array( $this->collection_name => Json::sort_for_export($all_records) );
	}
	
    
	/**
	 * ADD FIELD METHODS
	 */
	
    protected function add_field( $field_type, $field_name, $field_label = "", $is_nullable = FALSE, $default_value = NULL )
    {
    	if (!empty( $field_name ))
    	{
	    	$this->fields[$field_name] = new ModelSchemaField( $field_name, $field_type, $field_label, $is_nullable, $default_value );

	    	return $this->fields[$field_name];
    	}
    	else
    	{
    		return new ModelSchemaField('dev_null');
    	}
    }
    
    
	protected function add_array( $field_name, $field_label = "", $default_value = array() )
	{
		return $this->add_field( ModelSchemaField::_ARRAY, $field_name, $field_label, FALSE, $default_value );
	}
    
    
	protected function add_object( $field_name, $field_label = "" )
	{
		return $this->add_field( ModelSchemaField::_OBJECT, $field_name, $field_label );
	}
    
    
    protected function add_internal_field( $field_name, $field_label = "" )
    {
    	return $this->add_field( ModelSchemaField::INTERNAL, $field_name, $field_label, FALSE, "" );
    }
    
    
    protected function add_string_field( $field_name, $field_label = "", $is_nullable = TRUE )
    {
    	return $this->add_field( ModelSchemaField::TEXT, $field_name, $field_label, $is_nullable, "" );
    }
    
    
	protected function add_primary_key( $field_name, $field_label = "" )
	{
		$this->set_primary_key( $field_name );

		return $this->add_number_field( $field_name, $field_label )
		            ->primary_key()
		            ->readonly( TRUE )
		            ->display_on_forms( FALSE );
	}

	
	protected function add_number_field( $field_name, $field_label = "" )
    {
    	return $this->add_field( ModelSchemaField::NUMBER, $field_name, $field_label, FALSE, 0 );
    }
    
    
	protected function add_boolean_field( $field_name, $field_label = "" )
    {
    	return $this->add_field( ModelSchemaField::BOOLEAN, $field_name, $field_label, FALSE, FALSE );
    }
    
    
    protected function add_password_field( $field_name, $field_label = "", $is_nullable = TRUE )
	{
		return $this->add_field( ModelSchemaField::PASSWORD, $field_name, $field_label, $is_nullable, "" );
	}
    
    
    protected function add_timestamp_field( $field_name, $field_label = "" )
    {
    	return $this->add_field( ModelSchemaField::TIMESTAMP, $field_name, $field_label, FALSE, 0 );
    }
    
    
    protected function add_list_field( $field_name, $field_label = "" )
    {
    	return $this->add_field( ModelSchemaField::_LIST, $field_name, $field_label );
    }
        
    
    protected function add_number_list_field( $field_name, $field_label = "" )
    {
    	return $this->add_field( ModelSchemaField::_NUMBER_LIST, $field_name, $field_label );
    }
    
    
	protected function is_one_to_many_with( $foreign_model, $foreign_key, $local_key = "", $order_by = "", $form_name = "" )
	{
		if (empty( $local_key ))
			$local_key = $foreign_key;
		
		if (empty( $order_by ))
			$order_by = $foreign_key;
		
		$this->one_to_manys[] = array( $foreign_model, $foreign_key, $local_key, $order_by, $form_name );
	}
	
    
	public function get_one_to_manys()
	{
		return $this->one_to_manys;
	}
	
    
	protected function is_one_to_one_with( $foreign_model, $foreign_key, $local_key = "" )
	{
		if (empty( $local_key ))
			$local_key = $foreign_key;
		
		$this->one_to_ones[] = array( $foreign_model, $foreign_key, $local_key );
	}
	
    
	public function get_one_to_ones()
	{
		return $this->one_to_ones;
	}
	
    
	public function get_join_info( $foreign_model, $foreign_key, $local_key = "" )
	{
		$columns     = "`COLLECTION`.*";
		$join_table  = "";
		$join_clause = "";
		
		if (!empty( $foreign_model ) && !empty( $foreign_key ))
		{
			$table        = $this->get_table_name();
			$column_names = $this->get_field_names();
			
			$model        = new $foreign_model( FALSE );
			$join_table   = $model->get_table_name();
			$join_columns = $model->get_field_names();
			unset( $model );
			
			if (empty( $local_key ))
				$local_key = $foreign_key;
			
			$join_clause = "`COLLECTION`.$local_key = {$join_table}.$foreign_key";
			
			foreach( $join_columns as $one_join_column => $type )
			{
				if (array_key_exists( $one_join_column, $column_names ))
					$columns .= ", $join_table.$one_join_column AS `{$one_join_column}:{$foreign_model}`";
				else
					$columns .= ", $one_join_column";
			}
		}
		
		return array( $columns, $join_table, $join_clause );
	}
	
	
	/**
	 * @param ModelSchemaField|string $field
	 */
	protected function set_default_sort_field( $field )
	{
		$this->default_sort_field = $field;
	}
	
    
	public function get_default_sort()
	{
		$direction = ModelSchemaField::SORT_ASCENDING;
		
		if (is_a( $this->default_sort_field, 'ModelSchemaField' ))
		{
			$sort_string = $this->default_sort_field->get_name();
			
			if ($this->default_sort_field->is_sort_order_descending())
				$direction = ModelSchemaField::SORT_DESCENDING;
		}
		else
		{
			$sort_string = $this->default_sort_field;
		}
		
		if (empty( $sort_string ))
			$sort_string = "";
		
		if ($direction == ModelSchemaField::SORT_DESCENDING)
			$sort_string .= " DESC";
		
		return $sort_string;
	}
	
	
	/**
	 *  GENERAL UTILS
	 */
	
	/**
	 * @param Mixed $anything Anything at all!
	 * @since 2011-07-07
	 */
	protected function force_into_array( $anything )
	{
		if (empty( $anything ))
		{
			$ret = array();
		}
		elseif (is_scalar( $anything ))
		{
			return array( $anything );
		}
		elseif (is_object( $anything ))
		{
			return get_object_vars( $anything );
		}
		else
		{
			return $anything;
		}
	}
	

	public static function model_2_index_fields( $model_name )
	{
		return strtolower( $model_name ) . "_fields";
	}
	
	
	public static function model_2_index_field_names( $model_name )
	{
		return strtolower( $model_name ) . "_field_names";
	}
	
	
	public static function model_2_index_field_labels( $model_name )
	{
		return strtolower( $model_name ) . "_field_labels";
	}
	
	
}

/* End of file modelschema.php */
/* Location ./application/models/modelschema.php */
