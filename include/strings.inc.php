<?php
/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
include/strings.inc.php - string handling functions
--------------------------------------------------------------------------------

This file is part of Lichen. Lichen is free software; you can redistribute it
and/or modify it under the terms of the GNU General Public License as published
by the Free Software Foundation; either version 3 of the License, or (at your
option) any later version.

Lichen is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <http://www.gnu.org/licenses/>.

*/


// Returns a hash generated from an attachment's file name, plus the file's
// extension on the end, to identify attachments when composing messages.
function hashifyFilename( $filename ) {
	$fileBits = explode( ".", $filename );
	$extension = "";
	if ( count( $fileBits ) > 1 ) {
		$extension = "." . $fileBits[ count( $fileBits ) - 1 ];
		$extension = str_replace( array( "/", "\\" ), "", $extension );
	}

	return sha1( $filename ) . $extension;
}


// Check if a given string is likely an email address.
function isEmailAddress( $address ) {
	if ( preg_match( "/^([\w\d])+([\w\d\._])*@([\w\d_-])+([\w\d\._-]+)+$/", $address ) ) {
		return true;
	}
	return false;
}


// Function to interpret whatever the user enters into the
// To / Cc / Bcc composer fields.
// Returns an array of arrays, each of which has two keys:
// 'name' and 'address'.
// TODO: Handle the case where input is "email@address (Persons Name)"
function parseRecipientList( $inputString ) {
	// Split up the input on any of " ", ",", or ";".
	$inputBits = preg_split( "/[\s,;]/", $inputString, -1, PREG_SPLIT_NO_EMPTY );

	$foundAddresses = array();
	$currentName = "";
	// Iterate over the parts.
	foreach ( $inputBits as $thisBit ) {

		$thisBit = trim( $thisBit );

		// If it starts with < and ends with > strip those.
		if ( $thisBit[0] == "<" && $thisBit[strlen($thisBit) - 1] == ">" ) {
			$thisBit = substr( $thisBit, 1, strlen($thisBit) - 2 );
		}
		// If it starts with " and doesn't finish with " strip the ".
		if ( $thisBit[0] == '"' && $thisBit[strlen($thisBit) - 1] != '"' ) {
			$thisBit = substr( $thisBit, 1 );
		}
		// If it doesn't start with " and finishes with " strip the ".
		if ( $thisBit[0] != '"' && $thisBit[strlen($thisBit) - 1] == '"' ) {
			$thisBit = substr( $thisBit, 0, strlen($thisBit) - 1 );
		}

		if ( isEmailAddress( $thisBit ) ) {
			// We think it's an email address, add the address.
			$foundAddresses[] = array( "name" => trim( $currentName ), "address" => $thisBit );
			$currentName = "";
		} else {
			// This is probably part of a name. Add it to what we
			// know about it, for when we find the email address.
			$currentName .= $thisBit . " ";
		}
	}

	return $foundAddresses;
}

// This function takes an array as generated by parseRecipientsList(), and
// turns it back into a string of addresses.
function formatRecipientList( $recpList ) {
	
	$result = array();

	foreach ( $recpList as $recipient ) {
		$working = "";

		if ( !empty( $recipient['name'] ) ) {
			$working .= "\"{$recipient['name']}\" ";
		}

		$working .= "<{$recipient['address']}>";

		$result[] = $working;
	}

	return implode( ", ", $result );
}


// Given an object/array that is returned from the IMAP server that contains
// email addresses in the obscure personal/mailbox/host format, return a
// comma seperated list of email addresses.
function formatIMAPAddress( $object ) {
	if ( !isset( $object ) ) return "";

	$workingArray = $object;

	if ( !is_array( $object ) ) {
		$workingArray = array( $object );
	}

	$addressList = array();
	foreach ( $workingArray as $workingAddress ) {
		$address = "";

		if ( isset( $workingAddress->personal ) && !empty( $workingAddress->personal ) ) {
			$address = "\"{$workingAddress->personal}\" ";
		}

		$address .= "<";
		if ( isset( $workingAddress->mailbox ) && !empty( $workingAddress->mailbox ) ) {
			$address .= "{$workingAddress->mailbox}@";
		}
		if ( isset( $workingAddress->host ) && !empty( $workingAddress->host ) ) {
			$address .= "{$workingAddress->host}";
		}
		$address .= ">";

		$addressList[] = $address;
	}

	return implode( ", ", $addressList );
}


