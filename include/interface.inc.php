<?php
/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
include/interface.inc.php - functions for interacting with the client-side code
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

function isHtmlSession() {
	if ( isset( $_SESSION['htmlsession'] ) && $_SESSION['htmlsession'] ) {
		return true;
	} else {
		return false;
	}
}

function _GETORPOST( $variable, $default = "" ) {
	// Fetch the variable from GET first, or POST if it's not in GET.
	// If it's not in either, return the default value.
	if ( isset( $_GET[ $variable ] ) ) {
		return $_GET[ $variable ];
	} elseif ( isset( $_POST[ $variable ] ) ) {
		return $_POST[ $variable ];
	} else {
		return $default;
	}
}

function _ISSET_GETORPOST( $variable ) {
	if ( isset( $_GET[ $variable ] ) ) {
		return true;
	} elseif ( isset( $_POST[ $variable ] ) ) {
		return true;
	} else {
		return false;
	}
}

if ( !function_exists( 'json_encode' ) ) {
	// We don't have a json_encode function, so include one from PEAR.
	include( 'libs/JSON.php' );
	$json_convertor = new Services_JSON();

	function unobjectify_deep($value) {
		if ( is_object( $value ) ) {
			$value = get_object_vars( $value );
		}
		if ( is_array( $value ) ) {
			$value = array_map( 'unobjectify_deep', $value );
		}
		return $value;
	}
	function json_encode_assoc($data) {
		global $json_convertor;
		return $json_convertor->encode($data);
	}
	function json_decode_assoc($data) {
		global $json_convertor;
		$decoded = $json_convertor->decode($data);
		// PEAR's JSON follows the JSON spec exactly; so associative arrays become objects.
		// This breaks our code, because we expect to be able to array['foo'] which doesn't work with objects.
		$decoded = unobjectify_deep($decoded);
		return $decoded;
	}
} else {
	// PEAR's JSON functions take and return arrays.
	// PHP 5.2.0's will return 'stdClass' objects instead, which
	// breaks our code (because you can't call $data['foo'])
	function json_encode_assoc( $data ) {
		return json_encode( $data );
	}
	function json_decode_assoc( $data ) {
		// The second parameter here makes it return arrays instead.
		return json_decode( $data, true );
	}
}


// Prepares a JSON response to the client of a failure.
function remoteRequestFailure( $code, $message ) {
	$imapErrors = imap_errors();
	if ( $imapErrors ) {
		$imapErrors = implode( ", ", $imapErrors );
	} else {
		$imapErrors = "";
	}
	return json_encode_assoc(
		array(
			'resultCode' => $code,
			'errorMessage' => $message,
			'imapNotices' => $imapErrors
		));
}

// Successful remote request: merge the success tokens with the data
// and prepare that to send to the client.
function remoteRequestSuccess( $array = NULL ) {
	if ( $array == NULL ) {
		$array = array();
	}

	$array['resultCode'] = 'OK';
	$imapErrors = imap_errors();
	if ( $imapErrors ) {
		$array['imapNotices'] = implode( ", ", $imapErrors );
	}

	return json_encode_assoc( $array );
}

// Generates the part of a URL after the ? mark.
// Input is up to two associative arrays, with key value
// pairs to be made into a query string.
// Any keys in overData that already exist in baseData
// will be overridden.
// killData is an array of keys that will not be included, this is useful
// to generate URL bases that JavaScript can modify.
function genLinkQuery( $baseData, $overData = array(), $killData = array() ) {
	
	$allData = array_merge( $baseData, $overData );

	$bits = array();

	foreach ( $allData as $key => $value ) {
		if ( in_array( $key, $killData ) ) continue;

		$bits[] = urlencode( $key ) . "=" . urlencode( $value );
	}

	return htmlentities( implode( "&", $bits ) );
}

