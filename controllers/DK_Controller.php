<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Customized controller base class
 *
 * @author Dennis Slade
 * @since  2011-12-05
 */


class DK_Controller extends CI_Controller
{
	const DK_APP_TITLE       = 'SAFIA';
	const DK_VERSION         = '1.0';
	const DK_PERMISSION      = 'dk';
	const DK_NAMESPACE       = 'DKNS';
	
	const ACTION_ADD         = 'add';
	const ACTION_EDIT        = 'edit';
	const ACTION_EXPORT      = 'export';
	const ACTION_LIST        = 'index';
	const ACTION_LOGIN       = 'login';
	const ACTION_LOGOUT      = 'logout';
	const ACTION_SHOW        = 'show';
	
	const APP_TITLE          = 'app_title';
	const CANCEL_URL         = 'cancel_url';
	const DEFAULT_INFO       = 'default';
	const ENVIRONMENT        = 'environment';
	const ENVIRONMENT_LABEL  = 'environment_label';
	const ERROR_GUID_MISSING = 'error_guid_missing';
	const ERROR_MESSAGE      = 'error_message';
	const ERROR_NOT_FOUND    = 'error_not_found';
	const ERROR_TEMPLATE     = 'error';
	const GUID               = 'guid';
	const INDEX_PAGE_SIZE    = 50;
	const JSON               = 'response_json';
	const NOTICE_MESSAGE     = 'notice_message';
	const PAGE_LOADED_AT     = 'page_loaded_at';
	const OPTION_SUFFIX      = '_OPTION';
	const POST               = 'post';
	const PRIMARY_KEY        = 'primary_key';
	const NYC_TAX_RATE       = 0.08875;
	const TITLE              = 'title';
	const VERSION            = 'version';
	const YIELD              = 'yield';

	const IS_SUPER_ADMIN     = 'IsSuperAdmin';
	const IS_ADMIN           = 'IsAdmin';
	const IS_CONTENT_MANAGER = 'IsContentManager';
	const IS_SUPPORT         = 'IsSupport';
 	const IS_AUDITOR         = 'IsAuditor';
 	
 	const AUTOCOMPLETE_RESPONDERS = 'AutocompleteResponders';
	
 	/**
 	 * @var ModelBaseMysql
 	 * @var ModelSchema
 	 */
	protected $accessor_object       = NULL;
	
	protected $autocomplete_fields   = array();
	protected $autocomplete_models   = array();
	protected $auth                  = NULL;
	protected $data                  = array();
	protected $default_sort          = FALSE;
	protected $index_filter_name     = NULL;
	protected $minimum_permission    = self::IS_AUDITOR;
	protected $model_name            = "";
	protected $page_size_session_var = 'page_size';
	protected $show_details_list     = array();
	protected $skip_validation       = FALSE;
	protected $sort_session_var      = 'sort_field';
	
	private $PAGE_INFO = array
	(
		self::DEFAULT_INFO => array
		(
			self::TITLE => self::DK_APP_TITLE
		),
		'onsite_list' => array
		(
			self::TITLE => 'On-Site List'
		)
	);
		
	
	public function __construct( $model_name = "", $preload_models = array() )
	{
		date_default_timezone_set ( 'America/New_York' );

 		parent::__construct();
        
		log_message_info( __METHOD__, 'DK controller constructor' );
		
		$this->output->set_header( 'Expires: 0' );
        $this->output->set_header( 'Last-Modified: '.gmdate('D, d M Y H:i:s', time()).' GMT' );
		$this->output->set_header( "Cache-Control: no-store, no-cache, must-revalidate" );
		$this->output->set_header( "Cache-Control: post-check=0, pre-check=0" );
		$this->output->set_header( "Pragma: no-cache" );
        
		$this->config->load('settings');

    	log_message_info( __METHOD__, 'Settings loaded.' );
		
    	/**
		 * Login authentication required, except if this is an auth page
		 */
		$this->authenticate() or die;
    	
		$this->load->helper('html');
		$this->load->helper('url');
		$this->load->helper('asset');
		$this->load->helper('date_helper');
		
		$this->load->library('session');
		$this->load->library('form_validation');
		$this->load->library('authentication');
		
		$do_set_urls = !in_array( self::get_view_name(), array('edit') );

		log_message_info( __METHOD__, 'Libraries loaded.' );
		log_message_info( __METHOD__, 'Loading models...' );

		$this->load->model( array( 'modelschemaadapter', 'modelschemafield', 'modelbasemysql', 'modelschema' ) );
		
		$this->model_name = $model_name;
    	
		log_message_debug( __METHOD__, "Model name = {$this->model_name}" );
		log_message_debug( __METHOD__, 'Preload models = '.implode( ", ", $preload_models ));
		
		if (empty( $preload_models ))
		{
			$preload_models = array();
		}
		
		$preload_models = array_merge( $preload_models,
		                               array( $this->model_name ));
		
		$this->load->model( $preload_models );
		
		log_message_info( __METHOD__, 'Models pre-load completed.' );

		$this->data = $this->get_messages_from_request();
		
		$this->sort_session_var .= '_' . strtolower( $model_name );
		
		log_message_info( __METHOD__, 'Other models loading...' );
		
		/**
		 * If we can, set the accessor object here
		 */
		if ($model_name && class_exists( $model_name, TRUE ))
		{
			$this->accessor_object = new $model_name( TRUE, self::get_db_adapter( $model_name ) );
		}
		
		log_message_info( __METHOD__, '... done' );
		
		/**
		 * Set minimum permission level
		 */
		$this->set_minimum_permission_by_controller();
	}
    
	
    /**
     * Default index action
     */
	public function index( $skip = 0 )
	{
		if ($this->preflight_check() === FALSE)
			return;

		$this->save_cancel_link();
		
		$where       = "";
		$filtered    = FALSE;
		$filter_name = $this->index_filter_name;
		
		/**
		 * Enable filtering
		 */
		if (!empty( $filter_name ))
		{
			list( $where,
			      $filter,
			      $filtered ) = $this->process_filter( $filter_name );
		      
			$this->data['filter']       = $filter;
			$this->data[ $filter_name ] = new $filter_name();
		}

		log_message_info( __METHOD__, $where );
			      
		/**
		 * Lookup the records
		 */
				
		$sort      = $this->get_sort();
		$page_size = $this->get_page_size();
		
		$params = array( 'where'    => $where,
		                 'orderby'  => $sort,
		                 'max_rows' => $page_size);
		
		if ($join_params = $this->get_join_params())
			$params = array_merge( $params, $join_params );
		
		/**
		 * Lookup the records
		 */

		$total_count = $this->accessor_object->count( $params );

		if ($skip >= $total_count)
			$params['rownum'] = $total_count - 1;
		else
			$params['rownum'] = $skip;
			
		
		$this->data[ $this->model_name ] = $this->accessor_object->find( $params );
				
		/**
		 * Pagination!
		 */
		$this->load->library('pagination');
		$this->load->helper('pagination_helper');
		
		$pagination = new pagination_helper( $this->pagination, $total_count, $page_size );
		$this->data['Pagination']             = $pagination->create_links( site_url( self::get_controller_name('index') ));
		$this->data['PaginationSizeSelector'] = $pagination->create_size_selector( site_url( self::get_controller_name('page_size') ));
		$this->data['PaginationDescription']  = $pagination->create_description( $skip, $filtered );
		
		$this->data['SortedBy'] = $this->get_sort_description( $sort );

		
		$this->load_view( $this->data, __FUNCTION__ );
	}
	

