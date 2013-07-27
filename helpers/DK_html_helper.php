<?php
/**
 * General helper class for use in views
 *
 * @author Dennis Slade
 * @since  2011-11-11
 */

class dk_html
{
	const ACTION_ADD             = 'add';
	const ACTION_CHANGE_PASSWORD = 'change_password';
	const ACTION_CREATE          = 'create';
	const ACTION_EDIT            = 'edit';
	const ACTION_INVOICES        = 'invoices';
	const ACTION_LIST_BANNED     = 'chat_banned_list';
	const ACTION_LOGIN           = 'login';
	const ADD_ARRAY_FIELD        = 'add_array';
	const CHECKED_VALUE          = "yes";
	const CREATE_FORM            = 'create_form';
	const EDIT_ANCHOR            = 'edit_anchor';
	const EDIT_FORM              = 'edit_form';
	const EDIT_MODE              = TRUE;
	const HIDE_ACTIONS           = "HIDE_ACTIONS";
	const S3                     = "S3";
	
	private static $submitted_array_locator = "";
	
	
	public static function index_list_rows_from_schema( $db_cursor, $schema_list, $model_name = "", $hidden_fields = array() )
	{
		$html = "";
		
		list( $fields,
		      $field_names,
		      $field_labels ) = ModelSchema::extract_field_details_by_model( $schema_list, $model_name );
		
		$odd = false;
		
		$model = ModelSchema::get_model_instance( $model_name );
		
		$primary_key_field = $model->get_primary_key();
		
		foreach( $db_cursor as $row )
		{
			$guid = isset( $row[$primary_key_field] ) ? $row[$primary_key_field] : "";
			
			$actions_html = (in_array( self::HIDE_ACTIONS, $hidden_fields ))
			                   ?  ""
			                   : self::td_actions_buttons( $guid, $model_name );

			$tr_class = ($odd) ? "odd" : "";
			$odd      = !$odd;
			
			$html .= tr_open( $tr_class );
			$html .= $actions_html;
		
			$td_attributes = array( 'class' => 'list_row' );
	
			foreach( $field_names as $field_name => $field_type )
			{
				if (!in_array( $field_name, $hidden_fields) )
				{
					$field_definitions = ModelSchema::model_2_index_fields( $field_name );
					
					switch( $field_type )
					{
						case ModelSchemaField::_LIST:
						case ModelSchemaField::_NUMBER_LIST:
							$html .= td( self::extract_list_html( $row, $field_name, '(none)' ), $td_attributes);
							break;
							
						case ModelSchemaField::_OBJECT:
							$html .= td( self::extract_object_html( $schema_list[$field_definitions], $row, $field_name, '&nbsp;' ), $td_attributes);
							break;
							
						case ModelSchemaField::_ARRAY:
							$html .= td( self::extract_array_html( $schema_list[$field_definitions], $row, $field_name, '&nbsp;' ), $td_attributes);
							break;
							
						default:
							$value = self::extract_value_with_lookup( $schema_list, $model_name, $field_name, $row, $field_name, '&nbsp;' );
							
							$html .= td( self::extract_field_html( array(), $field_name, NULL, $value ), $td_attributes);
							break;
					}
				}
			}
			
			$html .= $actions_html;
			
			$html .= tr_close();
		}
		
		return $html;
	}
	
	/**
	 * FORMS GENERATION METHODS
	 */
	
