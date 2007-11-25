/**

Lichen - AJAX IMAP client
version 0.4 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
js/addressbook.js - address book interface
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

var AddressBookManager = new Class({
	initialize: function ( server, wrapper ) {
		this.wrapper = wrapper;
		this.server  = server;
		this.cache   = null;
	},

	showAddressBook: function ( searchin, searchterm ) {
		this.server.addressBookList( searchin, searchterm, this.showAddressBookCBStub.bind( this ) );
	},

	showAddressBookCBStub: function ( serverResponse ) {
		// Stub to call ourself through the interface controller.
		Lichen.action( 'abook', 'AddressBook', 'showAddressBookCB', [serverResponse] );
	},

	showAddressBookCB: function ( serverResponse ) {
		// Do stuff.
		this._render( serverResponse.addresses );
		this.cache = serverResponse.addresses;
	},

	getFromCache: function ( email ) {
		var result = null;

		for ( var i = 0; i < this.cache.length; i++ ) {
			if ( this.cache[i].email == email ) {
				result = this.cache[i];
			}
		}

		return result;
	},

	_render: function ( addresses ) {
		// Render the given addresses into our wrapper.
		var html = "";

		html += "<table border=\"1\">";
		html += "<tr><th>Name</th><th>Email</th><th>Notes</th><th></th></tr>";

		if ( addresses ) {
			for ( var i = 0; i < addresses.length; i++ ) {
				html += "<tr>";
				html += "<td>" + addresses[i].name_html + "</td>";
				html += "<td>" + addresses[i].email_html + "</td>";
				html += "<td>" + addresses[i].notes_html + "</td>";
				html += "<td>";
				html += "[<a href=\"#abook\" onclick=\"Lichen.AddressBook.editAddress('" + addresses[i].email + "');return false\">" + _("Edit") + "</a>]";
				html += "[<a href=\"#abook\" onclick=\"Lichen.AddressBook.deleteAddress('" + addresses[i].email + "');return false\">" + _("Delete") + "</a>]";
				html += "</tr>";
			}
		}

		html += "</table>";

		html += "<p>[<a href=\"#abook\" onclick=\"Lichen.AddressBook.newAddress();return false\">" + _("New Address") + "</a>]</p>";

		html += "<p>";
		html += "<input type=\"hidden\" id=\"abook-original\" size=\"30\" value=\"\"/>";
		html += "<label for=\"abook-name\">" + _('name:') + "</label>";
		html += "<input type=\"text\" id=\"abook-name\" size=\"30\" /><br />";
		html += "<label for=\"abook-email\">" + _('email:') + "</label>";
		html += "<input type=\"text\" id=\"abook-email\" size=\"30\" /><br />";
		html += "<label for=\"abook-notes\">" + _('notes:') + "</label>";
		html += "<input type=\"text\" id=\"abook-notes\" size=\"30\" /><br />";

		html += "<button id=\"abook-save\" onclick=\"Lichen.AddressBook.saveAddress();return false\">" + _("save changes") + "</button>";
		html += "</p>";

		$(this.wrapper).setHTML( html );
	},

	addAddress: function ( email ) {
		$('abook-original').value = "";
		$('abook-name').value = "";
		$('abook-email').value = "";
		$('abook-notes').value = "";
	},

	editAddress: function ( email ) {
		var original = this.getFromCache( email );

		if ( original ) {
			$('abook-original').value = original.email;
			$('abook-name').value = original.name;
			$('abook-email').value = original.email;
			$('abook-notes').value = original.notes;
		} else {
			this.addAddress();
		}
	},

	saveAddress: function () {
		// If abook-original is blank, this is a new entry.
		// If it is not blank, then it is an edited address.
		// However, the server doesn't care: we use the edit action
		// to handle both!
		this.server.addressBookEdit( $('abook-original').value, $('abook-name').value, $('abook-email').value, $('abook-notes').value, this.saveAddressCB.bind( this ) );
	},

	saveAddressCB: function () {
		// Crude: refresh the list by updating the whole thing.
		this.showAddressBook();
	},

	deleteAddress: function ( email ) {
		this.server.addressBookDelete( email, this.saveAddressCB.bind( this ) );
	}
});
