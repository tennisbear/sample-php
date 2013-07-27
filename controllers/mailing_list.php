<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Mailing list controller
 *
 * @author Dennis Slade
 * @since  2012-06-07
 */


class Mailing_list extends DK_Controller
{
	const MODEL_MAILINGLISTFORM = 'MailingListForm';
	const ROWS_PER_PAGE         = 28;
	
	private $sub_models = array( 'StateProvince', 'Country', 'Show', 'Contact', 'ExhibitorItem',
	                             'AdvertiserItem', 'Advertiser',
	                             self::MODEL_MAILINGLISTFORM );
	private $format_as   = NULL;
	private $report_type = NULL;
	private $show        = NULL;
	private $sort_by     = NULL;
	
	
	public function __construct()
	{
		parent::__construct( 'Exhibitor', $this->sub_models );
		
		ini_set( 'max_execution_time', '0' );
		ini_set( 'memory_limit', '256M' );
		
		setlocale( LC_MONETARY, 'en_US' );
		
		$this->load->helper( 'dk_mailing_list' );
	
		$this->set_minimum_permission( self::IS_CONTENT_MANAGER );
		
		log_message_info( __METHOD__, 'end' );
	}
	
	
	public function index()
	{
		log_message_info( __METHOD__, 'begin' );

		ini_set( 'memory_limit', '256M' );
		
		$this->set_schema_from_model( self::MODEL_MAILINGLISTFORM );
		
		$this->data['previous_input'] = $this->input->post();
		
		if (!empty( $this->data['previous_input'] ))
		{
			if ($this->form_validation->run() == FALSE)
			{
				$this->data[ self::ERROR_MESSAGE ] = 'There were errors in your submission. Please go back and correct: <br>';
				
				return $this->load_view( $this->data, self::ERROR_TEMPLATE );
			}
			elseif (isset( $this->data['previous_input'][ self::MODEL_MAILINGLISTFORM ] ))
			{
				$input = $this->data['previous_input'][ self::MODEL_MAILINGLISTFORM ];
				
				$this->show        = extract_value( $input, 'show' );
				$this->report_type = extract_value( $input, 'report_type' );
				$this->sort_by     = extract_value( $input, 'sort_by' );
				$this->format_as   = extract_value( $input, 'format_as' );
				
				if ($this->report_type == dk_mailing_list_helper::ADVERTISERS_MAILING_LIST)
				{
					if ($this->sort_by == 'booth_trade_name')
					    $this->sort_by = 'trade_name';
				}
				
				$this->data[ self::ERROR_MESSAGE ] = $this->generate();
				
				if ($this->data[ self::ERROR_MESSAGE ])
					return $this->load_view( $this->data, self::ERROR_TEMPLATE );
				else
					return;
			}
		}

		/**
		 * Show the form
		 */
		
		if ($this->data[ self::ERROR_MESSAGE ] == self::ERROR_MESSAGE)
			$this->data[ self::ERROR_MESSAGE ] = FALSE;
		
		$this->load_view( $this->data, __FUNCTION__ );
	}
	
	
	/**
	 * Generate mailing list based on the filtered list of exhibitors
	 */
	public function generate( $show_id = "", $report_type = "", $preview = "", $format_as = "", $sort_by = "" )
	{
		ini_set( 'memory_limit', '1024M' );
		
		$error_message = FALSE;
		
		if (!empty( $show_id ))
			$this->show = $show_id;
		
		if (!empty( $report_type ))
			$this->report_type = $report_type;
		
		if (!empty( $format_as ))
			$this->format_as = $format_as;
		
		if (!empty( $sort_by ))
			$this->sort_by = $sort_by;
		
		$this->get_data();
		
		switch ($this->format_as)
		{
			case 'csv':
				if (empty( $this->data[ $this->model_name ] ))
				{
					$error_message = 'No records found for the specified show. No report generated.';
				}
				else
				{
					$csv = $this->get_mailing_list_csv( !empty($preview) );
					
					if (!$preview)
						$this->send_headers_for_file_download( 'text/csv' );
					
					echo $csv;
					exit;
				}
				
				break;
			
			default:
				if (empty( $this->data[ $this->model_name ] ))
				{
					$this->data[ $this->model_name ] = array
					(
						array( 'show_id'                                => $this->show,
						       dk_mailing_list_helper::NO_RECORDS_FOUND => TRUE )
					);
				}
		
				$html = $this->get_mailing_list_html( !empty($preview) );
				
				if ($preview)
				{
					echo $html;
				}
				else
				{
					/**
					 * Create the PDF and send it
					 */
					
					$this->send_headers_for_file_download( 'application/pdf' );
		
					$this->load->helper( 'dompdf/dompdf' );
			
					echo pdf_create_landscape( $html );
					exit;
				}
				
				break;
		}
		
		return $error_message;
	}
	
	
	private function get_mailing_list_csv( $in_preview_mode = FALSE )
	{
		return ($this->is_advertisers_report_type())
		           ? $this->get_advertisers_csv( $in_preview_mode )
		           : $this->get_exhibitors_csv( $in_preview_mode );
	}
	
	
	private function get_advertisers_csv( $in_preview_mode = FALSE )
	{
		$csv = array( dk_mailing_list_helper::excel_csv( 'Alpha', 'Advertiser', 'Last Name', 'First Name',
		                                                 'Address', 'City', 'State', 'Zip', 'Country',
		                                                 'Work Phone', 'Fax', 'Mobile Phone',
		                                                 'Email', 'Website'  ));

		foreach( $this->data[ $this->model_name ] as $advertiser )
		{
			$csv[] = dk_mailing_list_helper::excel_csv
			         (
			             $advertiser['alpha'],
			             $advertiser['trade_name'],
			             $advertiser['last_name'],
			             $advertiser['first_name'],
			             $advertiser['address'],
			             $advertiser['city'],
			             $advertiser['state'],
			             $advertiser['postal_code'],
			             $advertiser['country'],
			             $advertiser['work_phone'],
			             $advertiser['work_fax'],
			             $advertiser['mobile_phone'],
			             $advertiser['email'],
			             $advertiser['website']
			         );
		}
				
		return implode( "\n", $csv );
	}
	
	
	private function get_exhibitors_csv( $in_preview_mode = FALSE )
	{
		$csv = array( dk_mailing_list_helper::excel_csv( 'Exhibitor', 'Alpha', 'Last Name', 'First Name',
		                                                 'Address', 'City', 'State', 'Zip', 'Country',
		                                                 'Work Phone', 'Fax', 'Mobile Phone',
		                                                 'Email', 'Website'  ));

		foreach( $this->data[ $this->model_name ] as $exhibitor )
		{
			$csv[] = dk_mailing_list_helper::excel_csv
			         (
			             $exhibitor['booth_trade_name'],
			             $exhibitor['alpha'],
			             $exhibitor['last_name'],
			             $exhibitor['first_name'],
			             $exhibitor['address'],
			             $exhibitor['city'],
			             $exhibitor['state'],
			             $exhibitor['postal_code'],
			             $exhibitor['country'],
			             $exhibitor['work_phone'],
			             $exhibitor['work_fax'],
			             $exhibitor['mobile_phone'],
			             $exhibitor['email'],
			             $exhibitor['website']
			         );
		}
				
		return implode( "\n", $csv );
	}
	
	
	private function get_mailing_list_html( $in_preview_mode = FALSE )
	{
		$first_exhibitor = reset( $this->data[ $this->model_name ] );
		 
		$mailing_list  = new dk_mailing_list_helper( $this->report_type );
		$show_details = $this->get_show_details( $first_exhibitor['show_id'] );
		$table_class  = 'mailing-list';
		$row_count    = 0;
		
		$body = table_open( $table_class )
		      . $mailing_list->page_header_html( $show_details )
		      . $mailing_list->header_html( $show_details );
		
		foreach( $this->data[ $this->model_name ] as $row )
		{
			list( $row_html,
			      $rows_used ) = $mailing_list->row_html( $row );
			
			if (!$in_preview_mode
			    && (($row_count + $rows_used)) > self::ROWS_PER_PAGE)
			{
				$body .= table_close()
				       . table_open( "$table_class break-before" )
				       . $mailing_list->page_header_html( $show_details )
				       . $mailing_list->header_html( $show_details );
				
				$row_count = 0;
			}
			
			$body .= $row_html;
			
			$row_count += $rows_used;
		}
		
		$body .= table_close();
			
		unset( $mailing_list );
		
		$html = "<html><head>".css_asset('mailing_list.css')."</head>\n"
		      . "<body>\n"
		      . $body
		      . "</body></html>";
		
		return $html;
	}
	
	
	protected function get_data()
	{
		log_message_info( __METHOD__, "Show = {$this->show}" );
		log_message_info( __METHOD__, "Report type = {$this->report_type}" );
		log_message_info( __METHOD__, "Format as = {$this->format_as}" );
		log_message_info( __METHOD__, "Sort by = {$this->sort_by}" );
		
		if ($this->is_advertisers_report_type())
			$this->accessor_object = new Advertiser();
		
		return parent::get_data( $this->get_ids_for_show(), $this->sort_by );
	}
	
	
	private function get_ids_for_show()
	{
		return $this->get_ids( "show_id = '{$this->show}'" );
	}
	
	
	private function get_ids( $where = "", $model_name = "", $id_column_name = "" )
	{
		$ret = array();
		
		$Accessor = (empty( $model_name )) ? $this->accessor_object
		                                   : new $model_name();
		
		if (!empty( $Accessor ))
		{
			if (empty( $id_column_name ))
				$id_column_name = $Accessor->get_primary_key();
			
			if (empty( $where ))
				$where = "1";
			
			$params = array( 'columns'  => $id_column_name,
			                 'where'    => $where,
			                 'orderby'  => $id_column_name,
			                 'max_rows' => "0" );
			
			$list = $Accessor->find( $params );
			
			if ($list && count( $list ) > 0)
			{
				foreach( $list as $item )
					$ret[] = $item[ $id_column_name ];
			}
		}
		
		unset( $Accessor );
		
		return $ret;
	}
	
	
	protected function get_download_filename()
	{
		$ret = "Mailing List for ";
		
		if ($this->is_advertisers_report_type())
			$ret = "Advertiser $ret";
		
		if (!empty( $this->data[ $this->model_name ] )
		    && is_array( $this->data[ $this->model_name ] ))
		{
			$data = reset( $this->data[ $this->model_name ] );
			
			if (is_string( $data ))
			{
				$ret .= $data;
			}
			else
			{
				$show_id = (empty( $this->show )) ? extract_value( $data, 'show_id' )
				                                  : $this->show;
				
				/**
				 * Do we have a show name?
				 */
				if ($show_id)
				{
					$show_details = $this->get_show_details( $show_id );
					$show_name    = extract_value( $show_details, 'short_name' );
				}
				
				if (empty( $show_name ))
					$show_name = "Show $show_id";
				
				$ret .= $show_name;
			}
		}
		
		return $ret . date(' (Y-m-d)');
	}
	
	
	public function reset_filter()
	{
		return parent::reset_filter( TRUE );
	}
	
	
	private function is_advertisers_report_type()
	{
		return ($this->report_type == dk_mailing_list_helper::ADVERTISERS_MAILING_LIST);
	}
	
}

/* End of file mailing_list.php */
/* Location: ./application/controllers/mailing_list.php */