	/**
     * Default show action
     */
	public function show( $guid = "" )
	{
		if ($this->preflight_check() === FALSE)
			return;
		
		$this->save_cancel_link();
		
		/**
		 * Need a guid for this operation
		 */
		if (empty( $guid ))
			return self::redirect_to_action( self::ACTION_LIST );
		
		
		$params = array( self::PRIMARY_KEY => $guid );
		
		/**
		 * If there's a one-to-one relationship with another table, specify that here
		 */
		
		$one_to_ones = $this->accessor_object->get_one_to_ones();
		
		if (!empty( $one_to_ones ))
		{
			/**
			 * @todo Extend ModelBaseMysql to be able to handle more than one join
			 * @todo Wrap this into a foreach()
			 */
			
			list( $params['columns'],
			      $params['join_table'],
			      $params['join_clause'] ) = $this->accessor_object->get_join_info( $one_to_ones[0][0],
			                                                                        $one_to_ones[0][1],
			                                                                        $one_to_ones[0][2] );
		}
		
		list( $this->data[ $this->model_name ],
		      $error_message ) = $this->safe_find_one( $this->accessor_object, $params );
		
		if ($error_message)
			$this->data[ self::ERROR_MESSAGE ] = $error_message;
		else
			$this->get_related_data();

//		log_message_debug( __METHOD__, $this->data[ $this->model_name ] );
		
		$this->data['TaxRate'] = $this->get_tax_rate( $this->data[ $this->model_name ] );
		$this->data['Guid']    = $guid;
		
		if (isset( $this->data[ $this->model_name ][0] ))
		{
			$this->load_view( $this->data, 'index' );
		}
		else
		{
			$this->load_view( $this->data, __FUNCTION__ );
		}
	}
	
	
	/**
     * Default edit action
     */
	public function edit( $guid = "" )
	{
		if ($this->preflight_check() === FALSE)
			return;
		
		/**
		 * Need a guid for this operation
		 */
		if (empty( $guid ))
			return self::redirect_to_action( self::ACTION_LIST );

		/**
		 * Variables we'll need to do our job
		 */
		$details_index  = lcfirst($this->model_name)."_details";
		$this->data     = array( $details_index => "" );
		$previous_input = $this->input->post();
				
		if (empty( $previous_input ))
		{
			/**
			 * We've not been here before, so load the form data from db
			 */
			list( $this->data[ $this->model_name ],
			      $this->data[ self::ERROR_MESSAGE ] ) = $this->safe_find_one( $this->accessor_object, array(self::PRIMARY_KEY => $guid) );
		}
		elseif ($this->check_validation_rules() != FALSE)
		{
			/**
			 * We've been here before...
			 *
			 * First make sure we have actual values for autocomplete fields
			 */
			list( $massaged_input,
			      $this->data[ self::ERROR_MESSAGE ] ) = $this->lookup_autocomplete_values( $previous_input[ $this->model_name ] );
			
			if (empty( $this->data[ self::ERROR_MESSAGE ] ))
			{
				$previous_input[ $this->model_name ] = array_merge( $previous_input[ $this->model_name ], $massaged_input );
				
				/**
				 * Update the record and leave the page if successful
				 */
				$this->data[ self::ERROR_MESSAGE ] = $this->safe_update( $this->accessor_object, $guid, $previous_input[ $this->model_name ] );
				
				if (empty( $this->data[ self::ERROR_MESSAGE ] ))
					return self::redirect_to_action( self::ACTION_SHOW, $guid, array('msg'=>'saved') );
			}
		}
		
		/**
		 * Getting this far means we need to show the form...
		 *
		 * Setup values for the form
		 */
		
		if (empty( $this->data[ $this->model_name ] ) && !empty( $previous_input[ $this->model_name ] ))
			$this->data[ $this->model_name ] = $previous_input[ $this->model_name ];
		
		$this->data['Schema']     = $this->accessor_object->get_schema();
		$this->data['Guid']       = $guid;
		$this->data['CancelLink'] = $this->get_cancel_link();
		
		$this->data = array_merge( $this->data, compact( 'previous_input' ));
		
		if (empty( $this->data[ $details_index ] ))
			$this->data[ $details_index ] = $this->input;
		
		$this->lookup_autocomplete_strings();
				
		/**
		 * Show it!
		 */
		$this->load_view( $this->data, __FUNCTION__ );
	}
	
	
	/**
     * Default duplicate action
     */
	public function duplicate( $guid = "" )
	{
		if ($this->preflight_check() === FALSE)
			return;
		
		/**
		 * Need a guid for this operation
		 */
		if (empty( $guid ))
			return self::redirect_to_action( self::ACTION_LIST );

		/**
		 * Variables we'll need to do our job
		 */
		$details_index  = lcfirst($this->model_name)."_details";
		$this->data     = array( $details_index => "" );
		$previous_input = $this->input->post();
		
		if (empty( $previous_input ))
		{
			/**
			 * We've not been here before, so load the form data from db
			 */
			list( $this->data[ $this->model_name ],
			      $this->data[ self::ERROR_MESSAGE ] ) = $this->safe_find_one( $this->accessor_object, array(self::PRIMARY_KEY => $guid) );

			if (empty( $this->data[ self::ERROR_MESSAGE ] ))
			{
				/**
				 * Automatically set certain timestamp fields back to now()
				 */
				foreach( array( 'created_at','last_modified_at','modified_at' ) as $reset_time_field )
				{
					/**
					 * @todo Needs to be reset to the field's default, which may or may not be ::to_timestamp()
					 */
					if (array_key_exists( $reset_time_field, $this->data[ $this->model_name ] ))
						$this->data[ $this->model_name ][ $reset_time_field ] = ModelSchemaField::to_timestamp();
				}
			}
		}
		elseif ($this->check_validation_rules() != FALSE)
		{
			/**
			 * We've been here before...
			 *
			 * First make sure we have actual values for autocomplete fields
			 */
			list( $massaged_input,
			      $this->data[ self::ERROR_MESSAGE ] ) = $this->lookup_autocomplete_values( $previous_input[ $this->model_name ] );
			
			if (empty( $this->data[ self::ERROR_MESSAGE ] ))
			{
				$previous_input[ $this->model_name ] = array_merge( $previous_input[ $this->model_name ], $massaged_input );
				
				/**
				 * Do the insert!
				 */
				$this->data[ self::ERROR_MESSAGE ] = $this->safe_insert( $this->accessor_object, $previous_input[ $this->model_name ] );
				
				if (empty( $this->data[ self::ERROR_MESSAGE ] ))
				{
					$new_id = $this->accessor_object->last_insert_id();
					
					if (!empty( $new_id ))
						return self::redirect_to_action( self::ACTION_SHOW, $new_id, array( 'msg' => 'added' ));
					else
						return self::redirect_to_action( self::ACTION_LIST, "", array( 'msg' => 'added' ));
				}
				
			}
		}
		
	
		/**
		 * Getting this far means we need to show the form...
		 *
		 * Setup values for the form
		 */
				
		if (empty( $this->data[ $this->model_name ] ) && !empty( $previous_input[ $this->model_name ] ))
			$this->data[ $this->model_name ] = $previous_input[ $this->model_name ];
		
		$this->data['Schema']     = $this->accessor_object->get_schema();
		$this->data['Guid']       = $guid;
		$this->data['CancelLink'] = $this->get_cancel_link();
		
		$this->data = array_merge( $this->data, compact( 'previous_input' ));
		
		if (empty( $this->data[ $details_index ] ))
			$this->data[ $details_index ] = $this->input;
		
		$this->lookup_autocomplete_strings();
				
		/**
		 * Show it!
		 */
		$this->load_view( $this->data, __FUNCTION__ );
	}
	
		
	/**
     * Default create action
     */
	public function create( $previous_input = array() )
	{
		if ($this->preflight_check() === FALSE)
			return;
		
		/**
		 * Variables we'll need to do our job
		 */
		$previous_input = array_merge( $previous_input,
		                               extract_value( $this->input->post(), $this->model_name, array() ) );
		
		/**
		 * We've been here before *and* we validate successfully
		 *
		 * Now make sure we have actual values for autocomplete fields
		 */
		list( $massaged_input,
		      $error_message ) = $this->lookup_autocomplete_values( $previous_input );
		
		$previous_input = array_merge( $previous_input, $massaged_input );
							
//		log_message_debug( __METHOD__, 'Data (post-autocomplete lookup):' );
//		log_message_debug( __METHOD__, $previous_input );
		
		if (!$this->skip_validation)
		{
			$this->data[ self::ERROR_MESSAGE ] = $error_message;
			
			if (($this->check_validation_rules() != FALSE)
			    && empty( $this->data[ self::ERROR_MESSAGE ] ))
			{
				/**
				 * We've been here before *and* we validate successfully. Now let's do the insert.
				 */
				
				$this->data[ self::ERROR_MESSAGE ] = $this->safe_insert( $this->accessor_object, $previous_input );
				
				$id = $this->accessor_object->last_insert_id();
					
				if (empty( $this->data[ self::ERROR_MESSAGE ] ))
				{
					$record_name = strtolower( get_class( $this->accessor_object ));
					
					return self::redirect_to_action( self::ACTION_SHOW, $id, array( 'msg' => "created|$record_name" ));
				}
			}
		}
		
		/**
		 * Getting this far means we need to show the form
		 *
		 * Setup values for the form
		 */
		$this->data['Schema']            = $this->accessor_object->get_schema();
		$this->data[ $this->model_name ] = $previous_input;
		$this->data['CancelLink']        = $this->get_cancel_link();
		
		/**
		 * Make sure we have actual values for autocomplete fields
		 */
		$this->lookup_autocomplete_strings();
		
		$this->load_view( $this->data, __FUNCTION__ );
	}
	
	
	/**
	 * OVERRIDE PLEASE!
	 * This is a hook allowing derived classes to write out any necessary one-to-many records
	 *
	 * @param Integer $ones_insert_id
	 */
	protected function create_one_to_manys( $ones_insert_id )
	{
		return FALSE;
	}
	
		
	/**
     * Default destroy action
     */
	public function destroy( $guid = "" )
	{
		if ($this->preflight_check() === FALSE)
			return;
		
		if (empty( $guid ))
			return self::redirect_to_action( self::ACTION_LIST );
			
		$error_message = $this->safe_destroy( $this->accessor_object, $guid );
		
		if (empty( $error_message ))
			return self::redirect_to_action( self::ACTION_LIST, "", array('msg'=>'destroyed') );
		else
			return self::redirect_to_action( self::ACTION_LIST, "", array('msg'=>'not-destroyed') );
	}
	
	
	protected function sort( $field_name = "" )
	{
		$sort_session_var = $this->sort_session_var;
		
		if (empty( $field_name ))
		{
			$this->session->set_userdata( $sort_session_var, "" );
		}
		else
		{
			$sort_field_now = $this->session->userdata( $sort_session_var );
			
			if ($sort_field_now == $field_name )
				$this->session->set_userdata( $sort_session_var, "$field_name DESC" );
			else
				$this->session->set_userdata( $sort_session_var, $field_name );
		}
		
		return self::redirect_to_action();
	}
	
	
	protected function page_size( $page_size = NULL )
	{
		$page_size_session_var = $this->model_name."_".$this->page_size_session_var;
		
		if (empty( $page_size ))
			$this->session->set_userdata( $page_size_session_var, "" );
		else
			$this->session->set_userdata( $page_size_session_var, $page_size );
		
		log_message_info( __METHOD__, "Session var = $page_size_session_var, incoming value = $page_size" );
			
		return self::redirect_to_action();
	}
	
	
	protected function download( $guid, $filename = "", $content_type = "" )
	{
		if ($this->preflight_check( TRUE, FALSE ) === FALSE)
			return;
		
		/**
		 * First set the headers...
		 */
		$this->send_headers_for_file_download( $filename, $content_type );
		
		/**
		 * ... and then output the file contents themselves.
		 */
		echo $this->get_download_json_text( $guid );
	}
	
	
	protected function send_headers_for_file_download( $content_type = "" )
	{
		$filename     = $this->get_download_filename();
		$content_type = $this->get_download_content_type( $content_type );
		$extension    = end( explode( '/', $content_type ));
		
		header( "Pragma: public", true );
		header( "Expires: 0" );
		header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
		header( "Content-Type: application/force-download" );
		header( "Content-Type: application/octet-stream" );
		header( "Content-Type: application/download" );
		header( "Content-Type: $content_type;charset=utf-8" );
		header( "Content-Transfer-Encoding: binary" );
		header( 'Content-Description: File Transfer' );
		
    	/**
		 * Remove commas from the filename. Otherwise Chrome gives a strange
		 * ERR_RESPONSE_HEADERS_MULTIPLE_CONTENT_DISPOSITION error
		 *
		 * Remove periods from the filename else ".pdf" might not get appended
		 */
		$filename = str_replace( array(",","."), "", $filename );
		
		log_message_debug( __METHOD__, "Setting Content-Disposition for filename '$filename'" );
		header( "Content-Disposition: attachment; filename=\"$filename.$extension\"" );
	}
	
	
	protected function get_download_content_type( $content_type = "" )
	{
		if (empty( $content_type ))
			$content_type = self::DOWNLOAD_CONTENT_TYPE;
			
		return $content_type;
	}
	

