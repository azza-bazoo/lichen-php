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

		if ( $nextOpenTag !== false && $nextCloseTag !== false ) {
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
				$tagName = strtolower( $tagName );

				// Is this a closing tag?
				$isClosingTag = false;
				if ( $tagName[0] == "/" ) {
					$isClosingTag = true;
				}

				// Is the tag allowed?
				if ( isset( $ALLOWED_ELEMENTS[ $tagName ] ) ) {
					// Yes, it is.
				} else {
					// No, it is not.
				}

				// Validate attributes.
				// TODO: Attribute parser.
				
				// Does the tag need to be modified?
				if ( isset( $MODIFIED_TAGS[ $tagName ] ) ) {
					// TODO: This function should modify attributes array,
					// but to do that we have to give it that.
					$bazArray = array();
					$tagData = $MODIFIED_TAGS[$tagName]( $tagName, $tagData, $bazArray );
				}

				//echo "Tag: ".htmlentities($tagName)." Data: ". htmlentities($tagData). "<br />";

				$output .= "<{$tagData}>";
			}

			$processPos = $nextCloseTag + 1;
		}
	}

	return $output;
}

// TODO: Make these work properly. At the moment, they don't get
// the attributes passed to them...
function mailImageTag( $tagName, $rawTag, &$attributes ) {
	$imgUrl = "message.php?mailbox=INCOMPLETE&uid=INCOMPLETE&filename=";
	$rawTag = preg_replace( '/src=\"(cid:.*?)\"/is', "src=\"{$imgUrl}$1\"", $rawTag );
	$rawTag = preg_replace( '/src=\"http:\/\/(.*?)\"/is', "src=\"http://_$1\" class=\"remoteimg\"", $rawTag );
	return $rawTag;
}

function mailAnchorTag( $tagName, $rawTag, &$attributes ) {
	$rawTag = preg_replace( '/href=\"http:\/\/(.*?)\"/is', "href=\"http://$1\" onclick=\"return if_newWin('http://$1');\"", $rawTag );
	$rawTag = preg_replace( '/href=\"mailto:(.*?)\"/is', "href=\"mailto:$1\" onclick=\"comp_showForm('mailto',null,'$1');return false\"", $rawTag );
	return $rawTag;
}

?>
