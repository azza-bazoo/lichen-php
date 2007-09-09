<?php
/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
include/settings.inc.php - functions to grab and manipulate user settings
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

// Get the users data directory, built from the logged in user.
// Creates them if they don't exist.
function getUserDirectory() {
	global $LICHEN_DATA;

	// TODO: this unsafe, username will need to be escaped (for ../ etc.)
	$userDataDir = $LICHEN_DATA . $_SESSION['user'];

	if ( !is_dir( $userDataDir ) ) {
		@mkdir( $userDataDir );
		@mkdir( $userDataDir . "/attachments" );
	}

	return $userDataDir;
}


// Load the current user's settings from file
function getUserSettings() {
	global $DEFAULT_SETTINGS, $SMTP_DOMAIN;

	$userDataDir = getUserDirectory();

	$dataFile = "{$userDataDir}/lichenrc";

	$settings = null;
	if ( file_exists( $dataFile ) ) {
		// Load previously saved settings
		$settings = json_decode_assoc( file_get_contents( $dataFile ) );
	}

	if ( $settings == null || $DEFAULT_SETTINGS['defaults_version'] != $settings['defaults_version'] ) {
		// Prepare default settings for this user, or update new settings.
		// This only adds new keys to the settings array, won't change others.
		$newSettings = array(
			"timezone" => "UTC",
			"theme" => "default",
			"list_pagesize" => 20,
			"list_sortmode" => "date_r",
			"forward_as_attach" => true,
			"list_showpreviews" => true,
			"list_showsize" => false,
			"boxlist_showtotal" => false,
			"identities" =>
				array(
					array(
						"name" => $_SESSION['user'],
						"address" => "{$_SESSION['user']}@{$SMTP_DOMAIN}",
						"isdefault" => true
					),
				),
			);

		if ( $settings == null ) $settings = array();

		// Precedence order: built-in settings highest, then administrator
		// settings from lichen-config.php, then the user's own settings.
		$settings = array_merge( $newSettings, $DEFAULT_SETTINGS, $settings );

		// Create a config file and write these settings.
		saveUserSettings( $settings );
	}

	return $settings;
}


function saveUserSettings( $settings ) {
	// Save the users settings.

	$userDataDir = getUserDirectory();

	$file = fopen( "{$userDataDir}/lichenrc", "w" );
	$result = false;
	if ( $file !== false ) {
		$result = fwrite( $file, json_encode_assoc( $settings ) );
		fclose( $file );
	}

	if ($result === false) {
		return false;
	} else {
		return true;
	}
}


function getUserIdentity( $byAddress = NULL ) {
	global $USER_SETTINGS;
	// Fetch the users identity from their settings.
	// If null is given, find the default.

	foreach ( $USER_SETTINGS['identities'] as $identity ) {
		if ( $identity['address'] == $byAddress ) {
			return $identity;
		}
		if ( $identity['isdefault'] && $byAddress == NULL ) {
			return $identity;
		}
	}

	// If control reached here, we didn't find it.
	// Return NULL to indicate no identities to send as.
	return NULL;
}