	protected function get_download_filename( $filename = "" )
	{
		if (empty( $filename ))
			$filename = self::DOWNLOAD_FILENAME;
		
		return $filename;
	}
	

	protected function filter( $filter_name, $value, $redirect = FALSE )
	{
		$class_name = $this->index_filter_name;
		
		if (class_exists( $class_name, TRUE )
		    && ($filter = new $class_name( FALSE )))
		{
			$field = $filter->get_field( $filter_name );
			
			if (empty( $field ))
				$field = $filter->get_field( "filter_$filter_name" );
			
			if (!empty( $field ))
			{
				$filter_name = $field->get_name();
				
				if ($field->has_autocomplete())
					$value = $field->lookup_autocomplete_string( $value );
				
				self::reset_filter();
				
				$this->session->set_userdata( $class_name.'_'.$filter_name, $value );
			}
		}
		
		if ($redirect)
			return self::redirect_to_action();
		else
			return TRUE;
	}
	
	
	protected function reset_filter( $redirect = FALSE )
	{
		$class_name = $this->index_filter_name;
		
		if (class_exists( $class_name, TRUE )
		    && ($filter = new $class_name( FALSE )))
		{
			$field_names = $filter->get_field_names();
			
			if (!empty( $field_names ))
			{
				foreach( $field_names as $name => $type)
				{
					$this->session->set_userdata( $class_name.'_'.$name, '' );
					$this->session->set_userdata( $class_name.'_'.$name.self::OPTION_SUFFIX, '' );
				}
			}
		}
		
		if ($redirect)
			return self::redirect_to_action();
		else
			return TRUE;
	}
	
	
	protected function reset_sort( $redirect = FALSE )
	{
		$this->session->set_userdata( $this->sort_session_var, '' );
		
		if ($redirect)
			return self::redirect_to_action();
		else
			return TRUE;
	}
	
	
	protected function preflight_check( $check_permission = TRUE, $check_db_connection = TRUE )
	{
		if ($check_permission && !$this->has_minimum_permission())
		{
			$this->load_view_no_permission();
			return FALSE;
		}
		
		if ($check_db_connection && $this->accessor_object->has_errors())
		{
			$this->load_view_db_error();
			return FALSE;
		}
		
		return TRUE;
	}
	
	
	protected function load_view( $data, $view_name = "" )
	{
		if (empty( $view_name ))
			$view_name = self::get_view_name();
		
		if ($this->accessor_object)
			$data['Schema'] = $this->accessor_object->get_schema();

		$this->load->helper('form');
		
		$this->load_view_with_template( rtrim( $this->get_controller_name()."/$view_name", "/" ),
		                                $data );
	}
	
	
	protected function load_view_with_template( $view, $vars = array(), $return = FALSE )
    {
    	$yield = array();

    	$yield[self::YIELD] = dk_html::messages( $vars )
    	                    . $this->load->view( $view, $vars, TRUE );
    	
    	$yield[self::APP_TITLE]         = self::DK_APP_TITLE;
    	$yield[self::ENVIRONMENT]       = $this->get_enviroment_string();
    	$yield[self::ENVIRONMENT_LABEL] = $this->get_enviroment_label();
    	$yield[self::PAGE_LOADED_AT]    = $this->get_page_loaded_at();
    	$yield[self::TITLE]             = $this->get_page_title( $view, $vars );
 		$yield[self::VERSION]           = self::DK_VERSION;
 		
		/**
		 * Permissions
		 */
		$yield[self::IS_SUPER_ADMIN]     = $this->has_super_admin_permission();
 		$yield[self::IS_ADMIN]           = $this->has_admin_permission();
		$yield[self::IS_CONTENT_MANAGER] = $this->has_content_manager_permission();
		$yield[self::IS_SUPPORT]         = $this->has_support_permission();
 		$yield[self::IS_AUDITOR]         = $this->has_auditor_permission();
 		
 		$yield[self::AUTOCOMPLETE_RESPONDERS] = $this->autocomplete_responders_html();
		
 		return $this->load->view('template', $yield, $return );
    }
    
    
	protected function load_view_no_permission( $view = "" )
	{
		if (empty( $view ))
			$view = $this->get_controller_name();
			
		if (empty( $view ))
			$view = 'main_menu';
			
		$data = array( 'original_view' => $view );
			
		return $this->load_view_with_template( 'permission_error', $data );
	}


    protected function load_view_db_error( ModelBaseMysql $model_object = NULL, $view = "" )
	{
		if (empty( $model_object ))
			$model_object = $this->accessor_object;
			
		if (empty( $view ))
			$view = $this->get_controller_name();
			
		$data = array( 'errors' => $model_object->all_errors(),
		               'original_view' => $view );
			
		return $this->load_view_with_template( 'db_error', $data );
	}


	protected function redirect_to_action( $action = self::ACTION_LIST, $guid = "", $vars = NULL )
	{
		return self::redirect_to( dk_html::get_controller_name(), $action, $guid, $vars );
	}


