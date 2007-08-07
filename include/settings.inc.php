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
			"list_defaultsort" => "date_r",
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
		saveUserSettings();
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


// Generate HTML for the sending identities editor
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


// Generate the HTML for the settings interface
function generateSettingsPanel() {
	global $USER_SETTINGS, $DEFAULT_SETTINGS;

	// _("Default sort") =>
	// array( "type" => "select", "key" => "list_defaultsort",
	// "sourcedata" => array(
	// array( "value" => "date", "display" => _("Date (oldest first)") ),
	// array( "value" => "date_r", "display" => _("Date (newest first)") ),
	// array( "value" => "from", "display" => _("From (ascending)")),
	// array( "value" => "from_r", "display" => _("From (descending)")),
	// array( "value" => "subject", "display" => _("Subject (ascending)")),
	// array( "value" => "subject_r", "display" => _("Subject (descending)")),
	// array( "value" => "to", "display" => _("To (ascending)")),
	// array( "value" => "to_r", "display" => _("To (descending)")),
	// array( "value" => "cc", "display" => _("CC (ascending)")),
	// array( "value" => "cc_r", "display" => _("CC (descending)")),
	// array( "value" => "size", "display" => _("Size (smallest first)")),
	// array( "value" => "size_r", "display" => _("Size (biggest first)")),

	$panel = "<form name=\"settings\" id=\"settings\" method=\"post\" onsubmit=\"OptionsEditor.saveOptions();return false\" action=\"#\">";

	//--------------------
	// Timezone selector
	$panel .= "<div class=\"opts-block\"><label for=\"opts-timezone\" class=\"opts-name\">Your timezone:</label> " . generateTimezoneSelect( $USER_SETTINGS['timezone'] ) . "</div>";

	//--------------------
	// Show message preview
	$panel .= "<div class=\"opts-block\">In message listings &nbsp; <input type=\"checkbox\" name=\"opts-list_showpreviews\" id=\"opts-list_showpreviews\" ";

	if ( $USER_SETTINGS['list_showpreviews'] ) { $panel .= "checked=\"checked\" "; }

	$panel .= "/> <label for=\"opts-list_showpreviews\" class=\"opts-name\">show message previews</label> &nbsp; ";

	//--------------------
	// Show 'size' column
	$panel .= "<input type=\"checkbox\" name=\"opts-list_showsize\" id=\"opts-list_showsize\" ";

	if ( $USER_SETTINGS['list_showsize'] ) { $panel .= "checked=\"checked\" "; }

	$panel .= "/> <label for=\"opts-list_showsize\" class=\"opts-name\">show size</label><br />";

	//--------------------
	// Messages per page
	$panel .= "and list <select name=\"opts-list_pagesize\" id=\"opts-list_pagesize\">";

	$sizeOptions = array( 5, 10, 20, 25, 50, 75, 100 );
	foreach ( $sizeOptions as $i ) {
		$panel .= "<option value=\"$i\" ";
		if ( $USER_SETTINGS['list_pagesize'] == $i ) { $panel .= "selected=\"selected\" "; }
		$panel .= ">$i</option>";
	}

	$panel .= "</select> <label for=\"opts-list_pagesize\" class=\"opts-name\">messages per page</label>";

	//--------------------
	// Show totals in mailbox list
	$panel .= "<div class=\"opts-block\"><input type=\"checkbox\" name=\"opts-boxlist_showtotal\" id=\"opts-boxlist_showtotal\" ";

	if ( $USER_SETTINGS['boxlist_showtotal'] ) { $panel .= "checked=\"checked\" "; }

	$panel .= "/> <label for=\"opts-boxlist_showtotal\" class=\"opts-name\">Show total count for each mailbox</label></div>";

	//--------------------
	// Default forward mode
	$panel .= "<div class=\"opts-block\">By default, <strong>forward messages</strong> <input type=\"radio\" name=\"opts-forward_as_attach\" id=\"opts-forward_as_attach-true\" value=\"true\" ";

	if ( $USER_SETTINGS['forward_as_attach'] ) { $panel .= "checked=\"checked\" "; }

	$panel .= " /> <label for=\"opts-forward_as_attach-true\" class=\"opts-name\">as attachments</label> <input type=\"radio\" name=\"opts-forward_as_attach\" id=\"opts-forward_as_attach-false\" value=\"false\" ";

	if ( !$USER_SETTINGS['forward_as_attach'] ) { $panel .= "checked=\"checked\" "; }

	$panel .= " /> <label for=\"opts-forward_as_attach-false\" class=\"opts-name\">inline</label></div>";

	//--------------------
	// Sending identities
	$panel .= "<div class=\"opts-block\">" . generateIdentityEditor() . "</div>";

	$panel .= "</form>";

	return array( "htmlFragment" => $panel );
}


// Assuming that $_POST variables have been set corresponding to input from
// the settings panel, update the global $USER_SETTINGS array with the changes
// and return an array of errors, if there are any.
function parseUserSettings() {
	global $USER_SETTINGS, $_DATE_TIMEZONE_DATA;

	$settingErrors = array();

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
