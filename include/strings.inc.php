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
		// TODO: convert mailto: links to composer links

		// Locate "cid:" images and realign the image links to point to them.
		// Bascially, 'src="cid:InlineId"' becomes 'src="message.php?mailbox=Mailbox&uid=NNN&filename=cid:InlineId"'
		$imgUrl = "message.php?mailbox=" . urlencode($mailbox) . "&uid=" . urlencode($uid) . "&filename=";
		$string = preg_replace( '/<img(.*?)src=\"(cid:.*?)\"/is', "<img$1src=\"{$imgUrl}$2\"", $string );

		// Quick code test. TODO: implement properly
		$config = HTMLPurifier_Config::createDefault();
		@$config->set( 'HTML', 'Strict', true );
		@$config->set( 'HTML', 'Mode', array('correctional') );
		@$config->set( 'HTML', 'TidyLevel', 'medium' );
		@$config->set( 'Core', 'DefinitionCache', null );

		// Based loosely on the HTML elements accepted by Mark Pilgrim's feedparser
		// Disabled on all elements: event handlers,
		// plus class and id (which might conflict with Lichen's)
		// Optionally disabled on images: src
		//$config->set('HTML', 'AllowedElements', 'a,abbr,acronym,address,area,blockquote,br,button,caption,cite,code,col,colgroup,dd,del,dfn,div,dl,dt,em,fieldset,form,h1,h2,h3,h4,h5,h6,hr,img,input,ins,kbd,label,legend,li,map,ol,optgroup,option,p,pre,q,samp,select,span,strong,table,tbody,td,textarea,tfoot,th,thead,tr,ul,var');
		//$config->set('HTML', 'AllowedAttributes', '*.dir,*.lang,*.style,*.title,a.charset,a.coords,a.href,a.hreflang,a.name,a.rel,a.rev,a.shape,a.tabindex,a.type,area.coords,area.href,area.nohref,area.shape,area.tabindex,blockquote.cite,button.disabled,button.name,button.tabindex,button.type,button.value,col.char,col.charoff,col.span,colgroup.char,colgroup.charoff,colgroup.span,del.cite,del.datetime,form.accept-charset,form.accept,form.action,form.enctype,form.method,form.name,img.alt,img.ismap,img.longdesc,img.usemap,input.accept,input.checked,input.disabled,input.name,input.ismap,input.maxlength,input.readonly,input.size,input.tabindex,input.type,input.usemap,input.value,ins.cite,ins.datetime,label.for,map.name,optgroup.disabled,optgroup.label,option.disabled,option.label,option.selected,option.value,q.cite,select.disabled,select.multiple,select.name,select.size,select.tabindex,table.summary,tbody.char,tbody.charoff,td.abbr,td.axis,td.char,td.charoff,td.colspan,td.rowspan,td.scope,textarea.cols,textarea.disabled,textarea.name,textarea.readonly,textarea.rows,textarea.tabindex,tfoot.char,tfoot.charoff,th.abbr,th.axis,th.char,th.charoff,th.colspan,th.rowspan,th.scope,thead.char,thead.charoff,tr.char,tr.charoff' );

		$purifier = new HTMLPurifier( $config );
		$string = $purifier->purify( $string );

		// Disable remote images. We do this by inserting a "_" before the url (but after the "http://")
		// We also tag the images with the "remoteimage" class, allowing the client side JS to be able to find
		// them and reenable the images. Count the number of matches so we know if there are any remote images.
		$replacementCount = 0;
		if ( version_compare( PHP_VERSION, '5.1.0', '<' ) ) {
			// PHP < 5.1.0's preg_replace doesn't have the ability to count the matches.
			// So we do this beforehand with a preg_match_all, and then do the replacement.
			// (This is really a hack)
			$replacementCount = preg_match_all( '/<img(.*?)src=\"http:\/\/(.*?)\"/is', $string, $matches );
			$string = preg_replace( '/<img(.*?)src=\"http:\/\/(.*?)\"/is', "<img$1src=\"http://_$2\" class=\"remoteimg\"",
							$string, -1 );
		} else {
			$string = preg_replace( '/<img(.*?)src=\"http:\/\/(.*?)\"/is', "<img$1src=\"http://_$2\" class=\"remoteimg\"",
							$string, -1, $replacementCount );
		}
		if ( is_array( $outMsgFlags ) && $replacementCount > 0 ) {
			$outMsgFlags['htmlhasremoteimages'] = true;
		}

		// Wrap external links with an onclick call to if_newWin().
		// This adds an onclick= part to the links.
		$string = preg_replace( '/<a(.*?)href=\"http:\/\/(.*?)\"/is', "<a$1href=\"http://$2\" onclick=\"return if_newWin('http://$2');\"", $string );

		// Convert "mailto:" links into composer links.
		// This leaves the href intact, and inserts an onclick= part to the link.
		$string = preg_replace( '/<a(.*?)href=\"mailto:(.*?)\"/is', "<a$1href=\"mailto:$2\" onclick=\"comp_showForm('mailto',null,'$2');return false\"", $string );

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