// From http://php.net/manual/en/function.number-format.php
function formatNumberBytes( $bytes ) {
	// Woohoo! Let's see emails of PB size!
	$unim = array( "B", "kB", "MB", "GB", "TB", "PB" );
	$c = 0;
	while ( $bytes >= 1024 ) {
		$c++;
		$bytes = $bytes/1024;
	}
	return number_format( $bytes, ( $c ? 2 : 0 ) ) . $unim[$c];
}


// Given a string from a message, its character set, and its transfer-encoding,
// return that string converted to normal UTF-8 suitable for display.
function decodeText( $string, $charset, $transferEncoding ) {
//	$string = imap_utf8( $string );

	if ( $transferEncoding == "quoted-printable" ) {
		$string = imap_qprint( $string );
	} elseif ( $transferEncoding == "base64" ) {
		$string = imap_base64( $string );
	}

	$string = convertToUTF8( $string, $charset );

	return $string;
}


/* These functions copied from http://php.net/html_entity_decode
   -- a workaround for PHP4 issues with that function */
if ( version_compare( PHP_VERSION, '5.0.0', '<' ) ) {
	function html_entity_decode_utf8($string)
	{
		static $trans_tbl;

		// replace numeric entities
		$string = preg_replace('~&#x([0-9a-f]+);~ei', 'code2utf(hexdec("\\1"))', $string);
		$string = preg_replace('~&#([0-9]+);~e', 'code2utf(\\1)', $string);

		// replace literal entities
		if (!isset($trans_tbl))
		{
			$trans_tbl = array();

			foreach (get_html_translation_table(HTML_ENTITIES) as $val=>$key)
				$trans_tbl[$key] = utf8_encode($val);
		}

		return strtr($string, $trans_tbl);
	}

	// Returns the utf string corresponding to the unicode value (from php.net, courtesy - romans@void.lv)
	function code2utf($num)
	{
		if ($num < 128) return chr($num);
		if ($num < 2048) return chr(($num >> 6) + 192) . chr(($num & 63) + 128);
		if ($num < 65536) return chr(($num >> 12) + 224) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
		if ($num < 2097152) return chr(($num >> 18) + 240) . chr((($num >> 12) & 63) + 128) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
		return '';
	}
}

// One key for each allowed element.
// If it has specific allowed attributes, then each key
// should have an associated array of allowed attributes.
// The allowed attributes array is keyed too.
$HTML_ALLOWEDELEMENTS = array(
	"a" => array(
		"charset" => true,
		"coords" => true,
		"href" => true,
		"hreflang" => true,
		"name" => true,
		"rel" => true,
		"rev" => true,
		"shape" => true,
		"tabindex" => true,
		"type" => true
	),
	"abbr" => true,
	"acronym" => true,
	"address" => true,
	"area" => array(
		"coords" => true,
		"href" => true,
		"nohref" => true,
		"shape" => true,
		"tabindex" => true,
	),
	"b" => true,
	"blockquote" => array(
		"cite" => true,	
	),
	"br" => true,
	"button" => array(
		"disabled" => true,
		"name" => true,
		"tabindex" => true,
		"type" => true,
		"value" => true,
	),
	"caption" => true,
	"cite" => true,
	"code" => true,
	"col" => array(
		"char" => true,
		"charoff" => true,
		"span" => true,
	),
	"colgroup" => array(
		"char" => true,
		"charoff" => true,
		"span" => true,
	),
	"dd" => true,
	"del" => array(
		"cite" => true,
		"datetime" => true,
	),
	"dfn" => true,
	"div" => true,
	"dl" => true,
	"dt" => true,
	"em" => true,
	"fieldset" => true,
	"font" => array(
		"size" => true,
		"color" => true,
		"face" => true,	
	),
	"form" => array(
		"accept-charset" => true,
		"accept" => true,
		"action" => true,
		"enctype" => true,
		"method" => true,
		"name" => true,	
	),
	"h1" => true,
	"h2" => true,
	"h3" => true,
	"h4" => true,
	"h5" => true,
	"h6" => true,
	"hr" => true,
	"i" => true,
	"img" => array(
		"alt" => true,
		"align" => true,
		"border" => true,
		"height" => true,
		"src" => true,
		"ismap" => true,
		"longdesc" => true,
		"usemap" => true,
		"width" => true,
	),
	"input" => array(
		"accept" => true,
		"checked" => true,
		"disabled" => true,
		"name" => true,
		"ismap" => true,
		"maxlength" => true,
		"readonly" => true,
		"size" => true,
		"tabindex" => true,
		"type" => true,
		"usemap" => true,
		"value" => true,	
	),
	"ins" => array(
		"cite" => true,
		"datetime" => true,
	),
	"kbd" => true,
	"label" => array(
		"for" => true,
	),
	"legend" => true,
	"li" => true,
	"map" => array(
		"name" => true,	
	),
	"ol" => true,
	"optgroup" => array(
		"disabled" => true,
		"label" => true,	
	),
	"option" => array(
		"disabled" => true,
		"label" => true,
		"selected" => true,
		"value" => true,
	),
	"p" => true,
	"pre" => true,
	"q" => array(
		"cite" => true,	
	),
	"samp" => true,
	"select" => array(
		"disabled" => true,
		"multiple" => true,
		"name" => true,
		"size" => true,
		"tabindex" => true,
	),
	"span" => true,
	"strong" => true,
	"table" => array(
		"align" => true,
		"border" => true,
		"bgcolor" => true,
		"cellpadding" => true,
		"cellspacing" => true,
		"summary" => true,
		"width" => true,
	),
	"tbody" => array(
		"char" => true,
		"charoff" => true,
	),
	"td" => array(
		"abbr" => true,
		"align" => true,
		"axis" => true,
		"bgcolor" => true,
		"border" => true,
		"background" => true, // Can have remote image, unsafe!
		"cellpadding" => true,
		"cellspacing" => true,
		"char" => true,
		"charoff" => true,
		"colspan" => true,
		"height" => true,
		"nowrap" => true,
		"rowspan" => true,
		"scope" => true,
		"valign" => true,
		"width" => true,
	),
	"textarea" => array(
		"cols" => true,
		"disabled" => true,
		"name" => true,
		"readonly" => true,
		"rows" => true,
		"tabindex" => true,	
	),
	"tfoot" => array(
		"char" => true,
		"charoff" => true,	
	),
	"th" => array(
		"abbr" => true,
		"align" => true,
		"axis" => true,
		"bgcolor" => true,
		"cellpadding" => true,
		"cellspacing" => true,
		"char" => true,
		"charoff" => true,
		"colspan" => true,
		"height" => true,
		"rowspan" => true,
		"scope" => true,
		"valign" => true,
		"width" => true,
	),
	"thead" => array(
		"char" => true,
		"charoff" => true,	
	),
	"tr" => array(
		"align" => true,
		"bgcolor" => true,
		"cellpadding" => true,
		"cellspacing" => true,
		"char" => true,
		"charoff" => true,
		"height" => true,
		"valign" => true,
		"width" => true,
	),
	"ul" => true,
	"var" => true,
);