	protected static function redirect_to( $controller, $action = self::ACTION_LIST, $guid = "", $vars = NULL )
	{
		$uri = array( "/$controller" );
		
		if (!empty( $action ) && ($action != self::ACTION_LIST))
			$uri[] = $action;
		
		if (!empty( $guid ))
			$uri[] = $guid;
		
		if (!empty( $vars))
		{
			$trailing = array();
			
			foreach( $vars as $var => $value)
				$trailing[] = "$var=$value";
				
			$uri[] = "?" . implode( "&", $trailing );
		}
		
		return redirect(implode( "/", $uri ));
	}
	
	
	protected function safe_find_one( ModelSchema $accessor_object, array $params = NULL )
	{
		$params = $this->safe_prep_params( $accessor_object, $params );
		
		list( $data, $error ) = $this->safe_find( $accessor_object, $params );
		
		if (!empty( $data ) && is_array( $data ))
			$data = reset( $data );
			
		return array( $data, $error );
	}
	
	
	protected function safe_find( ModelSchema $accessor_object, array $params = NULL )
	{
		$params = $this->safe_prep_params( $accessor_object, $params );
		
		try
		{
			$data  = $accessor_object->find( $params );
//			print_r( $accessor_object );
			$error = FALSE;
		}
		catch( Exception $e )
		{
			$data = NULL;
			$error = 'Database error: ' . $e->getMessage() . '.  Please try again.';
		}
		
		return array( $data, $error );
	}
	
	
	protected function safe_insert( ModelSchema $accessor_object, $fields )
	{
		try
		{
			$accessor_object->extract_data_for_insert( $fields );
			
			if ($accessor_object->insert() != FALSE)
				$accessor_object->save_sticky_insert_form_values( $fields );
			
			$error = FALSE;
		}
		catch( Exception $e )
		{
			$error = 'Database error: ' . $e->getMessage() . '.  Please try again.';
		}
		
		return $error;
	}
	
	
	protected function safe_update( ModelSchema $accessor_object, $primary_key_value, $fields, $extract_fields = TRUE )
	{
		try
		{
			$primary_key_field = $accessor_object->get_primary_key();
			
			if (!isset( $fields[ $primary_key_field ] ))
				$fields[ $primary_key_field ] = $primary_key_value;
			
			if ($extract_fields)
			{
				$accessor_object->extract_data_for_update( $fields );
			}
			else
			{
				foreach ($fields as $key => $one_field)
				{
					$accessor_object->$key = $one_field;
				}
			}

			$accessor_object->update( $fields );

//			log_message_debug( __METHOD__, $fields );
//			log_message_debug( __METHOD__, $accessor_object );
	
			$error = FALSE;
		}
		catch( Exception $e )
		{
			$error = 'Database error: ' . $e->getMessage() . '.  Please try again.';
			log_message_debug( __METHOD__, $error );
		}
		
		return $error;
	}
	
	
	protected function safe_update_if_there( ModelSchema $accessor_object, $primary_key_value, $update_data )
	{
		$response = $accessor_object->find( $this->safe_prep_params( $accessor_object, $update_data ));
		
		if (empty( $response ))
			return self::ERROR_NOT_FOUND;
		
		$response    = reset( $response );
		$primary_key = $accessor_object->get_primary_key();
		
		if (!isset( $response[ $primary_key ] ))
			return self::ERROR_NOT_FOUND;

		$ret = $this->safe_update( $accessor_object, $primary_key_value, $update_data );
			
		if (!empty( $ret ))
		{
			log_message_debug( __METHOD__, $ret );
			return $ret;
		}

		return FALSE;
	}
	
	
	protected function safe_destroy( ModelSchema $accessor_object, $guid )
	{
		try
		{
			$field_name = $accessor_object->get_primary_key();
			
			$accessor_object->remove( array( 'where'    => "$field_name = '$guid'",
			                                 'max_rows' => 1 ));
			$error = FALSE;
		}
		catch( Exception $e )
		{
			$error = 'Database error: ' . $e->getMessage() . '.  Please try again.';
		}
		
		return $error;
	}
	
	
	protected function safe_prep_params( ModelSchema $accessor_object, array $params = NULL )
	{
		if (isset( $params[self::PRIMARY_KEY] ))
			$params['where'] = $accessor_object->get_primary_key() . " = '{$params[self::PRIMARY_KEY]}'";
		
		return $params;
	}
	
	
	public function add_to_array( $array_locator, $guid = "", $second_array_locator = "" )
	{
		if ($this->accessor_object->has_errors())
			return $this->load_view_db_error();
		
		if (empty( $guid ))
			return $this->redirect_to_action( self::ACTION_LIST );
		
		$error_message = $this->add_to_array_db( $array_locator, $guid );
		
		if (empty( $error_message ) && !empty( $second_array_locator ))
		{
			$error_message = $this->add_to_array_db( $second_array_locator, $guid );
		}
		
		$msg = (empty( $error_message )) ? "added"
		                                 : "not-added";
		
		return self::redirect_to_action( self::ACTION_EDIT, $guid, array('msg'=>$msg) );
	}
	
	
	protected function add_to_array_db( $array_locator, $guid )
	{
		$error_message = FALSE;
				
		try
		{
			$this->accessor_object->load( $guid );
			$data = $this->accessor_object->get_data();

			$error_message = $this->add_to_data_using_locator( $data, $array_locator );
			
			if (!$error_message)
			{
				$this->accessor_object->extract_data_for_update( $data );
				$this->accessor_object->save();
			}
		}
		catch( Exception $e )
		{
			$error_message = 'Database error: ' . $e->getMessage() . '.  Please try again.';
		}

		return $error_message;
	}
	
	
	protected function add_to_data_using_locator( &$data, $array_locator )
	{
		$array_locator = dk_html::array_locator_string_explode( $array_locator );
		$array_name    = end( $array_locator );
		$class_name    = ucfirst( $array_name );
		
		if (!class_exists( $class_name,TRUE ))
		{
			return "Can't find class $class_name.";
		}
			
		$target =& $data;
		reset( $array_locator );
		
		for( $i = 0; $i < count($array_locator); $i += 2 )
		{
			if (array_key_exists( $i+1, $array_locator )
			    && array_key_exists( $array_locator[$i], $target ))
			{
				if (array_key_exists( $array_locator[$i+1], $target[ $array_locator[$i] ] ))
				{
					/**
					 * We've been given an array and an index - dereference by array[index] and continue
					 */
					$target =& $target[ $array_locator[$i] ][ $array_locator[$i+1] ];
				}
				else
				{
					/**
					 * We've been given only an object or some other non-array type - dereference by just the object name and continue
					 */
					$target =& $target[ $array_locator[$i] ];
				}
			}
		}
			
		$array_object = new $class_name();
		$target[ $array_name ][] = $array_object->get_default_empty_object();
		
		return FALSE;
	}
	

	public function remove_from_array( $array_locator, $guid = "" )
	{
		if ($this->accessor_object->has_errors())
			return $this->load_view_db_error();
		
		if (empty( $guid ))
			return $this->redirect_to_action( self::ACTION_LIST );
		
		$error_message = $this->remove_from_array_db( $array_locator, $guid );
		
		$msg = (empty( $error_message )) ? "destroyed"
		                                 : "not-destroyed";
		
		return self::redirect_to_action( self::ACTION_SHOW, $guid, array('msg'=>$msg) );
	}
	
	
	protected function remove_from_array_db( $array_locator, $guid )
	{
		$array_locator = dk_html::array_locator_string_explode( $array_locator );
		$array_name    = end( $array_locator );
		$class_name    = ucfirst( $array_name );
		
		try
		{
			$this->accessor_object->load( $guid );
			$data = $this->accessor_object->get_data();
				
			if (class_exists( $class_name,TRUE ))
			{
				$target =& $data;
				reset( $array_locator );
				
				for( $i = 0; $i < count($array_locator); $i += 2 )
				{
					if (array_key_exists( $i+1, $array_locator )
					    && array_key_exists( $array_locator[$i], $target ))
					{
						$last_target       =& $target[ $array_locator[$i] ];
						$last_target_index  = $array_locator[$i+1];
						
						if (array_key_exists( $array_locator[$i+1], $target[ $array_locator[$i] ] ))
						{
							/**
							 * We've been given an array and an index - dereference by array[index] and continue
							 */
							$target =& $target[ $array_locator[$i] ][ $array_locator[$i+1] ];
						}
						else
						{
							/**
							 * We've been given only an object or some other non-array type - dereference by just the object name and continue
							 */
							$target =& $target[ $array_locator[$i] ];
						}
					}
				}
				
				/**
				 * This is where the magic happens: delete the specified/found element from the array
				 */
				if (isset( $last_target, $last_target_index ))
				{
					array_splice( $last_target, intval($last_target_index), 1 );
				}
								
				$this->accessor_object->extract_data_for_update( $data );
				$this->accessor_object->save();

				$error_message = FALSE;
			}
			else
			{
				$error_message = "Can't find class $class_name.";
			}
		}
		catch( Exception $e )
		{
			$error_message = 'Database error: ' . $e->getMessage() . '.  Please try again.';
		}

		return $error_message;
	}
	

