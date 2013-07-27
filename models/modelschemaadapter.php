<?php if (!defined('BASEPATH')) exit("No direct access allowed.");
/**
 * Adapter class for model schema objects
 * 
 * @author Dennis Slade
 * @since  2011-12-05
 */


class ModelSchemaAdapter
{
	private $database_name = 'test';
	private $host_name     = 'localhost';
	private $port          = 80;
	private $user_name     = '';
	private $password      = '';
		
	
	public function __construct( $database_name = NULL, $host_name = NULL, $port = NULL, 
	                             $user_name = NULL, $password = NULL )
	{
		$this->set_database_name( $database_name )
		     ->set_host_name( $host_name )
		     ->set_port( $port )
		     ->set_user_name( $user_name )
		     ->set_password( $password );
	}
	
	
	public function set_database_name( $database_name = NULL )
	{
		if (isset( $database_name ) && (strlen( $database_name ) > 0))
			$this->database_name = $database_name;
		
		return $this;
	}
    
	
	public function get_database_name()
	{
		return $this->database_name;
	}
    
	
	public function set_host_name( $host_name = NULL )
	{
		if (isset( $host_name ) && (strlen( $host_name ) > 0))
			$this->host_name = $host_name;
		
		return $this;
	}
    
	
	public function get_host_name()
	{
		return $this->host_name;
	}
    
	
	public function set_port( $port = NULL )
	{
		if (isset( $port ))
			$this->port = $port;
			
		return $this;
	}
    
	
	public function get_port()
	{
		return $this->port;
	}

	
	public function set_user_name( $user_name )
	{
		$this->user_name = $user_name;
			
		return $this;
	}
    
	
	public function get_user_name()
	{
		return $this->user_name;
	}
	
	
	public function set_password( $password )
	{
		$this->password = $password;
			
		return $this;
	}
    
	
	public function get_password()
	{
		return $this->password;
	}
	
}

/* End of file modelschemaadapter.php */
/* Location ./application/models/modelschemaadapter.php */