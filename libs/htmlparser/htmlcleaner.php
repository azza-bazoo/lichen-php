<?php
/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
libs/htmlparser/htmlcleaner.php - basic HTML cleaner.
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

$ALLOWED_TAGS = Array(
	"a" => true,
	"img" => true,
	"p" => true
);

$ALLOWED_ATTRIBUTES = Array(
	"src" => true,
	"class" => true
);

$MODIFIED_TAGS = Array(

);

function cleanHtml( $input, $callbackData ) {
	global $ALLOWED_TAGS, $ALLOWED_ATTRIBUTES, $MODIFIED_TAGS;

	if ( !isset( $callbackData['ALLOWED_TAGS'] ) ) {
		$callbackData['ALLOWED_TAGS'] = &$ALLOWED_TAGS;
	}
	if ( !isset( $callbackData['ALLOWED_ATTRIBUTES'] ) ) {
		$callbackData['ALLOWED_ATTRIBUTES'] = &$ALLOWED_ATTRIBUTES;
	}
	if ( !isset( $callbackData['MODIFIED_TAGS'] ) ) {
		$callbackData['MODIFIED_TAGS'] = &$MODIFIED_TAGS;
	}

	return parseHtml( $input, "cleanerCallback", $callbackData );
}

// $tagName: the name of the tag (empty if text node) (lowercased)
// $textNode: text between the last tag and the next tag (by reference so it can be modified) (empty if tag is set)
// $isClosingTag: is the closing version of this tag.
// $isSelfClosed: is a self-closed tag.
// $attribtutes: array of attributes, by reference so it can be modified.
// $callbackData: data passed to cleaner, to pass along to callbacks.
//
// return true to rebuild and output tag (or just append output text)
// return false to suppress tag from output.
function cleanerCallback( $tagName, &$textNode, $isClosingTag, $isSelfClosed, &$attributes, $callbackData ) {

	if ( !empty( $textNode ) ) {
		// If text node, return true.
		// (Include it)
		return true;
	}

	$tagAllowed = false;

	// Is the tag allowed?
	if ( isset( $callbackData['ALLOWED_TAGS'][$tagName] ) ) {
		$tagAllowed = true;

		foreach ( $attributes as $attr => $value ) {
			if ( !isset( $callbackData['ALLOWED_ATTRIBUTES'][$attr] ) ) {
				unset( $attributes[$attr] );
			}
		}
	}

	return $tagAllowed;
}
