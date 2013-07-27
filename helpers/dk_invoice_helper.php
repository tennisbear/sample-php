<?php
/**
 * Invoice helper functions for use in views and/or controllers
 *
 * @author Dennis Slade
 * @since  2011-03-01
 */


class dk_invoice_helper
{
	const ITEMS_KEY    = 'ExhibitorItem';
	
	private $account_balance           = 0;
	private $total_additional_taxables = 0;
	private $total_booth_rent          = 0;
	private $total_booth_rent_due      = 0;
	private $total_charges             = 0;
	private $total_payments            = 0;
	
	static $settings = NULL;
	
	
	public function __construct()
	{
	}
	
	
	public function header_html( $show_details )
	{
		$date = date( 'j F Y' );
				
		// "<img align=\"left\" src=\"http://sanfordsmith.net/safia/assets/image/header2_grey.gif\">"

		$title = (self::is_ifpda_show( $show_details ))
		            ? 'Booth Extras Invoice'
		            : 'Exhibitor Invoice';
		
		$cells = th( "<h1>{$show_details['full_name']}</h1>" )
		       . th( "&nbsp;" )
		       . th( "<h2>$title</h2>Invoice Date: $date" );
		
		if (self::is_adaa_show( $show_details ))
			$cells = td( self::get_address( $show_details, TRUE )) . $cells;
		
		$html = table_open()
		      . tr( $cells )
		      . table_close();
		
		return div( $html, 'title-header' );
	}
	
	
	public function dealer_info_html( $exhibitor_details, $show_details )
	{
		$address     = nl2br( $this->address_html( $exhibitor_details ) );
		$booth_info  = nl2br( $this->booth_info_html( $exhibitor_details ) );
		$phone_email = nl2br( $this->phone_email_html( $exhibitor_details ) );
		
		if (!self::is_adaa_show( $show_details ))
			$phone_email .= div( "<h2>THANK YOU!</h2>", 'thank-you' );
		
		$html = table_open()
		      . tr( td( $address . $booth_info )
		            . td( $phone_email ))
		      . table_close();
		
		return $html;
	}
	
	
	public function address_html( $exhibitor_details )
	{
		static $us_style = array( 'USA', 'Australia' );
		static $uk_style = array( 'England', 'Great Britain', 'Ireland', 'Northern Ireland',
		                          'Scotland', 'United Kingdom', 'Wales' );
		
		$country = $exhibitor_details['country'];
		$name    = $exhibitor_details['first_name'] . ' ' . $exhibitor_details['last_name'];
		
		$address[] = $exhibitor_details['booth_trade_name'];
		
		if ($name != $exhibitor_details['booth_trade_name'])
			$address[] = $name;
		
		$address[] = $exhibitor_details['address'];

		if (empty( $country ) || in_array( $country, $us_style ))
		{
			$next = $exhibitor_details['city'];
			
			if (!empty( $exhibitor_details['state'] ))
				$next .= ", " . $exhibitor_details['state'];
			
			$next .= " " . $exhibitor_details['postal_code'];
			
			$address[] = $next;
		}
		elseif (in_array( $country, $uk_style ))
		{
			$address[] = strtoupper( $exhibitor_details['city'] );
			
			/**
			 * County would end up in the State field
			 */
			if (!empty( $exhibitor_details['state'] ))
				$address[] = strtoupper( $exhibitor_details['state'] );
			
			$address[] = strtoupper( $exhibitor_details['postal_code'] );
		}
		else
		{
			$address[] = strtoupper( $exhibitor_details['postal_code'] )
			           . " "
			           . $exhibitor_details['city'];
	
			if (!empty( $exhibitor_details['state'] ))
				$address[] = strtoupper( $exhibitor_details['state'] );
		}
		
		if (!empty( $country ) && ($country != 'USA'))
			$address[] = strtoupper( $country );
		
		return implode( "\n", $address );
	}
	
	
	public function booth_info_html( $exhibitor_details )
	{
		$booth_info[] = "Booth Size: " . $exhibitor_details['booth_size'];
		$booth_info[] = "Booth Number: " . $exhibitor_details['booth_number'];
		
		$booth_info = "\n\n" . implode( "\n", $booth_info );
		
		return $booth_info;
	}
	
	
	public function phone_email_html( $exhibitor_details )
	{
		$phone_email = array();
		
		if (!empty( $exhibitor_details['work_phone'] ))
			$phone_email[] = "T: " . $exhibitor_details['work_phone'];
		
		if (!empty( $exhibitor_details['work_fax'] ))
			$phone_email[] = "F: " . $exhibitor_details['work_fax'];
				
		if (!empty( $exhibitor_details['mobile_phone'] ))
			$phone_email[] = "M: " . $exhibitor_details['mobile_phone'];
				
		if (!empty( $exhibitor_details['email'] ))
			$phone_email[] = "\nE: " . $exhibitor_details['email'];
		
		$phone_email = implode( "\n", $phone_email );
		
		return $phone_email;
	}
	
	
	public function booth_rent_html( $exhibitor_details, $show_details )
	{
		setlocale( LC_MONETARY, 'en_US' );
		
		$this->total_booth_rent     = 0;
		$this->total_booth_rent_due = 0;

		/**
		 * Don't include this section for IFPDA Print Fair and ADAA shows
		 */
		if (self::is_ifpda_show( $show_details ) || self::is_adaa_show( $show_details ))
			return "";
		
		
		$rent_table = table_open()
		            . tr( th( 'Due Date', 'description-list' )
		                  . th( 'Description', 'description-list' )
		                  . th( 'Charge', 'description-list-charge-total' ) );
		
		$items = extract_value( $exhibitor_details, 'ExhibitorItem', FALSE );

		if (!empty( $items ) && is_array( $items ))
		{
			foreach( $items as $item )
			{
				$debit_type   = extract_value( $item, 'debit_type' );
				$billing_type = extract_value( $item, 'billing_type' );
				
				/**
				 * Only Debit R(ent) items are being processed here
				 */
				if ($debit_type != 'Debit' || $billing_type != 'R' )
					continue;
				
				$unit_cost = extract_value( $item, 'unit_cost' );
				$quantity  = extract_value( $item, 'quantity' );
				$charge    = $unit_cost * $quantity;
				
				/**
				 * Don't process if charge is zero. (A negative charge is a discount or coupon.)
				 */
				if (!$charge)
					continue;
					
				$this->total_booth_rent += $charge;
				
				/**
				 * Display this rent entry in the invoice
				 */
				
				$description = extract_value( $item, 'description' );
				$notes       = extract_value( $item, 'notes' );
				
				if ($description == ModelSchemaField::CUSTOM_RENTAL_CHARGE)
					$description = (empty( $notes )) ? ModelSchemaField::CUSTOM_RENTAL_CHARGE_MISSING : " $notes";
				else
					$description .= " $notes";
				
				$date = extract_value( $item, 'created_at' );
				
				if (empty( $date ))
				{
					$date        = time();
					$date_string = "";
				}
				else
				{
					$date        = strtotime( $date );
					$date_string = strftime( '%e-%b-%Y', $date );
				}
				
				$rent_table .= tr
				(
					td( $date_string )
					. td( $description )
					. td_amount( $charge )
				);
				
				/**
				 * Is the rent charge due now?
				 */
				
				$flex_days = extract_value( $show_details, 'invoice_flex_days' );
				
				if (empty( $flex_days ) || !is_numeric( $flex_days ))
					$flex_days = 0;
				
				if (strtotime( "+$flex_days days" ) >= $date)
					$this->total_booth_rent_due += $charge;
			}
		}
		
		$total_booth_rent_string = table_open()
		                         . tr
		                           (
		                               td( "<span class=\"subtotal\">Total Booth Rent:</span>" )
		                               . td_subtotal( $this->total_booth_rent )
		                           )
		                         . table_close();
		
		$total_booth_rent_string = div( $total_booth_rent_string, array( 'class' => 'booth-rent' ));
				
		$rent_table .= tr( th( $total_booth_rent_string,
		                       array( 'class'   => 'subtotal booth-rent',
		                              'colspan' => '3' ) ));
		$rent_table .= table_close();
		
		
		$html  = '<hr>'
		       . table_open()
		       . tr( th( 'Booth Rental &<br>Non-Taxable<br>Charges:', array( 'class' => 'left-column-label' ) )
		             . td( $rent_table ) )
		       . table_close();
		
		return $html;
	}
	
	
	public function additional_taxables_html( $exhibitor_details, $show_details, $tax_rate )
	{
		$is_adaa_show = self::is_adaa_show( $show_details );
		
		$this->total_additional_taxables = 0;
		
		$taxables_table = "";
		
		$taxables_table .= table_open()
		                 . tr( th( 'Description', 'description-list' )
		                       . th( 'Qty', 'description-list' )
		                       . th( 'Unit Cost', 'description-list' )
		                       . th( 'Total', 'description-list-charge-total' ) );
		
		$items = extract_value( $exhibitor_details, 'ExhibitorItem', FALSE );

		if (!empty( $items ) && is_array( $items ))
		{
			foreach( $items as $item )
			{
				$debit_type   = extract_value( $item, 'debit_type' );
				$billing_type = extract_value( $item, 'billing_type' );
				
				if ($debit_type != 'Debit')
					continue;
				if ($billing_type != 'I' && $billing_type != 'R')
					continue;
				if (!$is_adaa_show && $billing_type == 'R')
					continue;
				
				$unit_cost = extract_value( $item, 'unit_cost' );
				$quantity  = extract_value( $item, 'quantity' );
				$charge    = $unit_cost * $quantity;
				
				if ($charge < 0)
					continue;
				
				$description = extract_value( $item, 'description' );
				$notes       = extract_value( $item, 'notes' );
				$size        = extract_value( $item, 'size' );
				$color       = extract_value( $item, 'color' );
				
				if ($description == ModelSchemaField::CUSTOM_ADDITIONAL_CHARGE)
					$description = (empty( $notes )) ? ModelSchemaField::CUSTOM_CHARGE_MISSING : " $notes";
				else
					$description .= " $notes";
				
				if ($size)
					$description = trim( $description ) . ", $size";
					
				if ($color)
					$description = trim( $description ) . " [$color]";
					
				if ($quantity == floor( $quantity ))
					$quantity = floor( $quantity );
				
				$taxables_table .= tr
				(
					td( $description )
					. td( $quantity )
					. td_amount( $unit_cost )
					. td_amount( $charge )
				);
				
				$this->total_additional_taxables += $charge;
			}
		}
		
		if ($is_adaa_show)
		{
			$taxables_table .= table_close();
			$section_title  = "";
			$table_class    = "adaa";
		}
		else
		{
			$subtotal = $this->total_additional_taxables;
			$tax      = $subtotal * $tax_rate;
			
			$this->total_additional_taxables += $tax;
			
			$attributes = array( 'class' => 'section-subtotal', 'colspan' => '3' );
			
			$taxables_table .= tr( th( "Additional Charges Subtotal", $attributes )
			                       . td_amount( $subtotal ))
			                 . tr( th( "+ New York Sales Tax", $attributes )
			                       . td_amount( $tax ));
			
			$taxables_table .= table_close();
			
			$total_additional_html = table_open()
			                         . tr
			                           (
			                               td( "<span class=\"subtotal\"> = Total Additional Charges:</span>" )
			                               . td_subtotal( $this->total_additional_taxables )
			                           )
			                         . table_close();
			
			$taxables_table .= div( $total_additional_html, 'additional-taxables' );
					
			$section_title = (self::is_ifpda_show( $show_details ))
			                    ? 'Booth<br>Extras:'
			                    : 'Additional<br>Taxable<br>Charges:';
			
			$section_title = th( $section_title, 'left-column-label' );
			
			$table_class = "";
		}
		
		$html = '<hr>'
		      . table_open( $table_class )
		      . tr( $section_title
		            . td( $taxables_table ) )
		      . table_close();
		
		return $html;
	}
	
	
    public function account_balance_html( $exhibitor_details, $show_details )
	{
		$this->total_payments = 0;
		
		$payments_table = "";
		
		$items = extract_value( $exhibitor_details, 'ExhibitorItem', FALSE );

		if (!empty( $items ) && is_array( $items ))
		{
			foreach( $items as $item )
			{
				$debit_type   = extract_value( $item, 'debit_type' );
				$billing_type = extract_value( $item, 'billing_type' );
				
				if ($debit_type != 'Credit' || $billing_type != 'B' )
					continue;
				
				$date    = extract_value( $item, 'created_at' );
				$method  = extract_value( $item, 'description' );
				$notes   = extract_value( $item, 'notes' );
				$payment = extract_value( $item, 'unit_cost' );
				
				$date = empty( $date ) ? ""
				                       : strftime( '%e-%b-%Y', strtotime( $date ));
				
				if ($method == ModelSchemaField::CUSTOM_PAYMENT_METHOD)
					$method = (empty( $notes )) ? ModelSchemaField::CUSTOM_PAYMENT_MISSING : " $notes";
				else
					$method .= " $notes";
				
				$payments_table .= tr( td( $date )
				                       . td( $method )
				                       . td_amount( $payment ) );
				
				$this->total_payments += $payment;
			}
		}
		
		$this->total_charges   = $this->total_booth_rent + $this->total_additional_taxables;
		$this->account_balance = $this->total_charges - $this->total_payments;
		
		$table_class = "";
		
		if (self::is_adaa_show( $show_details ))
		{
			/**
			 * Special handling for ADAA shows
			 */
			$payments_table = table_open()
			                . tr( td( 'Total Charges:' )
			                      . td_subtotal( $this->total_charges ) )
					        . tr( td( '&nbsp;', '&nbsp;' ) )
					        . tr( td( 'Account Balance:', 'adaa-account-balance' )
			                      . td_subtotal( $this->account_balance, 'adaa-account-balance' ) )
			                . table_close();
			
			$table_class = "adaa-total-balance";
		}
		elseif (self::is_ifpda_show( $show_details ))
		{
			/**
			 * Special handling for IFPDA Print Fair shows
			 */
			$payments_table = table_open()
			                . tr( td( 'Total Additional Charges:' )
			                      . td_subtotal( $this->total_charges ) )
					        . tr( th( 'To pay by phone, please call (212) 674-6095',
					                  array( 'class'   => 'pay-by-phone',
					                         'colspan' => '2'  ) ))
			                . table_close();
		}
		else
		{
			$payments_table = table_open()
			                . $payments_table
			                . tr( td( 'Total Payments Received:', array( 'colspan' => '2' ))
			                      . td_subtotal( $this->total_payments ) )
			                . table_close();
			
			$payments_table = table_open()
			                . tr( th( '<h3>Payments Received:</h3>' )
			                      . td( $payments_table ) )
			                . table_close();
			
			$payments_table = table_open()
			                . tr( td( 'Total Booth Rent & Additional Charges:' )
			                      . td_subtotal( $this->total_charges ) )
					        . tr( td( $payments_table, array( 'colspan' => '2' ) ))
					        . tr( td( 'Account Balance:' )
			                      . td_subtotal( $this->account_balance ) )
			                . table_close();
		}
		
		$pre_address = (self::is_adaa_show( $show_details ))
		                  ? div( "Please make checks payable to", 'adaa-payable-to' )
		                  : div( "<h2>THANK YOU!</h2>", 'thank-you' );
		
		$address = $pre_address
		         . div( "<div class=\"sls-address\">"
		                . self::get_address( $show_details )
		                . "</div>" );
		
		$html  = '<hr>'
		       . table_open( $table_class )
		       . tr( td( $address )
		             . td( $payments_table ) )
		       . table_close();
		
		return $html;
	}
	
	
    public function amount_due_now_html()
	{
		$amount_due_now = $this->total_booth_rent_due + $this->total_additional_taxables - $this->total_payments;
		
		$html = table_open()
		      . tr( td( "Amount Due Now:" )
		            . td_subtotal( $amount_due_now ))
		      . table_close();
		
		return $html = div( $html, array( 'class' => 'amount-due-now' ));
	}
	
	
	public function how_to_pay_html( $show_details )
	{
		if (self::is_adaa_show( $show_details ))
		{
			$html = "<div class=\"adaa-payment-due\">Payment is due upon receipt.</div>";
		}
		else
		{
			$wire_transfer_info = "<div class=\"wire-transfer-info\">Wire transfer information:</div>"
		                        . "<div class=\"wire-transfer-details\">"
		                        . self::get_wire_transfer_info( $show_details )
			                    . "</div>";
		
			if (self::is_outsider_show( $show_details ))
			{
				$credit_card_form = "";
			}
			else
			{
				$credit_card_form = "<div class=\"credit-card-form-info\">Credit Card: We only accept American Express, Visa & Mastercard <br>"
				                    . "Service fee may be applicable</div>"
				                    . "<div class=\"credit-card-form\">"
				                    . "Card # _______________________________________________________________ <br>"
				                    . "Exp. Date _____________________ Security Code ________________________ <br>"
				                    . "Name on Card _______________________________ Amount __________________ <br>"
				                    . "Card Holder's Signature ______________________________________________ <br>"
				                    . "Billing Address ______________________________________________________ </div>";
			}
			
			$html  = table_open()
			       . tr( td( $wire_transfer_info )
			             . td( $credit_card_form ) )
			       . table_close();
		}
		
		return $html;
	}
		
	
	public static function get_address( $show_details, $for_header = FALSE )
	{
		if (self::is_adaa_show( $show_details ))
		{
			if ($for_header)
			{
				$address = "<div class=\"adaa-address-header\">Art Dealers Association of America <br>\n"
				         . "&nbsp;&nbsp; as agent for Henry Street Settlement<br>\n"
				         . "250 Lexington Avenue, Suite 901 <br>\n"
				         . "New York, NY 10016 <br>\n"
				         . "Tel: (212) 488-5540 <br>\n"
				         . "Fax: (646) 688-6809 </div>\n";
			}
			else
			{
				$address = "<div class=\"adaa-name\">Art Dealers Association of America</div>\n"
				         . "250 Lexington Avenue, Suite 901 <br>\n"
				         . "New York, NY 10016 <br>\n"
				         . "<span class=\"attn\">Attn:</span> Patricia Brundage";
			}
		}
		elseif (self::is_ifpda_show( $show_details ))
		{
			$address = "The International Fine Print <br>\n"
			         . "&nbsp;&nbsp;&nbsp;&nbsp;Dealer's Association <br>\n"
			         . "250 W. 26th St, Suite 405 <br>\n"
			         . "New York, NY 10001 <br>\n"
			         . "TEL: (212) 674-6095 <br>\n"
			         . "FAX: (212) 674-6783";
		}
		elseif (self::is_outsider_show( $show_details ))
		{
			$address = "Wide Open Arts, LLC <br>\n"
			         . "134 Tenth Avenue <br>\n"
			         . "New York, NY 10011 <br>\n"
			         . "TEL: 212-206-9723 <br>\n"
			         . "FAX: 212-206-9639";
		}
		else
		{
			$address = "Sanford L. Smith & Associates <br>\n"
			         . "447 West 24th Street <br>\n"
			         . "New York, NY 10011-1253 <br>\n"
			         . "(212) 777-5218 <br>\n"
			         . "FAX: (212) 477-6490";
		}
		
		return $address;
	}
		
	
	public static function get_wire_transfer_info( $show_details )
	{
		if (self::is_ifpda_show( $show_details ))
		{
			$wire_transfer_info = "<nobr>International Fine Print</nobr> <br>"
			                    . "&nbsp;&nbsp;&nbsp;&nbsp;Dealers Association, Inc. <br>"
			                    . "City National Bank <br>"
			                    . "Account # 665229106 <br>"
			                    . "Routing # 026013958 <br>"
			                    . "Swift Code: CINAUS6L";
		}
		elseif (self::is_outsider_show( $show_details ))
		{
			$wire_transfer_info = "<nobr>Wide Open Arts, LLC</nobr> <br>"
			                    . "Bank of America <br>"
			                    . "Account # 483033558162 <br>"
			                    . "Routing # 026009593 <br>"
			                    . "Swift Code: BOFAUS3N";
		}
		else
		{
			$wire_transfer_info = "<nobr>Sanford L. Smith & Associates</nobr> <br>"
			                    . "CHASE Bank <br>"
			                    . "475 W. 23rd St. <br>"
			                    . "New York, NY 10011 <br>"
			                    . "Account # 792805657 <br>"
			                    . "Routing # 021000021 <br>"
			                    . "Swift Code: CHASUS33";
		}
				
		return $wire_transfer_info;
	}
		
	
	private static function is_adaa_show( $show_details )
	{
		$invoice_type = extract_value( $show_details, 'invoice_type' );
		
		return ( $invoice_type == 'adaa' );
	}
		
	
	private static function is_ifpda_show( $show_details )
	{
		$invoice_type = extract_value( $show_details, 'invoice_type' );
		
		return ( $invoice_type == 'ifpda'
		         || $invoice_type == 'print' );
	}
		
	
	private static function is_outsider_show( $show_details )
	{
		$invoice_type = extract_value( $show_details, 'invoice_type' );
		
		return ( $invoice_type == 'outsider' );
	}
	
	
	/**
	 * SETTINGS
	 */
	
	private static function get_setting( $which_setting )
	{
		self::load_settings();
		
		return self::$settings[ $which_setting ];
	}
    
	
	private static function load_settings()
	{
		if (empty( self::$settings ))
		{
			$new_settings = array();
			
			$CI =& get_instance();
	
			$settings_items = array( self::AUTH_COOKIE_NAME,
			                         self::AUTH_TYPE,
			                         self::CRYPT_SALT,
			                         self::DEFAULT_EXPIRE_TIME,
			                         self::PERM_COOKIE_NAME );
			
			foreach( $settings_items as $item)
			{
				$new_settings[ $item ] = $CI->config->item( $item );
				
				if ($new_settings[ $item ] === "")
					error_log( "ERROR: \$config['$item'] not defined in the settings file for this environment!" );
			}
			
			self::$settings = $new_settings;
		}
		
		return self::$settings;
	}
	
	
}
	
/* End of file dk_invoice_helper.php */
/* Location ./application/helpers/dk_invoice_helper.php */