<?php if (!defined('BASEPATH')) exit("No direct access allowed.");

class ModelBaseMysql extends CI_Model
{
	const SETTING_DB_DB   = "dk_db";
	const SETTING_DB_HOST = 'dk_host';
	const SETTING_DB_PASS = 'dk_pass';
	const SETTING_DB_PORT = 'dk_port';
	const SETTING_DB_USER = 'dk_user';
	
	private static $settings = NULL;
	
    protected $affected;
	protected $collection_name;
	protected $database_name;
	protected $data = array();
	protected $db_link;
	protected $error;
	protected $errors;
	protected $fetched;
	protected $last_insert_id = NULL;
	protected $primary_key;
	protected $primary_key_clause;
	protected $query;
	protected $result;
	protected $rows_returned;
	protected $rows_total;
	protected $status;
	
	
	public function __construct( $host = NULL, $user = NULL, $pass = NULL, $db = NULL )
	{
		$log_prefix = "[".__METHOD__."]";
		
		parent::__construct();
		
		$this->_clear_errors();
		
		$settings = $this->load_settings();
		
		if (!isset( $host ))    $host = extract_value( $settings, self::SETTING_DB_HOST );
		if (!isset( $user ))    $user = extract_value( $settings, self::SETTING_DB_USER );
		if (!isset( $pass ))    $pass = extract_value( $settings, self::SETTING_DB_PASS );
		if (!isset( $db ))      $db   = extract_value( $settings, self::SETTING_DB_DB );
		
		if (empty( $user ))
		{
			log_message( 'info',  "$log_prefix No user specified, skipping mysql_connect()..." );
			log_message( 'debug', "$log_prefix   host=$host, user=$user, db=$db" );
			return;
		}
		
		log_message( 'debug', "$log_prefix Connecting to $host::$db, user=$user ..." );
		
		/**
		 * Connect to the database
		 */
		if (!($this->db_link = @mysql_connect( $host, $user, $pass )))
			$this->errors[] = "Database(1) not reachable";

		/**
		 * Select the database, if specified
		 */
		$this->selectDB( $db );
	}
	
	
	public function selectDB( $database_name = "" )
	{
		if (empty( $database_name))
			$database_name = $this->database_name;
			
		if ($database_name && $this->db_link)
		{
			if (!@mysql_select_db( $database_name ))
				$this->errors[] = "Database(2) not reachable";
			else
				$this->database_name = $database_name;
		}
	}
	
	
	public function &__get($k)
	{
		return $this->data[$k];
	}
	
	public function __set($k, $v)
	{
		$this->data[$k] = $v;
	}
	
	public function update( array $update_data = array(),
	                        array $query_params = array() )
	{
		$this->affected = 0;
		$this->error    = "";

		if (empty( $this->primary_key_clause ))
		{
			$this->error = "No primary key specified, aborting...";
			log_message( 'error', $this->error );
			
			return FALSE;
		}
    	    	
    	$params = array
    	(
			'table'       => $this->collection_name,
			'where'       => $this->primary_key_clause,
			'limit'       => 1
		);
		
		foreach( $query_params as $key => $new_param )
		{
			if (isset( $params[$key] ))
				$params[$key] = $new_param;
		}
		
		$table = trim( $params['table'] );
		$where = trim( $params['where'] );
		$limit = intval( $params['limit'] );
		
		/**
		 * Setup the query string
		 */
		
		$data = (empty( $update_data ))
		           ? $this->data
		           : $update_data;
		
		$primary_key_field = $this->get_primary_key();
		
		$set = array();
		
		foreach( $data as $column_name => $value)
		{
			if ($column_name != $primary_key_field)
			{
				/**
				 * Automatically set certain timestamp fields back to now()
				 */
				if (in_array( $column_name, array( 'last_modified_at', 'modified_at' )))
					$value = ModelSchemaField::prepare_timestamp( ModelSchemaField::to_timestamp() );
				
				$set[] = "$column_name = '".self::safe_string($value)."'";
			}
		}
		
		$set = implode( ", ", $set );

		$this->query = "UPDATE $table"
					 . " SET $set"
					 . " WHERE $where"
					 . " LIMIT $limit";
		
		log_message( 'debug', $this->query );
		
		// Do the insert
		$this->selectDB();
		$result = @mysql_query( $this->query );

		if (!$result)
		{
			$this->error = mysql_error();
			return FALSE;
		}

		$this->affected = mysql_affected_rows();

		return $result;
    }
    
    
	public function insert()
	{
		// Setup the query string
		$set = array();
		
		foreach( $this->data as $column_name => $value)
			$set[] = "$column_name = '".self::safe_string($value)."'";
		
		$set = implode( ", ", $set );

		$this->query = "INSERT INTO " . $this->collection_name;

		if (strlen( $set ) > 0)
			$this->query .= " SET " . $set;

		log_message( 'info', $this->query );

		$this->error = "";

		// Do the insert
		$this->selectDB();
		$result = @mysql_query( $this->query );

		if ($result)
		{
			$this->last_insert_id = @mysql_insert_id();
			return $this->last_insert_id;
		}
		else	// the insert query failed...
		{
			$this->last_insert_id = NULL;
			$this->error = @mysql_error();
			return FALSE;
		}
	}