// Globally allowed attributes: that is, attributes allowed for any element.
// Specific attributes for elements can be allowed by inserting them into the above
// array.
$HTML_ALLOWEDATTRS = array(
	"dir" => true,
	"lang" => true,
	"style" => true,
	"title" => true,
	"id" => true,      // Keep ONLY ids that lichen generates...
	"onclick" => true, // TODO: distinguish between the ones we add and others from the original mail.
);

// Tags that get modified in some way.
$HTML_MODIFYTAGS = array(
	"a" => true,
	"img" => true
);
$HTML_MODIFYATTRS = array(
	"background" => true,
	"style" => true,
	"id" => true,       // Strip any id provided by the original source message....
);

// Modify data needs at least:
//   mailbox - the mailbox the message comes from
//   uid - the UID of the message.
//   remoteimg - destination array for remote images.
//   imgcounter - the count of remote images - set to zero to start with.
//   allowimages - true to just allow images anyway.

// Clean HTML up - utilize the HTML Lexer that is part of HTMLPurifier,
// but do our own cleaning.
function cleanHTML( $inputHtml, &$modifyData ) {
	global $HTML_ALLOWEDELEMENTS, $HTML_ALLOWEDATTRS,
		$HTML_MODIFYTAGS, $HTML_MODIFYATTRS;

	// Prep the HTMLPurifier Lexer.
	$lexerConfig = HTMLPurifier_Config::create( null );
	$lexer       = HTMLPurifier_Lexer::create( $lexerConfig );
	$context     = new HTMLPurifier_Context();

	// Parse the HTML...
	$tokenisedHtml = $lexer->tokenizeHTML( $inputHtml, $lexerConfig, $context );

	// Now iterate over the result and parse it appropriately.
	ob_start();
	$stripUntil = false;
	$passOut = true;
	foreach ( $tokenisedHtml as $token ) {
		if ( $stripUntil !== false ) {
			$passOut = false;
		} else {
			$passOut = true;
		}
		if ( isset( $token->is_tag ) && $token->is_tag ) {
			// Are we stripping until this tag occurs again?
			if ( $stripUntil !== false ) {
				if ( $token->name == $stripUntil && $token->type == "end" ) {
					// Continue the output after this tag.
					$stripUntil = false;
				}
			} else {
				// Is this tag allowed?
				$allowed = isset( $HTML_ALLOWEDELEMENTS[ $token->name ] );

				if ( !$allowed ) {
					// This tag is not allowed. Strip everything
					// between it and the matching close tag.
					// Unless... it is a single tag, in which case we
					// just want to stop it.
					if ( $token->type == "empty" ) {
						// Self-closed/closing tag.
						$passOut = false;
					} else {
						$stripUntil = $token->name;
						$passOut = false;
					}
				} else {
					// It is allowed.
					
					if ( $token->type != "end" ) {
						// Do we need to modify the tag?
						if ( isset( $HTML_MODIFYTAGS[ $token->name ] ) ) {
							modifyTag( $token, $modifyData );
						}

						// Do we need to modify any attributes?
						foreach ( $token->attr as $name => $value ) {
							if ( isset( $HTML_MODIFYATTRS[ $name ] ) ) {
								modifyAttribute( $name, $token->attr, $modifyData );
							}
						}

						// Check to make sure all the attributes are allowed.
						foreach ( $token->attr as $name => $value ) {
							if ( !isset( $HTML_ALLOWEDELEMENTS[ $token->name ][ $name ] ) &&
								!isset( $HTML_ALLOWEDATTRS[ $name ] ) )
							{
								// Attribute is NOT allowed.
								//echo "Removing attr {$name} with value {$value} from {$token->name}...";
								unset( $token->attr[$name] );
							}
						}
						$passOut = true;
					}
				}
			}
		}

		if ( $passOut ) {
			reconstructTag( $token );
		}
	}

	return ob_get_clean();
}

