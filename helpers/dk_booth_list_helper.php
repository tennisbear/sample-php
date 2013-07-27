<?php
/**
 * Booth list helper functions for use in views and/or controllers
 *
 * @author Dennis Slade
 * @since  2013-03-13
 */


class dk_booth_list_helper
{
	const EXHIBITORS_BOOTH_LIST  = 'exhibitors';
	const ITEMS_KEY              = 'ExhibitorItem';
	const NO_RECORDS_FOUND       = 'no_records_found';
	
	private $page_number = 1;
	
	
	public function __construct()
	{
	}
	
	
	public function page_header_html( $show_details, $break_before = FALSE )
	{
		return $this->exhibitor_page_header_html( $show_details );
	}
	
	
	private function exhibitor_page_header_html( $show_details )
	{
		$html = th( "Booth List for {$show_details['full_name']}, page {$this->page_number}",
		            array( 'class' => "page-header", 'colspan' => 5 ))
		      . th( date( 'j F Y' ),
		            array( 'colspan' => 6, 'class' => "page-header date" ));
		
		$this->page_number++;
		
		return tr( $html );
	}
	
	
	public function header_html( $show_details )
	{
		return $this->exhibitor_header_html();
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
		return $this->exhibitor_row_html( $details );
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
		
}
	
/* End of file dk_booth_list_helper.php */
/* Location ./application/helpers/dk_booth_list_helper.php */