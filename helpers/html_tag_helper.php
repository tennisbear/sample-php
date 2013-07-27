<?php
/**
 * HTML tag helper functions
 *
 * @author Dennis Slade
 * @since  2011-03-07
 */


/**
 * SIMPLE TAGS
 */

function h1( $contents, $attributes = NULL )
{
	return tag( 'h1', $contents, $attributes );
}


function h2( $contents, $attributes = NULL )
{
	return tag( 'h2', $contents, $attributes );
}


function h3( $contents, $attributes = NULL )
{
	return tag( 'h3', $contents, $attributes );
}


function div( $contents, $attributes = NULL )
{
	return tag( 'div', $contents, $attributes );
}


function br_clear_all( $contents = "" )
{
	return '<br clear="all">' . "\n"
	       . $contents . "\n";
}


/**
 * TABLES
 */

function th( $contents, $attributes = NULL )
{
	if (is_array( $contents ))
	{
		$ret = "";
		
		foreach( $contents as $cell )
			$ret .= th( $cell, $attributes );
		
		return $ret;
	}
	else
	{
		return "\t<th"
		       . _ht_attribute_text_from_array( $attributes )
		       . ">$contents</th>\n";
	}
}

	
function td( $contents, $attributes = NULL )
{
	if (is_array( $contents ))
	{
		$ret = "";
		
		foreach( $contents as $cell )
			$ret .= td( $cell, $attributes );
		
		return $ret;
	}
	else
	{
		return "\t<td"
		       . _ht_attribute_text_from_array( $attributes )
		       . ">$contents</td>\n";
	}
}

	
function table_open( $css_class = "" )
{
	$css_class = (empty( $css_class )) ? ""
	                                   : " class=\"$css_class\"";

	return "<table$css_class>\n";
}


function table_close()
{
	return "</table>\n";
}


function tr( $contents )
{
	return tr_open()
	       . $contents
	       . tr_close();
}


function tr_open( $css_class = "" )
{
	return _tag_open( 'tr', $css_class ) . "\n";
}


function tr_close()
{
	return "</tr>\n";
}


function thead( $contents )
{
	return thead_open()
	       . $contents
	       . thead_close();
}


function thead_open()
{
	return "<thead>\n";
}


function thead_close()
{
	return "</thead>\n";
}


function td_amount( $number, $css_class = "" )
{
	return td( amount_html( $number ), trim( "amount $css_class" ) );
}


function td_quantity( $number, $css_class = "" )
{
	return td( quantity_html( $number ), trim( "quantity $css_class" ) );
}


function td_subtotal( $number, $css_class = "" )
{
	return td( subtotal_html( $number ), trim( "subtotal $css_class" ));
}


/**
 *
 */

function amount_html( $number )
{
	$html = "<span class=\"amount\">"
	      . display_with_currency( $number )
	      . "</span>";
	                    
	return $html;
}


function quantity_html( $number )
{
	$number = ($number == floor($number))
	              ? sprintf( "%d", $number )
	              : rtrim( sprintf( "%f", $number ), "0");
	
	$html = "<span class=\"quantity\">"
	      . $number
	      . "</span>";
	                    
	return $html;
}


function subtotal_html( $number )
{
	$html = "<span class=\"subtotal-amount\">"
	      . display_with_currency( $number )
	      . "</span>";
	                    
	return $html;
}


function display_with_currency( $number )
{
	return money_format( '%(#6n', $number );
}


function fieldset( $contents, $legend = "", $css_class = "" )
{
	if ($legend)
		$contents = "<legend>&nbsp;$legend&nbsp;</legend>$contents";
	
	return fieldset_open( $css_class )
	       . $contents
	       . fieldset_close();
}


function fieldset_open( $legend = "", $css_class = "" )
{
	$legend = (empty( $legend )) ? ""
	                             : _tag( 'legend', $legend );
	
	return _tag_open( 'fieldset', $css_class ) . $legend;
}


function fieldset_close( $trailing_html = "" )
{
	return _tag_close( 'fieldset', $trailing_html );
}


/**
 * LINKS
 */