// Assuming that $_POST variables have been set corresponding to input from
// the settings panel, update the global $USER_SETTINGS array with the changes
// and return an array of errors, if there are any.
function parseUserSettings() {
	global $USER_SETTINGS, $_DATE_TIMEZONE_DATA;

	$settingErrors = array();

	// To handle ye-old-fashioned HTML settings... we note
	// that if a checkbox is unchecked, nothing is returned.
	// The code below looks for keys and sets/unsets them...
	// which doesn't work if we posted back a form, because
	// the unset values don't get posted back. So merge in the
	// default values first (false) and then superimpose the real
	// values from $_POST.
	$_POST = array_merge(
		array(
			"opts-list_showpreviews" => "false",
			"opts-list_showsize" => "false",
			"opts-boxlist_showtotal" => "false"
		),
		$_POST );

	foreach ( $_POST as $keyName => $value ) {
		$value = urldecode( $value );

		switch ( $keyName ) {
			case 'opts-timezone':
				if ( isset( $_DATE_TIMEZONE_DATA ) ) {
					// PHP < 5.2: the PEAR library uses a global array
					if ( !isset( $_DATE_TIMEZONE_DATA[$value] ) ) {
						array_push( $settingErrors,
							"timezone - invalid timezone name" );
					} else {
						$USER_SETTINGS['timezone'] = $value;
					}
				} else {
					// PHP > 5.2: create an array of all supported
					// timezones, and look for the one given
					foreach ( DateTimeZone::listIdentifiers() as $zone ) {
						if ( $value == $zone ) {
							$USER_SETTINGS['timezone'] = $value;
							break;
						}
					}
				}
				break;

			case 'opts-list_pagesize':
				if ( !ctype_digit( $value ) ) {
					array_push( $settingErrors,
						"list_pagesize - not a valid number" );
				} elseif ( $value < 5 ) {
					array_push( $settingErrors,
						"list_pagesize - this value is too low" );
				} elseif ( $value > 200 ) {
					array_push( $settingErrors,
						"list_pagesize - this value is too high" );
				} else {
					$USER_SETTINGS['list_pagesize'] = $value;
				}
				break;

			case 'opts-list_showpreviews':
				if ( $value == 'true' ) {
					$USER_SETTINGS['list_showpreviews'] = true;
				} else {
					$USER_SETTINGS['list_showpreviews'] = false;
				}
				break;

			case 'opts-list_showsize':
				if ( $value == 'true' ) {
					$USER_SETTINGS['list_showsize'] = true;
				} else {
					$USER_SETTINGS['list_showsize'] = false;
				}
				break;

			case 'opts-boxlist_showtotal':
				if ( $value == 'true' ) {
					$USER_SETTINGS['boxlist_showtotal'] = true;
				} else {
					$USER_SETTINGS['boxlist_showtotal'] = false;
				}
				break;

			case 'opts-forward_as_attach':
				// This handles the old-skool HTML POST query style.
			case 'opts-forward_as_attach-true':
				if ( $value == 'true' ) {
					$USER_SETTINGS['forward_as_attach'] = true;
				} else {
					$USER_SETTINGS['forward_as_attach'] = false;
				}
				break;

			case 'opts-forward_as_attach-false':
				// Ignore this; option above is used instead
				break;

			case 'request':
				// This is used by the dispatcher in ajax.php
				break;

			case 'identities-list':
				// Identities should have been set in ajax.php already
				break;
		}
	}

	return $settingErrors;
}


// Generate the HTML for the settings interface
function generateOptionsPanel( $htmlMode = false, $htmlData = array(), $htmlPars = array() ) {
	global $USER_SETTINGS, $DEFAULT_SETTINGS;

	// It-will-do-for-now hack to display only the requested tab;
	// this is best done with display:none on the client side
	// (though it might slow PHP due to all of the concatenation)

	// Right-side float here is to prevent IE7 collapsing the div
	$panel = "<div class=\"header-bar\"><img src=\"themes/{$USER_SETTINGS['theme']}/top-corner.png\" alt=\"\" class=\"top-corner\" />";
	$panel .= "<div class=\"header-right\">&nbsp;</div>";

	$tab = _GETORPOST( 'tab', 'settings' );

	// Boring, repetitive 'if's for the tab bar
	if ( $htmlMode ) {
		$panel .= "<div class=\"opts-tabbar\"><a href=\"ajax.php?" . genLinkQuery( $htmlPars, array( "tab" => 'settings' ) ) . "\"";
	} else {
		$panel .= "<div class=\"opts-tabbar\"><a href=\"#\" onclick=\"return Lichen.action('options','OptionsEditor','showEditor',['settings'])\"";
	}
	if ( $tab == 'settings' ) { $panel .= " class=\"opts-activetab\""; }
	$panel .= ">" . _("Lichen settings") . "</a> ";
	if ( $htmlMode ) {
		$panel .= "<a href=\"ajax.php?" . genLinkQuery( $htmlPars, array( "tab" => 'identities' ) ) . "\"";
	} else {
		$panel .= "<a href=\"#\" onclick=\"return Lichen.action('options','OptionsEditor','showEditor',['identities'])\"";
	}
	if ( $tab == 'identities' ) { $panel .= " class=\"opts-activetab\""; }
	$panel .= ">" . _("Sending identities") . "</a> ";
	if ( $htmlMode ) {
		$panel .= "<a href=\"ajax.php?" . genLinkQuery( $htmlPars, array( "tab" => 'mailboxes' ) ) . "\"";
	} else {
		$panel .= "<a href=\"#\" onclick=\"return Lichen.action('options','OptionsEditor','showEditor',['mailboxes'])\"";
	}
	if ( $tab == 'mailboxes' ) { $panel .= " class=\"opts-activetab\""; }
	$panel .= ">" . _("Mailbox manager") . "</a></div></div>";

	switch ( $tab ) {
		case 'settings':
			$panel .= generateSettingsForm( $htmlMode, $htmlData, $htmlPars );
			break;
		case 'identities':
			$panel .= generateIdentityEditor( $htmlMode, $htmlData, $htmlPars );
			break;
		case 'mailboxes':
			$panel .= generateMailboxManager( $htmlMode, $htmlData, $htmlPars );
			break;
	}

	$resultData = array( "htmlFragment" => $panel );
	if ( $tab == 'mailboxes' ) {
		$resultData['mailboxes'] = getMailboxList();
	}

	return $resultData;
}


