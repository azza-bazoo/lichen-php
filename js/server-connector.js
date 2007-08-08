/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
js/server-connector.js - code for speaking to the IMAP server via ajax.php
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


// Class that knows how to ask our PHP server components for data.
var IMAPServerConnector = new Class({

	initialize: function( dataStoreObject ) {
		this.dataStore = dataStoreObject;
	},

	messageList: function( mailbox, search, page, sort, validity, cacheonly ) {
		if ( !cacheonly ) {
			// For user-initiated requests, show loading feedback
			if_remoteRequestStart();
		}

		new Ajax( 'ajax.php', {
			postBody: 'request=mailboxContentsList&mailbox='+encodeURIComponent(mailbox)+
				'&page='+page+'&search='+encodeURIComponent(search)+'&sort='+encodeURIComponent(sort)+
				'&validity='+encodeURIComponent(validity)+'&cacheonly='+encodeURIComponent(cacheonly),
			onComplete : this.messageListCB.bind( this ),
			onFailure : if_remoteRequestFailed
			} ).request();
	},

	messageListCB: function( serverResponse ) {
		// Parse the server's response.
		var result = if_checkRemoteResult( serverResponse );
		if ( !result ) { return null; } // TODO: Something other than this, here. IE: Error handling; let the user know what happened.

		this.dataStore.fetchMessageListCB( result, result.data.mailbox, result.data.search,
			result.data.thispage, result.data.sort, result.validity, result.cacheonly );
	},

	mailboxList: function( validity ) {
		new Ajax( 'ajax.php', {
			postBody: 'request=getMailboxList&validity='+encodeURIComponent(validity),
			onComplete : this.mailboxListCB.bind( this ),
			onFailure : if_remoteRequestFailed
			} ).request();
	},

	mailboxListCB: function( serverResponse ) {
		// Parse the server's response.
		var result = if_checkRemoteResult( serverResponse );
		if ( !result ) { return null; } // TODO: Something other than this, here. IE: Error handling; let the user know what happened.

		this.dataStore.fetchMailboxListCB( result );
	},

	messageBody: function( mailbox, uid, mode ) {
		// TODO: Do we need a validity for this? Do messages really change?
		if_remoteRequestStart();

		new Ajax( 'ajax.php', {
			postBody: 'request=getMessage&mailbox='+encodeURIComponent(mailbox)+'&msg='+encodeURIComponent(uid)+
				'&mode='+encodeURIComponent(mode),
			onComplete : this.messageBodyCB.bind( this ),
			onFailure : if_remoteRequestFailed
		} ).request();
	},

	messageBodyCB: function( serverResponse ) {
		// Parse the server's response.
		var result = if_checkRemoteResult( serverResponse );
		if ( !result ) { return null; } // TODO: Something other than this - let the user know what happened.

		this.dataStore.fetchMessageCB( result, result.data.mailbox, result.data.uid );
	}
});