function reconstructTag( $tagData ) {
	// Performance: echo it out, should be ob_captured by caller!
	if ( isset( $tagData->is_tag ) && $tagData->is_tag ) {
		//echo "Tag: ";
		// Print out the tag name.
		echo "<";
		if ( $tagData->type == "end" ) {
			// And make it </tagname if it's a closing tag.
			echo "/";
		}
		echo $tagData->name;

		if ( $tagData->type != "end" && count( $tagData->attr ) != 0 ) {
			// Print out the attributes for this tag.
			echo " ";
			foreach ( $tagData->attr as $name => $value ) {
				echo $name, "=\"", htmlentities( $value ), "\" ";
			}
		}

		if ( $tagData->type == "empty" ) {
			// Self closed tag?
			echo "/";
		}

		// Close the tag.
		echo ">";
	}
	if ( $tagData->type == "text" ) {
		// Text node: pass it out verbatim.
		//echo "Text node: ";
		echo $tagData->data;
		//var_dump( $tagData );
	}
}

function modifyTag( &$tagData, &$modifyData ) {
	// Transmogrify some tags. Transmogrify them good.
	switch ( $tagData->name ) {
		case "a":
			// Wrap external links with an onclick to call if_newWin().
			if ( substr( $tagData->attr['href'], 0, 4 ) == "http" ) {
				$tagData->attr['onclick'] = "return if_newWin('" . addslashes( $tagData->attr['href'] ) . "');";
			}
			// Convert mailto: links into composer links.
			if ( substr( $tagData->attr['href'], 0, 7 ) == "mailto" ) {
				$tagData->attr['onclick'] = "Lichen.action('compose','MessageCompose','showComposer',['mailto',null,'" .
					addslashes( substr( $tagData->attr['href'], 7 ) ) . "'];return false";
			}
			break;
		case "img":
			// Step 1: is this a cid: image?
			$srcBegin = substr( $tagData->attr['src'], 0, 4 );
			if ( $srcBegin == "cid:" ) {
				// Image is a cid: image, so replace the source so that the
				// correct image is loaded.
				$tagData->attr['src'] = "message.php?mailbox=" . urlencode( $modifyData['mailbox'] ) .
					"&uid=" . urlencode( $modifyData['uid'] ) . "&filename=" . urlencode( $tagData->attr['src'] );
			} else if ( !$modifyData['allowimages'] ) {
				// Remote image - and they are disabled.
				$remoteData = array();
				$remoteData['id'] = "ldr" . $modifyData['imgcounter'];
				$remoteData['attr'] = "src";
				$tagData->attr['id'] = $remoteData['id'];
				$remoteData['url'] = $tagData->attr['src'];
				unset( $tagData->attr['src'] );
				$modifyData['imgcounter']++;
				$modifyData['remoteimg'][] = $remoteData;
			}
			break;
	}
}

