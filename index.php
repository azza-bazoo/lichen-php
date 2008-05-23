<?php
/**

Lichen - AJAX IMAP client
version 0.4 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
index.php - session handling and interface initialisation
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

include( "functions.inc.php" );

session_start();


//------------------------------------------------------------------------------
// Is the user logging out?

if ( isset( $_REQUEST['logout'] ) ) {

	$_SESSION = array();
	if (isset($_COOKIE[session_name()])) {
		setcookie(session_name(), '', time()-42000, '/');
	}
	session_destroy();

	if ( _ISSET_GETORPOST( 'silent' ) ) {
		// Silent logout... do nothing
		die( remoteRequestSuccess() );
	}

	printPageHeader();
	drawLoginForm( _("You are now logged out.") );

	echo "</body></html>";
	exit;

}

if ( _ISSET_GETORPOST( 'interface' ) ) {
	if ( _GETORPOST( 'interface' ) == "html" ) {
		$_SESSION['htmlsession'] = true;
	} else {
		$_SESSION['htmlsession'] = false;
	}
}

//------------------------------------------------------------------------------
// Check login details

if ( ( isset( $_POST['user'] ) && !empty( $_POST['user'] ) &&
	isset( $_POST['pass'] ) && !empty( $_POST['pass'] ) )
	|| ( isset( $_SESSION['user'] ) && isset( $_SESSION['pass'] ) ) ) {

	if ( !isset( $_SESSION['user'] ) && !isset( $_SESSION['pass'] ) ) {
		$user = $_POST['user'];
		$pass = $_POST['pass'];
		$_SESSION['user'] = $user;
		$_SESSION['pass'] = $pass;
	} else {
		$user = $_SESSION['user'];
		$pass = $_SESSION['pass'];
	}

	// TODO: these functions might best be moved into, e.g., initialise()
	$result = connectToServer( $user, $pass, "INBOX", true );

	if ( !$result ) {
		// Unable to login? Probably incorrect username/password.
		printPageHeader();
		drawLoginForm( _("Unable to login: ") . imap_last_error() );

		$_SESSION = array();
		session_destroy();

		// Supress the IMAP errors.
		imap_errors();

		echo "</body></html>";
		exit;
	}

	if ( isset( $_POST['action'] ) && $_POST['action'] == "relogin" ) {
		// This is a "relogin" because the login timed out.
		// Thus, don't do the normal things, just say that we're done.
		die( remoteRequestSuccess() );
	}

	$USER_SETTINGS = getUserSettings();

	if ( version_compare( PHP_VERSION, '5.1.0', '>=' ) ) {
		date_default_timezone_set( $USER_SETTINGS['timezone'] );
	}

	if ( !isHtmlSession() ) {
		printPageHeader();

		// This is a hack.
		// Return the user settings here - this is the initial set.
		// We do this here so that all the startup JS has
		// access to it on initialisation.
		// TODO: Just get the client to request it via ajax callback;
		// also allows the client to cache it.
		echo "<script type=\"text/javascript\">";
		echo "var userSettings = ";
		$settings = json_encode_assoc( $USER_SETTINGS );
		if ( $settings == "null" ) {
			echo "{};";
		} else {
			echo $settings, ";";
		}

		// TODO: this shouldn't be needed or should be cleaned up
		echo "var serverUser = \"" . addslashes( $_SESSION['user'] ) . "\";";
		echo "var specialFolders = " . json_encode_assoc( $SPECIAL_FOLDERS ) . ";";
		echo "var lichenURL = \"" . addslashes( $LICHEN_URL ) . "\";";
		echo "</script>";


//------------------------------------------------------------------------------
// Draw toolbars

		echo <<<ENDJS
<script type="text/javascript" src="js/mootools.v1.11.js"></script>
<script type="text/javascript" src="js/lichen-r249.js"></script>
ENDJS;

		drawToolbar( 'corner-bar' );
		drawToolbar( 'list-bar' );
		drawToolbar( 'comp-bar' );
		drawToolbar( 'msg-bar' );
		drawToolbar( 'opts-bar' );

		echo "<ul id=\"mailboxes\">\n";
		echo "<li>", _("Loading ..."), "</li>\n";
		echo "</ul>\n";

		echo "<div id=\"list-wrapper\">", _("Loading ..."), "</div>";
		echo "<div id=\"msg-wrapper\"></div>";
		echo "<div id=\"opts-wrapper\"></div>";
		echo "<div id=\"comp-wrapper\"></div>";
		echo "<div id=\"addr-wrapper\"></div>";
		echo "<div id=\"notification\"></div>";
		echo "<div id=\"loading-box\" style=\"display: none;\">";
		echo "<img src=\"themes/{$USER_SETTINGS['theme']}/icons/spinner.gif\" /> ", _("Loading ...");
		echo "</div>";

		$imapErrors = imap_errors();
		@imap_close($mbox);
		echo "</body></html>";
	} else {
		// Redirect the user to ajax.php.
		header( "Location: ajax.php" );
	}


} else {

	// The user isn't logged in; display the login form.
	printPageHeader();
	drawLoginForm();
	echo "</body></html>";

}



?>
