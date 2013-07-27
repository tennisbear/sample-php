<?php if (!defined('BASEPATH')) exit("No direct access allowed.");
/**
 * Exhibitor item model
 *
 * @author Dennis Slade
 * @since  2011-03-01
 */


class ExhibitorItem extends ModelSchema
{
	protected $collection_name = "dk_exhibitor_items";
	
	
	public function __construct( $load_full_schema = TRUE, ModelSchemaAdapter $adapter = NULL )
	{
		$this->set_default_sort_field( 'exhibitor_item_id' );
		
		
		$this->add_primary_key( 'exhibitor_item_id', 'Id' );
		
		$this->add_number_field( 'show_id', 'Show' )
		     ->options_lookup( 'Show', 'show_id', 'short_name', "", 'short_name' );
		
		$this->add_number_field( 'exhibitor_id' )
		     ->default_value( "" )
		     ->autocomplete( 'Exhibitor', 'exhibitor_id', "CONCAT( booth_trade_name, ' ', exhibitor_id )" );
		
		$this->add_string_field( 'description' );
		$this->add_string_field( 'notes' );
		
		$this->add_string_field( 'size' );
		$this->add_string_field( 'color' );
		$this->add_number_field( 'unit_cost' );
		$this->add_number_field( 'quantity' );
		
		$this->add_string_field( 'debit_type' );
		$this->add_string_field( 'billing_type' );
		
		$this->add_timestamp_field( 'created_at' )
		     ->default_value( ModelSchemaField::to_timestamp() )
		     ->readonly( TRUE )
		     ->display_on_forms( FALSE );
		
		$this->add_timestamp_field( 'paid_at' )
		     ->default_value( ModelSchemaField::to_timestamp() )
		     ->readonly( TRUE )
		     ->display_on_forms( FALSE );
		
		
		parent::__construct( $load_full_schema, $adapter );
    }
    
}

/* End of file exhibitoritem.php */
/* Location ./event/models/exhibitoritem.php */