	public static function form_from_schema( $previous_input, $schema_list,
	                                         $model_name = "", $field_prefix = NULL,
	                                         $array_locator = "", $autofocus = TRUE )
	{
		$html = "";
		
		list( $fields,
		      $field_names,
		      $field_labels ) = ModelSchema::extract_field_details_by_model( $schema_list, strtolower($model_name) );
		
		/**
		 * Re-adjust the previous input array if the data was saved as a sub-array
		 */
		if (!empty( $model_name )
		    && is_array( $previous_input )
		    && isset( $previous_input[ $model_name ] )
		    && !empty( $previous_input[ $model_name ] ))
		{
			$previous_input = $previous_input[ $model_name ];
		}
		
		foreach ($fields as $name => $field)
		{
			if ($autofocus && !$field->is_readonly())
			{
				$field->autofocus( TRUE );
				$autofocus = FALSE;
			}
			
			$html .= self::form_field_html( $previous_input,
			                                $field,
			                                $schema_list,
			                                $model_name,
			                                $field_prefix,
			                                $array_locator );
		}

		return $html;
	}
	
	
	private static function form_field_html( $previous_input, ModelSchemaField $field,
	                                         $schema_list = NULL, $model_name = "", $field_prefix = NULL,
	                                         $array_locator = "" )
	{
		$field_name  = $field->get_name();
		$field_value = self::extract_posted_value( $previous_input, $field_name, $field->get_default_value( $model_name ) );

		$field->set_value( $field_value );
		
		/**
		 * Determine the name of the current input item being worked upon
		 */
		
		if (empty( $model_name ))
		{
			$input_name = $field_name;
		}
		elseif ($field_prefix !== NULL)
		{
			$input_name = ($field_prefix.'['.$field_name.']');
		}
		else
		{
			$input_name = ($model_name.'['.$field_name.']');
		}
		
		/**
		 * Output this row in the form (or this section if we need to do objects/arrays)
		 */
		
		switch( $field->get_type() )
		{
			case ModelSchemaField::_LIST:
			case ModelSchemaField::_NUMBER_LIST:
				$html = self::form_field_html_list( $field, $input_name, $previous_input );
				break;
				
			case ModelSchemaField::_OBJECT:
				$array_locator = self::array_locator_string_push( $array_locator, $field_name, $field_name );
				$html          = self::form_field_html_object( $field, $input_name, $field_value, $schema_list, $array_locator );
				break;
				
			case ModelSchemaField::_ARRAY:
				$html = self::form_field_html_array( $field, $input_name, $field_value, $schema_list, $array_locator );
				break;
				
			case ModelSchemaField::BOOLEAN:
				$html = self::form_field_html_default( $field, $input_name, ModelSchemaField::prepare_boolean( $field_value ));
				break;
				
			case ModelSchemaField::PASSWORD:
			case ModelSchemaField::TEXT:
			case ModelSchemaField::NUMBER:
			case ModelSchemaField::INTERNAL:
			case ModelSchemaField::TIMESTAMP:
				$html = self::form_field_html_default( $field, $input_name, $field_value, $previous_input );
				break;
				
			case ModelSchemaField::GUID:
			default:
				$html = "";
				break;
		}
		
		/**
		 * @todo Autofocus this control if there's an error?
		 */
		
		return $html;
	}
	
	
	private static function form_field_html_default( ModelSchemaField $field, $input_name, $posted_value, $previous_input = NULL )
	{
		if (!self::in_edit_mode() && !$field->is_displaying_on_forms())
			return "";
		
		$posted_value = $field->display_string( $posted_value );
		$form_label   = form_label( $field->get_label( self::EDIT_MODE ),
		                            $field->get_input_id() );
		
		$helper_text        = self::form_field_help_html( $field );
		$filter_option_html = self::form_field_filter_option_html( $field, $input_name, $previous_input );
		      
		$content = $form_label
		         . $filter_option_html
		         . self::form_field_input( $field, $input_name, $posted_value )
		         . $helper_text;
		                      
		return div( $content, array( 'class' => 'input_form' ));
	}
	
	
	private static function form_field_help_html( ModelSchemaField $field, $break_before = TRUE )
	{
		$helper_text = $field->get_helper_text();
		
		if (empty( $helper_text ))
		{
			$helper_text = "";
		}
		elseif (is_array( $helper_text ))
		{
			$helper_text = extract_value( $helper_text, self::get_action_name() );
		}
		
		if ($helper_text)
		{
			$helper_text = div( $helper_text, array( 'class' => 'helper_text' ));
			
			if ($break_before)
				$helper_text = br_clear_all() . $helper_text;
		}
		
		return $helper_text;
	}
	
	
	private static function form_field_filter_option_html( ModelSchemaField $field, $input_name, $previous_input )
	{
		$ret = "";
		
		if ($field->has_db_filter()
		    && !$field->has_autocomplete()
		    && !$field->is_primary_key()
		    && ($field->get_type() === ModelSchemaField::NUMBER))
		{
			$name        = $field->get_name();
			$option_name = $name . DK_Controller::OPTION_SUFFIX;
			$selected    = extract_value( $previous_input, $option_name );
			$select_name = str_replace( $name, $option_name, $input_name );
			
			$ret = "\n<select name=\"$select_name\" id=\"$select_name\">";
			
			foreach( self::form_field_filter_options($field) as $label => $value)
			{
				$s = ($value == $selected) ? " selected" : "";
				
				$ret .= "\n\t<option value=\"$value\"$s>$label</option>";
			}
			
			$ret .= "\n</select>";
		}
		
		return $ret;
	}
	
	
	private static function form_field_filter_options( ModelSchemaField $field )
	{
		static $opts = array( 'is'        => "",
		                      'is not'    => '<>',
		                      'at least'  => '>=',
		                      'at most'   => '<=',
		                      'more than' => '>',
		                      'less than' => '<' );
		return $opts;
	}
	
	
	private static function form_field_input( ModelSchemaField $field, $input_name, $value )
	{
		$attributes = array( 'name' => $input_name,
		                     'id'   => $field->get_input_id() );
		
		$value = str_replace( array('\n', chr(11)), "\n", $value );
		$extra = 'id="' . $field->get_input_id() . '"';
		$step  = NULL;
		
		if ($field->has_autofocus())
			$extra .= " autofocus";
		
		if ($field->is_required())
			$extra .= " required";
		
		$options = $field->get_options();
		list( $width,
		      $height ) = $field->get_display_size();

		if (($field->get_type() === ModelSchemaField::PASSWORD)
		    || ($field->get_display_as() === ModelSchemaField::CHECKBOX))
		{
			$ret = $field->display_form_html( $input_name, $value );
		}
		elseif ($field->is_readonly())
		{
			$display_value = ($value == "") ? '&nbsp;' : $value;
			
			$ret = form_hidden( $input_name, $value )
			     . div( $display_value, array( 'class' => 'readonly_display' ) );
		}
		elseif (!empty( $options ))
		{
			$ret = form_dropdown( $input_name, $options, $value, $extra );
		}
		elseif (!empty( $height ))
		{
			$attributes = array( 'name' => $input_name,
			                     'rows' => $height );

			if (!empty( $width ))
				$attributes['cols'] = $width;
			
			$ret = form_textarea( $attributes, $value, $extra );
		}
		else
		{
			if (!empty( $width ))
				$extra .= " size=\"$width\"";
			
			if (($type = $field->get_type()) == ModelSchemaField::TIMESTAMP)
			{
				$type = 'datetime';
			}
			elseif (($type == ModelSchemaField::NUMBER) && !$field->has_autocomplete())
			{
				$type = 'number';
				$step = 'any';
			}
			else
			{
				$type = 'text';
			}
			
			$data = array
			(
			    'type' => $type,
				'step' => $step,
				'name' => $input_name
			);
			
			$ret = form_input( $data, $value, $extra );
		}
		
		return $ret;
	}
	
	
	public static function form_field_html_list( $field, $input_name, $list_string_or_array )
	{
		$label = form_label( $field->get_label(),
		                     $field->get_name() );

		$value = ModelSchemaField::extract_list_string( $list_string_or_array, $field->get_name() );
		
		$attributes = array( 'name' => $input_name, 'id' => self::safe_html_id( $input_name ));

		$options = $field->get_options();
		list( $width,
		      $height ) = $field->get_display_size();
		                          
		if (empty( $height ))
		{
			$extra = (empty( $width )) ? "" : "size=\"$width\"";
			
			$form_control = form_input( $attributes, $value, $extra );
		}
		else
		{
			$attributes['rows'] = $height;

			if (!empty( $width ))
				$attributes['cols'] = $width;
			
			$form_control = form_textarea( $attributes, $value );
		}
			
		$form_control .= self::form_field_html_list_options_dropdown( $options, $input_name )
		               . br_clear_all();
			
		return div( $label.$form_control, array( 'class' => 'input_form' ));
	}
	
	
	private static function form_field_html_list_options_dropdown( $options, $field_name )
	{
		$ret = "";
		
		if (!empty( $options ))
		{
			$field_name = self::safe_html_id( $field_name );
			
			$controls = "Possible values: "
			          . form_dropdown( $field_name."_select",
			                      $options,
			                      "",
			                      "id=\"${field_name}_select\"" )
			          . form_button( array( 'name'    => $field_name."_addtolist",
			                                'onclick' => "add_select_value_to_list_field('$field_name','${field_name}_select');",
			                                'title'   => "add value from dropdown to end of list"),
			                         'Add' )
			          . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'
			          . form_button( array( 'name'    => $field_name."_alpha",
			                                'onclick' => "alphabetize_list_field('$field_name');",
			                                'title'   => "click to alphabetize the current field values"),
			                         'alpha' )
			          . form_button( array( 'name'    => $field_name."_clear",
			                                'onclick' => "clear_list_field('$field_name');",
			                                'title'   => "clears the current field values; click again to undo a clear"),
			                         'clear' );
			
			$ret = '<br>'
			     . form_label( "&nbsp;", "" )
			     . div( $controls, array( 'class' => 'list_options_dropdown' ));
		}
		
		return $ret;
	}
	
	
	private static function safe_html_id( $string )
	{
		return str_replace( array( '[', ']' ), "_", $string );
	}
	
	
	public static function form_field_html_object( $field, $input_name, $object, $schema_list = NULL, $array_locator = "" )
	{
		$html = self::form_section_header( $field->get_label() )
		      . div
		        (
		            self::form_from_schema( $object,
		                                    $schema_list,
		                                    $field->get_name(),
		                                    $input_name,
		                                    $array_locator ),
		            array('class'=>'section_indent')
		        );
		        
        return $html;
	}
	
	
	public static function form_field_html_array( $field, $input_name, $array, $schema_list = NULL, $array_locator = "" )
	{
		$add_link  = div( self::add_link( $field->get_name(), $array_locator ), array( 'class'=>'btn_panel add_to_array' ));
		$clear_br  = br_clear_all();
					
		$html = "";
			
		if (empty( $array ))
		{
			$html .= div( "<i>(none)</i>", array('class'=>'array_none') );
		}
		else
		{
			$i = 0;
			
			foreach ($array as $key => $row)
			{
				/**
				 * Place the anchor at the last record in the list
				 */
				if (($i+1) == count($array))
				{
					$html .= self::get_edit_anchor( $field->get_name(), $array_locator );
				}
				$i++;
					
				$prefix            = $input_name.'['.$key.']';
				$new_array_locator = self::array_locator_string_push( $array_locator, $input_name, $key );
				
				$html .= self::div_index_display( $key )
				       . div( self::form_from_schema( $row, $schema_list, $field->get_name(), $prefix, $new_array_locator ),
				              array('class'=>'array_row edit') );
			}
		}
			
		$html .= $add_link . $clear_br;
	
		return self::form_section_header( $field->get_label() )
		       . div( $html, array('class'=>'section_indent') );
	}
	
				
	public static function form_section_header( $content )
	{
		return div( "$content", array('class'=>'section_header') );
	}
	
	
	/**
	 * BUTTONS HTML GENERATION
	 */
	