	public function count( $query_params = array() )
	{
		$query_params['columns'] = "count(*) AS count_result";
		
		unset( $query_params['orderby'] );
		unset( $query_params['max_rows'] );
		unset( $query_params['rownum'] );
		
		$this->find( $query_params );
		
		if ($this->has_fetched()
			&& isset( $this->fetched[0], $this->fetched[0]['count_result'] ))
		{
			log_message( 'debug', "Count returns " . $this->fetched[0]['count_result'] );
			return $this->fetched[0]['count_result'];
		}

		log_message( 'debug', "Count query failed" );

		return FALSE;
	}


    public function find( $query_params = array() )
    {
    	$params = array
    	(
			'table'       => $this->collection_name,
			'columns'     => "",
			'where'       => "",
			'having'      => "",
			'orderby'     => "",
			'join_table'  => "",
			'join_clause' => "",
			'max_rows'    => 100,
			'rownum'      => 0
		);
		
		foreach( $query_params as $key => $new_param )
		{
			if (isset( $params[$key] ))
				$params[$key] = $new_param;
		}
		
		$table       = trim( $params['table'] );
		$columns     = trim( $params['columns'] );
		$join_table  = trim( $params['join_table'] );
		$join_clause = trim( $params['join_clause'] );
		$having      = trim( $params['having'] );
		$where       = trim( $params['where'] );
	    $orderby     = trim( $params['orderby'] );
		$rownum      = intval( $params['rownum'] );
		$max_rows    = intval( $params['max_rows'] );

		// Setup the query string

		if (empty( $where ))	$where   = "1";
		if (empty( $columns ))	$columns = "*";

		if (!empty( $join_table ) && !empty( $join_clause ))
		{
			$this->query = "SELECT " . $columns
						 . " FROM $table AS COLLECTION"
						 . " LEFT JOIN " . $join_table
						 . " ON " . $join_clause
						 . " WHERE " . $where;
		}
		else
		{
			$this->query = "SELECT " . $columns
						 . " FROM $table AS COLLECTION"
						 . " WHERE " . $where;
		}

		if (!empty( $having ))
			$this->query .= " HAVING " . $having;

		if (!empty( $orderby ))
			$this->query .= " ORDER BY " . $orderby;

		if ($rownum || $max_rows)
		{
			if (($rownum >= 0) && ($max_rows > 0))
				$this->query .= " LIMIT " . $rownum . ", " . $max_rows;
			elseif ($rownum >= 0)
				$this->query .= " LIMIT " . $rownum . ", 1";
			else
				$this->query .= " LIMIT " . $max_rows;
		}
		
		log_message( 'debug', $this->query );

		$this->clear_fetched();
		$this->error = "";

		$this->selectDB();
	
		/**
		 *  Do the query
		 */
		if (($result = @mysql_query( $this->query )) === FALSE)					// if the query fails...
		{
			$this->error = mysql_error();

			if (!empty( $error_to_print ))			// if we were given an error to print...
				$this->print_error( $error_to_print );

			return FALSE;
		}

		// Fetch all the returned rows, up to max_rows number of rows.

		$i = 0;
		$return_object = array();

		while (($row = mysql_fetch_array($result,MYSQL_ASSOC)) && (!$max_rows || ($i < $max_rows)))
		{
			$return_object[$i++] = $row;
		}

		$this->rows_returned = $i;
		$this->rows_total    = mysql_num_rows($result);
		$this->result        = $result;
		$this->fetched       = $return_object;

		return $return_object;
    }
    
    
    public function remove( $query_params = array() )
    {
    	$params = array
    	(
			'table'       => $this->collection_name,
			'where'       => "",
			'max_rows'    => 100
		);
		
		foreach( $query_params as $key => $new_param )
		{
			if (isset( $params[$key] ))
				$params[$key] = $new_param;
		}
		
		$table    = trim( $params['table'] );
		$where    = trim( $params['where'] );
		$max_rows = intval( $params['max_rows'] );

		// Setup the query string

		if (empty( $where ))	$where   = "1";

		$this->query = "DELETE FROM " . $table
					 . " WHERE " . $where;

		if ($max_rows > 0)
			$this->query .= " LIMIT " . $max_rows;
		else
			$this->query .= " LIMIT 1";

		log_message( 'info', $this->query );

		$this->clear_fetched();
		$this->error = "";

		$this->selectDB();
	
		/**
		 *  Do the query
		 */
		if (($result = @mysql_query( $this->query )) === FALSE)					// if the query fails...
		{
			$this->error = mysql_error();
			log_message( 'error', $this->error );

			if (!empty( $error_to_print ))			// if we were given an error to print...
				$this->print_error( $error_to_print );

			return FALSE;
		}
		
		return TRUE;
    }
    
    
    public function get_collection_name()
	{
		return $this->collection_name;
	}
    
	
	public function get_table_name()
	{
		return $this->collection_name;
	}
	
	
	protected function set_primary_key( $new_primary_key )
	{
		$this->primary_key = $new_primary_key;
	}
    
    
	public function get_primary_key()
	{
		return $this->primary_key;
	}
	
	
	public static function safe_string( $string )
	{
		return mysql_real_escape_string( $string );
	}
	
	
	public function load( $guid, $columns = array() )
    {
        $p = $this->db_link->findOne(array( 'guid' => $guid ), $columns);
        
        if ($p)
        {
            $this->data = $p;
            return $this;
        }
        $this->_add_error('error', 'Database returned no objects matching guid =' . $guid);
        return false;
    }

    
	public function clear_fetched()
	{
		$this->fetched = array();
	}