	protected function add_missing_guids()
	{
		if ($this->accessor_object->has_errors())
			return $this->load_view_db_error();

		$error_message = $this->add_missing_guids_db();
		
		$msg = (empty( $error_message )) ? "guids-added"
		                                 : "guids-not-added";
		
		return self::redirect_to_action( self::ACTION_LIST, "", array('msg'=>$msg) );
	}
	
		
	protected function add_missing_guids_db()
	{
		try
		{
			$this->accessor_object->add_missing_guids();

			$error_message = FALSE;
		}
		catch( Exception $e )
		{
			$error_message = 'Database error: ' . $e->getMessage() . '.  Please try again.';
		}

		return $error_message;
	}

	
	protected function get_data( $guid_or_array_of_guids, $order_by = "" )
	{
		if (empty( $guid_or_array_of_guids ))
			return FALSE;

		/**
		 * Prepare fields for the lookup
		 */
		$primary_key = $this->accessor_object->get_primary_key();
		
		if (is_array( $guid_or_array_of_guids ))
		{
			$guids  = implode( "', '", $guid_or_array_of_guids );
			
			$params = array( 'where'    => "$primary_key IN ('$guids')",
			                 'max_rows' => 0 );
		}
		else
		{
			$params = array( 'where'    => "$primary_key = '$guid_or_array_of_guids'",
			                 'max_rows' => 1 );
		}
		
		/**
		 * If there's a one-to-one relationship with another table, specify that here
		 */
		
		$one_to_ones = $this->accessor_object->get_one_to_ones();
		
		if (!empty( $one_to_ones ))
		{
			/**
			 * @todo Extend ModelBaseMysql to be able to handle more than one join
			 * @todo Wrap this into a foreach()
			 */
			
			list( $params['columns'],
			      $params['join_table'],
			      $params['join_clause'] ) = $this->accessor_object->get_join_info( $one_to_ones[0][0],
			                                                                        $one_to_ones[0][1],
			                                                                        $one_to_ones[0][2] );
		}
		
		if (!empty( $order_by ))
			$params['orderby'] = $order_by;
		
		/**
		 * Lookup the record
		 */
			
		$data = $this->accessor_object->find( $params );
		
		if (!$data)
			return $data;
		
		/**
		 * If there are one-to-many relationships with other tables, look those up now too
		 */
		
		$one_to_manys = $this->accessor_object->get_one_to_manys();
		
		if (!empty( $one_to_manys ))
		{
			foreach( $one_to_manys as $one_relation)
			{
				$foreign_model = $one_relation[0];
				$foreign_key   = $one_relation[1];
				$local_key     = $one_relation[2];
				$order_by      = $one_relation[3];
				
				if (empty( $local_key ))
					$local_key = $foreign_key;
				
				if (!class_exists( $foreign_model, TRUE ))
					$this->load->model( array( $foreign_model ));
					
				$model = new $foreign_model( FALSE );
				
				$table = $model->get_table_name();
				
				$local_keys = array();
				$map        = array();
				
				foreach( $data as $key => $row )
				{
					if (isset( $row[ $local_key ] ))
					{
						$local_keys[]                   = "'" . $row[ $local_key ] . "'";
						$map[ "{$row[$local_key]}" ]    = $key;
						$data[ $key ][ $foreign_model ] = array();
					}
				}
				
				if (!empty( $local_keys ))
				{
					$where = $foreign_key . " IN (" . implode(",", $local_keys) . ")";
					
					$params = array( 'where'    => $where,
					                 'orderby'  => $order_by,
					                 'max_rows' => 0 );
								
					$many_data = $model->find( $params );
					
					if ($many_data)
					{
						foreach( $many_data as $many_datum )
						{
							if (isset( $many_datum[ $foreign_key ] ))
							{
								$data[ $map["{$many_datum[$foreign_key]}"] ][ $foreign_model ][] = $many_datum;
							}
						}
					}
				}
				
				unset( $model );
			}
		}

//		log_message_debug( __METHOD__, print_r( $data, TRUE ) );
		
		$this->data[ $this->model_name ] = $data;
		
		return count( $this->data[ $this->model_name ] );
	}
	

	protected function get_data_filtered( $skip = 0, $autofilter = TRUE )
	{
		$where       = "";
		$filtered    = FALSE;
		$filter_name = $this->index_filter_name;
		
		/**
		 * Enable filtering
		 */
		if ($autofilter && !empty( $filter_name ))
		{
			list( $where,
			      $filter,
			      $filtered ) = $this->process_filter( $filter_name );
		      
			$this->data['filter']       = $filter;
			$this->data[ $filter_name ] = new $filter_name();
		}

		log_message_info( __METHOD__, $where );
			      
		/**
		 * Prepare fields for the lookup
		 */
		
		$sort      = $this->get_sort();
		$page_size = $this->get_page_size();
		
		$params = array( 'where'    => $where,
		                 'orderby'  => $sort,
		                 'max_rows' => $page_size);

		/**
		 * If there's a one-to-one relationship with another table, specify that here
		 */
		
		$one_to_ones = $this->accessor_object->get_one_to_ones();
		
		if (!empty( $one_to_ones ))
		{
			/**
			 * @todo Extend ModelBaseMysql to be able to handle more than one join
			 * @todo Wrap this into a foreach()
			 */
			
			list( $params['columns'],
			      $params['join_table'],
			      $params['join_clause'] ) = $this->accessor_object->get_join_info( $one_to_ones[0][0],
			                                                                        $one_to_ones[0][1],
			                                                                        $one_to_ones[0][2] );
		}
		
		/**
		 * Lookup the records
		 */

		$total_count = $this->accessor_object->count( $params );

		$params['rownum'] = ($skip >= $total_count) ? ($total_count - 1)
		                                            : $skip;
			
		$data = $this->accessor_object->find( $params );
		
		if (!$data)
			return $data;
		
		/**
		 * If there are one-to-many relationships with other tables, look those up now too
		 */
		
		$one_to_manys = $this->accessor_object->get_one_to_manys();
		
		if (!empty( $one_to_manys ))
		{
			foreach( $one_to_manys as $one_relation)
			{
				$foreign_model = $one_relation[0];
				$foreign_key   = $one_relation[1];
				$local_key     = $one_relation[2];
				$order_by      = $one_relation[3];
				
				if (empty( $local_key ))
					$local_key = $foreign_key;
				
				$model = new $foreign_model( FALSE );
				$table = $model->get_table_name();
				
				$local_keys = array();
				$map        = array();
				
				foreach( $data as $key => $row )
				{
					if (isset( $row[ $local_key ] ))
					{
						$local_keys[]                   = "'" . $row[ $local_key ] . "'";
						$map[ "{$row[$local_key]}" ]    = $key;
						$data[ $key ][ $foreign_model ] = array();
					}
				}
				
				if (!empty( $local_keys ))
				{
					$where = $foreign_key . " IN (" . implode(",", $local_keys) . ")";
					
					$params = array( 'where'    => $where,
					                 'orderby'  => $order_by );
								
					$many_data = $model->find( $params );
					
					if ($many_data)
					{
						foreach( $many_data as $many_datum )
						{
							if (isset( $many_datum[ $foreign_key ] ))
							{
								$data[ $map["{$many_datum[$foreign_key]}"] ][ $foreign_model ][] = $many_datum;
							}
						}
					}
				}
				
				unset( $model );
			}
		}

		log_message_debug( __METHOD__, print_r( $data, TRUE ) );
		
		$this->data[ $this->model_name ] = $data;
		
		return count( $this->data[ $this->model_name ] );
	}
	