function _ht_link( $name, $id = "", $attributes = array() )
{
	$src    = _ht_extract_value( $attributes, 'image',   "{$name}.png" );
	$align  = _ht_extract_value( $attributes, 'align',   "absmiddle" );
	$alt    = _ht_extract_value( $attributes, 'alt',     "[$name]" );
	$border = _ht_extract_value( $attributes, 'border',  0 );
	$height = _ht_extract_value( $attributes, 'height',  NULL );
	$width  = _ht_extract_value( $attributes, 'width',   NULL );
	
	$image  = image_asset( $src, "", array( 'align'  => $align,
	                                        'alt'    => $alt,
	                                        'border' => $border,
	                                        'height' => $height,
	                                        'width'  => $width ));
	
	$class   = _ht_extract_value( $attributes, 'class',   "btn boxed $name" );
	$onclick = _ht_extract_value( $attributes, 'onclick', NULL );
	$title   = _ht_extract_value( $attributes, 'title',   "$name this record" );
	$url     = _ht_extract_value( $attributes, 'url' );
	
	if (empty( $url ))
	{
		if (($action = _ht_extract_value( $attributes, 'action',  "$name/$id" )) == 'index')
			$action = "";
		
		$url = site_url( _ht_get_controller_name( $action ) );
	}
	
	$content = $image." ".ucfirst( str_replace( "_", " ", $name ));
	
	return anchor( $url, $content, array( 'class'   => $class,
	                                      'onclick' => $onclick,
	                                      'title'   => $title ));
}
	
	
function _ht_link_back_to_index( $display_text = 'Cancel', $append_to_link = "" )
{
	return anchor( _ht_get_controller_name( $append_to_link ),
	               $display_text );
}


function _ht_get_controller_name( $append = "" )
{
	if (function_exists( 'uri_string' ))
	{
		$uri = uri_string();
	}
	else
	{
		$uri = _ht_extract_value( $_SERVER, 'PATH_INFO' );
		
		if (!$uri)
		{
			$request_uri = _ht_extract_value( $_SERVER, 'REQUEST_URI' );
			$script_name = _ht_extract_value( $_SERVER, 'SCRIPT_NAME' );
			
			if ($script_name && $request_uri)
			{
				$uri = str_replace( $script_name, "", $request_uri );
			}
			else
			{
				$uri    = array();
				$add_em = FALSE;
			
				foreach( explode( "/", $script_name ) as $segment )
				{
					if (!$add_em && ($segment == 'index.php'))
					{
						$add_em = TRUE;
					}
					elseif ($add_em)
					{
						$uri[] = $segment;
					}
				}
				
				$uri = implode( "/", $uri );
			}
		}
		
	}
	
	$controller_name = reset( explode("/", ltrim($uri,"/") ));
	
	if ( $append )
		$controller_name .= "/$append";
	
	return $controller_name;
}


/**
 * BASE FUNCTIONS
 */

function tag( $tag, $contents, $attributes = NULL )
{
	return "\n<$tag"
	       . _ht_attribute_text_from_array( $attributes )
	       . ">$contents</$tag>";
}


function _tag( $tag, $contents, $css_class="", $trailing_html="" )
{
	return _tag_open( $tag, $css_class )
	       . $contents
	       . _tag_close( $tag, $trailing_html );
}


function _tag_open( $tag, $css_class = "" )
{
	$css_class = (empty( $css_class )) ? ""
	                                   : " class=\"$css_class\"";
	                                   
	return "<$tag$css_class>\n";
}


function _tag_close( $tag, $trailing_html = "" )
{
	return "\n</$tag>$trailing_html";
}


function _ht_attribute_text_from_array( $attributes = NULL )
{
	/**
	 * Turn objects into arrays
	 */
	if (is_object( $attributes ))
	{
		$attributes = get_object_vars( $attributes );
	}
	
	/**
	 * We're done already if we have an empty array, an empty string, FALSE or NULL
	 */
	if (empty( $attributes ))
		return "";
	
	if (is_scalar( $attributes ))
	{
		/**
		 * Force all scalars to be strings
		 */
		$attributes = "$attributes";
		
		return (strpbrk( $attributes, '="\'' ))
		          ? " $attributes"
		          : (' class="'.quotes_to_entities($attributes).'"');
	}

	$text = "";
	
	foreach( $attributes as $name => $value )
		$text .= " $name=\"" . quotes_to_entities($value) . "\"";
		
	return $text;
}


function _ht_extract_value( $hash, $value, $default="", array $default_if_in = NULL )
{
	if (!isset( $hash[$value] )
	    || ((!empty($default_if_in) && (in_array($hash[$value],$default_if_in,TRUE) ))))
	{
		return $default;
	}
	
	return $hash[$value];
}

/* End of file html_tag_helper.php */
/* Location ./application/helpers/html_tag_helper.php */