<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Exhibitors controller
 *
 * @author Dennis Slade
 * @since  2012-02-20
 */


class Exhibitors extends DK_Controller
{
	const MODEL_ADDCHARGEFORM          = 'AddAdditionalChargeForm';
	const MODEL_ADDCHARGEFORM_SCHEMA   = 'AddAdditionalChargeFormSchema';
	const MODEL_ADDPAYMENTFORM         = 'AddPaymentForm';
	const MODEL_ADDPAYMENTFORM_SCHEMA  = 'AddPaymentFormSchema';
	const MODEL_ADDRENTITEMFORM        = 'AddRentItemForm';
	const MODEL_ADDRENTITEMFORM_SCHEMA = 'AddRentItemFormSchema';
	
	private $sub_models = array( 'StateProvince', 'Country', 'Contact', 'Show', 'ExhibitorItem',
	                             'ExhibitorFilter', 'AddAdditionalChargeForm', 'AddPaymentForm', 'AddRentItemForm' );
	
	
	public function __construct()
	{
		parent::__construct( 'Exhibitor', $this->sub_models );
		
		ini_set( 'max_execution_time', '0' );
		ini_set( 'memory_limit', '256M' );
		
		setlocale( LC_MONETARY, 'en_US' );
		
		$this->load->helper( 'dk_exhibitor_html' );
	
		$this->index_filter_name   = 'ExhibitorFilter';
		$this->set_minimum_permission( self::IS_CONTENT_MANAGER );
	}
	
	
	public function index( $skip = 0 )
	{
		return parent::index( $skip );
	}
	
	
	public function edit( $guid = "" )
	{
		return parent::edit( $guid );
	}
	
	
	public function destroy( $guid = "" )
	{
		return parent::destroy( $guid );
	}

	
	public function duplicate( $guid = "" )
	{
		return parent::duplicate( $guid );
	}
	
	
	public function sort( $field_name = "" )
	{
		return parent::sort( $field_name );
	}
	
	
	public function page_size( $page_size = "" )
	{
		return parent::page_size( $page_size );
	}
	
	
	public function filter( $filter_name = "", $value = "" )
	{
		return parent::filter( $filter_name, $value, TRUE );
	}
	
	
	public function reset_filter()
	{
		return parent::reset_filter( TRUE );
	}
	
	
	public function reset_sort()
	{
		return parent::reset_sort( TRUE );
	}
	
	
	/**
	 * SPECIAL METHODS FOR EXHIBITORS :: SHOW
	 */
	