	protected function get_related_data()
	{
		if (empty( $this->data[ $this->model_name ] ))
			return FALSE;
		
		$in_singleton_mode = (!isset( $this->data[ $this->model_name ][0] ));
		
		$primary_data = ($in_singleton_mode) ? array( 0 => $this->data[ $this->model_name ] )
		                                     : $this->data[ $this->model_name ];
		
		$one_to_manys = $this->accessor_object->get_one_to_manys();
		
		if (empty( $one_to_manys ))
			return FALSE;
		
		foreach( $one_to_manys as $one_relation)
		{
			$foreign_model = $one_relation[0];
			$foreign_key   = $one_relation[1];
			$local_key     = $one_relation[2];
			$order_by      = $one_relation[3];
			
			if (empty( $local_key ))
				$local_key = $foreign_key;
			
			$model = new $foreign_model( FALSE );
			$table = $model->get_table_name();
			
			$local_keys = array();
			$map        = array();
			
			foreach( $primary_data as $key => $row )
			{
				if (isset( $row[ $local_key ] ))
				{
					$local_keys[]                   = "'" . $row[ $local_key ] . "'";
					$map[ "{$row[$local_key]}" ]    = $key;
					$primary_data[ $key ][ $foreign_model ] = array();
				}
			}
			
			if (!empty( $local_keys ))
			{
				$where = $foreign_key . " IN (" . implode(",", $local_keys) . ")";
				
				$params = array( 'where'    => $where,
				                 'orderby'  => $order_by );
							
				$many_data = $model->find( $params );
				
				if ($many_data)
				{
					foreach( $many_data as $many_datum )
					{
						if (isset( $many_datum[ $foreign_key ] ))
						{
							$primary_data[ $map["{$many_datum[$foreign_key]}"] ][ $foreign_model ][] = $many_datum;
						}
					}
				}
			}
			
			unset( $model );
		}

//		log_message_debug( __METHOD__, print_r( $primary_data, TRUE ) );

		if ($in_singleton_mode)
			$this->data[ $this->model_name ] = $primary_data[0];
		else
			$this->data[ $this->model_name ] = $primary_data;
		
		return TRUE;
	}
	
	
	private function get_join_params()
	{
		$one_to_ones = $this->accessor_object->get_one_to_ones();
		
		if (!empty( $one_to_ones ))
		{
			/**
			 * @todo Extend ModelBaseMysql to be able to handle more than one join
			 * @todo Wrap this in a foreach()
			 */
			
			$params = array();
			
			list( $params['columns'],
			      $params['join_table'],
			      $params['join_clause'] ) = $this->accessor_object->get_join_info( $one_to_ones[0][0],
			                                                                        $one_to_ones[0][1],
			                                                                        $one_to_ones[0][2] );
			return $params;
		}
		
		return NULL;
	}
	
	
	/**
	 * VALIDATION METHODS
	 */
	
	private function check_validation_rules( $model_object = NULL )
	{
		$previous_input = $this->input->post();
		
		if (empty( $model_object ))
			$model_object = $this->accessor_object;
		
		/**
		 * No previous input to process OR no db object === FALSE
		 */
		if (empty( $previous_input ) || empty( $model_object ))
			return FALSE;
		
		/**
		 * Previous input AND db object AND no validation rules === TRUE
		 */
		if (!$model_object->has_validation_rules())
			return TRUE;
		
		/**
		 * We have everything in place to run the validation rules, so let's do it
		 */
		
		$this->set_validation_rules( $model_object );
		
		return $this->form_validation->run();
	}

	
	protected function set_schema_from_model( $model_name, $set_validation_rules = TRUE )
	{
		log_message_info( __METHOD__, 'begin' );
		
		$accessor                  = new $model_name( TRUE, self::get_db_adapter( $model_name ));
		$this->data[ $model_name ] = $accessor->get_schema();
		
		unset( $accessor );
		
		if ($set_validation_rules)
			$this->set_validation_rules_from_schema( $this->data[ $model_name ], $model_name );
		
		log_message_info( __METHOD__, 'end' );
		
		return TRUE;
	}
	
	
	private function set_validation_rules( $model_object = NULL )
	{
		if (empty( $model_object ))
			$model_object = $this->accessor_object;
		
		if (empty( $model_object )
		    || !method_exists( $model_object, 'get_schema' )
		    || !($class_name = get_class( $model_object ))
		    || empty( $class_name ))
		{
			return FALSE;
		}
		
		return $this->set_validation_rules_from_schema( $model_object->get_schema(), $class_name );
	}
	
	
	protected function set_validation_rules_from_schema( $fields_or_enclosing_array, $model_name = "" )
	{
		$index = ModelSchema::model_2_index_fields( $model_name );
		
		$fields = (isset( $index, $fields_or_enclosing_array, $fields_or_enclosing_array[ $index ] ))
		             ? $fields_or_enclosing_array[ $index ]
		             : $fields_or_enclosing_array;
		
		if (empty( $fields ) || !is_array( $fields ))
			return FALSE;

		foreach( $fields as $one_field )
		{
			$validation_rules = $one_field->get_validation_rules();
			
			if ($validation_rules)
				$this->form_validation->set_rules( $model_name.'['.$one_field->get_name().']',
				                                   $one_field->get_label_raw(),
				                                   $validation_rules );
		}
		
		return TRUE;
	}
	
	
	protected function has_just_one_record()
	{
		$ret = FALSE;
		
		$result = iterator_to_array( $this->accessor_object->find( array(), array('guid'=>1) ));
		
		if (count( $result ) == 1 )
		{
			$result = reset( $result );
			
			if (isset( $result[ ModelSchemaField::GUID ] ) && !empty( $result[ ModelSchemaField::GUID ] ))
				$ret = $result[ ModelSchemaField::GUID ];
		}
		
		unset( $result );
		
		return $ret;
	}
	
	
	/**
	 * AUTOCOMPLETE METHODS
	 */
	
	private function autocomplete_responders_html()
	{
    	$html   = "";
		$fields = $this->accessor_object->get_fields();
		
		$index_filter_name = $this->index_filter_name;
		
		if (!empty( $index_filter_name ))
		{
			$filter_accessor  = new $index_filter_name();
			$fields          += $filter_accessor->get_fields();
			
			unset( $filter_accessor );
		}
    	
		if (!empty( $this->autocomplete_models ))
		{
			foreach( $this->autocomplete_models as $extra_model )
			{
				$extra_accessor  = new $extra_model();
				$fields         += $extra_accessor->get_fields();
				
				unset( $extra_accessor );
			}
		}
		
		foreach( $fields as $one_field )
		{
			if ($one_field->has_autocomplete())
				$html .= $this->autocomplete_jquery( $one_field->get_name() );
		}
    	
		if ($html)
			$html = "\n\t<script type=\"text/javascript\">$html\n\t</script>\n";
    	
		return $html;
    }
    
    
	private function autocomplete_jquery( $field_name )
    {
    	$action_id = "autocomplete_$field_name";
    	$url       = site_url( self::get_controller_name( $action_id ));
    	
    	$html =
<<<JQUERY

		\$(document).ready(function() {
			\$(function() {
				\$( "#$action_id" ).autocomplete({
					source: function(request, response) {
						\$.ajax({ url: "$url",
						data: { term: \$("#$action_id").val()},
						dataType: "json",
						type: "POST",
						success: function(data){
							response(data);
						}
					});
				},
				minLength: 2
				});
			});
		});
JQUERY;
    	
    	return $html;
    }
    
    
	protected function autocomplete( $input_value,
	                                 $lookup_model_name,
	                                 $lookup_descriptor,
	                                 $lookup_display_column,
	                                 $reverse_lookup = "" )
	{
		$ret         = array();
		$column_name = "AUTOCOMPLETE_DISPLAY_VALUE";
		$descriptor  = "$lookup_descriptor AS $column_name";
		
		$accessor = new $lookup_model_name();
		
		if (empty( $input_value ) && !empty( $reverse_lookup ))
		{
			log_message_info( __METHOD__, "Reverse lookup..." );
			
			$ret = NULL;
			
			$params = array( 'columns' => "$descriptor, $lookup_display_column",
			                 'having'  => "$column_name = '" . $accessor->safe_string( $reverse_lookup ) . "'" );
			
			$values = $accessor->find( $params );
			
			if ($values
			    && (count( $values ) > 0)
			    && ($first_row = reset( $values ))
			    && isset( $first_row[ $column_name ], $first_row[ $lookup_display_column ] ))
			{
				$ret = $first_row[ $lookup_display_column ];
			}
		}
		else
		{
			log_message_info( __METHOD__, "Doing (forward) lookup..." );
			
			$params = array( 'columns' => $descriptor,
			                 'having'  => "$column_name LIKE '$input_value%'",
			                 'orderby' => "$column_name" );
			
			$list = $accessor->find( $params );
			
			if ($list && count( $list ) > 0)
			{
				foreach( $list as $item )
					$ret[] = $item[ $column_name ];
			}
			
			if (empty( $ret ))
				$ret = array( " (none found) " );
			
			echo json_encode( $ret );
		}

		unset( $accessor );
		
		return $ret;
	}
	
	
	private function lookup_autocomplete_values( $previous_input = NULL )
	{
		$ret_array = array();
		$ret_error = "";
		
		$fields = $this->accessor_object->get_fields();
		
		if (!empty( $previous_input )
		    && is_array( $previous_input )
			&& !empty( $fields )
			&& is_array( $fields ))
		{
			foreach( $fields as $one_field )
			{
				if ($one_field->has_autocomplete_validation())
				{
					$name   = $one_field->get_name();
					$target = extract_value( $previous_input, $name );
					
					if ($target === "")
						continue;
					
					$value = $this->lookup_one_autocomplete_value( $one_field, $target );
		
					if ($value === FALSE || $value === NULL)
					{
						$label = $one_field->get_label();
						$error = "Can't find $label '$target'. Select another string and try again.";
						
						log_message_error( __METHOD__, $error );
						
						$ret_error .= "<p>$error</p>";
					}
					else
					{
						$ret_array[ $name ] = $value;
					}
				}
			}
		}
		
		return array( $ret_array, $ret_error );
	}

	
	private function lookup_one_autocomplete_value( ModelSchemaField $field, $value )
	{
		$ret = NULL;
		
		if ($field->has_autocomplete() && $value !== "")
		{
			$name        = $field->get_name();
			$method_name = "autocomplete_$name";
			
			if (method_exists( $this, $method_name ))
			{
				$lookup = $this->$method_name( $value );
				
				if (is_scalar( $lookup ))
					$ret = $lookup;
			}
		}
		
		return $ret;
	}

	
	private function lookup_autocomplete_strings()
	{
		$fields = $this->accessor_object->get_fields();
		
		if (!empty( $fields )
			&& is_array( $fields )
			&& isset( $this->data[ $this->model_name ] )
			&& is_array( $this->data[ $this->model_name ] ))
		{
			foreach( $fields as $one_field )
			{
				$data = &$this->data[ $this->model_name ];
				$name = $one_field->get_name();
				
				if ($one_field->has_autocomplete()
				    && isset( $data[ $name ] ))
				{
					$data[ $name ] = $this->lookup_one_autocomplete_string( $one_field, $data[ $name ] );
				}
			}
		}
	}

	
	private function lookup_one_autocomplete_string( ModelSchemaField $field, $value )
	{
		$ret         = $value;
		$DESC_FIELD  = 'DESCRIPTOR';
		
		list( $model,
		      $column_name,
		      $descriptor) = $field->get_autocomplete();
		
		if (empty( $column_name ))
			$column_name = $field->get_name();
		
		$accessor = new $model();
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
		
		unset( $accessor );
		
		return $ret;
	}

	
	/**
	 * AUTHENTICATION / PERMISSION
	 */
	
