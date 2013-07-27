<?php if (!defined('BASEPATH')) exit("No direct access allowed.");
/**
 * Contact model
 *
 * @author Dennis Slade
 * @since  2011-12-02
 */


class Contact extends ModelSchema
{
	protected $collection_name = "dk_contacts";
	
	
	public function __construct( $load_full_schema = TRUE, ModelSchemaAdapter $adapter = NULL )
	{
		$this->is_one_to_many_with( 'Exhibitor', 'contact_id', 'contact_id', 'exhibitor_id desc' );
		$this->is_one_to_many_with( 'Advertiser', 'contact_id', 'contact_id', 'advertiser_id desc' );
		
		$this->add_primary_key( 'contact_id', 'Id' );
		
		$this->set_default_sort_field( 'contact_id desc' );
		
		
		$this->add_string_field( 'last_name' );
		$this->add_string_field( 'first_name' );
		$this->add_string_field( 'middle_name' );
		$this->add_string_field( 'title' );
		
		$this->add_string_field( 'company' )
		     ->required( TRUE )
		     ->validation_rules( 'trim|required' )
		     ->helper_text( 'Copied as "Booth Trade Name" for new exhibitors based on this contact <br>'
		                    . 'Copied as "Trade Name" for new advertisers based on this contact' );
		
		$this->add_string_field( 'alpha' );
		
		$this->add_string_field( 'address' )
		     ->set_display_size( ModelSchema::DEFAULT_TEXT_FIELD_WIDTH, 3);
		
		$this->add_string_field( 'city' );
		
		$this->add_string_field( 'state' )
		     ->options_lookup( 'StateProvince', 'short_name', 'full_name' );
		
		$this->add_string_field( 'postal_code', 'Postal/Zip code' );
		
		$this->add_string_field( 'country' )
		     ->default_value( 'USA' )
		     ->options_lookup( 'Country', 'short_name', 'full_name' )
		     ->required( TRUE )
		     ->validation_rules( 'trim|required' );
		
		$this->add_string_field( 'address_shipping' )
		     ->set_display_size( ModelSchema::DEFAULT_TEXT_FIELD_WIDTH, 3);
		$this->add_string_field( 'address_ups', 'Address UPS' )
		     ->set_display_size( ModelSchema::DEFAULT_TEXT_FIELD_WIDTH, 3);
		
		$this->add_string_field( 'work_phone' )
		     ->set_display_size( ModelSchema::DEFAULT_TEXT_FIELD_WIDTH, 2);
		$this->add_string_field( 'work_fax' )
		     ->set_display_size( ModelSchema::DEFAULT_TEXT_FIELD_WIDTH, 2);
		$this->add_string_field( 'mobile_phone' )
		     ->set_display_size( ModelSchema::DEFAULT_TEXT_FIELD_WIDTH, 2);
		$this->add_string_field( 'home_phone' )
		     ->set_display_size( ModelSchema::DEFAULT_TEXT_FIELD_WIDTH, 2);
		$this->add_string_field( 'home_fax' )
		     ->set_display_size( ModelSchema::DEFAULT_TEXT_FIELD_WIDTH, 2);
		$this->add_string_field( 'other_phone' )
		     ->set_display_size( ModelSchema::DEFAULT_TEXT_FIELD_WIDTH, 2);
		
		$this->add_string_field( 'email' )
		     ->set_display_size( ModelSchema::DEFAULT_TEXT_FIELD_WIDTH, 2)
		     ->display_as( 'mailto' );
		$this->add_string_field( 'website' )
		     ->set_display_size( ModelSchema::DEFAULT_TEXT_FIELD_WIDTH, 2)
		     ->display_as( 'url' );
		
		$this->add_string_field( 'tax_number' );
		$this->add_string_field( 'tax_number_state_province', 'Tax number state' );
		
		$this->add_string_field( 'keywords' )
		     ->set_display_size( ModelSchema::DEFAULT_TEXT_FIELD_WIDTH, 3);
		$this->add_string_field( 'history' )
		     ->set_display_size( ModelSchema::DEFAULT_TEXT_FIELD_WIDTH, 3);
		$this->add_string_field( 'notes' )
		     ->set_display_size( ModelSchema::DEFAULT_TEXT_FIELD_WIDTH, 3);
		
		$this->add_timestamp_field( 'modified_at' )
		     ->default_value( ModelSchemaField::to_timestamp() )
		     ->readonly( TRUE )
		     ->display_on_forms( FALSE );
		
		$this->add_timestamp_field( 'created_at' )
		     ->default_value( ModelSchemaField::to_timestamp() )
		     ->readonly( TRUE )
		     ->display_on_forms( FALSE );
		
		$this->add_string_field( 'is_archived', 'Archived?' )
		     ->default_value( 'no' );
				
		
		parent::__construct( $load_full_schema, $adapter );
    }
    
}

/* End of file contact.php */
/* Location ./event/models/contact.php */