	public function create( $contact_guid = "" )
	{
		$previous_input = array( 'contact_id' => $contact_guid );

		if (!empty( $contact_guid ))
		{
			$contact_id = $this->accessor_object->get_field( 'contact_id' );
			$descriptor = $contact_id->lookup_autocomplete_string( $contact_guid );
			
			if (!empty( $descriptor ))
			{
				$previous_input[ 'contact_id' ] = $descriptor;
				
				$Contact = new Contact();
				
				list( $values, $error ) = $this->safe_find_one( $Contact, array( 'columns'         => 'company',
				                                                                 self::PRIMARY_KEY => $contact_guid ) );
				
				if (isset( $values[ 'company' ] ))
				{
					$previous_input[ 'booth_trade_name' ] = extract_value( $values, 'company' );
				}
				
				unset( $Contact );
			}
		}
		
		return parent::create( $previous_input );
	}
	
	
	protected function create_one_to_manys( $ones_insert_id )
	{
		return FALSE;
	}
	
		
	public function show( $guid = "" )
	{
		$this->autocomplete_models = array( self::MODEL_ADDCHARGEFORM,
		                                    self::MODEL_ADDPAYMENTFORM );
		
		$this->data['previous_input'] = $this->input->post();
		$this->data['Guid']           = $guid;
		
		log_message_debug( __METHOD__, $this->data['previous_input'] );
		
		$AddRentItemForm = new AddRentItemForm( TRUE, self::get_db_adapter( self::MODEL_ADDRENTITEMFORM ));
		$this->data[ self::MODEL_ADDRENTITEMFORM_SCHEMA ] = $AddRentItemForm->get_schema();
		
		$AddAdditionalChargeForm = new AddAdditionalChargeForm( TRUE, self::get_db_adapter( self::MODEL_ADDCHARGEFORM ));
		$this->data[ self::MODEL_ADDCHARGEFORM_SCHEMA ] = $AddAdditionalChargeForm->get_schema();
		
		$AddPaymentForm = new AddPaymentForm( TRUE, self::get_db_adapter( self::MODEL_ADDPAYMENTFORM ));
		$this->data[ self::MODEL_ADDPAYMENTFORM_SCHEMA ] = $AddPaymentForm->get_schema();
		
		unset( $AddRentItemForm, $AddAdditionalChargeForm, $AddPaymentForm );
		
		if (isset( $this->data['previous_input'][ self::MODEL_ADDRENTITEMFORM ] ))
		{
			$this->data[ self::ERROR_MESSAGE ] = $this->add_rent_item();
				
			if (empty( $this->data[ self::ERROR_MESSAGE ] ))
				return self::redirect_to_action( self::ACTION_SHOW, $guid, array( 'msg' => 'added' ));
		}
		elseif (isset( $this->data['previous_input'][ self::MODEL_ADDCHARGEFORM ] ))
		{
			$this->data[ self::ERROR_MESSAGE ] = $this->add_additional_charge();
				
			if (empty( $this->data[ self::ERROR_MESSAGE ] ))
				return self::redirect_to_action( self::ACTION_SHOW, $guid, array( 'msg' => 'added' ));
		}
		elseif (isset( $this->data['previous_input'][ self::MODEL_ADDPAYMENTFORM ] ))
		{
			$this->data[ self::ERROR_MESSAGE ] = $this->add_payment();
				
			if (empty( $this->data[ self::ERROR_MESSAGE ] ))
				return self::redirect_to_action( self::ACTION_SHOW, $guid, array( 'msg' => 'added' ));
		}
		
		if ( $this->data[ self::ERROR_MESSAGE ] == self::ERROR_MESSAGE )
			$this->data[ self::ERROR_MESSAGE ] = FALSE;
		
		return parent::show( $guid );
	}
	
	
	private function add_additional_charge()
	{
		if (!isset( $this->data[ self::MODEL_ADDCHARGEFORM_SCHEMA ],
		            $this->data['previous_input'],
		            $this->data['previous_input'][ self::MODEL_ADDCHARGEFORM ] ))
			return FALSE;
		
		$data   = $this->data['previous_input'][ self::MODEL_ADDCHARGEFORM ];
		$fields = extract_value( $this->data[ self::MODEL_ADDCHARGEFORM_SCHEMA ],
		                         ModelSchema::model_2_index_fields( self::MODEL_ADDCHARGEFORM ));
		
		/**
		 * Conditional required field if the custom option is picked from the item dropdown
		 *
		 * @todo Encapsulate into a separate method
		 */
		if ($data['item'] == ModelSchemaField::CUSTOM_ADDITIONAL_CHARGE
		    && is_array( $fields )
		    && ($item_note_field = extract_value( $fields, 'item_note' ))
		    && method_exists( $item_note_field, 'validation_rules' ))
		{
			$item_note_field->validation_rules( 'trim|required' );
		}
		
		$this->set_validation_rules_from_schema( $this->data[ self::MODEL_ADDCHARGEFORM_SCHEMA ],
		                                         self::MODEL_ADDCHARGEFORM );
		
		if ($this->form_validation->run() == FALSE)
			return self::ERROR_MESSAGE;
		
		$insert_data = array
		(
			'exhibitor_id' => $this->data['Guid'],
			'unit_cost'    => $data['unit_cost'],
			'description'  => $data['item'],
			'notes'        => $data['item_note'],
			'size'         => $data['size'],
			'color'        => $data['color'],
			'quantity'     => $data['quantity'],
			'debit_type'   => 'Debit',
			'billing_type' => 'I',
			'created_at'   => ModelSchemaField::to_timestamp()
		);
		
		/**
		 * Insert the record and refresh the page if successful
		 */
		
		$accessor      = new ExhibitorItem( TRUE, self::get_db_adapter( 'ExhibitorItem' ));
		$error_message = $this->safe_insert( $accessor, $insert_data );
		
		unset( $accessor );
		
		return $error_message;
	}
	
	
	private function add_payment()
	{
		if (!isset( $this->data[ self::MODEL_ADDPAYMENTFORM_SCHEMA ],
		            $this->data['previous_input'],
		            $this->data['previous_input'][ self::MODEL_ADDPAYMENTFORM ] ))
			return FALSE;
		
		$data   = $this->data['previous_input'][ self::MODEL_ADDPAYMENTFORM ];
		$fields = extract_value( $this->data[ self::MODEL_ADDPAYMENTFORM_SCHEMA ],
		                         ModelSchema::model_2_index_fields( self::MODEL_ADDPAYMENTFORM ));
		
		/**
		 * Conditional required field if the custom option is picked from the item dropdown
		 *
		 * @todo Encapsulate into a separate method
		 */
		if ($data['method'] == ModelSchemaField::CUSTOM_PAYMENT_METHOD
		    && is_array( $fields )
		    && ($method_note_field = extract_value( $fields, 'method_note' ))
		    && method_exists( $method_note_field, 'validation_rules' ))
		{
			$method_note_field->validation_rules( 'trim|required' );
		}
		
		$this->set_validation_rules_from_schema( $this->data[ self::MODEL_ADDPAYMENTFORM_SCHEMA ],
		                                         self::MODEL_ADDPAYMENTFORM );
		
		if ($this->form_validation->run() == FALSE)
			return self::ERROR_MESSAGE;
		
		$data = $this->data['previous_input'][ self::MODEL_ADDPAYMENTFORM ];
		
		$insert_data = array
		(
			'exhibitor_id' => $this->data['Guid'],
			'unit_cost'    => $data['amount'],
			'quantity'     => 1,
			'debit_type'   => 'Credit',
			'billing_type' => 'B',
			'description'  => $data['method'],
			'notes'        => $data['method_note'],
			'created_at'   => $data['dateReceived']
		);
		
		/**
		 * Insert the record and refresh the page if successful
		 */
		
		$accessor      = new ExhibitorItem( TRUE, self::get_db_adapter( 'ExhibitorItem' ));
		$error_message = $this->safe_insert( $accessor, $insert_data );
		
		unset( $accessor );
		
		return $error_message;
	}
	
	
	private function add_rent_item()
	{
		if (!isset( $this->data[ self::MODEL_ADDRENTITEMFORM_SCHEMA ],
		            $this->data['previous_input'],
		            $this->data['previous_input'][ self::MODEL_ADDRENTITEMFORM ] ))
			return FALSE;
		
		$data   = $this->data['previous_input'][ self::MODEL_ADDRENTITEMFORM ];
		$fields = extract_value( $this->data[ self::MODEL_ADDRENTITEMFORM_SCHEMA ],
		                         ModelSchema::model_2_index_fields( self::MODEL_ADDRENTITEMFORM ));
		
		$this->add_conditional_required_fields( $fields,
		                                        $data,
		                                        array( 'description' => ModelSchemaField::CUSTOM_RENTAL_CHARGE ));
		
		$this->set_validation_rules_from_schema( $this->data[ self::MODEL_ADDRENTITEMFORM_SCHEMA ],
		                                         self::MODEL_ADDRENTITEMFORM );

		if ($this->form_validation->run() == FALSE)
			return self::ERROR_MESSAGE;

		$charge      = extract_value( $data, 'charge' );
		$description = extract_value( $data, 'description' );
		
		if (dk_exhibitor_html::is_negative_rental_charge_item( $description ))
			$charge = -$charge;
		
		$insert_data = array
		(
			'exhibitor_id' => $this->data['Guid'],
			'unit_cost'    => $charge,
			'quantity'     => 1,
			'debit_type'   => 'Debit',
			'billing_type' => 'R',
			'description'  => $description,
			'notes'        => $data['description_note'],
			'created_at'   => $data['dateDue']
		);
		
		/**
		 * Insert the record and refresh the page if successful
		 */
		
		$accessor      = new ExhibitorItem( TRUE, self::get_db_adapter( 'ExhibitorItem' ));
		$error_message = $this->safe_insert( $accessor, $insert_data );
		
		unset( $accessor );
		
		return $error_message;
	}
	
	
	private function add_conditional_required_fields( $fields, $data, $rules = array())
	{
		if (empty( $rules ) || !is_array( $rules ))
			return;
		
		foreach ($rules as $test_field => $test_field_value)
		{
			$test_field = explode( ';', "$test_field" );
			$note_field = extract_value( $test_field, 1, "{$test_field}_note" );
			$test_field = $test_field[0];
			
			if (extract_value( $data, $test_field ) == $test_field_value
			    && is_array( $fields )
			    && ($note_field_object = extract_value( $fields, $note_field ))
			    && method_exists( $note_field_object, 'validation_rules' ))
			{
				$note_field_object->validation_rules( 'trim|required' );
			}
		}
	}
	
	
	public function delete_item( $item_id, $exhibitor_id )
	{
		if ($this->preflight_check() === FALSE)
			return;
		
		if (empty( $item_id ))
			return self::redirect_to_action( self::ACTION_SHOW, $exhibitor_id );
		
		$accessor      = new ExhibitorItem( TRUE, self::get_db_adapter( 'ExhibitorItem' ));
		$error_message = $this->safe_destroy( $accessor, $item_id );
		
		unset( $accessor );
		
		if (empty( $error_message ))
			return self::redirect_to_action( self::ACTION_SHOW, $exhibitor_id, array('msg'=>'destroyed') );
		else
			return self::redirect_to_action( self::ACTION_SHOW, $exhibitor_id, array('msg'=>'not-destroyed') );
	}
	
	
	/**
	 * INVOICE METHODS
	 */
	
