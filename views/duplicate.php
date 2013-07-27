<?php
/**
 * Form for duplicating a exhibitor record
 *
 * @author Dennis Slade
 * @since  2012-02-20
 */


echo dk_html::form_open_duplicate();
echo dk_html::form_validation_errors();

echo dk_html::form_from_schema( $Exhibitor, $Schema, 'Exhibitor' );
echo dk_html::form_duplicate_buttons( $Guid, $CancelLink );
                                
echo form_close();

?>