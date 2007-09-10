<?php
/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
functions.inc.php - stub for initialisation and including other files
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

if ( !file_exists( "lichen-config.php" ) ) {
	die( _("<p><strong>Lichen isn&#8217;t set up yet.</strong></p><p>You need to edit the file <code>lichen-config-example.php</code> and save it with the name <code>lichen-config.php</code> to get started.</p><p>For more details, <a href=\"http://lichen-mail.org/docs/\">check the documentation</a>.</p>") );
}

include( "lichen-config.php" );

$LICHEN_VERSION = "0.3";

$mailbox = $SPECIAL_FOLDERS['inbox'];

// URL for this installation of Lichen - automagically determined.
$LICHEN_URL = dirname($_SERVER['SCRIPT_NAME']);
if ( $LICHEN_URL != "/" ) {
	// Add a trailing slash.
	$LICHEN_URL .= "/";
}

// Build our IMAP connection string.
// Start by determining whether we should use SSL: not if the server
// is local, and not if $IMAP_FORCE_NO_SSL has been set true.
if ( $IMAP_PORT == 0 ) {
	if ( $IMAP_SERVER == "localhost" || $IMAP_SERVER == "127.0.0.1" ) {
		$IMAP_PORT = 143;
	} elseif ( $IMAP_FORCE_NO_SSL ) {
		$IMAP_PORT = 143;
	} else {
		$IMAP_PORT = 993;
	}
}

$IMAP_CONNECT = "{" . $IMAP_SERVER . ":" . $IMAP_PORT;
$IS_SSL = false;	// used to call libs/streamattach.php

if ( $IMAP_USE_TLS ) {
	// Encrypted with TLS
	if ( $IMAP_CHECK_CERTS ) {
		$IMAP_CONNECT .= "/tls}";
	} else {
		$IMAP_CONNECT .= "/tls/novalidate-cert}";
	}
	$IS_SSL = false;

} elseif ( $IMAP_FORCE_NO_SSL || $IMAP_SERVER == "localhost" || $IMAP_SERVER == "127.0.0.1" ) {
	// Unencrypted connection
	$IMAP_CONNECT .= "/notls}";
	$IS_SSL = false;

} else {
	// Encrypted with SSL
	$IS_SSL = true;
	if ( $IMAP_CHECK_CERTS ) {
		$IMAP_CONNECT .= "/ssl}";
	} else {
		$IMAP_CONNECT .= "/ssl/novalidate-cert}";
	}
}

if ( get_magic_quotes_gpc() ) {
	// Magic quotes are on in the config.
	// This means that $_POST variables are escaped.
	// Undo this.
	// (Idea borrowed from WordPress)
	function stripslashes_deep($value) {
		$value = is_array($value) ? array_map('stripslashes_deep', $value) : stripslashes($value);
		return $value;
	}

	$_GET    = stripslashes_deep($_GET);
	$_POST   = stripslashes_deep($_POST);
	$_COOKIE = stripslashes_deep($_COOKIE);
}


include( 'include/imap.inc.php' );
include( 'include/interface.inc.php' );
include( 'include/message.inc.php' );
include( 'include/settings.inc.php' );
include( 'include/strings.inc.php' );
include( 'include/compose.inc.php' );

?>
