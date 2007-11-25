/**

Lichen - AJAX IMAP client
version 0.4 by Hourann Bosci and Daniel Foote
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

		this.allMessagesMailbox = null;
		this.allMessagesSearch  = null;
	},

	nextOperationOnAllMessages: function( mailbox, search ) {
		this.allMessagesMailbox = mailbox;
		this.allMessagesSearch  = search;
	},
	clearOperationOnAllMessages: function() {
		this.allMessagesMailbox = null;
		this.allMessagesSearch  = null;
	},
	getOperationOnAllMessages: function () {
		if ( this.allMessagesMailbox != null && this.allMessagesSearch != null ) {
			return "&allmailbox=" + encodeURIComponent(this.allMessagesMailbox) +
				"&allsearch=" + encodeURIComponent(this.allMessagesSearch);
		} else {
			return "";
		}
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
				'&uid=' + encodeURIComponent(uid) +
				this.getOperationOnAllMessages(),
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
				'&uid=' + encodeURIComponent(uid) +
				this.getOperationOnAllMessages(),
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
		postbody += this.getOperationOnAllMessages();
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
	},
	
	addressBookList: function( searchin, searchterm ) {
		var postbody = "request=addressBookList";
		if ( searchin ) {
			postbody += "&searchin=" + encodeURIComponent( searchin );
		}
		if ( searchterm ) {
			postbody += "&searchterm=" + encodeURIComponent( searchterm );
		}
		new Ajax( 'ajax.php', {
			postBody: postbody,
			onComplete: this.addressBookListCB.bind( this ),
			onFailure: if_remoteRequestFailed
			} ).request();
	},
	addressBookListCB: function ( serverResponse ) {
		var result = if_checkRemoteResult( serverResponse );
		if ( result ) {
			this.dataStore.addressBookListCB( result, false );
		} else {
			this.dataStore.addressBookListCB( result, true );
		}
	},
	
	addressBookEdit: function( original, name, email, notes ) {
		var postbody = "request=addressBookEdit";
		postbody += "&original=" + encodeURIComponent(original);
		postbody += "&name=" + encodeURIComponent(name);
		postbody += "&email=" + encodeURIComponent(email);
		postbody += "&notes=" + encodeURIComponent(notes);
		new Ajax( 'ajax.php', {
			postBody: postbody,
			onComplete: this.addressBookEditCB.bind( this ),
			onFailure: if_remoteRequestFailed
			} ).request();
	},
	addressBookEditCB: function ( serverResponse ) {
		var result = if_checkRemoteResult( serverResponse );
		if ( result ) {
			this.dataStore.addressBookEditCB( result, false );
		} else {
			this.dataStore.addressBookEditCB( result, true );
		}
	},

	addressBookDelete: function( email ) {
		var postbody = "request=addressBookDelete";
		postbody += "&email=" + encodeURIComponent(email);
		new Ajax( 'ajax.php', {
			postBody: postbody,
			onComplete: this.addressBookDeleteCB.bind( this ),
			onFailure: if_remoteRequestFailed
			} ).request();
	},
	addressBookDeleteCB: function ( serverResponse ) {
		var result = if_checkRemoteResult( serverResponse );
		if ( result ) {
			this.dataStore.addressBookDeleteCB( result, false );
		} else {
			this.dataStore.addressBookDeleteCB( result, true );
		}
	}
});