	public function has_fetched()
	{
		return (isset($this->rows_returned)
				&& ($this->rows_returned > 0));
	}


	public function num_rows_fetched()
	{
		return (isset( $this->rows_returned )) ? $this->rows_returned : 0;
	}


	public function last_insert_id()
	{
		return $this->last_insert_id;
	}


	/**
	 * SETTINGS
	 */
	
	private function load_settings()
	{
		$log_prefix = "[".__METHOD__."]";
		
		if (empty( self::$settings ))
		{
			$new_settings = array();
			
			$CI =& get_instance();
	
			$settings_items = array( self::SETTING_DB_HOST,
			                         self::SETTING_DB_USER,
			                         self::SETTING_DB_PASS,
			                         self::SETTING_DB_DB,
			                         self::SETTING_DB_PORT );
			
			foreach( $settings_items as $item)
			{
				$new_settings[ $item ] = $CI->config->item( $item );
				
				if ($new_settings[ $item ] === "")
					log_message( 'info', "$log_prefix WARNING - \$config['$item'] not defined in the settings file for this environment" );
			}
			
			self::$settings = $new_settings;
		}
		
		return self::$settings;
	}
	
	
    /**
     * ERROR HANDLING
     */
	
    protected function _add_error($type, $message)
    {
        $err = new stdClass();
        $err->type = $type;
        $err->message = $message;
        $this->errors[] = $err;
    }
    
    
    public function has_errors()
    {
        return (!empty($this->errors));
    }
    
    
    /**
     * Pops off an error and returns it.
     */
    public function next_error()
    {
        return array_shift($this->errors);
    }
    
    
    /**
     * Simply returns all errors for processing.
     */
    public function all_errors()
    {
        return $this->errors;
    }
    
    
    public function get_status()
    {
        return $this->status;
    }
    
    
    private function _clear_errors()
    {
        $this->errors = array();
    }
    
}

/* End of file modelbasemysql.php */
/* Location ./application/models/modelbasemysql.php */