	public static function form_billing_summary_buttons()
	{
		$button = self::form_buttons_submit_button( 'Generate Report' );
		
		return div( $button, 'btn_panel' );
	}

	
	public static function form_booth_list_buttons()
	{
		return self::form_billing_summary_buttons();
	}
	
	
	public static function form_change_password_buttons( $cancel_url = "" )
	{
		$button      = self::form_buttons_submit_button( 'Change Password' );
		$cancel_link = self::form_buttons_cancel_link( $cancel_url );
		
		return div( "$button &nbsp; $cancel_link", 'btn_panel' );
	}

	
	public static function form_create_buttons( $cancel_url = "" )
	{
		$button      = self::form_buttons_submit_button( 'Create' );
		$cancel_link = self::form_buttons_cancel_link( $cancel_url );
		
		return div( "$button &nbsp; $cancel_link", 'btn_panel' );
	}

	
	public static function form_duplicate_buttons( $guid = "", $cancel_url = "" )
	{
		$button      = self::form_buttons_submit_button( 'Duplicate' );
		$cancel_link = self::form_buttons_cancel_link( $cancel_url, $guid );
		
		return div( "$button &nbsp; $cancel_link", 'btn_panel' );
	}
	
	
	public static function form_edit_buttons( $guid = "", $cancel_url = "" )
	{
		$button      = self::form_buttons_submit_button( 'Save' );
		$cancel_link = self::form_buttons_cancel_link( $cancel_url, $guid );
		
		return div( "$button &nbsp; $cancel_link", 'btn_panel' );
	}
	
	
	public static function form_email_list_buttons()
	{
		return self::form_mailing_list_buttons();
	}
	
	
	public static function form_filter_buttons()
	{
		$button     = self::form_buttons_submit_button( 'Filter' );
		$reset_link = self::link_back_to_index( 'Reset', 'reset_filter' );
		
		return div( "$button &nbsp; $reset_link", 'btn_panel' );
	}

	
	public static function form_login_buttons()
	{
		$button = self::form_buttons_submit_button( 'OK' );
		
		return div( $button, 'btn_panel' );
	}

	
	public static function form_mailing_list_buttons()
	{
		return self::form_billing_summary_buttons();
	}
	
	
	public static function form_onsite_list_buttons()
	{
		return self::form_billing_summary_buttons();
	}
	
	
	public static function form_tax_id_list_buttons()
	{
		return self::form_billing_summary_buttons();
	}
	
	
	public static function form_export_buttons( $radio_name, $radio_value,
	                                            $input_name, $input_value,
	                                            $attributes = array() )
	{
		$always_show_s3 = FALSE;
		
		if (!$always_show_s3 && (ENVIRONMENT == 'development'))
		{
			$radio = div( form_hidden( $radio_name, "" )
			              . form_label( 'Export to: ' )
			              . form_input( $input_name, $input_value, 'size="90"' ));
		}
		else
		{
			$id = $radio_name.'1';
			$radio = div( form_radio( array( 'name' => $radio_name, 'id' => $id ), self::S3, ($radio_value === self::S3) )
			              . form_label( 'Export to s3 ('.strtoupper(ENVIRONMENT).')', "", array('onclick'=>"set_checked('$id');") ));
					
			$id = $radio_name.'2';
			$radio .= div( form_radio( array( 'name' => $radio_name, 'id' => $id ), "", ($radio_value !== self::S3) )
			               . form_label( 'Export to: ', "", array( 'onclick' => "set_checked('$id');" ))
			               . form_input( array( 'name'     => $input_name,
			                                    'value'    => $input_value,
			                                    'size'     => '90',
			                                    'onchange' => "set_checked('$id');" )) );
		}
		                         
		$submit_button = self::form_buttons_submit_button( 'Export' );
		
		return div( $radio . $submit_button, $attributes );
	}

	
	public static function form_import_buttons( $confirmed_field_name, $Confirmed, $confirmed_class,
	                                            $remove_old_field_name, $RemoveOld, $remove_class,
	                                            $submit_button_name = "",
	                                            $attributes = array() )
	{
		if (empty( $submit_button_name ))
			$submit_button_name = 'Import';
		
		$submit_button = self::form_buttons_submit_button( $submit_button_name );

		$confirmed_checkbox = div( form_checkbox( array( 'name' => $confirmed_field_name, 'id' => $confirmed_field_name ), self::CHECKED_VALUE, ($Confirmed === self::CHECKED_VALUE) )
                                   . form_label( ' Yes, I\'m really convinced I want to do this', "", array( 'onclick' => "toggle_checked('$confirmed_field_name');" )),
                                   array( 'class' => $confirmed_class ));
                          
        $remove_checkbox = div( form_checkbox( array( 'name' => $remove_old_field_name, 'id' => $remove_old_field_name ), self::CHECKED_VALUE, ($RemoveOld === self::CHECKED_VALUE) )
                                . form_label( ' REPLACE old data when importing', "", array( 'onclick' => "toggle_checked('$remove_old_field_name');" ))
                                . $submit_button,
                                array( 'class' => $remove_class ));

		return div( $confirmed_checkbox . $remove_checkbox, $attributes );
	}

	
	public static function form_show_buttons( $guid = "", $links='edit' )
	{
		switch ($links)
		{
			case 'return_to_list':
				$links = 'return_to_list';
				break;
				
			case 'Advertiser':
			case 'Exhibitor':
				$links = 'return_to_list;edit;invoice;duplicate;create;destroy';
				break;
				
			case 'Contact':
				$links = 'return_to_list;edit;duplicate;create;new_exhibitor;new_advertiser;destroy';
				break;
				
			default:
				$links = 'return_to_list;edit;duplicate;create;destroy';
				break;
		}

		$links_html = self::show_links_html( $guid, $links );
		
		return div( $links_html, array( 'class' => 'btn_panel' ));
	}
	
	
	/**
	 * Returns the cancel link/anchor
	 */
	private static function form_buttons_cancel_link( $url = "", $guid = "" )
	{
		if (!empty( $url ))
		{
			return anchor( $url, 'Cancel' );
		}
		elseif (!empty( $guid ))
		{
			return anchor( self::show_url( $guid ), 'Cancel' );
		}
		else
		{
			return self::link_back_to_index();
		}
	}

	
	private static function form_buttons_submit_button( $title )
	{
		return form_button( array( 'name'    => $title,
		                           'value'   => $title,
		                           'type'    => 'submit',
		                           'content' => $title ));
	}

	
	/**
	 * FORM OPEN METHODS
	 */
	