// Generate HTML for the form to edit general preferences
function generateSettingsForm( $htmlMode = false, $htmlData = array(), $htmlPars = array() ) {
	global $USER_SETTINGS;

	$result = "";

	if ( $htmlMode ) {
		$result .= "<form class=\"opts-tab\" id=\"opts-settings\" method=\"post\" action=\"ajax.php\">";
		$result .= genLinkForm( $htmlPars );
	} else {
		$result .= "<form class=\"opts-tab\" id=\"opts-settings\" method=\"post\" onsubmit=\"return Lichen.action('options','OptionsEditor','saveOptions')\" action=\"#\">";
	}

	//--------------------
	// Timezone selector
	$result .= "<div class=\"opts-block\"><label for=\"opts-timezone\" class=\"opts-name\">Your timezone:</label> " . generateTimezoneSelect( $USER_SETTINGS['timezone'] ) . "</div>";

	//--------------------
	// Messages per page
	// TODO: clean up the CSS here
	$result .= "<div class=\"opts-block\">In message listings:<div style=\"margin-left:170px;margin-top:-18px\">";

	$result .= "show <select name=\"opts-list_pagesize\" id=\"opts-list_pagesize\">";

	$sizeOptions = array( 5, 10, 20, 25, 50, 75, 100 );
	foreach ( $sizeOptions as $i ) {
		$result .= "<option value=\"$i\" ";
		if ( $USER_SETTINGS['list_pagesize'] == $i ) { $result .= "selected=\"selected\" "; }
		$result .= ">$i</option>";
	}

	$result .= "</select> <label for=\"opts-list_pagesize\" class=\"opts-name\">messages per page</label><br />";

	//--------------------
	// Show message preview
	$result .= "<input type=\"checkbox\" name=\"opts-list_showpreviews\" id=\"opts-list_showpreviews\" ";

	if ( $USER_SETTINGS['list_showpreviews'] ) { $result .= "checked=\"checked\" "; }

	$result .= " value=\"true\" /> <label for=\"opts-list_showpreviews\" class=\"opts-name\">show message previews</label><br />";

	//--------------------
	// Show 'size' column
	$result .= "<input type=\"checkbox\" name=\"opts-list_showsize\" id=\"opts-list_showsize\" ";

	if ( $USER_SETTINGS['list_showsize'] ) { $result .= "checked=\"checked\" "; }

	$result .= " value=\"true\" /> <label for=\"opts-list_showsize\" class=\"opts-name\">show size</label></div></div>";

	//--------------------
	// Show totals in mailbox list
	$result .= "<div class=\"opts-block\"><input type=\"checkbox\" name=\"opts-boxlist_showtotal\" id=\"opts-boxlist_showtotal\" ";

	if ( $USER_SETTINGS['boxlist_showtotal'] ) { $result .= "checked=\"checked\" "; }

	$result .= " value=\"true\" /> <label for=\"opts-boxlist_showtotal\" class=\"opts-name\">Show total count for each mailbox</label></div>";

	//--------------------
	// Default forward mode
	$result .= "<div class=\"opts-block\">By default, forward messages as: <input type=\"radio\" name=\"opts-forward_as_attach\" id=\"opts-forward_as_attach-true\" value=\"true\" ";

	if ( $USER_SETTINGS['forward_as_attach'] ) { $result .= "checked=\"checked\" "; }

	$result .= " /> <label for=\"opts-forward_as_attach-true\" class=\"opts-name\">attachments</label> <input type=\"radio\" name=\"opts-forward_as_attach\" id=\"opts-forward_as_attach-false\" value=\"false\" ";

	if ( !$USER_SETTINGS['forward_as_attach'] ) { $result .= "checked=\"checked\" "; }

	$result .= " /> <label for=\"opts-forward_as_attach-false\" class=\"opts-name\">inline</label></div>";

	//--------------------
	// OK/cancel buttons
	$result .= "<p class=\"opts-buttons\">";

	if ( $htmlMode ) {
		$result .= "<button type=\"submit\" name=\"setaction\" value=\"save changes\"><img src=\"themes/{$USER_SETTINGS['theme']}/icons/button_ok.png\" alt=\"\" /> " . _( "save changes" ) . "</button>";

		$result .= "<a href=\"ajax.php?" . genLinkQuery( $htmlPars, array( 'sequence' => 'list' ) ) . "\">" .
			"<img src=\"themes/{$USER_SETTINGS['theme']}/icons/button_cancel.png\" alt=\"\" /> " . _( "cancel" ) . "</a>";
	} else {
		$result .= "<button type=\"submit\"><img src=\"themes/{$USER_SETTINGS['theme']}/icons/button_ok.png\" alt=\"\" /> " . _( "save changes" ) . "</button>";

		$result .= "<button onclick=\"return Lichen.action('options','OptionsEditor','closePanel'])\"><img src=\"themes/{$USER_SETTINGS['theme']}/icons/button_cancel.png\" alt=\"\" /> " . _( "cancel" ) . "</button>";
	}

	$result .= "</p></form>";

	return $result;
}


