<?php
/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
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

	if ( isset( $_REQUEST['silent'] ) ) {
		// Silent logout... do nothing
		die( remoteRequestSuccess() );
	}

	printPageHeader();
	drawLoginForm( _("You are now logged out.") );

	echo "</body></html>";
	exit;

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

	printPageHeader();

	// This is a hack.
	// Return the user settings here - this is the initial set.
	// We do this here so that all the startup JS has
	// access to it on initialisation.
	// TODO: Just get the client to request it via ajax callback;
	// also allows the client to cache it.
	echo "<script type=\"text/javascript\">";
	echo "var userSettings = ";
	$settings = json_encode_real( $USER_SETTINGS );
	if ( $settings == "null" ) {
		echo "{};";
	} else {
		echo $settings, ";";
	}

	// TODO: this shouldn't be needed or should be cleaned up
	echo "var serverUser = \"" . addslashes( $_SESSION['user'] ) . "\";";
	echo "</script>";


//------------------------------------------------------------------------------
// Draw toolbars

	echo <<<ENDBAR
<div id="flash" style="display: none" class="flash"></div>
<script type="text/javascript" src="lichen.js"></script>
<ul id="corner-bar" class="toolbar">
ENDBAR;

	drawToolbarButton( "settings", "configure", "#settings", "settings", "OptionsEditor.showEditor()" );
	drawToolbarButton( "log out", "exit", $LICHEN_URL."?logout", "logout", "" );

	echo "</ul><ul id=\"list-bar\" class=\"toolbar\">";

	drawToolbarButton( "compose", "mail_new", "#compose", "compose", "comp_showForm('','')" );

	// Caution: HTML hacks in the search form.
	echo <<<ENDSEARCH
<li id="btn-search"><form action="$LICHEN_URL" onsubmit="doQuickSearch();return false" style="display: inline; margin: 0;"><label for="qsearch"><img src="themes/{$USER_SETTINGS['theme']}/icons/mail_find.png" alt="" title="Search messages" /></label> <input type="text" name="qsearch" id="qsearch" style="display:inline;vertical-align:middle" /> <input type="submit" value="Go" style="display:inline;vertical-align:middle" /></form></li>
ENDSEARCH;

	echo "</ul><ul id=\"comp-bar\" class=\"toolbar\">";

	drawToolbarButton( "send message", "mail_send", "#inbox", "sendmsg", "comp_send()" );
	drawToolbarButton( "save draft", "filesave", "#compose", "savemsg", "comp_send(null,true)" );
	drawToolbarButton( "cancel", "button_cancel", "#inbox", "stopcomp", "if_returnToList(false)" );

	echo "</ul><ul id=\"msg-bar\" class=\"toolbar\">";

//	drawToolbarButton( "back to list", "back", "#inbox", "back", "if_returnToList(lastShownUID)" );

	drawToolbarButton( "reply", "mail_reply", "#compose", "reply", "comp_showForm('reply',lastShownUID)" );
	drawToolbarButton( "reply all", "mail_replyall", "#compose", "replyall", "comp_showForm('replyall',lastShownUID)" );
	drawToolbarButton( "forward", "mail_forward", "#compose", "forward", "comp_showForm('forward_default',lastShownUID)" );
	drawToolbarButton( "edit as draft", "editcopy", "#compose", "draft", "comp_showForm('draft',lastShownUID)" );

	// TODO: anchor target here should be to message.php
//	drawToolbarButton( "change view", "view_text", "#inbox", "view", "if_newWin('message.php?source&amp;mailbox='+listCurrentMailbox+'&amp;uid='+encodeURIComponent(lastShownUID))" );

	echo "</ul><ul id=\"opts-bar\" class=\"toolbar\">";

	drawToolbarButton( "save settings", "button_ok", "#inbox", "saveopts", "OptionsEditor.saveOptions()" );
	drawToolbarButton( "cancel", "button_cancel", "#inbox", "stopopts", "OptionsEditor.closePanel()" );

	echo "</ul>\n";

	echo "<ul id=\"mailboxes\">\n";
	echo "<li>", _("Loading ..."), "</li>\n";
	echo "</ul>\n";

	echo "<div id=\"list-wrapper\">", _("Loading ..."), "</div>";
	echo "<div id=\"msg-wrapper\"></div>";
	echo "<div id=\"opts-wrapper\"></div>";
	echo "<div id=\"comp-wrapper\"></div>";
	echo "<div id=\"addr-wrapper\"></div>";

	$imapErrors = imap_errors();
	imap_close($mbox);
	echo "</body></html>";


} else {

	// The user isn't logged in; display the login form.
	printPageHeader();
	drawLoginForm();
	echo "</body></html>";

}


//------------------------------------------------------------------------------
// Functions for this page - some reusable echo blocks

// Print HTML for the page header.
function printPageHeader() {
	global $USER_SETTINGS;

	if ( isset( $USER_SETTINGS['theme'] ) ) {
		$themePath = "themes/" . $USER_SETTINGS['theme'];
	} else {
		$themePath = "themes/default";
	}

	echo <<<ENDHEAD
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en"><head>
<title>Lichen Webmail</title>
<script type="text/javascript" src="mootools.v1.11.js"></script>
<link rel="StyleSheet" type="text/css" href="$themePath/default.css" />
<link rel="StyleSheet" type="text/css" href="$themePath/layout.css" />
</head><body>
ENDHEAD;
}


// Output the code (with LI, A, IMG tags) for a toolbar button,
// passing the label through gettext for l10n.
function drawToolbarButton( $buttonLabel, $icon, $anchorTarget, $id, $clickHandler ) {
	global $USER_SETTINGS;

	echo "<li id=\"btn-$id\">";
	echo "<a href=\"$anchorTarget\"";
	if ( $clickHandler ) {
		// TODO: this should be "return $clickHandler"
		echo " onclick=\"$clickHandler;return false\"";
	}
	echo ">";
	echo "<img src=\"themes/", $USER_SETTINGS['theme'], "/icons/", $icon, ".png\" alt=\"\" /> ";
	echo _( $buttonLabel );
	echo "</a></li>\n";
}


// Display a login form with optional user message.
function drawLoginForm( $message='' ) {
	global $LICHEN_URL;

	echo "<form action=\"$LICHEN_URL\" method=\"post\" class=\"login\"><div class=\"input-block\">\n";

	if ( $message ) {
		echo "<p class=\"login-notice\">$message</p>";
	}

	echo "<p><label for=\"user\">", _("username"), "</label><br />";
	echo "<input type=\"text\" name=\"user\" id=\"user\" /></p>\n";
	echo "<p><label for=\"pass\">", _("password"), "</label><br />";
	echo "<input type=\"password\" name=\"pass\" id=\"pass\" /></p>\n";
	echo "<p class=\"login-submit\"><input type=\"submit\" value=\"", _("Login"), "\" /></p>\n";
	echo "</div></form>";
}

?>