	/**
	 * Default form_open() for create pages
	 */
	public static function form_open_create()
	{
		$hidden = array( self::get_add_array_name() => self::get_add_array_name() );
		
		return form_open( uri_string(), self::form_open_attributes(), $hidden );
	}
	
	
	/**
	 * Default form_open() for duplicate pages
	 */
	public static function form_open_duplicate( $classname_or_array_of_extra_attributes = NULL )
	{
		$hidden = array( self::get_add_array_name() => self::get_add_array_name() );
		
		return form_open( uri_string(),
		                  self::form_open_attributes( $classname_or_array_of_extra_attributes ),
		                  $hidden );
	}
	
	
	/**
	 * Default form_open() for edit pages
	 */
	public static function form_open_edit()
	{
		$hidden = array( self::get_add_array_name() => self::get_add_array_name() );
		
		return form_open( uri_string(), self::form_open_attributes(), $hidden );
	}
	
	
	/**
	 * Default form_open() for filter forms
	 */
	public static function form_open_filter()
	{
		return form_open( self::get_controller_name(), self::form_filter_attributes() );
	}
	
	
	/**
	 * Default form_open() for generate pages
	 */
	public static function form_open_generate()
	{
		return form_open( uri_string(), self::form_generate_attributes() );
	}
	
	
	/**
	 * Default form attributes for edit pages
	 */
	private static function form_generate_attributes()
	{
		return array( 'id'     => self::get_form_name(),
                      'name'   => self::get_form_name() );
	}
	
	
	/**
	 * Default form attributes for filter forms
	 */
	private static function form_filter_attributes()
	{
		return array( 'id'     => 'filter',
                      'name'   => 'filter' );
	}
	
	
	/**
	 * Default form_open() for edit pages
	 */
	public static function form_open_login( $encoded_uri = "" )
	{
		return form_open( uri_string( $encoded_uri ), self::form_open_attributes() );
	}
	
	
	/**
	 * Default form attributes for edit pages
	 */
	private static function form_open_attributes()
	{
		return array( 'id'   => self::get_form_name(),
                      'name' => self::get_form_name() );
	}
	
	
	public static function form_validation_errors()
	{
		$errors = validation_errors();
		
		return (empty( $errors )) ? ""
		                          : self::error( $errors );
	}

	
	public static function get_add_array_name()
	{
		return self::get_form_name() . "_" . self::ADD_ARRAY_FIELD;
	}
	
	
	private static function get_form_name()
	{
		$action = self::get_action_name();
		
		if ($action == self::ACTION_CREATE)
			return self::CREATE_FORM;
		
		return self::EDIT_FORM;
	}
		
	
	/**
	 * FIELDS DISPLAY (SHOW) PAGE GENERATION
	 */
	