	/**
	 * Generates ONE invoice based on an exhibitor id
	 */
	public function invoice( $exhibitor_id, $preview = "" )
	{
		ini_set( 'memory_limit', '1024M' );
		
		if (!parent::get_data( $exhibitor_id ))
			self::redirect_to_action( self::ACTION_SHOW, $exhibitor_id, array( 'msg' => 'no-invoice-data' ));
				
		$html = $this->get_invoice_html();
		
		/**
		 * Is this an HTML preview?
		 */
		if ($preview)
		{
			echo $html;
		}
		else
		{
			/**
			 * First send the headers...
			 */
			$this->send_headers_for_file_download( 'application/pdf' );

			/**
			 * Then create the PDF and send it
			 */
			$this->load->helper( 'dompdf/dompdf' );
	
			echo pdf_create( $html );
			exit;
		}
	}
	
	
	/**
	 * Generates MULTIPLE invoices based on the filtered list of exhibitors
	 */
	public function invoices( $skip = 0, $preview = "" )
	{
		ini_set( 'max_execution_time', '0' );
		ini_set( 'memory_limit', '1024M' );
				
		parent::get_data_filtered( $skip );

		$html = $this->get_invoice_html();
		
		if ($preview)
		{
			echo $html;
		}
		else
		{
			/**
			 * First send the headers...
			 */
			$this->send_headers_for_file_download( 'application/pdf' );

			/**
			 * Then create the PDF and send it
			 */
			$this->load->helper( 'dompdf/dompdf' );
	
			echo pdf_create( $html );
			exit;
		}
	}
	
	
	private function get_invoice_html()
	{
		$this->load->helper( 'dk_invoice' );
		
		$invoice = new dk_invoice_helper();
		
		$table_class   = 'listTable';
		$do_page_break = FALSE;
		$body          = "";
		
		foreach( $this->data[ $this->model_name ] as $row )
		{
			$show_details = $this->get_show_details( $row['show_id'] );
			
			if ($do_page_break)
				$body .= table_open( "$table_class break-before" );
			else
				$body .= table_open( $table_class );
			
			$body .= tr( td( $invoice->header_html( $show_details )));
			$body .= tr( td( $invoice->dealer_info_html( $row, $show_details )));
			$body .= tr( td( $invoice->booth_rent_html( $row, $show_details )));
			$body .= tr( td( $invoice->additional_taxables_html( $row, $show_details, $this->get_tax_rate( $row ) )));
			$body .= tr( td( $invoice->account_balance_html( $row, $show_details )));
			$body .= tr( td( $invoice->amount_due_now_html( $row )));
			$body .= tr( td( $invoice->how_to_pay_html( $show_details )));
			$body .= table_close();
	
			$do_page_break = TRUE;
		}
		
		unset( $invoice );
		
		$html = "<html><head>".css_asset('invoice.css')."</head>\n"
		      . "<body>\n"
		      . $body
		      . "</body></html>";
		
		return $html;
	}
	
	
	protected function get_download_filename()
	{
		$ret = "Invoices";
		
		if (!empty( $this->data[ $this->model_name ] )
		    && is_array( $this->data[ $this->model_name ] ))
		{
			$data = reset( $this->data[ $this->model_name ] );
			
			if (is_string( $data ))
			{
				$ret = "Invoices for $data";
			}
			else
			{
				/**
				 * Do we have a show name?
				 */
				if ($show_id = extract_value( $data, 'show_id' ))
				{
					$show_details = $this->get_show_details( $show_id );
					$show_name    = extract_value( $show_details, 'short_name' );
					
					$show_name = (empty( $show_name ))
					                ? "Show $show_id - "
					                : "$show_name - ";
				}
				else
				{
					$show_name = "";
				}
				
				/**
				 * Determine the middle "description" part
				 */
				if (count( $this->data[ $this->model_name ] ) != 1)
				{
					$middle_part = "Invoices";
				}
				else if ($booth_trade_name = extract_value( $data, 'booth_trade_name' ))
				{
					$middle_part = $booth_trade_name;
				}
				else
				{
					$middle_part = "Invoice for " . extract_value( $data, 'exhibitor_id' );
				}
				
				$ret = $show_name . $middle_part;
			}
		}
		
		return $ret . date(' (Y-m-d)');
	}
	
	
	/**
	 * AUTOCOMPLETE METHODS
	 */
	
