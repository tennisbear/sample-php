<?php if (!defined('BASEPATH')) exit("No direct access allowed.");
/**
 * Exhibitor model
 *
 * @author Dennis Slade
 * @since  2011-12-02
 */


class Exhibitor extends ModelSchema
{
	protected $collection_name = "dk_exhibitors";
	
	
	public function __construct( $load_full_schema = TRUE )
	{
		$this->is_one_to_one_with( 'Contact', 'contact_id' );
		$this->is_one_to_many_with( 'ExhibitorItem', 'exhibitor_id', 'exhibitor_id', 'created_at' );
		
		$this->set_default_sort_field( 'exhibitor_id desc' );
				
		$this->add_primary_key( 'exhibitor_id', 'Id' );
		
		
		$this->add_number_field( 'show_id', 'Show' )
		     ->default_value( ModelSchemaField::LAST_INSERTED_VALUE )
		     ->options_lookup( 'Show', 'show_id', 'short_name', "is_archived <> 'yes'", 'short_name' )
		     ->display_as( 'link:/exhibitors/filter/show/%s:show all exhibitors for this show' );
		
		$this->add_number_field( 'contact_id', 'Contact' )
		     ->required( TRUE )
		     ->validation_rules( 'trim|required' )
		     ->default_value( "" )
		     ->autocomplete( 'Contact', 'contact_id', "CONCAT(last_name,', ',first_name,' (',company,' ',contact_id,')')" )
		     ->display_as( 'link:/contacts/show/%s::show' )
		     ->sort_using( 'alpha' )
		     ->helper_text( array ( 'create' => 'Need to create a contact for your new exhibitor? <a href="'
		                                        . site_url( 'contacts/create' )
		                                        . '">CLICK HERE</a> to create one' ));
		
		$this->add_string_field( 'booth_trade_name' )
		     ->required( TRUE )
		     ->validation_rules( 'trim|required' )
		     ->autocomplete( 'Contact', 'company', 'company', "", 'company', ModelSchemaField::NO_VALIDATION );
		
		$this->add_string_field( 'booth_number' );
		$this->add_string_field( 'booth_size' );
		$this->add_string_field( 'wall_color' );
		
		$this->add_string_field( 'invoice_show_name' );
		$this->add_string_field( 'invoice_payable_to' );
		
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
		
		$this->add_timestamp_field( 'invoiced_at' )
		     ->readonly( TRUE )
		     ->display_on_forms( FALSE );
		
		$this->add_string_field( 'is_archived', 'Archived?' )
		     ->default_value( 'no' )
		     ->readonly( TRUE )
		     ->display_on_forms( FALSE );
		
		
		parent::__construct( $load_full_schema );
    }
    
}

/* End of file exhibitor.php */
/* Location ./application/models/exhibitor.php */