	public static function show_from_schema( $values, $schema_list, $model_name = "", $array_locator = "" )
	{
		
		$html = "";
				
		list( $fields,
		      $field_names,
		      $field_labels ) = ModelSchema::extract_field_details_by_model( $schema_list, $model_name );
		
		foreach ($field_names as $name => $type)
		{
			$html .= self::show_field_html( $values,
			                                $schema_list,
			                                $type,
			                                $name,
			                                self::extract_value( $field_labels, $name, $name ),
			                                $model_name,
			                                $array_locator );
		}
		
		return $html;
	}
	
	
	public static function show_field_html( $values, $schema_list = NULL,
	                                        $field_type = "", $field_name = "", $field_label="",
	                                        $model_name = "", $array_locator = "" )
	{
		$field_default    = '&nbsp;';
		$field_display_as = FALSE;
		
		if (is_a( $values, 'ModelSchemaField' ))
		{
			$field_type       = $values->get_type();
			$field_name       = $values->get_name();
			$field_label      = $values->get_label();
			$field_default    = $values->get_default_value();
			$field_display_as = $values->get_display_as();
			
			/**
			 * Overwrite the object with an array to pass down
			 */
			$values = array( $field_name => $values->get_value() );
		}
		
		if ($field_label === "")
			$field_label = $field_name;
				
		$form_label = form_label( $field_label, $field_name );

		switch( $field_type )
		{
			case ModelSchemaField::TEXT:
			case ModelSchemaField::NUMBER:
			case ModelSchemaField::INTERNAL:
				$value = self::extract_value_with_lookup( $schema_list, $model_name, $field_name, $values, $field_name, '&nbsp;' );
				
				$content = $form_label
				         . div( $value );
				
				$html = div( $content, array( 'class' => 'fields show' ));
				break;
				
			case ModelSchemaField::BOOLEAN:
				$value = ModelSchemaField::boolean_string( self::extract_value( $values, $field_name, false, array("") ));
				
				$content = $form_label
				         . div( $value );
				
				$html = div( $content, array( 'class' => 'fields show' ));
				break;
				
			case ModelSchemaField::PASSWORD:
				$value = ModelSchemaField::password_string( self::extract_value( $values, $field_name, false, array("") ));
				
				$content = $form_label
				         . div( $value )
					     . form_label( "&nbsp;" )
				         . div( self::change_password_link( self::get_page_guid() ), array( 'class'=>'btn_panel' ));
				
				$html = div( $content, array( 'class' => 'fields show' ));
				break;
				
			case ModelSchemaField::TIMESTAMP:
				$value = ModelSchemaField::timestamp_string( self::extract_value( $values, $field_name, '&nbsp;', array("") ),
				                                             $field_display_as );
				
				$content = $form_label
				         . div( $value );
				
				$html = div( $content, array( 'class' => 'fields show' ));
				break;
				
			case ModelSchemaField::_LIST:
			case ModelSchemaField::_NUMBER_LIST:
				$content = $form_label
				           . div( self::extract_list_html( $values, $field_name, '<i>(none)</i>' )
				                  . br_clear_all());
												
				$html = div( $content, array( 'class' => 'fields show' ));
				break;
				
			case ModelSchemaField::_OBJECT:
				$new_array_locator = self::array_locator_string_push( $array_locator, $field_name, $field_name );
						
				$html = self::show_section_header( $field_label )
				      . self::div
				        (
				            self::show_from_schema( self::extract_posted_value( $values, $field_name ), $schema_list, $field_name, $new_array_locator ),
				            array('class'=>'section_indent')
				        );
				break;
				
			case ModelSchemaField::_ARRAY:
				$add_link  = "";
				$edit_link = div( self::edit_link( self::get_page_guid() ), array( 'class'=>'btn_panel edit_all' ));
				$clear_br  = br_clear_all();
								
				$array = self::extract_posted_value( $values, $field_name );
				
				if (empty( $array ))
				{
					$html = $edit_link
					      . div( '<p><i>(none)</i></p>', array('class'=>'array_none') )
					      . $add_link;
				}
				else
				{
					$html = "";
				
					foreach ($array as $key => $row)
					{
						$new_array_locator = self::array_locator_string_push( $array_locator, $field_name, $key );
						$remove_link       = self::div_index_display( $key )
						                   . div( self::remove_link( $field_name, $new_array_locator ), array( 'class'=>'btn_panel remove_from_array' ));
						
						$html .= $remove_link
						       . div( self::show_from_schema($row, $schema_list, strtolower($field_name), $new_array_locator),
						              array('class'=>'array_row') ) . $clear_br;
					}
					
					$html = $edit_link . $html . $add_link;
				}
								
				$html = self::show_section_header( $field_label )
				      . div( $html, array('class'=>'section_indent') );
				
				break;
				
			default:
				$html = "";
				break;
		}
		
		return $html;
	}
	
	
	public static function show_section_header( $content )
	{
		return self::form_section_header( $content );
	}
	

	public static function field_headers( $schema_list, $hidden_fields, $model_name = "" )
	{
		$html = "";
		
		list( $fields,
		      $field_names,
		      $field_labels ) = ModelSchema::extract_field_details_by_model( $schema_list, $model_name );
		
		foreach( $fields as $field )
		{
			if (!in_array( $field->get_name(), $hidden_fields ))
			{
				$sort_using = $field->get_sort_using();
				
				$content = ($sort_using) ? self::sort_link( $sort_using, $field->get_label() )
				                         : $field->get_label();
				
				$html .= "\t<th>$content</th>\n";
			}
		}
		
		return $html;
	}
	
	
	public static function field_header_actions()
	{
		return "\t<th align=\"center\">Actions</th>\n";
	}
	
	
	public static function sorted_by_html( $sorted_by_html )
	{
		return div( "$sorted_by_html", array( 'class' => 'btn_panel sorted_by' ));
	}

	
	public static function filter_form_html( ModelSchema $filter_object, array $filter )
	{
		$html = "";
		
		if (!empty( $filter_object ))
		{
			$html .= self::form_open_filter();
			$html .= fieldset_open( 'Filter by', 'filter_by' );
			
			$html .= self::form_from_schema( $filter,
			                                 $filter_object->get_schema(),
			                                 get_class( $filter_object ) );
			
			$html .= self::form_filter_buttons();
			$html .= fieldset_close();
			$html .= form_close();
		}
		
		return $html;
	}
	
	
	public static function pagination_html( $pagination, $description = NULL, $size_selector = NULL )
	{
		$html = "";
		
		if (!empty( $pagination ))
			$html = div( $pagination, array( 'class' => 'pagination' ));
		
		if (!empty( $size_selector ))
			$html .= div( $size_selector, array( 'class' => 'pagination_size_selector' ));
			
		if (!empty( $description ))
			$html .= div( $description, array( 'class' => 'pagination_description' ));
			
		return $html;
	}

	
	/**
	 * EXTRACT (DATA MANIPULATION) UTILITY FUNCTIONS
	 */
	
	public static function extract_array_html( $field_definitions, $array, $field, $default_value = "" )
	{
		$html = $default_value;
			
		if (isset( $array[$field] ) && is_array( $array[$field] ))
		{
			$html = '<ul>';
			
			foreach( $array[$field] as $key => $row )
			{
				$values = array();
				
				foreach( $field_definitions as $field_name => $field_type )
				{
					if ($field_type != 'guid')
						$values[] = self::extract_field( $row, $field_name );
				}
				
				if (empty($values))
				{
					$html .= '<li>' . print_r( $field_definitions, TRUE ) . '</li>';
					$html .= '<li>' . print_r( $field, TRUE ) . '</li>';
					$html .= '<li>' . print_r( $array, TRUE ) . '</li>';
				}
				else
				{
					$values_string = array();
					
					foreach( $values as $a_value )
					{
						$values_string[] = (is_scalar($a_value)) ? $a_value : print_r($a_value,TRUE);
					}
					
					$html .= '<li>' . $key . ': ' . implode(", ", $values_string) . '</li>';
				}
			}
			
			$html .= '</ul>';
		}
		
		return $html;
	}
	
	
	public static function extract_field( $array, $field, $default_value = "" )
	{
		return (isset( $array[$field] )) ? $array[$field] : $default_value;
	}
	
	
	public static function extract_field_html( $array, $field, $model_field = NULL, $default_value = "" )
	{
		$value = self::extract_field( $array, $field, $default_value );
		
		if (method_exists( $model_field, 'display_string' ))
		{
			$value = $model_field->display_string( $value );
		}
		
		$css_class = $field;
		$trailing  = "\n";
		
		return _tag( 'span',
		             $value,
		             $css_class,
		             $trailing );
	}

	
	public static function extract_list_html( $array, $field, $default_value = "" )
	{
		if (isset( $array[$field] ) && is_array( $array[$field] ))
		{
			$ret = ModelSchemaField::extract_list_string( $array, $field );
			
			if ($ret !== "")
				return $ret;
		}
		
		return $default_value;
	}
	

