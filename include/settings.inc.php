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
			"theme" => "default",
			"list_pagesize" => 20,
			"forward_as_attach" => true,
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

	$file = fopen( "{$userDataDir}/lichenrc", "w" );
	$result = false;
	if ( $file !== false ) {
		$result = fwrite( $file, json_encode_real( $settings ) );
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

$SETTINGS_PANEL = array(

	_("General") =>
		array(
			_("Timezone") =>
				array( "type" => "select", "key" => "timezone", "sourcedata" => "timezone_identifiers_list" ), // TODO: PHP >=5.1.0
			),
	_("Message List") =>
		array(
//			_("Show Flagged column") =>
//				array( "type" => "boolean", "key" => "list_showflagged" ),
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
				array( "type" => "boolean", "key" => "forward_as_attach" ),
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


// Returns a string containing a select box of timezones; this list omits
// the extensive duplication in the zoneinfo database.
// The list was manually generated on an unexciting summer's day ...
function drawTimeZoneSelect( $selected ) {

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

	$outputString = "<select name=\"timezone\" id=\"timezone\">\n";

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
