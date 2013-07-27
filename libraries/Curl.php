<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Class for executing and processeing cURL calls
 * 
 * @author Dennis Slade
 * @since  2011-08-02
 */

class Curl
{
	const STATUS_OK    = 200;
	const STATUS_ERROR = 500;

	const BODY         = 'Body';
	const STATUS       = 'Status';
	
	private $text = "";
	private $data = NULL;
	private $status = self::STATUS_OK;
	
	
    public function __construct()
    {
    }
    
    
	public static function post( $url, $content, $content_type = "" )
	{
		if (($handle = curl_init( $url )) === FALSE)
			return FALSE;
		
		curl_setopt( $handle, CURLOPT_POSTFIELDS, $content ); 
		curl_setopt( $handle, CURLOPT_POST, TRUE );
		curl_setopt( $handle, CURLOPT_HEADER, TRUE );
		
		if ($content_type)
		{
			curl_setopt( $handle, CURLOPT_HTTPHEADER, array( "Content-Type: $content_type" ));
		}
		
		ob_start(); 
		$status = curl_exec( $handle );
		curl_close( $handle ); 
		$response = ob_get_contents(); 
		ob_end_clean();
		
		return self::parse_http_response( $response );
	}
	
	
	public static function parse_http_response( $response_text )
	{
		$response = explode( "\r\n\r\n", $response_text );
		
		$ret = self::http_header_to_array( $response[0] );
			
		if (empty( $ret ))
		{
			$ret[ self::BODY ] = $response_text;
		}
		else
		{
			$ret[ self::BODY ] = (isset( $response[1] )) ? $response[1] : "";
		}
		
		if (!isset( $ret[ self::STATUS ] ))
		{
			$ret[ self::STATUS ] = self::STATUS_OK;
		}
		
		return $ret;
	}
	
	
	public static function http_header_to_array( $header_text )
	{
		$ret = array();
		
		if ((strncasecmp( $header_text, 'HTTP', 4 ) === 0)
		    || (strstr( $header_text, 'Content-Type: ' ) !== FALSE))
		{
			$headers = explode( "\r\n", $header_text );
			
			if (!empty( $headers ) && is_array( $headers))
			{
				foreach( $headers as $one_header )
				{
					$one_header = explode( ": ", $one_header );
					
					if (count($one_header) > 1)
					{
						$ret[ $one_header[0] ] = $one_header[1];
					}
					else
					{
						$one_header = explode( " ", $one_header[0] );
						
						if (isset( $one_header[1] ) && is_numeric( $one_header[1] ))
						{
							$ret[ self::STATUS ] = $one_header[1];
						}
						else
						{
							$ret[ self::STATUS ] = $one_header[0];
						}
					}
				}
			}
		}
		
		return $ret;
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
	
}

/* End of file Curl.php */
/* Location: ./application/libraries/Curl.php */