	public function autocomplete_filter_booth_trade_name( $reverse_lookup = "" )
	{
		return $this->autocomplete_booth_trade_name( $reverse_lookup );
	}
	
	
	public function autocomplete_filter_contact_id( $reverse_lookup = "" )
	{
		return $this->autocomplete_contact_id( $reverse_lookup );
	}
	
	
	public function autocomplete_booth_trade_name( $reverse_lookup = "" )
	{
		return parent::autocomplete( $this->input->post( 'term' ),
		                             'Contact',
		                             'DISTINCT(company)',
		                             'company',
		                             $reverse_lookup );
	}
	
	
	public function autocomplete_contact_id( $reverse_lookup = "" )
	{
		return parent::autocomplete( $this->input->post( 'term' ),
		                             'Contact',
		                             "CONCAT(last_name,', ',first_name,' (',company,' ',CONVERT(contact_id,CHAR),')')",
		                             'contact_id',
		                             $reverse_lookup );
	}
	
	
	public function autocomplete_description_note( $reverse_lookup = "" )
	{
		return parent::autocomplete( $this->input->post( 'term' ),
		                             'ExhibitorItem',
		                             'DISTINCT(notes)',
		                             'notes',
		                             $reverse_lookup );
	}
	
	
	public function autocomplete_item_note( $reverse_lookup = "" )
	{
		return parent::autocomplete( $this->input->post( 'term' ),
		                             'ExhibitorItem',
		                             'DISTINCT(notes)',
		                             'notes',
		                             $reverse_lookup );
	}
	
	
	public function autocomplete_method_note( $reverse_lookup = "" )
	{
		return parent::autocomplete( $this->input->post( 'term' ),
		                             'ExhibitorItem',
		                             'DISTINCT(notes)',
		                             'notes',
		                             $reverse_lookup );
	}
	
}

/* End of file exhibitors.php */
/* Location: ./event/controllers/exhibitors.php */
