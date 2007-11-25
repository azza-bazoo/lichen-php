<?php
/**

Lichen - AJAX IMAP client
version 0.4 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
include/addressbook.inc.php - Lichen's Address book implementation
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

/* Data that the addres book stores:
 *
 * The baseline data that an address book should store is:
 * - Name
 * - Email
 * - Notes
 *
 * Email should be unique.
 *
 * And... urm... we'll have to think about how to make this extensible without going insane.
 */

/* NOT part of the base class due to a PHPism with array_walk(). */
function html_encode_address( &$address ) {
	// In place HTML encodes a list of address book entries, ready to go back to the client.
	// sort calls this so it is transparent to the subclasses.
	$address['name_html']  = htmlentities( $address['name'] );
	$address['email_html'] = htmlentities( $address['email'] );
	$address['notes_html'] = htmlentities( $address['notes'] );
}

/* NOT part of the base class due to a PHPism with usort(). */
function address_sort_callback( $a, $b ) {
	return strcmp( $a['name'], $b['name'] );
}


class BaseAddressBook {
	// Base class for the address book. Contains various useful stuff that
	// gets inherited by the other classes.
	var $errors = array();

	function has_errors() {
		return count( $this->errors ) != 0;
	}

	function get_errors() {
		return $this->errors;
	}

	function add_error( $error ) {
		$this->errors[] = $error;
	}

	// Validate an address book entry given to us.
	function validate_address( $address ) {
		$result = true;

		// First check: that we have name, email, and notes.
		if ( !isset( $address['name'] ) && !isset( $address['email'] ) && !isset( $address['notes'] ) ) {
			$result = false;
			$this->add_error( _("Invalid address: missing data.") );

		} else if ( !isEmailAddress( $address['email'] ) ) {
		 	// Now check to see if the email address even looks like an email address.
			$result = false;
			$this->add_error( _("Email address is not valid.") );
		}

		return $result;
	}

	// Internal function to sort a given email list.
	// It sorts by the name field... this might need some rethinking.
	// You should call this and let it sort the given list
	// before returning addresses.
	function sort( &$list ) {
		usort( $list, "address_sort_callback" );
		array_walk( $list, "html_encode_address" );
	}

	// **********************************************************
	// These functions should be overridden in inherited classes.
	// Unless you don't need to.
	// (Eg, save() might mean nothing to you if this is a DB or LDAP server)
	
	// Load the address book, if appropriate - or connect to the DB, or
	// whatever is required.
	// Returns TRUE on success, or FALSE on failure - set errors as appropriate.
	// $settings is an associative array given in Lichen's config file.
	function load( $settings ) {
		return true;
	}

	// Save the address book, if appropriate.
	function save() {
		return true;
	}

	// List addresses. Should return an array of associative arrays.
	function list_addresses() {
		return array();
	}

	// Search for addresses.
	// infield is one of name, email, or notes, and
	// parameter is the search parameter. It should be implemented as a substring
	// search.
	// The return value should be an array of associative arrays with addresses
	// that match.
	function search( $infield, $parameter ) {
		return array();
	}

	// Add a new address.
	// Input is an associative array with 'name', 'email', and 'notes'.
	// Returns true on success, or false on failure.
	function add( $address ) {
		$this->add_error( "Adding email addresses not implemented in base class." );
		return false;
	}

	// Remove an address.
	// The key is the email address.
	// Return true on success, or false on failure.
	function remove( $email ) {
		$this->add_error( "Removing email addresses not implemented in base class." );
		return false;
	}

	// Edit an address.
	// The key is the email address, but it can be changed.
	// The input is an associative array with 'name', 'email', and 'notes'.
	function edit( $email, $data ) {
		$this->add_error( "Editing email addresses not implemented in base class." );
		return false;
	}
	
}


class FlatFile_AddressBook extends BaseAddressBook {
	// The most straight forward possible address book storage format.
	var $addresses = array();