// Generate HTML for the sending identities editor
function generateIdentityEditor( $htmlMode = false, $htmlData = array(), $htmlPars = array() ) {
	global $USER_SETTINGS;

	// Temporary workaround in case a default identity isn't set
	// (the saving code should check for & prevent this scenario)
	$defaultIdentity = $USER_SETTINGS['identities'][count($USER_SETTINGS['identities']) - 1];

	$result = "";

	if ( $htmlMode ) {
		$result .= "<form class=\"opts-tab\" id=\"opts-identities\" method=\"post\" action=\"ajax.php\">";
		$result .= genLinkForm( $htmlPars );
	} else {
		$result .= "<form class=\"opts-tab\" id=\"opts-identities\" method=\"post\" onsubmit=\"return false\" action=\"#\">";
	}
	
	$result .= "<div id=\"opts-identity-sel\"><select size=\"10\" id=\"opts-identity-list\" name=\"opts-identity-list\" ";
	if ( !$htmlMode ) {
		$result .= "onchange=\"Lichen.OptionsEditor.identity_edit()\"";
	}
	$result .= ">";

	foreach ( $USER_SETTINGS['identities'] as $thisIdentity ) {

		// TODO: Don't do this comma seperated address/name thing.
		// Will probably come out when signatures get worked into the mix.
		$result .= "<option value=\"" . htmlentities( $thisIdentity['address'] ) . "," . htmlentities( $thisIdentity['name'] ) . "\" ";
		$isSelected = false;
		if ( isset( $htmlData['workingident'] ) && $thisIdentity['address'] == $htmlData['workingident'] ) {
			$isSelected = true;
		} else if ( $thisIdentity['isdefault'] ) {
			$isSelected = true;
		}
		if ( $isSelected ) {
			$result .= "selected=\"selected\" ";
		}
//		$result .= " onclick=\"OptionsEditor.identity_edit()\">";
		$result .= ">";

		$result .= htmlentities( $thisIdentity['name'] ) . " &lt;" . htmlentities( $thisIdentity['address'] ) . "&gt;";

		if ( $thisIdentity['isdefault'] ) {
			$result .= " (default)";
			$defaultIdentity = $thisIdentity;
		}

		$result .= "</option>";

	}

	$result .= "</select>";

	if ( $htmlMode ) {
		// TODO: Fix the layout of these buttons.
		$result .= "<p class=\"opts-buttons\"><input type=\"submit\" name=\"setaction\" value=\"add identity\" />";
		$result .= "<input type=\"submit\" name=\"setaction\" value=\"edit identity\" /><br />";
		$result .= "<input type=\"submit\" name=\"setaction\" value=\"set as default\" />";
		$result .= "<input type=\"submit\" name=\"setaction\" value=\"remove identity\" /></p></div>";
	} else {
		$result .= "<p class=\"opts-buttons\"><button onclick=\"return Lichen.OptionsEditor.identity_add()\"><img src=\"themes/{$USER_SETTINGS['theme']}/icons/edit_add.png\" alt=\"\" />" . _("add identity") . "</button> ";
		$result .= "<button onclick=\"return Lichen.OptionsEditor.identity_setdefault()\"><img src=\"themes/{$USER_SETTINGS['theme']}/icons/filenew.png\" alt=\"\" />" . _("set as default") . "</button> ";
		$result .= "<button onclick=\"return Lichen.OptionsEditor.identity_remove()\"><img src=\"themes/{$USER_SETTINGS['theme']}/icons/edit_remove.png\" alt=\"\" />" . _("remove") . "</button></p></div>";
	}

	$result .= "<div id=\"opts-identity-edit\">";

	$detailsToShow = $defaultIdentity;
	if ( isset( $htmlData['workingident'] ) ) {
		$detailsToShow = array( "name" => "", "address" => "" );

		foreach ( $USER_SETTINGS['identities'] as $identity ) {
			if ( $identity['address'] == $htmlData['workingident'] ) {
				$detailsToShow = $identity;
			}
		}
		$result .= "<input type=\"hidden\" name=\"opts-identity-working\" value=\"". htmlentities( $htmlData['workingident'] ) . "\" />";
	}

	$result .= "<p><label for=\"opts-identity-name\">" . _("your name:") . "</label> ";
	$result .= "<input type=\"text\" size=\"30\" id=\"opts-identity-name\" name=\"opts-identity-name\" value=\"" . $detailsToShow['name'] . "\" /><br />";
	$result .= "<label for=\"opts-identity-address\">" . _("e-mail address:") . "</label> ";
	$result .= "<input type=\"text\" size=\"30\" id=\"opts-identity-address\" name=\"opts-identity-address\" value=\"" . $detailsToShow['address'] . "\" /></p>";
	
	if ( $htmlMode ) {
		$result .= "<p><input type=\"submit\" name=\"setaction\" value=\"save identity\" /></p>";
	} else {
		$result .= "<p><button id=\"opts-identity-save\" onclick=\"return Lichen.OptionsEditor.identity_edit_done('" . $defaultIdentity['address'] . "')\">" . _("save changes") . "</button></p>";
	}

	$result .= "</div>";

	if ( !$htmlMode ) {
		$result .= "<p class=\"opts-close\"><button onclick=\"Lichen.action('options','OptionsEditor','closePanel');return false\">" . _( "close" ) . "</button></p></form>";
	}

	return $result;
}