// This function works just like the above, but instead of generating a URL,
// generates a series of input type="hidden" elements, for a form.
// Also optionally puts out a form, if supplied with a form name. The form is NOT CLOSED.
function genLinkForm( $baseData, $overData = array(), $killData = array(), $formName = "", $formPost = "" ) {
	
	$allData = array_merge( $baseData, $overData );

	$bits = array();

	$form = "";
	if ( $formName != "" ) {
		// TODO: HTML Hack below to show form inline... I wonder how many browsers that's going to work for!
		$form = "<form name=\"{$formName}\" id=\"{$formName}\" method=\"post\" action=\"{$formPost}\" style=\"display: inline\">";
	}

	foreach ( $allData as $key => $value ) {
		if ( in_array( $key, $killData ) ) continue;
		$form .= "<input type=\"hidden\" name=\"" . htmlentities( $key ) . "\" value=\"" . htmlentities( $value ) . "\" />"; 
	}

	return $form;
}

// Print HTML for the page header.
function printPageHeader( $title = "" ) {
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
<title>Lichen Webmail{$title}</title>
<link rel="StyleSheet" type="text/css" href="$themePath/default.css" />
<link rel="StyleSheet" type="text/css" href="$themePath/layout.css" />
</head><body>
ENDHEAD;
}