	public function authenticate()
	{
		if (self::get_controller_name() == 'auth')
			return TRUE;
		
		$this->auth = Authentication::authenticate( site_url('auth/login'), current_url() );

		if (!$this->auth)
			return FALSE;
			
		return $this->auth;
	}
	
	
	private function set_minimum_permission_by_controller()
	{
		switch( self::get_controller_name() )
		{
			case 'permission_levels':
				$this->set_minimum_permission( self::IS_SUPER_ADMIN );
				break;
				
			case 'users':
				$this->set_minimum_permission( self::IS_ADMIN );
				break;
				
			case 'main_menu':
			default:
				$this->set_minimum_permission( self::IS_AUDITOR );
				break;
		}
	}
    
    
	protected function set_minimum_permission( $permission_level = "" )
	{
		$this->minimum_permission = $permission_level;
	}
	
	
	protected function has_minimum_permission()
	{
		switch ( $this->minimum_permission )
		{
			case self::IS_SUPER_ADMIN:
				return $this->has_super_admin_permission();
			
			case self::IS_ADMIN:
				return $this->has_admin_permission();
			
			case self::IS_CONTENT_MANAGER:
				return $this->has_content_manager_permission();
			
			case self::IS_SUPPORT:
				return $this->has_support_permission();
			
			case self::IS_AUDITOR:
			default:
				return $this->has_auditor_permission();
		}
	}
	
	
	protected function has_super_admin_permission()
	{
		return $this->has_permission( 'is_super_admin' );
	}
    
    
    protected function has_admin_permission()
	{
		return $this->has_permission( 'is_admin' );
	}
    
    
    protected function has_content_manager_permission()
	{
		return $this->has_permission( 'is_content_manager' );
	}
    
    
    protected function has_support_permission()
	{
		return $this->has_permission( 'is_support' );
	}
    
    
    protected function has_auditor_permission()
	{
		return $this->has_permission( 'is_auditor' );
	}
    
    
    protected function has_permission( $method_name )
	{
		return (method_exists( $this->auth, $method_name ))
		          ? $this->auth->$method_name( self::DK_PERMISSION )
		          : (self::get_enviroment_string() == 'development');
	}
	
	
	/**
	 * DISPLAY FILTERING, PAGINATION, AND SORTING
	 */
    
	protected function get_filter( $model_name = "" )
	{
		if (empty( $model_name ))
			return;
		
		$filter   = $this->input->post( $model_name );
		$accessor = new $model_name( FALSE );

		$field_names = $accessor->get_field_names();
		
		if (empty( $filter ))
		{
			foreach( $field_names as $name => $type)
			{
				$filter[ $name ]                     = $this->session->userdata( $model_name.'_'.$name );
				$filter[ $name.self::OPTION_SUFFIX ] = $this->session->userdata( $model_name.'_'.$name.self::OPTION_SUFFIX );
			}
		}
		else
		{
			foreach( $field_names as $name => $type)
			{
				$this->session->set_userdata( $model_name.'_'.$name,
				                              extract_value( $filter, $name ));
				$this->session->set_userdata( $model_name.'_'.$name.self::OPTION_SUFFIX,
				                              extract_value( $filter, $name.self::OPTION_SUFFIX ));
			}
		}
		
		return $filter;
	}
	
	
	private function process_filter( $model_name )
	{
		$where    = "1";
		$filtered = FALSE;
		
		$filter = $this->get_filter( $model_name );
		
		if (!empty( $filter ))
		{
			$accessor    = new $model_name( FALSE );
			$field_names = $accessor->get_field_names();
			
			foreach( $field_names as $name => $type)
			{
				switch( $name )
				{
					case 'filter_named':
					case 'filter_archived':
						$apply_filter = (ModelSchemaField::prepare_boolean( extract_value( $filter, $name )) !== TRUE);
						break;
					
					default:
						$apply_filter = !empty( $filter[$name] );
						break;
				}
				
				if ($apply_filter
				    && ($field = $accessor->get_field( $name )))
				{
					$criteria = $field->db_filter( extract_value( $filter, $name ));
					
					/**
					 * If there is an operation selector, replace '=' with the selected operation
					 */
					if ($operation = extract_value( $filter, $name.self::OPTION_SUFFIX ))
						$criteria = str_replace( '=', $operation, $criteria );
				
					$where .= " AND " . $criteria;
					
					$filtered = TRUE;
				}
			}
		}
		
		return array( $where, $filter, $filtered );
	}
	
	
	protected function get_sort()
	{
		/**
		 * If someone set attribute default_sort then that overrides what was set in the model
		 */
		if ($this->default_sort)
			$default_sort = $this->default_sort;
		else
			$default_sort = $this->accessor_object->get_default_sort();
		
		$sort = $this->session->userdata( $this->sort_session_var );
		
		if (!$sort && $default_sort)
			$sort = $default_sort;
		
		if (empty( $sort ))
			return "";
		
		if (!is_string( $sort ))
			return $sort;
		
		log_message_debug( __METHOD__, "incoming sort = $sort", TRUE );
			
		if ($sort[0] != '-')
		{
			$direction = ModelSchemaField::SORT_ASCENDING;
		}
		else
		{
			$direction = ModelSchemaField::SORT_DESCENDING;
			$sort = substr( $sort, 1 ) . " DESC";
		}
		
		log_message_debug( __METHOD__, "sort = $sort, direction = $direction" );
	
		return $sort;
	}
	
	
	protected function get_sort_description( $sort_order )
	{
		$text = array();
			
		if (empty( $sort_order ))
		{
			$text[] = "(unsorted)";
		}
		else
		{
			if (is_string( $sort_order ))
				$sort_order = array( $sort_order );

			foreach( $sort_order as $field )
			{
				$field         = trim( $field );
				$field_no_desc = str_replace( array("desc","DESC"), "", $field );
				$field_display = ModelSchemaField::name_2_label( $field_no_desc );
				
				if ($field != $field_no_desc)
					$field_display .= " (descending)";
				
				$text[] = $field_display;
			}
		}
		
		return "<b>Sorted by:</b> " . ucfirst( implode( ", ", $text ))
		       . div( dk_html::link_back_to_index( 'use default sort', 'reset_sort' ),
		              array( 'class' => 'use_default_sort' ));
	}
	
	
	protected function get_page_size()
	{
		$page_size_session_var = $this->model_name."_".$this->page_size_session_var;
		
		$page_size = $this->session->userdata( $page_size_session_var );
		
		log_message_info( __METHOD__, "Session var = $page_size_session_var, current value = '$page_size'" );
		
		if (!$page_size)
			$page_size = self::INDEX_PAGE_SIZE;
		
		return $page_size;
	}
	
	
	/**
	 *
	 */
	