function modifyAttribute( $triggerAttr, &$attributes, &$modifyData ) {
	// Modify some attributes.
	switch ( $triggerAttr ) {
		case "background":
			// Change this to be a remote image.
			$remoteData = array();
			$remoteData['id'] = "ldr" . $modifyData['imgcounter'];
			$remoteData['attr'] = "background";
			$attributes['id'] = $remoteData['id'];
			$remoteData['url'] = $attributes['background'];
			unset( $attributes['background'] );
			$modifyData['imgcounter']++;
			$modifyData['remoteimg'][] = $remoteData;
			break;
		case "style":
			// Clean JS, and change attributes with urls() into remote content.
			$originalContent = $attributes['style'];

			// Remove JS. TODO: Test that this actually does what it claims to do!
			$originalContent = preg_replace( '/j.*a.*v.*a.*s.*c.*r.*i.*p.*t/s', '', $originalContent );
			$attributes['style'] = $originalContent;

			// Remove the images from the CSS.
			$cleanContent = preg_replace( '/(url\(.*\))/', 'none', $originalContent );

			if ( $cleanContent != $originalContent ) {
				// This is considered a remote element.
				$remoteData = array();
				$remoteData['id'] = "ldr" . $modifyData['imgcounter'];
				$remoteData['attr'] = "style";
				$attributes['id'] = $remoteData['id'];

				// It seems in FireFox you can't just set the "style" attribute with raw data.
				// Instead it will have to be calls to the mootools setStyle().
				// So prepare the data for that.
				// Basically; the CSS has to be split on semi-colons, and then further
				// split on ":" characters to split it into key/value pairs.
				// TODO: This code is far from foolproof.
				$cssSections = explode( ";", $originalContent );
				$resultKeys = array();
				foreach ( $cssSections as $css ) {
					$css = trim( $css );
					$keyval = explode( ":", $css );
					if ( count( $keyval ) == 1 ) {
						$resultKeys[] = array( trim( $keyval[0] ), "" );
					} else if ( count( $keyval ) == 2 ) {
						$resultKeys[] = array( trim( $keyval[0] ), trim( $keyval[1] ) );
					} else if ( count( $keyval ) > 2 ) {
						// Oops. Must have been more than one colon in the result.
						// Reassemble the remaining part with colons.
						$resultKeys[] = array( trim( $keyval[0] ), trim( implode( ":", array_slice( $keyval, 1 ) ) ) );
					}
				}
				$remoteData['url'] = $resultKeys;

				unset( $attributes['style'] );
				$modifyData['imgcounter']++;
				$modifyData['remoteimg'][] = $remoteData;
			}
			break;
	}
}


// Add (or strip) HTML markup within a message to allow it to be displayed.
// If the message is plain text, this includes things like adding <br />
// for linebreaks and adding link tags where appropriate. If it's HTML,
// this means filtering out untrusted code.
//
// TODO: this functionality should move to a different function
// outMsgFlags is used to set flags that should be passed back to the client.
// It should be passed a reference to an array.
// The idea is that the "remote images" replacement should be able to set a flag saying
// that there are remote images in the message.
function processMsgMarkup( $string, $contentType, $mailbox, $uid, &$outMsgFlags ) {

	if ( $contentType == "text/html" ) {
		$modifyData = array();
		$modifyData['mailbox']     = $mailbox;
		$modifyData['uid']         = $uid;
		$modifyData['remoteimg']   = array();
		$modifyData['imgcounter']  = 0;
		if ( is_array( $outMsgFlags ) && isset( $outMsgFlags['allowimages'] ) ) {
			$modifyData['allowimages'] = $outMsgFlags['allowimages'];
		} else {
			$modifyData['allowimages'] = false;
		}

		$string = cleanHTML( $string, $modifyData );
		
		if ( is_array( $outMsgFlags ) && count( $modifyData['remoteimg'] ) > 0 ) {
			$outMsgFlags['htmlhasremoteimages'] = true;
			$outMsgFlags['remotecontent'] = $modifyData['remoteimg'];
		}

	} else {
		// Assume we're dealing with plain text.
		if ( version_compare( PHP_VERSION, '5.0.0', '<' ) ) {
			$string = html_entity_decode_utf8( $string );
		} else {
			// UTF-8 here is for mailers (e.g. Slashdot) that put UTF-8
			// characters in supposedly ASCII messages.
			$string = html_entity_decode( $string, ENT_COMPAT, 'UTF-8' );
		}

		// Now reinsert the entities, keeping in mind the charset.
		$string = htmlspecialchars( $string, ENT_COMPAT, 'UTF-8' );

		// Convert inline text links into real hyperlinks.
		$string = convertLinks( $string );

		// Convert newlines to <br />'s for display purposes.
		$string = nl2br( $string );
	}

	return $string;
}


