<?php
/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
libs/htmlcleaner.php - basic HTML cleaner for HTML messages.
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

$ALLOWED_ELEMENTS = array(

);

$ALLOWED_ATTRIBUTES = array(

);

$MODIFIED_TAGS = array(
	"img" => "mailImageTag",
	"a" => "mailAnchorTag"
);

function cleanHtml( $htmlInput ) {
	global $ALLOWED_ELEMENTS;
	global $ALLOWED_ATTRIBUTES;
	global $MODIFIED_TAGS;

	$output = "";
	$inputLength = strlen( $htmlInput );

	for ( $processPos = 0; $processPos < $inputLength; ) {
		// Find the next open and close tag.
		$nextOpenTag  = strpos( $htmlInput, "<", $processPos );
		$nextCloseTag = strpos( $htmlInput, ">", $processPos );

		if ( $nextOpenTag === false || $nextCloseTag === false ) {
			// No more open/closing tags.
			// Copy the remainder, then we're done.
			$nextOpenTag = $inputLength;
			$nextCloseTag = $inputLength;
		}

		// Copy output from the last process position up until the next open tag.
		$output .= substr( $htmlInput, $processPos, $nextOpenTag - $processPos );
		$processPos = $nextOpenTag;

		// If we have a next tag...
		if ( $nextOpenTag !== false && $nextCloseTag !== false ) {
			$processPos = $nextCloseTag + 1;

			// If the tags appear to be nested correctly.
			if ( $nextCloseTag > $nextOpenTag ) {
				// Extract the tag.
				$tagData = substr( $htmlInput, $nextOpenTag, $nextCloseTag - $nextOpenTag );

				// Strip the <> around the tag.
				$tagData = substr( $tagData, 1, strlen( $tagData ) - 1 );

				// Figure out the tag name.
				$tagNameEnd = strpos( trim( $tagData ), " " );
				$tagName = "";
				if ( $tagNameEnd === false ) {
					$tagName = $tagData;
				} else {
					$tagName = substr( trim( $tagData ), 0, $tagNameEnd );
				}

				if ( $tagName == "!DOCTYPE" ) {
					// Pass this out directly; no modification.
					$output .= "<{$tagData}>";
					continue;
				}

				$tagName = strtolower( $tagName );

				// Is this a closing tag?
				$isClosingTag = false;
				$selfClosedTag = false;
				if ( $tagName[0] == "/" ) {
					$isClosingTag = true;
					$tagName = trim( substr( $tagName, 1 ) );
				}
				// Or a self-closed tag?
				if ( $tagData[ strlen( $tagData ) - 1 ] == "/" ) {
					$tagData = substr( $tagData, 0, strlen( $tagData ) - 1 );
					$selfClosedTag = true;
				}

				// Is the tag allowed?
				if ( isset( $ALLOWED_ELEMENTS[ $tagName ] ) ) {
					// Yes, it is.
				} else {
					// No, it is not.
				}

				// Parse attributes into an array.
				if ( !$isClosingTag ) {
					$attributes = array();
					$attBits = explode( " ", trim( substr( $tagData, strlen( $tagName ) ) ) );
					$halfAtt = "";
					$quoteOpen = "";
					foreach ( $attBits as $attBit ) {
						// Got any quotes in this bit?
						$singleQuote = strpos( $attBit, "'" );
						$doubleQuote = strpos( $attBit, '"' );

						if ( $quoteOpen == "" ) {
							// The "set to 1M" is a hack.
							// What we're trying to accomplish here is to determine
							// which quotes started this attribute.
							// Ie, this code handles these cases:
							//   bar='foo'
							//   bar="foo"
							//   bar='"foo"'
							//   bar="'foo'"
							if ( $singleQuote === false ) $singleQuote = 1000000;
							if ( $doubleQuote === false ) $doubleQuote = 1000000;

							if ( $singleQuote != 1000000 && $singleQuote < $doubleQuote ) {
								$quoteOpen = "'";
							}
							if ( $doubleQuote != 1000000 && $doubleQuote < $singleQuote ) {
								$quoteOpen = '"';
							}
							$halfAtt = $attBit;
							if ( $quoteOpen != "" ) {
								// If the quotes are contained inside this bit,
								// then this is our attribute.
								if ( substr_count( $attBit, $quoteOpen ) == 2 ) {
									$quoteOpen = "";
								}
							}
						} else {
							if ( $quoteOpen == '"' && $doubleQuote !== false ) {
								$halfAtt .= $attBit;
								$quoteOpen = "";
							} else if ( $quoteOpen == "'" && $singleQuote !== false ) {
								$halfAtt .= $attBit;
								$quoteOpen = "";
							}
						}

						if ( $quoteOpen == "" ) {
							// $halfAtt contains a complete attribute. Parse it.
							$seperator = strpos( $halfAtt, "=" );
							$halfAtt = trim( $halfAtt );
	
							if ( $seperator === false ) {
								// It's an attribute with no parameter.
								$attributes[strtolower($halfAtt)] = $halfAtt;
							} else {
								// Split out the name and value.
								$attName  = strtolower( substr( $halfAtt, 0, $seperator ) );
								$attValue = substr( $halfAtt, $seperator + 1 );
								if ( $attValue[0] == '"' || $attValue[0] == "'" ) {
									$attValue = substr( $attValue, 1, strlen( $attValue ) - 2 );
								}
	
								// Store it.
								$attributes[$attName] = $attValue;
							}
						}
					}
				}
				
				// Does the tag need to be modified?
				if ( !$isClosingTag ) {
					if ( isset( $MODIFIED_TAGS[ $tagName ] ) ) {
						$tagData = $MODIFIED_TAGS[$tagName]( $tagName, $tagData, $attributes );
					}
				}

				//echo "Tag: ".htmlentities($tagName)." Data: ". htmlentities($tagData). " {$isClosingTag}<br />";
				if ( !$isClosingTag ) {
					$output .= rebuildTag( $tagName, $selfClosedTag, $attributes );
				} else {
					$output .= "<{$tagData}>";
				}
			}
		}
	}

	return $output;
}

