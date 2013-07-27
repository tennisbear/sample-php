<?php if (!defined('BASEPATH')) exit("No direct access allowed.");
/**
 * Model for exhibitor filter form
 *
 * @author Dennis Slade
 * @since  2012-02-20
 */


class ExhibitorFilter extends ModelSchema
{
	protected $collection_name = "";
	
	
	public function __construct( $load_full_schema = TRUE, ModelSchemaAdapter $adapter = NULL )
    {
		$this->add_number_field( 'filter_exhibitor_id', 'Id' )
		     ->set_db_filter( "`COLLECTION`.exhibitor_id = '%s'" )
		     ->default_value( "" )
		     ->primary_key();
		     
		$this->add_number_field( 'filter_contact_id', 'Contact' )
		     ->default_value( "" )
		     ->set_db_filter( "`COLLECTION`.contact_id = '%s'" )
		     ->autocomplete( 'Contact', 'contact_id', "CONCAT(last_name,', ',first_name,' (',company,' ',contact_id,')')" );
		
		$this->add_string_field( 'filter_show', 'Show' )
		     ->set_db_filter( "`COLLECTION`.show_id = '%s'" )
		     ->options_lookup( 'Show', 'show_id', 'short_name', "", 'short_name' );
		
		$this->add_string_field( 'filter_booth_trade_name', 'Booth trade name' )
		     ->set_db_filter( "`COLLECTION`.booth_trade_name LIKE '%%s%'" )
		     ->autocomplete( 'Exhibitor', 'booth_trade_name', 'booth_trade_name', "", 'booth_trade_name' );
		
		$this->add_string_field( 'filter_booth_number', 'Booth info' )
		     ->set_db_filter( "CONCAT(`COLLECTION`.booth_number, ',', `COLLECTION`.booth_size) LIKE '%s'" )
		     ->multifield_filter( TRUE )
//		     ->set_db_filter( "`COLLECTION`.booth_number LIKE '%%s%' OR `COLLECTION`.booth_size LIKE '%%s%'" )
		     ->helper_text( '<pre style="margin-top:0;">searches fields <u>booth number</u> and <u>booth size</u>. Wildcard override is %. Examples: <br>'
		                    . '  A5      = has "A5" anywhere in booth number or anywhere in booth size <br>'
		                    . '  A2,     = has "A2" anywhere in booth number (like "A2" or "A22"), booth size can be anything <br>'
		                    . '  ,C      = booth number can be anything, has "C" anywhere in booth size <br>'
		                    . '  ,C%     = booth number can be anything, booth size must start with "C" <br>'
		                    . '  B%,w/   = booth size must start with "B", booth number has string "w/" anywhere in it</pre>' );
		
		$this->add_boolean_field( 'filter_archived', 'Include archived' )
		     ->set_db_filter( "NOT (`COLLECTION`.is_archived = 'yes')" )
		     ->display_as( ModelSchemaField::CHECKBOX )
		     ->helper_text( 'include exhibitors which have been archived' );
		
		
		parent::__construct( $load_full_schema, $adapter );
    }

    
}

/* End of file exhibitorfilter.php */
/* Location ./application/models/exhibitorfilter.php */