// Convert some HTML into a suitable textual representation of that HTML.
// Pretty basic: won't handle complex things like tables and what not.
function HTMLToText( $inputHtml, $textWidth ) {
	include_once( 'libs/HTMLPurifier.auto.php' );

	// Prep the HTMLPurifier Lexer.
	$lexerConfig = HTMLPurifier_Config::create( null );
	$lexer       = HTMLPurifier_Lexer::create( $lexerConfig );
	$context     = new HTMLPurifier_Context();

	// Parse the HTML...
	$tokenisedHtml = $lexer->tokenizeHTML( $inputHtml, $lexerConfig, $context );

	// Now iterate over the result and parse it appropriately.
	$result = "";
	$fragment = "";
	$openTag = "";

	foreach ( $tokenisedHtml as $token ) {
		if ( $token->type == "text" ) {
			$fragment .= $token->data;
			$fragment = trim( $fragment );
		}
		if ( $openTag == "" ) {
			$result  .= $fragment;
			$fragment = "";
		}
		if ( isset( $token->is_tag ) && $token->is_tag && $token->type != "end" ) {
			// Depending on what tag this is...
			if ( $openTag == "" ) {
				if ( in_array( $token->name, array( "h1", "h2", "h3", "h4", "h5", "h6", "p", "li" ) ) ) {
					$openTag = $token->name;
				}
			} else {
				// Something else is open.
			}
		}
		if ( $token->type == "empty" ) {
			switch ( $token->name ) {
				case 'hr':
					$result .= "\n\n" . str_repeat( "-", $textWidth - 2 ) . "\n\n\n";
					break;
			}
		}
		if ( $token->type == "end" ) {
			if ( $token->name == $openTag ) {
				// End of tag...
				switch ( $token->name ) {
					case 'h1':
					case 'h2':
					case 'h3':
					case 'h4':
					case 'h5':
					case 'h6':
						$fragment = strtoupper( $fragment );
						$result .= wordwrap( $fragment, $textWidth ) . "\n\n\n";
						$fragment = "";
						break;
					case 'p':
						$result .= wordwrap( $fragment, $textWidth ) . "\n\n";
						$fragment = "";
						break;
					case 'li':
						$line = wordwrap( $fragment, $textWidth );
						$lines = explode( "\n", $line );
						if ( count( $lines ) > 1 ) {
							for ($i = 1; $i < count( $lines ); $i++) {
								$lines[$i] = "   " . $lines[$i];
							}
						}
						$lines[0] = " * " . $lines[0];
						$line = implode( "\n", $lines );
						$result .= $line . "\n";
						$fragment = "";
						break;
				}
				$openTag = "";
			}
		}
	}

	return $result;
}


// Takes a header (such as Subject: or From:) that might potentially be
// MIME-encoded, decodes it (if needed), and strips out HTML and newlines.
function filterHeader( $header ) {

	$headerBits = imap_mime_header_decode( $header );

	$returnString = "";

	foreach ( $headerBits as $element ) {
		$returnString .= convertToUTF8( $element->text, $element->charset );
	}

	// To ensure HTML entities are displayed correctly by the JavaScript
	/*
	if ( version_compare( PHP_VERSION, '5', '>=' ) ) {
		$returnString = html_entity_decode( $returnString, ENT_QUOTES, 'UTF-8' );
	} else {
		$returnString = html_entity_decode( $returnString, ENT_QUOTES );
	}
	 */

	$returnString = str_replace( "\r", "", $returnString );
	$returnString = str_replace( "\n", " ", $returnString );

	//$returnString = htmlspecialchars( $returnString, ENT_NOQUOTES );

	return $returnString;
}


// Run PHP's iconv on the given string to get UTF8 output,
// taking into account the character set name(s) that are
// valid for e-mail but not recognised by iconv.
function convertToUTF8( $string, $charset ) {

	$brokenEncodings = array(
		'unicode-1-1-utf-7' => 'utf7',
		// To account for messages that claim to be ASCII but
		// actually aren't -- I'm looking at you, Slashdot!
		// -- treat ASCII as UTF-8, because the 127 real ASCII
		// characters are identical anyway.
		'us-ascii' => 'utf-8',
		'default' => 'ISO-8859-1',
		'' => 'ISO-8859-1'
	);

	if ( isset( $brokenEncodings[ strtolower($charset) ] ) ) {
		$charset = $brokenEncodings[ strtolower($charset) ];
	}

	return @iconv( $charset, 'UTF-8//TRANSLIT', $string );
}


