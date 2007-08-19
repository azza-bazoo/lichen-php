/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
js/cache.js - locally cache mailbox and message data from the server
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


// TODO for cache classes:
// - Limit their sizes based on preferences.

// Class that knows how to get and store data from Google Gears.
var GoogleCacheConnector = new Class({
	initialize: function( dataStoreObject, username ) {

	}
	// TODO: Write me!
});


// Very simple hash table storage of data.
var HashCacheConnector = new Class({

	initialize: function( dataStoreObject, username ) {
		this.messagelists = {};
		this.messagelistsvalidity = {};
		this.messages = {};
		this.mailboxlist = {};
		this.mailboxlistvalidity = null;

		this.dataStore = dataStoreObject;
	},

	storeMessageList: function( mailbox, search, page, sort, messagelist, validity ) {
		// Store the message list in the cache.
		var cacheKey = mailbox + search + page + sort;

		if ( validity && this.messagelistsvalidity[cacheKey] && validity == this.messagelistsvalidity[cacheKey] ) {
			// Data is valid; the data itself will be null. Return the cached data.
			return this.messagelists[cacheKey];
		} else {
			// Data is not valid or new; update the cache.
			this.messagelists[cacheKey] = messagelist;
			this.messagelistsvalidity[cacheKey] = validity;

			return messagelist;
		}
	},

	getMessageList: function( mailbox, search, page, sort, validity ) {
		// Get the message list from the cache if it a) exists, and b)
		// is still valid. Pass null for validity to force a miss.
		var cacheKey = mailbox + search + page + sort;

		if ( this.messagelists[cacheKey] && this.messagelistsvalidity[cacheKey] &&
			validity && this.messagelistsvalidity[cacheKey] == validity ) {

			return this.messagelists[cacheKey];
		} else {
			return null;
		}
	},

	getMessageListValidity: function( mailbox, search, page, sort ) {
		// TODO: Couple this with the function above, rather than calling it seperately.
		var cacheKey = mailbox + search + page + sort;

		if ( this.messagelistsvalidity[cacheKey] != null ) {
			return this.messagelistsvalidity[cacheKey];
		} else {
			return null;
		}
	},

	haveCachedMessageList: function( mailbox, search, page, sort ) {
		if ( this.getMessageList( mailbox, search, page, sort, this.getMessageListValidity( mailbox, search, page, sort ) ) ) {
			return true;
		}
		return false;
	},

	storeMessage: function( mailbox, uid, message, validity ) {
		// Store a message into the cache.
		// TODO: Use the validitity to determine when it's out of date.
		var cacheKey = mailbox + uid;

		if ( this.messages[cacheKey] ) {
			// Update the message stored in the cache with the missing data..
			var cached = this.messages[cacheKey];
			if ( cached.texthtmlpresent && cached.texthtml.length == 0 && message.texthtml.length > 0 ) {
				cached.texthtml = message.texthtml;
			}
			if ( cached.textplainpresent && cached.textplain.length == 0 && message.textplain.length > 0 ) {
				cached.textplain = message.textplain;
			}
			if ( cached.source == null && message.source != null ) {
				cached.source = message.source;
			}
		} else {
			this.messages[cacheKey] = message;
		}

		// Return the data from the cache.
		return this.messages[cacheKey];
	},

	getMessage: function( mailbox, uid, mode, validity ) {
		// Get the message from the cache if it a) exists, and b) is still valid.
		// Pass null for validity to force a miss.
		// TODO: Implement the part that deals with validity.
		var cacheKey = mailbox + uid;
		var result = null;

		if ( this.messages[cacheKey] && validity ) {
			// Ok, it exists, but do we have the data we need?
			// TODO: move this somewhere else... this is the cache maintaining it's integrity; but should it?
			var cacheValid = false;
			var message = this.messages[cacheKey];
			switch ( mode ) {
				case 'html':
					if ( message.texthtmlpresent && message.texthtml.length != 0 ) {
						cacheValid = true;
					}
					break;
				case 'text':
					if ( message.textplainpresent && message.textplain.length != 0 ) {
						cacheValid = true;
					}
					break;
				case 'source':
					if ( message.source ) {
						cacheValid = true;
					}
					break;
				case 'all':
					// TODO: Decide what should happen here.
					break;
				default:
					// TODO: make a proper decision.
					cacheValid = true;
					break;
			}

			if ( cacheValid ) {
				result = this.messages[cacheKey];
			}
		}

		return result;
	},

	storeMailboxList: function( mailboxList, validity ) {
		// Store the mailbox list into the cache.
		if ( validity && validity == this.mailboxlistvalidity ) {
			return this.mailboxlist;
		} else {
			this.mailboxlist = mailboxList;
			this.mailboxlistvalidity = validity;
			return mailboxList;
		}
	},

	getMailboxList: function ( validity ) {
		if ( validity && validity == this.mailboxlistvalidity ) {
			return this.mailboxlist;
		} else {
			return null;
		}
	},

	getMailboxListValidity: function () {
		return this.mailboxlistvalidity;
	}
});