	public static function extract_object_html( $field_definitions, $array, $field, $default_value = "" )
	{
		$html = $default_value;

		if (isset( $array[$field] ) && is_array( $array[$field] ))
		{
			$html = '<div>';
			
			foreach( $field_definitions as $field_name => $field_type )
			{
				if (($field_type != 'guid') && ($field_type != 'object') && ($field_type != 'array'))
					$html .= '<li>' . $field_type->get_label() . ': ' . self::extract_field_html( $array[$field], $field_name, $field_type ) . '</li>';
			}
			
			$html .= '</div>';
		}
		
		return $html;
	}
	

	public static function extract_posted_value( $previous_input, $variable_name, $default = FALSE )
	{
		if (!method_exists($previous_input, 'post'))
		{
			return self::extract_value( $previous_input, $variable_name, $default );
		}
		else
		{
			$post = $previous_input->post();
			
			return (empty( $post )) ? $default
			                        : $previous_input->post( $variable_name );
		}
	}

	
	public static function extract_value( $hash, $value, $default = "", array $default_if_in = NULL )
	{
		if (!isset( $hash[$value] )
		    || ((!empty($default_if_in) && (in_array($hash[$value],$default_if_in,TRUE) ))))
		{
			return $default;
		}
		
		return $hash[$value];
	}
	
	
	public static function extract_value_with_lookup( $schema_list, $model_name, $field_name,
	                                                  $hash, $value, $default = "", array $default_if_in = array("") )
	{
		$starting_value = self::extract_value( $hash, $value, $default, $default_if_in );
		
		$ret = str_replace( array('\n', "\n", chr(11)), '<br>', $starting_value );
		$key = ModelSchema::model_2_index_fields( $model_name );
				
		if ($model_name
		    && $field_name
		    && isset( $schema_list[ $key ],
		              $schema_list[ $key ][$field_name] ) )
		{
			log_message( 'info', "Looking up value for field $field_name" );
			
			$lookup_field = $schema_list[ $key ][ $field_name ];
			
			if ($lookup_field->has_autocomplete_validation())
			{
				$ret = $lookup_field->lookup_autocomplete_string( $ret );
			}
			else
			{
				if (!$lookup_field->has_options())
					$lookup_field->set_options_from_options_lookup();
					
				$lookup = $lookup_field->get_options();
			
				if (isset( $lookup[ $ret ] ))
					$ret = $lookup[ $ret ];
			}
			
			$display_as = $lookup_field->get_display_as();
			
			if ($display_as)
			{
				$display_as  = explode( ":", $display_as );
				$only_action = extract_value( $display_as, 3 );
				
				if (empty( $only_action ) || ($only_action == self::get_action_name() ))
				{
					if (strcasecmp($display_as[0], 'link') == 0)
					{
						$href = sprintf( $display_as[1], $starting_value );
						
						if (strncmp( $href, 'http', 4 ) != 0)
							$href = site_url( $href );
						
						$title = (isset( $display_as[2] ))
						            ? $display_as[2]
						            : "jump to this " . $lookup_field->get_label() . " record";
						
						$ret   = "<a href=\"$href\" title=\"$title\">$ret</a>";
					}
					elseif (strcasecmp($display_as[0], 'mailto') == 0)
					{
						$href = $starting_value;
						
						if (strncmp( $href, 'mailto', 6 ) != 0)
							$href = "mailto://$href?to=$starting_value";
						
						$title = (isset( $display_as[2] ))
						            ? $display_as[2]
						            : "send email to this address via your Mail application";
						
						$ret   = "<a href=\"$href\" target=\"_blank\" title=\"$title\">$ret</a>";
					}
					elseif (strcasecmp($display_as[0], 'url') == 0)
					{
						$href = $starting_value;
						
						if (strncmp( $href, 'http', 4 ) != 0)
							$href = "http://$href";
						
						$title = (isset( $display_as[2] ))
						            ? $display_as[2]
						            : "jump to this external link (in a separate window)";
						
						$ret   = "<a href=\"$href\" target=\"_blank\" title=\"$title\">$ret</a>";
					}
				}
			}
		}
		
		return $ret;
	}
	
	
	/**
	 * OTHER BUTTONS AND NAVIGATION LINKS
	 */
	
	private static function show_links_html( $guid, $links_list = "" )
	{
		$links = explode( ";", $links_list );
		$html    = "";
		
		foreach( $links as $one_link )
		{
			$method_name = $one_link."_link";
			
			if (method_exists( __CLASS__, $method_name ))
				$html .= self::$method_name( $guid );
			else
				$html .= self::link( $one_link, $guid );
		}
		
		return $html;
	}
	
	
	public static function create_links_panel( $section, $class = "" )
	{
    	$html = self::link( self::ACTION_CREATE, "",
			                array( 'title' => 'create a new one' ) );
    	
		if (($section == 'Exhibitor')
		    || ($section == 'Advertiser'))
    	{
			$html .= self::link( self::ACTION_INVOICES, "",
			                     array( 'title' => 'download invoices for shown records' ));
    	}
    	
    	return div( $html, "btn_panel $class" );
	}
	
	
	public static function anchor_link( $action, $link_html, $class = "" )
    {
    	if (empty( $class ))
    		$class = "btn";
    	
    	return anchor( self::get_controller_name().'/'.$action, $link_html, array('class' => $class) );
    }
    
    
	public static function add_link( $array_name, $array_locator )
	{
		$guid = self::get_page_guid();
		
		$class   = "btn add";
		$title   = "add a new $array_name record";
		$image   = '<img src="' . base_url() . 'assets/image/create.png" alt="add" border="0" align="absmiddle">';
		$url     = self::add_url( $guid, $array_name, $array_locator );
		$onclick = self::add_onclick( $guid, $array_name, $array_locator );
		
		return "<a href=\"$url\"$onclick class=\"$class\" title=\"$title\">$image Add a new $array_name record</a>";
	}
	

