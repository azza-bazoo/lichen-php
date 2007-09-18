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
		new Ajax( 'ajax.php', {
			postBody: 'request=mailboxContentsList&mailbox='+encodeURIComponent(mailbox)+
				'&page='+page+'&search='+encodeURIComponent(search)+'&sort='+encodeURIComponent(sort)+
				'&validity='+encodeURIComponent(validity)+'&cacheonly='+encodeURIComponent(cacheonly),
			onComplete: this.messageListCB.bind( this ),
			onFailure: if_remoteRequestFailed
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
			onComplete: this.mailboxListCB.bind( this ),
			onFailure: if_remoteRequestFailed
			} ).request();
	},

	mailboxListCB: function( serverResponse ) {
		// Parse the server's response.
		var result = if_checkRemoteResult( serverResponse );
		if ( !result ) { return null; } // TODO: Something other than this, here. IE: Error handling; let the user know what happened.

		this.dataStore.fetchMailboxListCB( result );
	},

	messageBody: function( mailbox, uid, mode ) {
		new Ajax( 'ajax.php', {
			postBody: 'request=getMessage&mailbox='+encodeURIComponent(mailbox)+'&msg='+encodeURIComponent(uid)+
				'&mode='+encodeURIComponent(mode),
			onComplete: this.messageBodyCB.bind( this ),
			onFailure: if_remoteRequestFailed
		} ).request();
	},

	messageBodyCB: function( serverResponse ) {
		// Parse the server's response.
		var result = if_checkRemoteResult( serverResponse );
		if ( !result ) { return null; } // TODO: Something other than this - let the user know what happened.

		this.dataStore.fetchMessageCB( result, result.data.mailbox, result.data.uid );
	},
			
	fetchLargePart: function( mailbox, uid, part ) {
		// Synchronously fetch a large part of a message.
		var remoteRequest = new XHR({method: 'get', async: false});
		var url = "message.php?mailbox=" + encodeURIComponent( mailbox ) + "&uid=" + encodeURIComponent( uid ) +
			"&filename=part:" + encodeURIComponent( part );
		remoteRequest.send( url, null );

		return remoteRequest.response.text;
	},

	moveMessage: function( sourceMailbox, destMailbox, uid, callback ) {
		new Ajax( 'ajax.php', {
			postBody: 'request=moveMessage&mailbox=' + encodeURIComponent(sourceMailbox) +
				'&destbox=' + encodeURIComponent(destMailbox) +
				'&uid=' + encodeURIComponent(uid),
				onComplete: this.moveMessageCB.bind( this ),
				onFailure: if_remoteRequestFailed
			} ).request();
	},
	moveMessageCB: function( serverResponse ) {
		var result = if_checkRemoteResult( serverResponse );
		if ( result ) {
			this.dataStore.moveMessageCB( result, false );
		} else {
			this.dataStore.moveMessageCB( result, true );
		}
        },

	deleteMessage: function( mailbox, uid ) {
		new Ajax( 'ajax.php', {
			postBody: 'request=deleteMessage&mailbox=' + encodeURIComponent(mailbox) +
				'&uid=' + encodeURIComponent(uid),
			onComplete: this.deleteMessageCB.bind( this ),
			onFailure: if_remoteRequestFailed
			} ).request();
	},
	deleteMessageCB: function( serverResponse ) {
		var result = if_checkRemoteResult( serverResponse );
		if ( result ) {
			this.dataStore.deleteMessageCB( result, false );
		} else {
			this.dataStore.deleteMessageCB( result, true );
		}
	},

	twiddleFlag: function( mailbox, uid, flag, state ) {
		var postbody = "request=setFlag";
		postbody += "&flag=" + encodeURIComponent( flag );
		postbody += "&mailbox=" + encodeURIComponent( mailbox );
		postbody += "&uid=" + encodeURIComponent( uid );
		if ( state ) {
			postbody += "&state=" + state;
		}
		new Ajax( 'ajax.php', {
			postBody: postbody,
			onComplete: this.twiddleFlagCB.bind( this ),
			onFailure: if_remoteRequestFailed
			} ).request();
	},
	twiddleFlagCB: function( serverResponse ) {
		var result = if_checkRemoteResult( serverResponse );
		if ( result ) {
			this.dataStore.twiddleFlagCB( result, false );
		} else {
			this.dataStore.twiddleFlagCB( result, true );
		}
	}
});