// Take a string containing a plain-text message, look for things that
// seem to be links, and convert them into functional links.
function convertLinks( $string ) {

	// Regexp to deal with http[s] links on a single line
	$string = preg_replace_callback( '/(?<![<">]|&lt;|&gt;)http([s]{0,1})\:\/\/(.+?)\s/i', 'convertLinksCallback', $string );

	// This version is meant to find links spanning multiple lines, delimited by < and >
	$string = preg_replace_callback( '/(<|&lt;)http([s]{0,1})\:\/\/(.+?)(>|&gt;)/is', 'convertLinksCallback', $string );

	// Simpler regexp to find www.foo without an http://
	$string = preg_replace_callback( '/(?<!http\:\/\/|https\:\/\/)www\.(.+?)(>|\"|\s)/i', 'convertLinksCallback', $string );

	// Convert e-mail addresses to composer links
	$string = preg_replace( '/(\<|\>|\;|\s|\"|\,)([\w\d]+[\w\d\.\_\-\+]*\@[\w\d]+[\w\d\.\_\-]+)(\,|\>|\&|\"|\s)/',
		"$1<a href=\"#compose\" onclick=\"Lichen.action('compose','MessageCompose','showComposer',['mailto',null,'$2']);return false\">$2</a>$3", $string );

	return $string;
}


// Callback function for regexps in convertLinks() above:
// finds the
function convertLinksCallback( $matches ) {
	$fullUrl = $matches[0];
	$originalText = $fullUrl;
	$leader = "";
	$trailer = "";

	$fullUrl = trim( $fullUrl );

	// Remove leading <, &lt;, >, &gt;
	if ( $fullUrl[0] == "<" ) {
		$fullUrl = substr( $fullUrl, 1 );
		$leader = "<";
	}
	if ( substr( $fullUrl, 0, 4 ) == "&lt;" ) {
		$fullUrl = substr( $fullUrl, 4 );
		$leader = "&lt;";
	}
	if ( $fullUrl[strlen($fullUrl) - 1] == ">" ) {
		$fullUrl = substr( $fullUrl, 0, -1 );
		$trailer = ">";
	}
	if ( substr( $fullUrl, -4 ) == "&gt;" ) {
		$fullUrl = substr( $fullUrl, 0, -4 );
		$trailer = "&gt;";
	}

	// Add http:// if needed.
	if ( substr( $fullUrl, 0, 4 ) != "http" ) {
		$fullUrl = "http://" . $fullUrl;
	}

	// Strip newlines from the URI
	$fullUrl = str_replace( array( "\r", "\n", " " ), "", $fullUrl );

	// Figure out what the "trailer" is, since the regexp that
	// found the link also returns any whitespace at the end
	$trimmedOriginal = trim( $originalText );
	if ( strlen( $trimmedOriginal ) != strlen( $originalText ) ) {
		$trailer .= substr( $originalText, strlen( $trimmedOriginal ) + 1 );
		$originalText = $trimmedOriginal;
	}

	// If we have a leader and trailer, omit them from the link text
	if ( $trailer != "" ) {
		$originalText = substr( $originalText, strlen( $leader ), -strlen( $trailer ) );
	}

	// Return formatted string
	return "$leader<a href=\"$fullUrl\" onclick=\"return if_newWin('$fullUrl')\">$originalText</a>$trailer";
}

// Function used by markupQuotedMessage() to insert a ">" before all the lines of replies.
function quoteReplyMarkup( $line ) {
	return "> {$line}";
}

// Prepare a block of text to be suitable as a quoted reply.
// This does things like strip HTML, insert ">" marks, and so forth.
// All kinda crude, and currently only outputs as plain text.
// $inputType should be either "text/html" or "text/plain".
// $mode should be either "reply" or "forward"
function markupQuotedMessage( $input, $inputType, $mode ) {

	$message = $input;

	if ( !is_array( $message ) ) {
		$message = array( $message );
	}

	// The input is likely an array of text or html sections,
	// stick them all together.
	$message = implode( "", $message );

	// If it's HTML...
	if ( $inputType == "text/html" ) {
		// Strip the HTML tags.
		$message = trim( wordwrap( strip_tags( $message ), 75 ) );

		// Decode all the HTML entities.
		if ( version_compare( PHP_VERSION, '5.0.0', '<' ) ) {
			$message = html_entity_decode_utf8( $message );
		} else {
			$message = html_entity_decode( $message, ENT_COMPAT, 'UTF-8' );
		}

		// Wordwrap at 80 chars.
		$message = wordwrap( $message, 80 );
	} else if ( $inputType == "text/plain" ) {
		// Just trim excess whitespace.
		$message = trim( wordwrap( $message, 75 ) );
	}

	if ( $mode == "reply" ) {
		// Insert ">" into each line of the reply.
		// TODO: This line does too much in one go.
		$message = implode( "\n", array_map( "quoteReplyMarkup", explode( "\n", $message ) ) );
	}

	return $message;
}


