<?php if (!defined('BASEPATH')) exit("No direct access allowed.");
/**
 * Model for contact filter form
 *
 * @author Dennis Slade
 * @since  2011-12-06
 */


class ContactFilter extends ModelSchema
{
	protected $collection_name = "";
	
	
	public function __construct( $load_full_schema = TRUE, ModelSchemaAdapter $adapter = NULL )
    {
		$this->add_number_field( 'filter_contact_id', 'Contact id' )
		     ->set_db_filter( "contact_id = '%s'" )
		     ->default_value( "" )
		     ->primary_key();
		     
		$this->add_string_field( 'filter_last_name', 'Last name' )
		     ->set_db_filter( "last_name LIKE '%%s%'" );
		     
		$this->add_string_field( 'filter_first_name', 'First name' )
		     ->set_db_filter( "first_name LIKE '%%s%'" );
		
		$this->add_string_field( 'filter_company', 'Company' )
		     ->set_db_filter( "company LIKE '%%s%'" );
		
		$this->add_string_field( 'filter_city', 'City' )
		     ->set_db_filter( "city LIKE '%%s%'" );
		
		$this->add_string_field( 'filter_state', 'State' )
		     ->set_db_filter( "state LIKE '%%s%'" )
		     ->options_lookup( 'StateProvince', 'short_name', 'full_name' );
		
		$this->add_string_field( 'filter_country', 'Country' )
		     ->set_db_filter( "country LIKE '%%s%'" )
		     ->options_lookup( 'Country', 'short_name', 'full_name' );
		
		$this->add_boolean_field( 'filter_named', 'Include unnamed' )
		     ->set_db_filter( "NOT (last_name = '' AND first_name = '')" )
		     ->display_as( ModelSchemaField::CHECKBOX )
		     ->helper_text( 'include those contacts without first/last names' );
		
		$this->add_boolean_field( 'filter_archived', 'Include archived' )
		     ->set_db_filter( "NOT (is_archived = 'yes')" )
		     ->display_as( ModelSchemaField::CHECKBOX )
		     ->helper_text( 'include contacts which have been archived' );
		
		
		parent::__construct( $load_full_schema, $adapter );
    }

    
}

/* End of file contactfilter.php */
/* Location ./application/models/contactfilter.php */