// Generate HTML for the mailbox manager, polling the IMAP server for a list.
function generateMailboxManager( $htmlMode = false, $htmlData = array(), $htmlPars = array() ) {
	global $USER_SETTINGS;

	$result = "";

	if ( $htmlMode ) {
		//$result .= "<form class=\"opts-tab\" id=\"opts-mailboxes\" method=\"post\" action=\"ajax.php\">";
		$result .= genLinkForm( $htmlPars, array(), array(), "opts-mailboxes", "ajax.php" );
		$result .= "<input type=\"submit\" name=\"setaction\" value=\"add mailbox\" />";
		$result .= "</form>";
	} else {
		$result .= "<form class=\"opts-tab\" id=\"opts-mailboxes\" method=\"post\" onsubmit=\"return false\" action=\"#\">";
		$result .= "<button onclick=\"Lichen.MailboxManager.newChild('');return false\">" . _("add mailbox") . "</button>";
	}
	$result .= "<div id=\"mbm-changearea-\">";
	if ( $htmlMode && $htmlPars['setaction'] == "add mailbox" ) {
		$result .= genLinkForm( $htmlPars, array( "setactionreturn" => "true", "mbm-mailbox" => "" ), array(), "opts-mailboxes", "ajax.php" );
		$result .= "<input type=\"text\" size=\"20\" name=\"newname\" />";
		$result .= "<input type=\"submit\" value=\"add\" />";
		$result .= "</form>";
	}
	$result .= "</div>";

// 	$result .= "<div class=\"sidebar-panel\">";
// 	$result .= "<img src=\"themes/{$USER_SETTINGS['theme']}/icons/edit_add.png\" alt=\"\" /> <strong>" . _("add mailbox") . "</strong><br />";
// 	$result .= "<label for=\"mbm-newchild-\">" . _("name:") . "</label> <input id=\"mbm-newchild-\" type=\"text\" />";
// 	$result .= "<button onclick=\"MailboxManager.newChildSubmit(''); return false\">Add</button>";
// 	$result .= "</div>";

	$result .= "<ul id=\"opts-mbm-list\">";
	$mailboxList = getMailboxList();

	foreach ( $mailboxList as $thisMailbox ) {
		$result .= "<li>";
		if ( $htmlMode ) {
			$result .= genLinkForm( $htmlPars, array( "mbm-mailbox" => $thisMailbox['fullboxname'] ), array(), "opts-mailboxes", "ajax.php" );
			$result .= "<input type=\"submit\" name=\"setaction\" value=\"move mailbox\" /> ";
		} else {
			$result .= "[<a href=\"#\" onclick=\"Lichen.MailboxManager.changeParentInline('{$thisMailbox['fullboxname']}', '{$thisMailbox['mailbox']}'); return false\">move</a>] ";
		}

		$result .= "<span class=\"opts-mbm-name\" id=\"mbm-namearea-{$thisMailbox['fullboxname']}\">";
		$result .= str_repeat( "-", $thisMailbox['folderdepth'] );
		$result .= $thisMailbox['mailbox'];
		$result .= "</span>";

		if ( $htmlMode ) {
			$result .= " <input type=\"submit\" name=\"setaction\" value=\"rename mailbox\" /> ";
			$result .= "<input type=\"submit\" name=\"setaction\" value=\"delete mailbox\" />";
		} else {
			$result .= " [<a href=\"#\" onclick=\"Lichen.MailboxManager.renameInline('{$thisMailbox['fullboxname']}', '{$thisMailbox['mailbox']}'); return false\">rename</a>] ";
			$result .= "[<a href=\"#\" onclick=\"Lichen.MailboxManager.mailboxDelete('{$thisMailbox['fullboxname']}', '{$thisMailbox['mailbox']}'); return false\">delete</a>]";
		}

		if ( $htmlMode ) {
			$result .= "<br /><input type=\"submit\" name=\"setaction\" value=\"add subfolder\" />";
			$result .= "</form>";
		} else {
			$result .= "<br />[<a href=\"#\" onclick=\"Lichen.MailboxManager.newChild('{$thisMailbox['fullboxname']}'); return false\">add subfolder</a>]";
		}
		$result .= "<div id=\"mbm-changearea-{$thisMailbox['fullboxname']}\">";
		if ( $htmlMode && $htmlPars['mbm-mailbox'] == $thisMailbox['fullboxname'] && $htmlPars['setaction'] != "nothing" ) {
			$result .= genLinkForm( $htmlPars, array( "setactionreturn" => "true" ), array(), "opts-mailboxes", "ajax.php" );
			switch ( $htmlPars['setaction'] ) {
				case "move mailbox":
					$result .= "<select name=\"newparent\">";
					$result .= "<option value=\"\">-- Top Level</option>";
					foreach ( $mailboxList as $mbox ) {
						$result .= "<option value=\"" . htmlentities( $mbox['fullboxname'] ) . "\">";
						$result .= str_repeat( "-", $mbox['folderdepth'] ) . htmlentities( $mbox['mailbox'] );
						$result .= "</option>";
					}
					$result .= "</select>";
					break;
				case "rename mailbox":
					$result .= "<input type=\"text\" size=\"20\" name=\"newname\" value=\"" . 
						htmlentities( $thisMailbox['mailbox'] ) . "\" />";
					break;
				case "delete mailbox":
					$result .= "Are you sure you want to do this? This is irreversable!";
					break;
				case "add subfolder":
					$result .= "<input type=\"text\" size=\"20\" name=\"newname\" />";
					break;
			}
			$result .= "<input type=\"submit\" value=\"{$htmlPars['setaction']}\" />";
			$result .= "<input type=\"submit\" name=\"setaction\" value=\"cancel\" />";
			$result .= "</form>";
		}
		$result .= "</div>";

		$result .= "</li>";
	}

	$result .= "</ul>";

	if ( !$htmlMode ) {
		$result .= "<p class=\"opts-close\"><button onclick=\"Lichen.OptionsEditor.closePanel();return false\">" . _( "close" ) . "</button></p></form>";
		$result .= "</form>";
	}

	return $result;
}


