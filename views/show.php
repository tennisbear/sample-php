<?php
/**
 * View for showing one exhibitor
 *
 * @author Dennis Slade
 * @since  2012-02-20
 */


echo dk_html::form_show_buttons( $Guid, 'Exhibitor' );

if (count( $Exhibitor ) < 1)
{
	echo dk_html::error( 'No exhibitors found.' );
	return;
}


$exhibitor_html = new dk_exhibitor_html();

$payments_received_form  = $exhibitor_html->payments_received_form( $Exhibitor, $AddPaymentFormSchema, $previous_input );
$rental_charges_form     = $exhibitor_html->rental_charges_form( $Exhibitor, $AddRentItemFormSchema, $previous_input );
$additional_charges_form = $exhibitor_html->additional_charges_form( $Exhibitor, $AddAdditionalChargeFormSchema, $previous_input );

$forms = $rental_charges_form
       . $payments_received_form
       . $additional_charges_form;

$html = div( $forms, 'charges-forms' )
      . $exhibitor_html->rental_charges_section( $Exhibitor )
      . $exhibitor_html->additional_charges_section( $Exhibitor, $TaxRate )
      . $exhibitor_html->payments_received_section( $Exhibitor );

$html .= $exhibitor_html->totals_section( $Exhibitor );

/**
 * For accuracy, this call needs to happen after all the other ones
 */
echo $exhibitor_html->account_balance_header();

echo dk_html::show_from_schema( $Exhibitor, $Schema, 'Exhibitor' );
echo br_clear_all();
echo $html;

echo dk_html::form_show_buttons( $Guid, 'Exhibitor' );

?>