// Takes a header (such as Subject: or From:) that might potentially be
// MIME-encoded, decodes it (if needed), and strips out HTML and newlines.
function filterHeader( $header ) {

	$headerBits = imap_mime_header_decode( $header );

	$returnString = "";

	foreach ( $headerBits as $element ) {
		$returnString .= convertToUTF8( $element->text, $element->charset );
	}

	// To ensure HTML entities are displayed correctly by the JavaScript
	if ( version_compare( PHP_VERSION, '5', '>=' ) ) {
		$returnString = html_entity_decode( $returnString, ENT_QUOTES, 'UTF-8' );
	} else {
		$returnString = html_entity_decode( $returnString, ENT_QUOTES );
	}

	$returnString = str_replace( "\r", "", $returnString );
	$returnString = str_replace( "\n", " ", $returnString );

	$returnString = htmlspecialchars( $returnString, ENT_NOQUOTES );

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
	$string = preg_replace_callback( '/(?<![<">]|&lt;|&gt;)http([s]?)\:\/\/(.+?)\s/i', 'convertLinksCallback', $string );

	// This version is meant to find links spanning multiple lines, delimited by < and >
	$string = preg_replace_callback( '/(<|&lt;)http([s]{0,1})\:\/\/(.+?)(>|&gt;)/is', 'convertLinksCallback', $string );

	// Simpler regexp to find www.foo without an http://
	$string = preg_replace_callback( '/(?<!http\:\/\/)www\.(.+?)(>|\"|\s)/i', 'convertLinksCallback', $string );

	// Convert e-mail addresses to composer links
	$string = preg_replace( '/(\<|\>|\;|\s|\"|\,)([\w\d]+[\w\d\.\_\-\+]*\@[\w\d]+[\w\d\.\_\-]+)(\,|\>|\&|\"|\s)/',
		"$1<a href=\"#compose\" onclick=\"comp_showForm('mailto',null,'$2');return false\">$2</a>$3", $string );

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
		$message = trim( strip_tags( $message ) );

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
		$message = trim( $message );
	}

	if ( $mode == "reply" ) {
		// Insert ">" into each line of the reply.
		// TODO: This line does too much in one go.
		$message = implode( "\n", array_map( "quoteReplyMarkup", explode( "\n", $message ) ) );
	}

	return $message;
}


// Takes the e-mail header Date: and converts it to the user's local
// time zone, formatted according to their preferences.
// PHP 5.1 introduced much-improved timezone handling that we use if
// available; otherwise, use the clunky putenv() trick and separate
// zone database from PEAR's Date library.
if ( version_compare( PHP_VERSION, '5.2.0', '>=' ) ) {

	function processDate( $headerString, $dateFormat='' ) {
		global $DATE_FORMAT_OLD, $DATE_FORMAT_NEW;

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
			$dateFormat = $DATE_FORMAT_OLD;
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
			$dateFormat = $DATE_FORMAT_OLD;
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
