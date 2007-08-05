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


// Get the users settings from file.
function getUserSettings() {
	global $DEFAULT_SETTINGS, $SMTP_DOMAIN;

	$userDataDir = getUserDirectory();

	$dataFile = "{$userDataDir}/lichenrc";

	$settings = NULL;
	if ( file_exists( $dataFile ) ) {
		// Load it...
		$settings = json_decode_real( file_get_contents( $dataFile ) );
	}

	if ( $settings == NULL || $settings['BUILTINVER'] != 1 || $DEFAULT_SETTINGS['defaults_version'] != $settings['defaults_version'] ) {
		// Prepare default settings for this user, or update new settings.
		// This only adds new keys to the settings array, won't change others.
		// TODO: This is mainly for development - saves me editing my lichenrc file too much.
		// I guess it's a little complicated though... probably should be taken out.
		$newSettings = array(
			"BUILTINVER" => 2, // Increment for each change to this array.
			"timezone" => "UTC",
			"list_pagesize" => 20,
			"comp_forwardattdef" => true,
			"list_showpreviews" => true,
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

		// Later arguments to this function take precedence.
		// So, first is BUILT IN SETTINGS, then the ADMINISTRATOR SETTINGS,
		// then the user's own settings.
		$settings = array_merge( $newSettings, $DEFAULT_SETTINGS, $settings );

		// Create a config file and write these settings.
		// saveUserSettings();
	}

	return $settings;
}


function saveUserSettings( $settings ) {
	// Save the users settings.

	$userDataDir = getUserDirectory();

	$result = file_put_contents( "{$userDataDir}/lichenrc", json_encode_real( $settings ) );

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

$SETTINGS_PANEL = array(

	_("General") =>
		array(
			_("Timezone") =>
				array( "type" => "select", "key" => "timezone", "sourcedata" => "timezone_identifiers_list" ), // TODO: PHP >=5.1.0
			),
	_("Message List") =>
		array(
			_("Show Flagged column") =>
				array( "type" => "boolean", "key" => "list_showflagged" ),
			_("Show Message Previews") =>
				array( "type" => "boolean", "key" => "list_showpreviews" ),
			_("Show Message Size") =>
				array( "type" => "boolean", "key" => "list_showsize" ),
			_("Messages per page") =>
				array( "type" => "number", "key" => "list_pagesize", "min" => 5 ),
			_("Default sort") =>
				array( "type" => "select", "key" => "list_defaultsort",
					"sourcedata" => array(
						array( "value" => "date", "display" => _("Date (Oldest first)") ),
						array( "value" => "date_r", "display" => _("Date (Newest first)") ),
						array( "value" => "from", "display" => _("From")),
						array( "value" => "from_r", "display" => _("From (Reverse)")),
						array( "value" => "subject", "display" => _("Subject")),
						array( "value" => "subject_r", "display" => _("Subject (Reverse)")),
						array( "value" => "to", "display" => _("To")),
						array( "value" => "to_r", "display" => _("To (Reverse)")),
						array( "value" => "cc", "display" => _("CC")),
						array( "value" => "cc_r", "display" => _("CC (Reverse)")),
						array( "value" => "size", "display" => _("Size (Smallest First)")),
						array( "value" => "size_r", "display" => _("Size (Smallest Last)")),
					)
				),
			_("Show mailbox totals") =>
				array( "type" => "boolean", "key" => "boxlist_showtotal" ),
		),
	_("Composer") =>
		array(
			_("Forward as attachment by default") =>
				array( "type" => "boolean", "key" => "comp_forwardattdef" ),
		),
	_("Message Display") =>
		array(
			_("Fixed width display") =>
				array( "type" => "boolean", "key" => "message_fixedwidth" ),
		),
	_("Identities") =>
		array(
			"custom" => "generateIdentityEditor"
		)

);


// Generate the HTML required to have a working identity editor.
function generateIdentityEditor() {
	global $USER_SETTINGS;

	$result = "<select size=\"10\" id=\"identities-list\">";
	foreach ( $USER_SETTINGS['identities'] as $identity ) {
		$result .= "<option value=\"" . htmlentities( $identity['address'] ) . "," . htmlentities( $identity['name'] ) . "\">" .
		       htmlentities( $identity['name'] ) . " &lt;" . htmlentities( $identity['address'] ) . "&gt;";
		if ( $identity['isdefault'] ) $result .= " (default)";
		$result .= "</option>";
	}
	$result .= "</select><br />";
	$result .= "<div id=\"identity-editor\"></div>";
	$result .= "</select>";
	$result .= "<a href=\"#\" onclick=\"return OptionsEditor.identity_add()\">" . _("Add") . "</a> | ";
	$result .= "<a href=\"#\" onclick=\"return OptionsEditor.identity_setdefault()\">" . _("Make Default") . "</a> | ";
	$result .= "<a href=\"#\" onclick=\"return OptionsEditor.identity_edit()\">" . _("Edit") . "</a> | ";
	$result .= "<a href=\"#\" onclick=\"return OptionsEditor.identity_remove()\">" . _("Remove") . "</a>";

	return $result;
}


// Generate the HTML to change settings. The result is dictated by the settings panel array.
function generateSettingsPanel() {
	global $SETTINGS_PANEL, $USER_SETTINGS, $DEFAULT_SETTINGS;

	$result = "<div>";
	$result .= "<form name=\"settings\" id=\"settings\" method=\"post\" onsubmit=\"OptionsEditor.saveOptions();return false\" action=\"#\">";
	$result .= "<div>";

	foreach ( $SETTINGS_PANEL as $panelname => $value ) {
		$result .= "<span class=\"settpanel\"><a href=\"#\" onclick=\"OptionsEditor.showPanel('" . $panelname . "'); return false\">";
		$result .= "{$panelname}</a></span> &nbsp; &nbsp;";
	}

	$result .= "</div>";

	$firstPanel = "";
	foreach ( $SETTINGS_PANEL as $panelname => $settings ) {
		$result .= "<div id=\"settings_{$panelname}\"";
		if ( $firstPanel == "" ) {
			$firstPanel = $panelname;
		} else {
			$result .= " style=\"display: none;\"";
		}
		$result .= ">";

		foreach ( $settings as $settingname => $settingdata ) {
			if ( $settingname == "custom" ) {
				$result .= "<div>" . $settingdata() . "</div>";
				continue;
			}
			$result .= "<div class=\"setting_row\">";
			$result .= "<span class=\"setting_name\">{$settingname}:</span>";
			$result .= "<span class=\"setting_input\">";

			switch ( $settingdata['type'] ) {
				case 'boolean':
					$result .= "<input type=\"checkbox\" name=\"settings[{$settingdata['key']}]\" ";
					$result .= "id=\"settings[{$settingdata['key']}]\" value=\"true\" ";
					// This if says: if the user has the key and it's set, mark as checked.
					// If the user doesn't have the key, but there is a default setting for it, mark it as checked.
					if ( ( isset( $USER_SETTINGS[$settingdata['key']] ) && $USER_SETTINGS[$settingdata['key']] ) ||
						( !isset( $USER_SETTINGS[$settingdata['key']] ) && isset( $DEFAULT_SETTINGS[$settingdata['key']] ) &&
						$DEFAULT_SETTINGS[$settingdata['key']] ) ) {

						$result .= "checked=\"checked\"";
					}
					$result .= " />";
					break;
				case 'number':
				case 'string':
					$result .= "<input type=\"text\" size=\"20\" ";
				       	$result .= "name=\"settings[{$settingdata['key']}]\" ";
					$result .= "id=\"settings[{$settingdata['key']}]\" ";
					if ( isset( $USER_SETTINGS[$settingdata['key']] ) ) {
						$result .= "value=\"" . htmlentities($USER_SETTINGS[$settingdata['key']]) . "\" />";
					} else if ( isset( $DEFAULT_SETTINGS[$settingdata['key']] ) ) {
						$result .= "value=\"" . htmlentities($DEFAULT_SETTINGS[$settingdata['key']]) . "\" />";
					}
					break;
				case 'select':
					$result .= "<select name=\"settings[{$settingdata['key']}]\" ";
					$result .= "id=\"settings[{$settingdata['key']}]\">";
					$soucedata = array();
					if ( is_array( $settingdata['sourcedata'] ) ) {
						// Static array - take a reference.
						$sourcedata = &$settingdata['sourcedata'];
					} else {
						// Function that generates the data.
						// TODO: Pass it in the current value? This needs to be
						// enhanced.
						$sourcedata = $settingdata['sourcedata']();
					}
					foreach ( $sourcedata as $element ) {
						$selected = "";
						$value = $element;
						$display = $element;
						if ( is_array( $element ) ) {
							$value = $element['value'];
							$display = $element['display'];
						}
						if ( ( isset( $USER_SETTINGS[$settingdata['key']] ) &&
							$USER_SETTINGS[$settingdata['key']] == $value ) ||
							( !isset( $USER_SETTINGS[$settingdata['key']] ) &&
								isset( $DEFAULT_SETTINGS[$settingdata['key']] ) &&
								$DEFAULT_SETTINGS[$settingdata['key']] == $value ) ) {
							$selected = "selected=\"selected\"";
						}
						$result .= "<option value=\"" . htmlentities( $value ) . "\" {$selected}>";
						$result .= htmlentities( $display ) . "</option>";
					}
					$result .= "</select>";
					break;
			}

			$result .= "</span>";
			$result .= "</div>";
		}

		$result .= "</div>";
	}

	$result .= "<div align=\"right\">";
	$result .= "<button onclick=\"OptionsEditor.saveOptions(); return false\">" . _("Save Settings") . "</button>";
	$result .= "<button onclick=\"OptionsEditor.closePanel(); return false\">" . _("Cancel") . "</button>";
	$result .= "</div>";

	$result .= "</form>";
	$result .= "</div>";

	return array( "htmlFragment" => $result, "startPanel" => $firstPanel );
}


// Given a blob from the client, which is the status of the settings on the panel,
// go ahead and parse them and merge them with the current user settings.
function parseUserSettings( $inputSettings ) {
	global $USER_SETTINGS, $SETTINGS_PANEL;

	$settingErrors = array();

	foreach ( $SETTINGS_PANEL as $panel => $settings ) {
		foreach ( $settings as $settingname => $setting ) {
			if ( !isset( $inputSettings[$setting['key']] ) ) {
				// Ignore this setting, no change.
				continue;
			}
			$invalidValue = false;
			$settingValue = "";
			$inputValue = $inputSettings[$setting['key']];
			switch ( $setting['type'] ) {
				case 'boolean':
					if ( $inputValue == "true" ) {
						$settingValue = true;
					} else {
						$settingValue = false;
					}
					break;
				case 'string':
					$settingValue = $inputValue;
					break;
				case 'number':
					if ( is_numeric( $inputValue ) ) {
						$settingValue = $inputValue += 0;

						if ( isset( $setting['min'] ) ) {
							if ( $settingValue < $setting['min'] ) {
								$invalidValue = true;
								$msg = sprintf( _("%s -> %s must be %d or greater."), $panel, $settingname, $setting['min'] );
								$settingErrors[] = $msg;
							}
						}
					} else {
						// Ignore this value - invalid input.
						$invalidValue = true;
					}
					break;
				case 'select':
					$allowableValues = array();
					if ( is_array( $setting['sourcedata'] ) ) {
						$allowableValues = &$setting['sourcedata'];
					} else {
						$allowableValues = $setting['sourcedata']();
					}
					$valueOk = false;
					foreach ( $allowableValues as $value ) {
						if ( is_array( $value ) ) {
							if ( $value['value'] == $inputValue ) {
								$valueOk = true;
								break;
							}
						} else {
							if ( $value == $inputValue ) {
								$valueOk = true;
								break;
							}
						}
					}
					if ( $valueOk ) {
						// Setting is ok.
						$settingValue = $inputValue;
					} else {
						$invalidValue = true;
						$msg = sprintf( _("%s -> %s is invalid."), $panel, $settingname );
						$settingErrors[] = $msg;
					}
					break;
			}

			if ( !$invalidValue ) {
				$USER_SETTINGS[$setting['key']] = $settingValue;
			}
		}
	}

	return $settingErrors;
}

?>