	private static function change_password_button( $id = "", $title = "" )
	{
		if (empty( $id ))
			$id = self::get_page_guid();
			
		if (empty( $title ))
			$title = "change the password for this entry";
		
		$class = "btn_panel change_password";
		$image = '<img src="' . base_url() . 'assets/image/lock.png" alt="change password" border="0" align="absmiddle">';
		$url   = self::change_password_url( $id );

		return "<a href=\"$url\" class=\"$class\" title=\"$title\">$image</a>";
	}
	

	private static function change_password_link( $id = "", $title = "" )
	{
		return self::link( 'change_password', $id, array( 'title' => $title ));
		
		if (empty( $id ))
			$id = self::get_page_guid();
			
		if (empty( $title ))
			$title = "change the password for this entry";
		
		$class = "btn_panel change_password";
		$image = '<img src="' . base_url() . 'assets/image/lock.png" alt="change password" border="0" align="absmiddle">';
		$url   = self::change_password_url( $id );

		return "<a href=\"$url\" class=\"$class\" title=\"$title\">$image Change Password...</a>";
	}
	

	private static function create_link()
	{
		return self::link( self::ACTION_CREATE, "", array( 'title' => 'create a new record' ) );
	}
	

	public static function destroy_button( $id = "" )
	{
		$class = "btn destroy";
		$title = "permanently destroy this record -- THIS ACTION CANNOT BE UNDONE!";
		$image = '<img src="' . base_url() . 'assets/image/destroy.png" alt="destroy" border="0" align="absmiddle">';
		$url   = current_url() . "/destroy/" . $id;
				
		return "<a href=\"$url\" onclick=\"return confirm_destroy();\" class=\"$class\" title=\"$title\">$image</a>";
	}
	

	private static function destroy_link( $id = "" )
	{
		return self::link( 'destroy', $id, array( 'title'   => 'permanently destroy this record -- THIS ACTION CANNOT BE UNDONE!',
		                                          'onclick' => 'return confirm_destroy();' ));
	}
	
	
	public static function duplicate_button( $id = "" )
	{
		$class = "btn duplicate";
		$title = "duplicate this record";
		$image = '<img src="' . image_asset_url('duplicate.png') . '" alt="duplicate" border="0" align="absmiddle">';
		$url   =  site_url( self::get_controller_name( "duplicate/$id" ) );
		
		return "<a href=\"$url\" class=\"$class\" title=\"$title\">$image</a>";
	}
	

	public static function edit_button( $id, $title="" )
	{
		$class = "btn edit";
		$image = '<img src="' . base_url() . 'assets/image/edit.png" alt="edit" border="0" align="absmiddle">';
		$url   = self::edit_url( $id );

		if (empty( $title ))
			$title = "edit details for this record";
		
		return "<a href=\"$url\" class=\"$class\" title=\"$title\">$image</a>";
	}
	
	
	public static function invoice_button( $id = "" )
	{
		$class = "btn invoice";
		$title = "download invoice for this record";
		$image = '<img src="' . image_asset_url('invoice.png') . '" alt="invoice" border="0" align="absmiddle">';
		$url   =  site_url( self::get_controller_name( "invoice/$id" ) );
		
		return "<a href=\"$url\" class=\"$class\" title=\"$title\">$image</a>";
	}
	
		
	public static function new_advertiser_link( $id = "" )
	{
		return self::link( 'new_advertiser', $id, array( 'url'   => site_url( "advertisers/create/$id" ),
		                                                 'title' => 'create a new advertiser for this contact' ));
	}
	

	public static function new_exhibitor_link( $id = "" )
	{
		return self::link( 'new_exhibitor', $id, array( 'url'   => site_url( "exhibitors/create/$id" ),
		                                                'title' => 'create a new exhibitor for this contact' ));
	}
	

	private static function sort_link( $sort_field, $label )
	{
		$class = "sort_link";
		$title = "sort list by $label";
		$image = '<img src="' . base_url() . 'assets/image/edit.png" alt="sort" border="0" align="absmiddle">';
		$url   = self::sort_url( $sort_field );
				
		return "<a href=\"$url\" class=\"$class\" title=\"$title\">$label</a>";
	}
	
	
	public static function show_button( $id = "" )
	{
		$class = "btn show";
		$title = "show details for this record";
		$image = '<img src="' . base_url() . 'assets/image/show.png" alt="show" border="0" align="absmiddle">';
		$url   = site_url( self::get_controller_name() ) . "/show/" . $id;
				
		return "<a href=\"$url\" class=\"$class\" title=\"$title\">$image</a>";
	}
	

	public static function remove_link( $array_name, $array_locator )
	{
		$guid = self::get_page_guid();
		
		$class = "btn remove";
		$title = "destroy this $array_name record";
		$image = '<img src="' . base_url() . 'assets/image/destroy.png" alt="remove" border="0" align="absmiddle">';
		$url   = self::remove_url( $guid, $array_name, $array_locator );
				
		return "<a href=\"$url\" class=\"$class\" title=\"$title\">$image</a>";
	}
	

	private static function return_to_list_link( $id = "" )
	{
		return self::link( 'return_to_list', "", array( 'action' => 'index', 'title' => 'return to list view ' ));
	}
	

	private static function reset_to_default_link( $id = "" )
	{
		return self::link( 'reset_to_default', $id, array( 'title'   => 'reset to gameserver default values',
		                                                   'onclick' => 'return confirm_reset_to_default();' ));
	}
	

	private static function link( $name, $id = "", $attributes = array() )
	{
		$src    = extract_value( $attributes, 'image',   "{$name}.png" );
		$align  = extract_value( $attributes, 'align',   "absmiddle" );
		$alt    = extract_value( $attributes, 'alt',     "[$name]" );
		$border = extract_value( $attributes, 'border',  0 );
		$height = extract_value( $attributes, 'height',  NULL );
		$width  = extract_value( $attributes, 'width',   NULL );
		
		$image  = image_asset( $src, "", array( 'align'  => $align,
		                                        'alt'    => $alt,
		                                        'border' => $border,
		                                        'height' => $height,
		                                        'width'  => $width ));
		
		$class   = extract_value( $attributes, 'class',   "btn boxed $name" );
		$onclick = extract_value( $attributes, 'onclick', NULL );
		$title   = extract_value( $attributes, 'title',   "$name this record" );
		$url     = extract_value( $attributes, 'url' );
		
		if (empty( $url ))
		{
			if (($action = extract_value( $attributes, 'action',  "$name/$id" )) == 'index')
				$action = "";
			
			$url = site_url( self::get_controller_name( $action ) );
		}
		
		$content = $image." ".ucfirst( str_replace( "_", " ", $name ));
		
		return anchor( $url, $content, array( 'class'   => $class,
		                                      'onclick' => $onclick,
		                                      'title'   => $title ));
	}
	
	
	public static function get_edit_anchor( $array_name, $array_locator )
	{
		$in_loc   = self::array_locator_string_push( $array_locator, $array_name );
		$this_loc = self::array_locator_string_push( self::get_submitted_array_locator() );
		
		return ($in_loc == $this_loc) ? '<a name="'.self::EDIT_ANCHOR.'"></a>'
		                              : "";
	}
	
	
	public static function back_button_html( $content = 'Try again' )
	{
		$html = '<br><button onclick="history.back(-1)">'.$content.'</button>';
		
		return $html;
	}
	

