<?php
/**
 * Form for editing a exhibitor
 *
 * @author Dennis Slade
 * @since  2012-02-20
 */


if (empty( $Exhibitor ))
{
	echo dk_html::error( 'No exhibitors found.' );
	return;
}


echo dk_html::form_open_edit();
echo dk_html::form_validation_errors();

echo dk_html::form_from_schema( $Exhibitor, $Schema, 'Exhibitor' );
echo dk_html::form_edit_buttons( $Guid, $CancelLink );
                                
echo form_close();

?>