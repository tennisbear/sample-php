<?php
/**
 * Form for creating a new exhibitor
 *
 * @author Dennis Slade
 * @since  2012-02-20
 */


$exhibitor_html = new dk_exhibitor_html();

echo dk_html::form_open_create();
echo dk_html::form_validation_errors();

echo dk_html::form_from_schema( $Exhibitor, $Schema, 'Exhibitor' );
echo dk_html::form_create_buttons( $CancelLink );

echo form_close();

?>