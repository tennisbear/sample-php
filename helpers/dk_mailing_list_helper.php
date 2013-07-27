<?php
/**
 * Mailing list helper functions for use in views and/or controllers
 *
 * @author Dennis Slade
 * @since  2012-06-07
 */


class dk_mailing_list_helper
{
	const ADVERTISERS_MAILING_LIST = 'advertisers';
	const EXHIBITORS_MAILING_LIST  = 'exhibitors';
	const ITEMS_KEY                = 'ExhibitorItem';
	const NO_RECORDS_FOUND         = 'no_records_found';
	
	private $page_number = 1;
	
	
	public function __construct( $report_type = self::EXHIBITORS_MAILING_LIST )
	{
		$this->report_type = $report_type;
	}
	
	
	public function page_header_html( $show_details, $break_before = FALSE )
	{
		return $this->is_an_advertiser_report()
		          ? $this->advertiser_page_header_html( $show_details )
		          : $this->exhibitor_page_header_html( $show_details );
	}
	
	
	private function advertiser_page_header_html( $show_details )
	{
		$html = th( "Advertiser Mailing List for {$show_details['full_name']}, page {$this->page_number}",
		            array( 'class' => "page-header", 'colspan' => 8 ))
		      . th( date( 'j F Y' ),
		            array( 'colspan' => 2, 'class' => "page-header date" ));
		
		$this->page_number++;
		
		return tr( $html );
	}
	
	
	private function exhibitor_page_header_html( $show_details )
	{
		$html = th( "Mailing List for {$show_details['full_name']}, page {$this->page_number}",
		            array( 'class' => "page-header", 'colspan' => 5 ))
		      . th( date( 'j F Y' ),
		            array( 'colspan' => 6, 'class' => "page-header date" ));
		
		$this->page_number++;
		
		return tr( $html );
	}
	
	
	public function header_html( $show_details )
	{
		return $this->is_an_advertiser_report()
		          ? $this->advertiser_header_html()
		          : $this->exhibitor_header_html();
	}
	
	
	private function advertiser_header_html()
	{
		return tr( th( 'Advertiser', array( 'class' => 'title-header exhibitor-column' ))
		           . th( 'Last&nbsp;Name', array( 'class' => 'title-header lastname-column' ))
		           . th( array( '<nobr>First&nbsp;Name</nobr>', 'Address', 'City', 'State', 'Zip', 'Country',
		                        'Phone Numbers', 'Email/Website' ),
		           array( 'class' => "title-header" )));
	}
	
	
	private function exhibitor_header_html()
	{
		return tr( th( 'Exhibitor', array( 'class' => 'title-header exhibitor-column' ))
		           . th( 'Last&nbsp;Name', array( 'class' => 'title-header lastname-column' ))
		           . th( array( '<nobr>First&nbsp;Name</nobr>', 'Address', 'City', 'State', 'Zip', 'Country',
		                        'Mobile Phone', 'Work Phone', 'Work Fax' ),
		           array( 'class' => "title-header" )));
	}
	
	
	public function row_html( $details )
	{
		return $this->is_an_advertiser_report()
		          ? $this->advertiser_row_html( $details )
		          : $this->exhibitor_row_html( $details );
	}
	
	
	private function advertiser_row_html( $advertiser_details )
	{
		if (isset( $advertiser_details[ self::NO_RECORDS_FOUND ] ))
		{
			$rows_used = 1;
			$html      = tr( th( 'No advertisers found for this show.',
			                     array( 'colspan' => 10,
			                            'class'   => 'no-records-found' ) ));
		}
		else
		{
			$email_website = self::get_internet_details_html( $advertiser_details );
			$phone_details = self::get_phone_details_html( $advertiser_details );
			$rows_used     = max( count($email_website),
			                      count($phone_details),
			                      1 );
			
			$html = tr( td( $advertiser_details['trade_name'], 'advertiser-column' )
			            . td( $advertiser_details['last_name'], 'lastname-column' )
			            . td( $advertiser_details['first_name'] )
			            . td( $advertiser_details['address'], 'address-column' )
			            . td( $advertiser_details['city'], 'city-column' )
			            . td( $advertiser_details['state'] )
			            . td( $advertiser_details['postal_code'] )
			            . td( $advertiser_details['country'] )
			            . td( implode( "<br>\n", $phone_details ), 'phone-details-column' )
			            . td( implode( "<br>\n", $email_website  ) ));
		}
		
		return array( $html, $rows_used );
	}
	
	
	private function exhibitor_row_html( $exhibitor_details )
	{
		$rows_used = 1;
		
		if (isset( $exhibitor_details[ self::NO_RECORDS_FOUND ] ))
		{
			$html = tr( th( 'No exhibitors found for this show.',
			                array( 'colspan' => 11,
			                       'class'   => 'no-records-found' ) ));
		}
		else
		{
			$html = tr( td( $exhibitor_details['booth_trade_name'], 'exhibitor-column' )
			            . td( $exhibitor_details['last_name'], 'lastname-column' )
			            . td( $exhibitor_details['first_name'] )
			            . td( $exhibitor_details['address'], 'address-column' )
			            . td( $exhibitor_details['city'], 'city-column' )
			            . td( $exhibitor_details['state'] )
			            . td( $exhibitor_details['postal_code'] )
			            . td( $exhibitor_details['country'] )
			            . td( $exhibitor_details['mobile_phone'] )
			            . td( $exhibitor_details['work_phone'] )
			            . td( $exhibitor_details['work_fax'] ));
		}
		
		return array( $html, $rows_used );
	}
	
	
	public static function excel_csv()
	{
		$cells = func_get_args();
		
		foreach( $cells as $index => $cell )
		{
			$cell = str_replace( array('\n', "\n", chr(11)), "\r", $cell );
			$cell = str_replace( '"', '""', $cell );
			
			$cells[ $index ] = '"'.$cell.'"';
		}
		
		return implode( ",", $cells );
	}
	
	
	private static function get_internet_details_html( $details )
	{
		$email   = self::cleanup_text( trim( extract_value( $details, 'email' )));
		$website = self::cleanup_text( trim( extract_value( $details, 'website' )));
		$ret     = array();
		
		if ($email)
			$ret[] = self::mailto_link( $email );
		
		if ($website)
			$ret[] = self::website_link( $website );
		
		return $ret;
	}
	
	
	private static function get_phone_details_html( $details )
	{
		static $lookup = array( 'M' => 'mobile_phone',
		                        'W' => 'work_phone',
		                        'F' => 'work_fax' );
		
		$ret = array();
		
		foreach( $lookup as $label => $key )
		{
			if ($text = extract_value( $details, $key ))
			{
				$ret[] = "$label: " . self::cleanup_text( $text );
			}
		}
				
		return $ret;
	}
	
	
	private static function cleanup_text( $text )
	{
		return str_replace( array('\n', "\n", chr(11)), "<br>\n", $text );
	}
	
	
	private static function mailto_link( $email )
	{
		return "<a href=\"mailto:$email\">$email</a>";
	}
	
	
	private static function website_link( $website )
	{
		$prefix = (strncmp( $website, 'http', 4 ) != 0)
		             ? "http://"
		             : "";
		
		return "<a href=\"$prefix$website\">$website</a>";
	}
	
	
	public function is_an_advertiser_report()
	{
		return ($this->report_type === self::ADVERTISERS_MAILING_LIST);
	}
	
}
	
/* End of file dk_mailing_list_helper.php */
/* Location ./application/helpers/dk_mailing_list_helper.php */