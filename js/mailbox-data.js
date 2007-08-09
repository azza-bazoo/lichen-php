/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
js/mailbox-data.js - data storage management for message lists
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


var MessagesDatastore = new Class({

	initialize: function( connectionState ) {
		// Are we in "online" or "offline" mode?
		this.online = connectionState;

		this.server = new IMAPServerConnector( this );
		// We pass the username so that it can store different data per user, even if it's
		// on the same browser profile. (Only basic security)
		this.cache = new HashCacheConnector( this, serverUser );
	},

	fetchMessageList: function( mailbox, search, page, sort ) { // TODO: better function name

		// TODO: Validity key is just the number of messages in the box... probably not a good key.
		var validityKey = this.cache.getMessageListValidity( mailbox, search, page, sort );

		var result = this.cache.getMessageList( mailbox, search, page, sort, validityKey );

		if ( result ) {
			// From cache: just directly use the data.
			list_showCB( result );
		}

		// Now fall through and ask the server anyway. It will tell us if we need new data.
		if ( this.online ) {
			// This step is optionally asynchronous, so let it call us back.
			this.server.messageList( mailbox, search, page, sort, validityKey );

			// While we're at it, also ask for the next/previous page.
			if ( page != 0 ) {
				this.server.messageList( mailbox, search, page - 1, sort,
					this.cache.getMessageListValidity( mailbox, search, page - 1, sort ), true );
			}
			this.server.messageList( mailbox, search, page + 1, sort,
					this.cache.getMessageListValidity( mailbox, search, page + 1, sort ), true );
		}

		if ( !this.online && !result ) {
			// Cache miss + not online! Unable to do as the user wishes.
			Flash.flashMessage( "Not online, and that data is not cached." );
		}
	},

	fetchMessageListCB: function ( metadata, mailbox, search, page, sort, validity, cacheonly ) {
		// Callback from the data store: the data is ready.

		if ( page == -1 ) {
			// This is a callback from an invalid page number.
			// Just do nothing.
			return;
		}

		// The cache will check the validity and return us either a cached copy or the new data.
		var result = this.cache.storeMessageList( mailbox, search, page, sort, metadata.data, metadata.validityKey );

		// Pass the data off to the callback.
		if ( cacheonly != "true" ) {
			list_showCB( result );
		}
	},

	fetchAdjacentMessages: function ( mailbox, search, page, sort, uid ) {
		// From the message lists that should be cached on the client side, fetch the data about the previous and
		// next messages - with respect to the given UID.

		// Retrieve the message list that this message is on.
		var messageIndex = -1;
		var messagePage = -1;
		var localMessagePage = -1;

		var previousMessage = null;
		var nextMessage = null;

		var messagePages = Array();
		messagePages.push( this.cache.getMessageList( mailbox, search, page, sort,
					this.cache.getMessageListValidity( mailbox, search, page, sort ) ) );
		messagePages.push( this.cache.getMessageList( mailbox, search, page + 1, sort,
					this.cache.getMessageListValidity( mailbox, search, page + 1, sort ) ) );
		messagePages.push( this.cache.getMessageList( mailbox, search, page - 1, sort,
					this.cache.getMessageListValidity( mailbox, search, page - 1, sort ) ) );

		for ( var j = 0; j < messagePages.length; j++ ) {
			if ( messagePages[j] == null ) {
				// Skip if non-existant page.
				continue;
			}

			// Search for the message with that uid in the list.
			for ( var i = 0; i < messagePages[j].messages.length; i++ ) {
				if ( messagePages[j].messages[i].uid == uid ) {
					// Found it!
					messageIndex = i;
					messagePage = messagePages[j].thispage.toInt();
					localMessagePage = j; // Index in the messagePages list.
					break;
				}
			}

			if ( messageIndex != -1 ) break;
		}

		/* There is a corner case here that is handled here.
		   Situation: you're on the last page of a mailbox.
		   You view the first message on the page. You hit "previous".
		   This function gets called to find the adjacent messages, but
		   what happens is that the page passed is as if you were still on the
		   last page. Thus, the prefetch that occurs above will fetch the WRONG
		   next and previous pages with respect to the given UID. In practice, this
		   gives completely the wrong results.
		   The solution: recurse. Make it look as if the actual page is the page where the UID actually is.
		*/
		if ( messagePage != page ) {
			return this.fetchAdjacentMessages( mailbox, search, messagePage, sort, uid );
		}

		// Did we find the message?
		if ( messageIndex != -1 && messagePage != -1 ) {
			// Locate the previous message.
			if ( messageIndex > 0 ) {
				previousMessage = messagePages[localMessagePage].messages[messageIndex - 1];
			}
			if ( messageIndex == 0 ) {
				// Grab the previous message from the end of the previous page.
				// Note the special use of the index "2"...
				if ( messagePages[2] != null ) {
					previousMessage = messagePages[2].messages[messagePages[2].messages.length - 1];
				}
			}

			// Locate the next message.
			if ( messageIndex < (messagePages[localMessagePage].messages.length - 1) ) {
				nextMessage = messagePages[localMessagePage].messages[messageIndex + 1];
			}
			if ( messageIndex == (messagePages[localMessagePage].messages.length - 1) ) {
				// Grab the next message from the end of the next page.
				// Note the special use of the index "1"...
				if ( messagePages[1] != null ) {
					nextMessage = messagePages[1].messages[0];
				}
			}
		}

		// Was this message not on the current page? If so, preload the list so that it will
		// be on the correct page, and force a preload of the data for that list view.
		// TODO: Looking at a global variable (listCurrentPage) to make this happen here...
		if ( messagePage != -1 && messagePage != listCurrentPage ) {
			// Update listCurrentPage.
			listCurrentPage = messagePage;

			if ( this.online ) {
				if ( messagePage != 0 ) {
					this.server.messageList( mailbox, search, messagePage - 1, sort,
						this.cache.getMessageListValidity( mailbox, search, messagePage - 1, sort ), true );
				}
				this.server.messageList( mailbox, search, messagePage + 1, sort,
						this.cache.getMessageListValidity( mailbox, search, messagePage + 1, sort ), true );
			}
		}

		// Ok, now... return the data that we needed.
		var result = {};
		result.previous = previousMessage;
		result.next = nextMessage;

		return result;
	},

	fetchMailboxList: function( ) {
		// Ask the data store for the data.
		var validity = this.cache.getMailboxListValidity();
		var result = this.cache.getMailboxList( validity );

		if ( this.online ) {
			// Ask the server to return us the most valid data.
			this.server.mailboxList( validity );
		} else if ( result ) {
			// It was cached and we're not online, return the data immediately.
			list_countCB( result );
		} else {
			// Not online, not in cache.
			Flash.flashMessage( "Unable to fetch mailbox list: not online and not cached." );
		}
	},

	fetchMailboxListCB: function( mailboxList ) {
		// Got the response from the server, check it and store as appropriate.
		var result = this.cache.storeMailboxList( mailboxList.data, mailboxList.validity );
		list_countCB( result );
	},

	fetchMessage: function( mailbox, uid, mode ) {
		// Return from the cache if we can.
		// mode is the type of data we want: the server only sends us what we want by default.
		// mode can be: "html", "text", "source", "all"
		var result = this.cache.getMessage( mailbox, uid, mode, true ); // TODO: The "true" is hack to never invalidate the cache.
		if ( result ) {
			MessageDisplayer.showMessageCB( result );
		} else {
			// Cache Miss, ask the server.
			if ( this.online ) {
				this.server.messageBody( mailbox, uid, mode );
			} else {
				// We're not online.
				Flash.flashMessage( "Unable to view message: in offline mode, and not cached." );
			}
		}
	},

	fetchMessageCB: function( message, mailbox, uid ) {
		// Cache this message.
		var result = this.cache.storeMessage( mailbox, uid, message.data );

		MessageDisplayer.showMessageCB( message.data );
	}
});