	function load( $settings ) {
		// Ignore settings - we don't need them.
		// Load the addresses from a CSV file.
		$filename = getUserDirectory() . "/addresses.txt";

		$rawData = @file_get_contents( $filename );

		if ( $rawData === false ) {
			// Unable to open the file - don't worry, just assume
			// the file is blank.
			$rawData = "";
		}
		
		$rawData = explode( "\n", $rawData );

		// What is this trying to achieve? It's a CSV parser written by
		// a psychopathic person (Daniel). TODO: Comment this code or replace it.
		foreach ( $rawData as $rawLine ) {
			$exdata = array("", "", "");
			$expos = 0;

			$lineBits = explode( ",", $rawLine );
			if ( count( $lineBits ) >= 3 ) {
				foreach ( $lineBits as $bit ) {
					if ( $bit[0] == '"' && $bit[strlen( $bit ) - 1] == '"' ) {
						$exdata[$expos] = substr( $bit, 1, strlen( $bit ) - 2 );
						$expos++;
					} else if ( $bit[0] == '"' ) {
						$exdata[$expos] = substr( $bit, 1 ) . ",";
					} else {
						$exdata[$expos] .= substr( $bit, 0, strlen( $bit ) - 1 );
						$expos++;
					}
				}
			}

			if ( !(empty( $exdata[0] ) && empty( $exdata[1] ) && empty( $exdata[2] )) ) {
				$this->addresses[] = array(
					"name" => $exdata[0],
					"email" => $exdata[1],
					"notes" => $exdata[2]
				);
			}
		}

		// And now we have the addresses loaded.
	}

	function save() {
		$filename = getUserDirectory() . "/addresses.txt";

		// Write out a CSV version of the address book.
		// In the format "name","email","notes"
		$datafp = fopen( $filename, "w" );

		foreach ( $this->addresses as $address ) {
			$line = sprintf( "\"%s\",\"%s\",\"%s\"\n", $address['name'], $address['email'], $address['notes'] );

			fwrite( $datafp, $line );
		}

		fclose( $datafp );
	}

	function list_addresses() {
		// Caution: PHP4/PHP5 do different things in terms of references when this is done.
		$this->sort( $this->addresses );
		return $this->addresses;
	}

	function search( $infield, $parameter ) {
		$result = array();

		foreach ( $this->addresses as $address ) {
			// TODO: we're trusting infield to be correct here.
			if ( stripos( $address[$infield], $parameter ) !== false ) {
				$result[] = $address;
			}
		}

		$this->sort( $result );
		return $result;
	}

	function add( $address ) {
		// Make sure this is not a duplicate.
		$isDup = false;
		$result = true;

		if ( $this->validate_address( $address ) ) {

			foreach ( $this->addresses as $addr ) {
				if ( $addr['email'] == $address['email'] ) {
					$isDup = true;
					break;
				}
			}

			if ( $isDup ) {
				$this->add_error( _("Unable to add new address: duplicate email address.") );
				$result = false;
			} else {
				$this->addresses[] = $address;
			}

		} else {
			$result = false;
		}

		return $result;
	}

	function remove( $email ) {
		$idxcounter = 0;
		$delindex = -1;
		$result = true;

		foreach ( $this->addresses as $addr ) {
			if ( $addr['email'] == $email ) {
				$delindex = $idxcounter;
				break;
			}
			$idxcounter++;
		}

		if ( $delindex != -1 ) {
			array_splice( $this->addresses, $delindex, 1 );
		} else {
			$this->add_error( _("Unable to find that address to remove it.") );
			$result = false;
		}

		return $result;
	}

	function edit( $email, $data ) {
		// Look for this record and replace it.
		// If it doesn't exist, add it.
		$result = true;

		if ( $this->validate_address( $data ) ) {
			$idxcounter = 0;
			$editindex = -1;
			$isDup = false;
			$maybeDup = $email != $data['email'] || empty( $email );
		
			foreach ( $this->addresses as $addr ) {
				if ( $addr['email'] == $email ) {
					$editindex = $idxcounter;
				}
				if ( $maybeDup && $addr['email'] == $data['email'] ) {
					$isDup = true;
				}
				$idxcounter++;
			}

			if ( $isDup ) {
				$this->add_error( _("Editing this address would create a duplicate.") );
				$result = false;
			} else if ( $editindex != -1 ) {
				// Change it, it exists.
				$this->addresses[$editindex] = $data;
			} else {
				// Add it, it does not exist.
				$this->addresses[] = $data;
			}
		} else {
			$result = false;
		}

		return $result;
	}
}

function addressBook_initialize() {
	// Initialize and load our address book.
	// TODO: Don't hardcode this!
	// TODO: Error handling.
	global $ADDRESSBOOK;

	$ADDRESSBOOK = new FlatFile_AddressBook();

	$ADDRESSBOOK->load( array() );
}

function addressBook_uninitialize() {
	global $ADDRESSBOOK;

	$ADDRESSBOOK->save();
}

?>