// Draw a particular toolbar.
function drawToolbar( $toolbarName, $htmlMode = false, $toolbarData = array() ) {
	global $USER_SETTINGS, $LICHEN_URL, $mailbox;

	echo "<ul id=\"{$toolbarName}\" class=\"toolbar\"";
	if ( $htmlMode ) {
		// Force style to display.
		echo " style=\"display: block;\"";
	}
	echo ">";

	switch ( $toolbarName ) {
		case 'corner-bar':
			if ( $htmlMode ) {
				drawToolbarButton( "settings", "configure", "ajax.php?" . genLinkQuery( $toolbarData, array( 'sequence' => 'settings' ) ), "settings", "" );
			} else {
				drawToolbarButton( "settings", "configure", "#settings", "settings", "Lichen.action('options','OptionsEditor','showEditor',['settings'])" );
			}
			drawToolbarButton( "log out", "exit", $LICHEN_URL."?logout", "logout", "" );

			break;
		case 'list-bar':
			if ( $htmlMode ) {
				drawToolbarButton( "compose", "mail_new", "ajax.php?" .
					genLinkQuery( $toolbarData, array( "sequence" => "comp" ) ),
					"compose", "" );
			} else {
				drawToolbarButton( "compose", "mail_new", "#compose", "compose", "Lichen.action('compose','MessageCompose','showComposer')" );
			}

			// TODO: fix HTML hacks in the search form.
			if ( $htmlMode ) {
				echo "<li id=\"btn-search\"><form action=\"ajax.php\" style=\"display:inline;margin:0;\">",
					"<label for=\"search\">",
					"<img src=\"themes/{$USER_SETTINGS['theme']}/icons/mail_find.png\" alt=\"\" title=\"",
					_("Search messages"),
					"\" /></label>",
					" <input type=\"text\" name=\"search\" id=\"search\" style=\"display:inline;vertical-align:middle\" />",
					" <input type=\"submit\" value=\"", _("search"), "\" style=\"display:inline;vertical-align:middle\" />",
					"<input type=\"hidden\" name=\"mailbox\" value=\"", htmlentities($mailbox) , "\" />",
					"<input type=\"hidden\" name=\"sequence\" value=\"list\" />",
					"</form></li>";
			} else {
				echo "<li id=\"btn-search\"><form action=\"$LICHEN_URL\" onsubmit=\"Lichen.action('list','MessageList','setSearch',[$('qsearch').value]);return false\" style=\"display:inline;margin:0;\"><label for=\"qsearch\"><img src=\"themes/{$USER_SETTINGS['theme']}/icons/mail_find.png\" alt=\"\" title=\"", _("Search messages"), "\" /></label> <input type=\"text\" name=\"qsearch\" id=\"qsearch\" style=\"display:inline;vertical-align:middle\" /> <input type=\"submit\" value=\"", _("search"), "\" style=\"display:inline;vertical-align:middle\" /></form></li>";
			}

			break;
		case 'comp-bar':
			if ( $htmlMode ) {
				// How do we handle these toolbar buttons in the HTML version? Some headscratching required.
				//drawToolbarButton( "send message", "mail_send", "#inbox", "sendmsg", "Lichen.action('compose','MessageCompose','sendMessage')" );
				//drawToolbarButton( "save draft", "filesave", "#compose", "savemsg", "Lichen.action('compose','MessageCompose','sendMessage',[true])" );
				drawToolbarButton( "cancel", "button_cancel", "ajax.php?" .
					genLinkQuery( $toolbarData, array( "sequence" => "list" ) ),
					"stopcomp", "" );
			} else {
				drawToolbarButton( "send message", "mail_send", "#inbox", "sendmsg", "Lichen.action('compose','MessageCompose','sendMessage')" );
				drawToolbarButton( "save draft", "filesave", "#compose", "savemsg", "Lichen.action('compose','MessageCompose','sendMessage',[true])" );
				drawToolbarButton( "cancel", "button_cancel", "#inbox", "stopcomp", "Lichen.action('list','MessageList','listUpdate')" );
			}
			break;
		case 'msg-bar':
			if ( $htmlMode ) {
				drawToolbarButton( "reply", "mail_reply", "ajax.php?" .
					genLinkQuery( $toolbarData, array( "sequence" => "comp", "mode" => "reply", "uid" => $toolbarData['msg'] ) ),
					"reply", "" );
				drawToolbarButton( "reply all", "mail_replyall", "ajax.php?" .
					genLinkQuery( $toolbarData, array( "sequence" => "comp", "mode" => "replyall", "uid" => $toolbarData['msg'] ) ),
					"replyall", "" );
				drawToolbarButton( "forward", "mail_forward", "ajax.php?".
					genLinkQuery( $toolbarData, array( "sequence" => "comp", "mode" => "forward_default", "uid" => $toolbarData['msg'] ) ),
					"forward", "" );
				drawToolbarButton( "edit as draft", "editcopy", "ajax.php?".
					genLinkQuery( $toolbarData, array( "sequence" => "comp", "mode" => "draft", "uid" => $toolbarData['msg'] ) ),
					"draft", "" );
			} else {
				drawToolbarButton( "reply", "mail_reply", "#compose", "reply", "Lichen.action('compose','MessageCompose','showComposer',['reply',Lichen.MessageDisplayer.getViewedUID()])" );
				drawToolbarButton( "reply all", "mail_replyall", "#compose", "replyall", "Lichen.action('compose','MessageCompose','showComposer',['replyall',Lichen.MessageDisplayer.getViewedUID()])" );
				drawToolbarButton( "forward", "mail_forward", "#compose", "forward", "Lichen.action('compose','MessageCompose','showComposer',['forward_default',Lichen.MessageDisplayer.getViewedUID()])" );
				drawToolbarButton( "edit as draft", "editcopy", "#compose", "draft", "Lichen.action('compose','MessageCompose','showComposer',['draft',Lichen.MessageDisplayer.getViewedUID()])" );
			}
			break;
		case 'opts-bar':
			// For this release, these have been moved to below the options themselves
		//	drawToolbarButton( "save changes", "button_ok", "#inbox", "saveopts", "OptionsEditor.saveOptions()" );
		//	drawToolbarButton( "cancel", "button_cancel", "#inbox", "stopopts", "OptionsEditor.closePanel()" );
			break;
	}

	echo "</ul>";
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
	echo "<p><label><input type=\"checkbox\" name=\"interface\" id=\"interface\" value=\"html\" checked=\"checked\" />";
	echo _("Disable JavaScript"), "</label></p>\n";
	echo "<p class=\"login-submit\"><input type=\"submit\" value=\"", _("Login"), "\" /></p>\n";
	echo "</div></form>";
	
	echo <<<ENDJS
<script language="JavaScript" type="text/javascript">
if ( document.getElementById( 'interface' ) ) {
	document.getElementById( 'interface' ).checked = false;
}
</script>
ENDJS;
}

?>