	protected function get_messages_from_request()
	{
		$data = array();
		
		$messages = array( self::ERROR_MESSAGE,
		                   self::NOTICE_MESSAGE );

		foreach( $messages as $one_message )
		{
			$data[ $one_message ] = $this->input->post( $one_message );
		}

		$msg = $this->input->get( 'msg' );
		$msg = explode( "|", $msg );
		
		switch( $msg[0] )
		{
			case 'added':
				$data[ self::NOTICE_MESSAGE ] = 'Requested item added.';
				break;

			case 'created':
				$record_name = extract_value( $msg,  1, 'record' );
				$data[ self::NOTICE_MESSAGE ] = "New $record_name created.";
				break;

			case 'destroyed':
				$data[ self::NOTICE_MESSAGE ] = 'Item has been deleted.';
				break;
				
			case 'saved':
				$data[ self::NOTICE_MESSAGE ] = 'Changes saved.';
				break;

			case 'guids-added':
				$data[ self::NOTICE_MESSAGE ] = 'All records should now have guids.';
				break;

			case 'password-changed':
				$data[ self::NOTICE_MESSAGE ] = 'Password has been changed.';
				break;

			case 'missing-guid':
				$data[ self::ERROR_MESSAGE ] = 'Player\'s guid is missing. <br> Do "Add missing guids" from the players list page, then come back and try again.';
				break;

			case 'no-editing-for-logs':
				$data[ self::ERROR_MESSAGE ] = 'Sorry, editing of log entries is not supported.';
				break;
			
			case 'no-invoice-data':
				$data[ self::ERROR_MESSAGE ] = 'No invoice generated. Exhibitor data not found or inaccessible.<br/>Please try again.';
				break;
			
			case 'not-added':
				$data[ self::ERROR_MESSAGE ] = 'Requested item could not be added. Please try again <br/>' . $data[ self::ERROR_MESSAGE ];
				break;

			case 'not-destroyed':
				$data[ self::ERROR_MESSAGE ] = 'Item could not be deleted. Please try again <br/>' . $data[ self::ERROR_MESSAGE ];
				break;

			case 'not-saved':
				$data[ self::ERROR_MESSAGE ] = 'Changes not saved. Please try again <br/>' . $data[ self::ERROR_MESSAGE ];;
				break;

			case 'guids-not-added':
				$data[ self::ERROR_MESSAGE ] = 'Guids could not be added. Please try again <br/>' . $data[ self::ERROR_MESSAGE ];
				break;

			case 'not-yet-implemented':
				$data[ self::ERROR_MESSAGE ] = 'This feature has not yet been implemented (perhaps because the schema remains undefined?)';
				break;

			default:
				break;
		}
		
		return $data;
	}
	
	
	protected function get_enviroment_string()
    {
    	return ENVIRONMENT;
    }
    
    
	private function get_enviroment_label()
    {
    	$label = ENVIRONMENT;
    	
    	if ($label === 'qa')
    		$label = 'QA (internal)';
    	elseif ($label === 'testing')
    		$label = 'testing (public)';
    		
    	return ucfirst( $label );
    }
    
    
    private function get_page_info( $page_name, $value )
    {
    	if (isset( $this->PAGE_INFO[$page_name],
    	           $this->PAGE_INFO[$page_name][$value] ))
    	{
    		return $this->PAGE_INFO[$page_name][$value];
    	}
    	else
    	{
    		return "";
    	}
    }
    
    
    private function get_page_loaded_at()
    {
    	return date("Y-m-d H:i:s T");
    }

    
	private function get_page_title( $view, $vars )
	{
		if ($view == "main_menu")
			return "";
    	
		$view = reset(explode( "/", $view ));
		$type = ucfirst(str_replace("_", " ", $this->router->method));

		$is_form = in_array( $view, array( 'billing_summary', 'email_list', 'mailing_list', 'onsite_list', 'tax_id_list' ));
		
		if (substr( $view,strlen($view)-6,6 ) == '_error')
			$view = $vars['original_view'];
		
		$type = str_replace( "json", "JSON", $type );
		
		if ($type == 'Index')
			$type = ($is_form) ? 'Download' : 'List';
		elseif ($type == 'Show')
			$type = 'Details';
		
		$section = $this->get_section_name( $view );
		
		return "$section :: $type";
    }
    
    
    protected function get_section_name( $view_name )
    {
		$section = $this->get_page_info( $view_name, self::TITLE );

		if (empty( $section ))
		{
			$segments = explode( "_", $view_name );
			$section  = "";
			
			foreach( $segments as $segment )
			{
				$section .= ucfirst( $segment ) . " ";
			}
		}
		
		return trim( $section );
    }
    
    
	protected static function get_controller_name( $append = "" )
	{
		$controller_name = reset( explode("/", ltrim(uri_string(),'/') ));
		
		return self::appended_url( $controller_name, $append );
	}
    	
	
    protected function get_view_name()
    {
    	$uri = explode( "/", uri_string() );
    	
    	return next($uri);
    }
    
    
    protected function get_absolute_file_path( $file_path )
    {
		return realpath(dirname( $file_path )) . "/" . basename( $file_path );
    }
   
    
	protected function get_db_adapter( $model_name )
	{
		return new ModelSchemaAdapter( $this->get_db_name(),
			                           $this->config->item( 'dk_host' ),
			                           $this->config->item( 'dk_port' ),
			                           $this->config->item( 'dk_user' ),
			                           $this->config->item( 'dk_pass' ) );
	}
	
	private function get_db_name()
	{
		return $this->config->item( 'dk_db' );
	}
	
	
	private function save_cancel_link()
	{
		$this->save_session_vars( self::CANCEL_URL,
		                          uri_string(),
		                          self::DK_NAMESPACE );
	}
	
	
	protected function get_cancel_link()
	{
		return $this->get_session_vars( self::CANCEL_URL, self::DK_NAMESPACE );
	}
	
	
	public static function get_logged_in_cms_user()
	{
		return 'SYSTEM';
	}
	
	
	public static function get_tax_rate( $details )
	{
		return self::NYC_TAX_RATE;
	}
	
	
	protected static function appended_url( $url, $append = "" )
	{
		return ( $append ) ? (rtrim( $url, "/" ) . "/$append")
		                   : $url;
	}
	
	
	/**
	 * SESSIONS
	 */
	
	protected function save_session_vars( $key_or_key_value_pairs, $value = "", $namespace = "" )
	{
		if (empty( $namespace ))
			$namespace = self::get_controller_name();
		
		if (is_array( $key_or_key_value_pairs ) && !empty( $key_or_key_value_pairs ))
		{
			foreach( $key_or_key_value_pairs as $one_key => $one_value)
				$this->save_session_vars( $one_key, $one_value, $namespace );
		}
		else
		{
			log_message_info( __METHOD__, $namespace."_".$key_or_key_value_pairs." => ".$value );
			$this->session->set_userdata( $namespace."_".$key_or_key_value_pairs, $value );
		}
	}
	
	
	protected function get_session_vars( $key_or_array_of_keys, $namespace = "" )
	{
		if (empty( $namespace ))
			$namespace = self::get_controller_name();
		
		if (is_array( $key_or_array_of_keys ) && !empty( $key_or_array_of_keys ))
		{
			$ret = array();
			
			foreach( $key_or_array_of_keys as $one_key )
				$ret[ $one_key ] = $this->get_session_vars( $one_key, $namespace );
		}
		else
		{
			$ret = $this->session->userdata( $namespace."_".$key_or_array_of_keys );
			log_message_info( __METHOD__, $namespace."_".$key_or_array_of_keys." => ".$ret );
		}
		
		return $ret;
	}
	
	
	/**
	 * UTILITY METHODS
	 */
	
	protected function get_show_details( $show_id = 0 )
	{
		if (isset( $this->show_details_list[ $show_id ] ))
		{
			log_message_info( __METHOD__, "Retrieving show $show_id from cached list" );
		}
		else
		{
			log_message_info( __METHOD__, "Looking up show $show_id from database..." );

			$Show = new Show( TRUE );
			
			$params = array( 'where'    => "show_id = '$show_id'",
			                 'max_rows' => 1 );
			
			$data = $Show->find( $params );
			
			if (is_array( $data ))
				$data = reset( $data );
			
			unset( $Show );
			
			$this->show_details_list[ $show_id ] = $data;
		
			log_message_debug( __METHOD__, $this->show_details_list[ $show_id ] );
		}
		
		return $this->show_details_list[ $show_id ];
	}
	
	
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

	
	/**
	 * @param Mixed $anything Anything at all!
	 * @since 2011-08-11
	 */
	protected static function force_into_array_recursive( $anything )
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
	
	
}

/* End of file DK_Controller.php */
/* Location: ./application/core/DK_Controller.php */