// Determine the date format to use.
// This varies depending on how old the message is.
function chooseDateFormat( $timeStamp ) {
	global $DATE_FORMAT_NEW, $DATE_FORMAT_OLD, $DATE_FORMAT_LONG;

	$nowTimestamp = time();
	$now = localtime( $nowTimestamp, true );
	$msgTime = localtime( $timeStamp, true );

	$format = "";

	if ( $nowTimestamp - ( 60 * 60 * 24 * 6 ) < $timeStamp ) {
		// In the last 6 days.
		$format = $DATE_FORMAT_NEW;
	} else if ( $msgTime['tm_year'] == $now['tm_year'] ) {
		// In the current year.
		$format = $DATE_FORMAT_OLD;
	} else {
		// Not the current year, use the full date format.
		$format = $DATE_FORMAT_LONG;
	}

	return $format;
}


// Takes the e-mail header Date: and converts it to the user's local
// time zone, formatted according to their preferences.
// PHP 5.1 introduced much-improved timezone handling that we use if
// available; otherwise, use the clunky putenv() trick and separate
// zone database from PEAR's Date library.
if ( version_compare( PHP_VERSION, '5.2.0', '>=' ) ) {

	function processDate( $headerString, $dateFormat='' ) {
		global $DATE_FORMAT_OLD, $DATE_FORMAT_NEW, $DATE_FORMAT_LONG;

		if ( substr( $headerString, -3 ) == " UT" ) {
			// Hack: UT is a valid abbreviation for UTC in
			// mail headers, but strtotime doesn't recognise it.
			$headerString .= "C";
		}

		// Convert the string from the header to a Unix timestamp,
		// then format it; PHP will adjust for the user's timezone
		// because we called date_default_timezone_set earlier.
		$adjustedTime = strtotime( $headerString );

		if ( $dateFormat == '' ) {
			$dateFormat = chooseDateFormat( $adjustedTime );
		}

		return date( $dateFormat, $adjustedTime );
	}

} else {

	include( "libs/TimeZone.php" );

	// TODO: this works only on Linux
	if ( isset( $_ENV['TZ'] ) ) {
		$SYSTEM_TIMEZONE = $_ENV['TZ'];
	} elseif ( file_exists( '/etc/timezone' ) && !is_link( '/etc/timezone' ) ) {
		$SYSTEM_TIMEZONE = rtrim( file_get_contents( '/etc/timezone' ) );
	} else {
		$SYSTEM_TIMEZONE = 'UTC';
	}

	function processDate( $headerString, $dateFormat='' ) {
		global $USER_SETTINGS, $DATE_FORMAT_OLD, $DATE_FORMAT_NEW;
		global $SYSTEM_TIMEZONE;

		if ( substr( $headerString, -3 ) == " UT" ) {
			$headerString .= "C";
		}

		$timestamp = strtotime( $headerString );

		$timestamp = convertToOrFromUTC( $timestamp, $SYSTEM_TIMEZONE, -1 );

		$adjustedTime = convertToOrFromUTC( $timestamp, $USER_SETTINGS['timezone'], 1 );

		if ( $dateFormat == '' ) {
			$dateFormat = chooseDateFormat( $adjustedTime );
		}

		return date( $dateFormat, $adjustedTime );
	}


	function convertToOrFromUTC( $timestamp, $timezoneName, $direction=-1 ) {
		global $_DATE_TIMEZONE_DATA;

		// First adjust the timestamp assuming
		// there's no daylight saving.
		$timestamp = $timestamp + $direction * $_DATE_TIMEZONE_DATA[ $timezoneName ][ 'offset' ] / 1000;

		// This block copied from the PEAR Date library.
		$env_tz = '';
		if( isset( $_ENV['TZ'] ) && getenv('TZ') ) {
			$env_tz = getenv('TZ');
		}
		putenv( 'TZ=' . $timezoneName );
		$ltime = localtime( $timestamp, true );
		if ( $env_tz != '' ) {
			putenv( 'TZ=' . $env_tz );
		}

		if ( $ltime['tm_isdst'] ) {
			// DST is currently active in the target timezone.
			return $timestamp + $direction * 3600000;
		} else {
			return $timestamp;
		}
	}
}

?>