// Reassemble the tag - given its name and the attributes of the tag.
// Result should be XHTML.
function rebuildTag( $tagName, $isSelfClosed, $attributes ) {
	$tag = "<" . $tagName;
	$outAtts = array();

	//echo "--<br />";
	//echo $tagName . "<br />";
	//print_r($attributes);
	//echo $isSelfClosed;

	foreach ( $attributes as $att => $value ) {
		$outAtts[] = "{$att}=\"" . htmlentities( $value ) . "\"";
	}

	if ( count( $outAtts ) > 0 ) {
		$tag .= " " . implode( " ", $outAtts );
	}

	if ( $isSelfClosed ) {
		$tag .= " /";
	}

	$tag .= ">";

	//echo htmlentities($tag);
	//echo "--<br />";

	return $tag;
}

// Function to modify an image tag.
function mailImageTag( $tagName, $rawTag, &$attributes ) {
	if ( isset( $attributes['src'] ) ) {
		if ( substr( $attributes['src'], 0, 4 ) == "cid:" ) {
			$attributes['src'] = "message.php?mailbox=INCOMPLETE&uid=INCOMPLETE&filename=" . $attributes['href'];
		} else {
			$attributes['src'] = str_replace( "http://", "http://_", $attributes['src'] );
			$attributes['class'] = "remoteimg";
		}
	}
}

// Function to modify an anchor (a) tag.
function mailAnchorTag( $tagName, $rawTag, &$attributes ) {
	if ( isset( $attributes['href'] ) ) {
		if ( substr( $attributes['href'], 0, 7 ) == "mailto:" ) {
			$attributes['onclick'] = "comp_showForm('mailto',null,'".$attributes['href']."');return false";
		} else {
			$attributes['onclick'] = "return if_newWin('". $attributes['href'] . "');";
		}
	}
}

?>
