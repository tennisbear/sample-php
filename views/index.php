<?php
/**
 * View for showing a list of exhibitors
 *
 * @author Dennis Slade
 * @since  2012-02-20
 */


echo dk_html::create_links_panel( 'Exhibitor', 'list_top' );

/**
 * Print out the "Sorted by" descriptor
 */
echo div( $SortedBy, array( 'class' => 'btn_panel sorted_by' ));

/**
 * Render the filter box
 */

echo dk_html::form_open_filter();
echo fieldset_open( 'Filter by', 'filter_by' );

echo dk_html::form_from_schema( $filter,
                                $ExhibitorFilter->get_schema(),
                                'ExhibitorFilter' );

echo dk_html::form_filter_buttons();
echo fieldset_close();
echo form_close();

/**
 * If there's no records to display, end right here
 */
if (empty( $Exhibitor ) || count( $Exhibitor ) < 1)
{
	echo dk_html::error( 'No exhibitors found.' );
	return;
}

/**
 * Pagination!!
 */
echo dk_html::pagination_html( $Pagination, $PaginationDescription, $PaginationSizeSelector );

/**
 * Output the list of exhibitors
 */

echo table_open( 'listTable' );

$hidden_fields = array( 'invoice_show_name', 'invoice_payable_to', 'notes', 'modified_at', 'invoiced_at' );

$header_html = dk_html::field_header_actions()
             . dk_html::field_headers( $Schema, $hidden_fields )
             . dk_html::field_header_actions();

echo thead( tr( $header_html ) );
echo dk_html::index_list_rows_from_schema( $Exhibitor, $Schema, 'Exhibitor', $hidden_fields );

echo table_close();

echo $Pagination;
echo dk_html::create_links_panel( 'Exhibitor', 'list_bottom' );

?>