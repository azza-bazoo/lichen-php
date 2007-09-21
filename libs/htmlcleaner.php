<?php
/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
libs/htmlparser/htmlparser.php - basic HTML parser.
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

error_reporting( E_ALL );

function parseHtml( $htmlInput, $callback = "", $callbackData = array() ) {
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
		$text = substr( $htmlInput, $processPos, $nextOpenTag - $processPos );
		$callbackResult = true;

		// Call the callback.
		if ( !empty( $callback ) && !empty( $text ) ) {
			$atts = array();
			$callbackResult = $callback( "", $text, false, false, $atts, $callbackData );
		}

		if ( $callbackResult && !empty( $text ) ) {
			$output .= $text;
		}

		// Shift along the processing position.
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
					// TODO: Used a continue, and also, what if the callbacks want to play with this?
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

				// Parse attributes into an array.
				$attributes = array();
				if ( !$isClosingTag ) {
					$tagDataLen = strlen( $tagData );
					$attrIndex = strlen( $tagName ) + 1;
					while ( $attrIndex < $tagDataLen ) {
						// First step: search for an = or a space.
						$nextEq = strpos( $tagData, "=", $attrIndex );
						$nextSp = strpos( $tagData, " ", $attrIndex );

						if ( $nextEq === false ) $nextEq = $tagDataLen;
						if ( $nextSp === false ) $nextSp = $tagDataLen;

						if ( $nextSp < $nextEq ) {
							// Next space is before the next equals.
							// This means that this attribute in not in the form "foo=bar", instead is just "foo"
							$att = substr( $tagData, $attrIndex, $nextSp - $attrIndex );
							if ( !empty( $att ) ) {
								$attributes[strtolower($att)] = strtolower($att);
							}
							$attrIndex = $nextSp + 1;
						} else if ( $nextSp > $nextEq ) {
							// We have an attribute in the form foo=bar.
							// Parse that.
							$att = substr( $tagData, $attrIndex, $nextEq - $attrIndex );

							// Now get the value.
							// If the character after the value is a quote...
							$startVal = $nextEq + 1;
							$endVal   = $nextEq + 1;
							switch ( $tagData[$nextEq + 1] ) {
								case "'":
									// Single quoted value.
									$startVal = $startVal + 1;
									$endVal = strpos( $tagData, "'", $startVal + 1 );
									break;
								case '"':
									// Double quoted value.
									$startVal = $startVal + 1;
									$endVal = strpos( $tagData, '"', $startVal + 1 );
									break;
								default:
									// Unquoted value.
									$endVal = strpos( $tagData, ' ', $startVal );
									break;
							}

							if ( $endVal === false ) {
								$endVal = $tagDataLen;
							}

							// Extract the value of the attribute.
							$attVal = substr( $tagData, $startVal, $endVal - $startVal );

							if ( !empty( $att ) ) {
								$attributes[strtolower($att)] = $attVal;
							}

							$attrIndex = $endVal + 1;

						} else if ( $nextSq == $nextEq ) {
							// Neither were found.
							$attrIndex = $tagDataLen;
						}
					}
				}

				foreach ( $attributes as $att => $value ) {
					$attributes[$att] = html_entity_decode( $value );
				}

				$callbackResult = true;

				if ( !empty( $callback ) ) {
					// Call callback. Return tells us what to do.
					$text = "";
					$callbackResult = $callback( $tagName, $text, $isClosingTag, $selfClosedTag, $attributes, $callbackData );
				}
				
				//echo "Tag: ".htmlentities($tagName)." Data: ". htmlentities($tagData). " {$isClosingTag}<br />";
				if ( $callbackResult ) {
					if ( !$isClosingTag ) {
						// Opening tag - build it.
						$output .= rebuildTag( $tagName, $selfClosedTag, $attributes );
					} else {
						// Closing tag - pass verbatim.
						$output .= "<{$tagData}>";
					}
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
		if ( empty( $att ) ) continue;
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

?>