// Returns a string containing a select box of timezones; this list omits
// the extensive duplication in the zoneinfo database.
// The list was manually generated on an unexciting summer's day ...
function generateTimezoneSelect( $selected ) {

	$timezones = array(
		"Pacific/Midway" => "[UTC -11:00] Midway, Pago Pago, Samoa",
		"America/Adak" => "[UTC -10:00] Adak, Atka",
		"Pacific/Honolulu" => "[UTC -10:00] Honolulu, Johnston",
		"Pacific/Tahiti" => "[UTC -10:00] Tahiti",
		"Pacific/Marquesas" => "[UTC -10:30] Marquesas",
		"America/Anchorage" => "[UTC -9:00] Anchorage, Juneau, Nome",
		"America/Los_Angeles" => "[UTC -8:00] Los Angeles, San Francisco, Tijuana, Vancouver",
		"America/Boise" => "[UTC -7:00] Denver, Edmonton, Phoenix",
		"America/Chicago" => "[UTC -6:00] Chicago, Dallas, Guatemala, Mexico City, Winnipeg",
		"Pacific/Galapagos" => "[UTC -6:00] Galapagos Islands",
		"America/Bogota" => "[UTC -5:00] Bogota",
		"America/New_York" => "[UTC -5:00] New York City, Detroit, Montreal, Jamaica, Panama",
		"America/Eirunepe" => "[UTC -5:00] Eirunepe, Porto Acre, Rio Branco, Acre",
		"America/Guayaquil" => "[UTC -5:00] Guayaquil",
		"America/Havana" => "[UTC -5:00] Havana",
		"America/Lima" => "[UTC -5:00] Lima",
		"America/St_Johns" => "[UTC -4:30] St Johns, Newfoundland",
		"America/Santo_Domingo" => "[UTC -4:00] Halifax, Santo Domingo, Barbados, Puerto Rico",
		"America/Asuncion" => "[UTC -4:00] Asuncion",
		"America/Boa_Vista" => "[UTC -4:00] Boa Vista, Cuiaba, Manaus, Porto Velho",
		"America/Caracas" => "[UTC -4:00] Caracas, La Paz, Guyana",
		"America/Santiago" => "[UTC -4:00] Santiago",
		"Atlantic/Stanley" => "[UTC -4:00] Stanley",
		"America/Sao_Paulo" => "[UTC -3:00] Sao Paulo, Bel&eacute;m, Fortaleza, Macei&oacute;, Recife",
		"America/Buenos_Aires" => "[UTC -3:00] Buenos Aires, C&oacute;rdoba, Montevideo, Paramaribo",
		"America/Godthab" => "[UTC -3:00] Godthab",
		"America/Miquelon" => "[UTC -3:00] Miquelon",
		"America/Paramaribo" => "[UTC -3:00] Paramaribo",
		"Atlantic/South_Georgia" => "[UTC -2:00] South Georgia, Noronha Archipelago",
		"America/Scoresbysund" => "[UTC -1:00] Scoresbysund",
		"Atlantic/Azores" => "[UTC -1:00] Azores",
		"Atlantic/Cape_Verde" => "[UTC -1:00] Cape Verde",
		"Europe/London" => "[UTC] London, Abidjan, Dublin, Lisboa, Reykjavik",
		"Africa/Casablanca" => "[UTC] Casablanca, El Aaiun, Canary, Faeroe, Madeira",
		"Europe/Paris" => "[UTC +1:00] Paris, Berlin, Madrid, Belgrade, Stockholm, Warsaw",
		"Africa/Kinshasa" => "[UTC +1:00] Brazzaville, Kinshasa, Lagos",
		"Atlantic/Jan_Mayen" => "[UTC +1:00] Jan Mayen",
		"Africa/Harare" => "[UTC +2:00] Gaborone, Kigali, Lubumbashi, Lusaka, Maputo",
		"Africa/Cairo" => "[UTC +2:00] Cairo, Beirut, Istanbul, Athens, Helsinki, Minsk",
		"Africa/Johannesburg" => "[UTC +2:00] Johannesburg, Maseru, Mbabane",
		"Asia/Jerusalem" => "[UTC +2:00] Jerusalem, Tel Aviv",
		"Africa/Addis_Ababa" => "[UTC +3:00] Addis Ababa, Dar es Salaam, Mogadishu, Nairobi",
		"Asia/Riyadh" => "[UTC +3:00] Baghdad, Doha, Riyadh",
		"Europe/Moscow" => "[UTC +3:00] Moscow",
		"Asia/Tehran" => "[UTC +3:30] Tehran",
		"Asia/Aqtau" => "[UTC +4:00] Aqtau",
		"Asia/Baku" => "[UTC +4:00] Baku",
		"Asia/Dubai" => "[UTC +4:00] Dubai, Muscat",
		"Asia/Tbilisi" => "[UTC +4:00] Tbilisi",
		"Asia/Yerevan" => "[UTC +4:00] Yerevan",
		"Europe/Samara" => "[UTC +4:00] Samara",
		"Indian/Mauritius" => "[UTC +4:00] Mauritius, R&eacute;union, Seychelles",
		"Asia/Kabul" => "[UTC +4:30] Kabul",
		"Asia/Aqtobe" => "[UTC +5:00] Aqtobe",
		"Asia/Ashgabat" => "[UTC +5:00] Ashgabat, Ashkhabad, Samarkand",
		"Asia/Bishkek" => "[UTC +5:00] Bishkek",
		"Asia/Karachi" => "[UTC +5:00] Karachi, Tashkent, Maldives, Kerguelen, Dushanbe",
		"Asia/Yekaterinburg" => "[UTC +5:00] Yekaterinburg",
		"Asia/Calcutta" => "[UTC +5:30] Calcutta",
		"Asia/Katmandu" => "[UTC +5:45] Katmandu",
		"Asia/Almaty" => "[UTC +6:00] Almaty",
		"Asia/Dacca" => "[UTC +6:00] Dhaka, Colombo, Chagos, Thimphu",
		"Asia/Novosibirsk" => "[UTC +6:00] Novosibirsk",
		"Asia/Omsk" => "[UTC +6:00] Omsk",
		"Asia/Rangoon" => "[UTC +6:30] Rangoon, Cocos Islands",
		"Asia/Bangkok" => "[UTC +7:00] Bangkok, Phnom Penh, Saigon, Vientiane",
		"Asia/Jakarta" => "[UTC +7:00] Jakarta, Pontianak",
		"Asia/Krasnoyarsk" => "[UTC +7:00] Krasnoyarsk",
		"Asia/Shanghai" => "[UTC +8:00] Beijing, Shanghai, Taipei, Hong Kong",
		"Asia/Irkutsk" => "[UTC +8:00] Irkutsk",
		"Asia/Kuala_Lumpur" => "[UTC +8:00] Kuala Lumpur, Manila, Singapore, Makassar",
		"Asia/Ulaanbaatar" => "[UTC +8:00] Ulaanbaatar",
		"Australia/Perth" => "[UTC +8:00] Perth",
		"Asia/Jayapura" => "[UTC +9:00] Dili, Jayapura, Choibalsan, Palau",
		"Asia/Seoul" => "[UTC +9:00] Pyongyang, Seoul",
		"Asia/Tokyo" => "[UTC +9:00] Tokyo",
		"Asia/Yakutsk" => "[UTC +9:00] Yakutsk",
		"Australia/Adelaide" => "[UTC +9:30] Adelaide",
		"Australia/Darwin" => "[UTC +9:30] Darwin",
		"Asia/Sakhalin" => "[UTC +10:00] Sakhalin",
		"Asia/Vladivostok" => "[UTC +10:00] Vladivostok",
		"Australia/Brisbane" => "[UTC +10:00] Brisbane",
		"Australia/Sydney" => "[UTC +10:00] Sydney, Melbourne, Canberra",
		"Australia/Hobart" => "[UTC +10:00] Hobart",
		"Pacific/Guam" => "[UTC +10:00] Guam, Saipan, Port Moresby, Chuuk",
		"Australia/Lord_Howe" => "[UTC +10:30] Lord Howe Island",
		"Asia/Magadan" => "[UTC +11:00] Magadan",
		"Pacific/Efate" => "[UTC +11:00] Vanuatu, Guadalcanal, Noumea, Ponape",
		"Pacific/Norfolk" => "[UTC +11:30] Norfolk Island",
		"Asia/Anadyr" => "[UTC +12:00] Anadyr",
		"Asia/Kamchatka" => "[UTC +12:00] Kamchatka",
		"Pacific/Auckland" => "[UTC +12:00] Auckland",
		"Pacific/Fiji" => "[UTC +12:00] Fiji, Majuro, Nauru",
		"Pacific/Chatham" => "[UTC +12:45] Chatham",
		"Pacific/Enderbury" => "[UTC +13:00] Enderbury, Tongatapu",
		"Pacific/Kiritimati" => "[UTC +14:00] Kiritimati" );

	if ( $selected == "UTC" ) {
		// This timezone has no daylight saving and is the same as UTC;
		// seeing "Casablanca" will hopefully prompt users in other
		// places to set their timezone correctly.
		$selected = "Africa/Casablanca";
	}

	$outputString = "<select name=\"opts-timezone\" id=\"opts-timezone\">\n";

	foreach ( $timezones as $zoneName => $displayName ) {
		if ( $selected == $zoneName ) {
			$outputString .= "<option value=\"" . $zoneName . "\" selected=\"selected\">" . $displayName . "</option>\n";
		} else {
			$outputString .= "<option value=\"" . $zoneName . "\">" . $displayName . "</option>\n";
		}
	}

	$outputString .= "</select>\n";
	return $outputString;

}


?>