	/**
	 * PAGE MESSAGING
	 */

	public static function messages( array $messages )
	{
		$error  = self::extract_value( $messages, 'error_message' );
		$notice = self::extract_value( $messages, 'notice_message' );
		
		return self::error_message( $error )
		       . self::notice_message( $notice );
	}
	
	
	public static function error_message( $error_message )
	{
		return (empty( $error_message ))
		          ? ""
		          : self::error( $error_message );
	}
	
	
	public static function notice_message( $notice_message )
	{
		return (empty( $notice_message ))
		          ? ""
		          : self::notice( $notice_message );
	}
	
	
	public static function error( $html )
	{
		return div( $html, array('class' => 'error') );
    }
    
    
	public static function notice( $html )
	{
		return div( $html, array('class' => 'notice') );
    }
    
    
    /**
     * HTML GENERATION
     */
    
	public static function div_index_display( $index )
	{
		return div( "$index", array( 'class'=>'btn_panel remove_from_array index_display' ));
	}
	
	
	public static function td_actions_buttons( $guid, $buttons='show;edit;destroy' )
	{
		switch ($buttons)
		{
			case 'Contact':
				$buttons = 'show;edit';
				break;
				
			case 'Advertiser':
			case 'Exhibitor':
				$buttons = 'show;edit;invoice;duplicate;create;destroy';
				break;
				
			default:
				$buttons = 'show;edit;duplicate;create;destroy';
				break;
		}
		
		$buttons      = explode( ";", $buttons );
		$actions_html = "";
		
		foreach( $buttons as $one_button )
		{
			$method_name = $one_button."_button";
			
			if (method_exists( __CLASS__, $method_name ))
			{
				$actions_html .= self::$method_name( $guid );
			}
		}
		
		if ($actions_html === "")
			$actions_html = "&nbsp;";
		
		return td( $actions_html, array('class'=>'actions') );
    }
	
	
	/**
	 * ADD ARRAY SUPPORT METHODS
	 */
	
	public static function array_locator_string_push()
	{
		return ltrim( implode( ":", func_get_args() ), ":" );
	}

	
	public static function array_locator_string_explode( $string )
	{
		return explode( ":", ltrim( $string, ":" ));
	}

	
	/**
	 * URI/CONTROLLER UTILITY METHODS
	 */
	
	private static function add_url( $id, $array_name, $array_locator = "" )
	{
		if (empty( $id ))
			$id = 1;
		
		$array_name = str_replace( " ", "_", $array_name );
		
		return site_url() . "/". self::get_controller_name() . rtrim( "/add_to_array/$id/$array_name/$array_locator", "/" );
	}
	
	
	private static function add_onclick( $id, $array_name, $array_locator = "" )
	{
		if (!self::in_edit_mode() && !self::in_create_mode())
			return "";

		if (empty( $id ))
			$id = 1;
		
		$array_name = str_replace( " ", "_", $array_name );
		$form_name  = self::get_form_name();
		$field_name = self::get_add_array_name();
		
		$value = implode( ":", array( $array_locator, $array_name ));
		
		return " onclick=\"return submit_add_array('$form_name', '$field_name', '$value');\"";
	}
	
	
	public static function remove_url( $id, $array_name, $array_locator = "" )
	{
		$array_name = str_replace( " ", "_", $array_name );
		
		return site_url() . "/". self::get_controller_name() . rtrim( "/remove_from_array/$id/$array_name/$array_locator", "/" );
	}
	
	
	public static function edit_url( $guid )
	{
		return site_url() . "/". self::get_controller_name() . "/edit/" . $guid;
	}
	

	private static function sort_url( $sort_field )
	{
		return site_url( self::get_controller_name( "sort/$sort_field" ) );
	}
	

	public static function change_password_url( $guid )
	{
		return site_url() . "/". self::get_controller_name() . "/change_password/" . $guid;
	}
	

	public static function in_create_mode()
	{
		return (self::get_action_name() == self::ACTION_CREATE);
	}
	
	
	public static function in_edit_mode()
	{
		return (self::get_action_name() == self::ACTION_EDIT
		        || self::get_action_name() == self::ACTION_CHANGE_PASSWORD);
	}
	
	
	public static function in_login_mode()
	{
		return (self::get_action_name() == self::ACTION_LOGIN);
	}
	
	
	public static function get_controller_name( $append = "" )
	{
		$controller_name = reset( explode("/", ltrim(uri_string(),'/') ));
		
		if ( $append )
			$controller_name .= "/$append";
		
		return $controller_name;
	}
	
	
	public static function get_action_name()
	{
		/**
		 * BIIIIG ASSUMPTION: Url is like "http://domainname/controller/action/GUID/etc/etc",
		 *   so the uri_string will be "controller/action/GUID/etc/etc"
		 */
		return self::get_uri_segment(1);
	}
	
	
	public static function get_page_guid()
	{
		/**
		 * BIIIIG ASSUMPTION: Url is like "http://domainname/controller/action/GUID/etc/etc",
		 *   so the uri_string will be "controller/action/GUID/etc/etc"
		 */
		return self::get_uri_segment(2);
	}
	
	
	public static function get_submitted_array_locator()
	{
		return self::$submitted_array_locator;
	}
	
	
	public static function set_submitted_array_locator( $value )
	{
		self::$submitted_array_locator = $value;
	}
	
	
	public static function get_uri_segment( $segment_number )
	{
		$uri = explode("/", uri_string());
		
		$segment_number = intval( $segment_number );
		
		if (isset( $uri[$segment_number] ))
			return $uri[$segment_number];
		
		return "";
	}

	
	public static function try_again_button_html( $display_text = 'Try Again' )
	{
		return self::link_back_to_index( "<span><button>$display_text</button></span>" );
	}
	
	
	public static function link_back_to_index( $display_text = 'Cancel', $append_to_link = "" )
	{
		return anchor( self::get_controller_name( $append_to_link ),
		               $display_text );
	}
	
	
	public static function show_url( $guid )
	{
		return site_url() . "/". self::get_controller_name() . "/show/" . $guid;
	}
	
}

/* End of file DK_html_helper.php */
/* Location ./application/helpers/DK_html_helper.php */
