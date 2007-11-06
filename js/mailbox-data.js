/**

Lichen - AJAX IMAP client
version 0.4 by Hourann Bosci and Daniel Foote
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

/* CALLBACK HANDLING
 *
 * HISTORY
 *
 * Often many of the requests that this class may field require an async round trip
 * to a server component. To make this work, you need callbacks.
 *
 * Once upon a time, there was a convoluted system where a callback function was passed
 * to these functions. Then this was wrapped in a new closure, and passed to the server
 * component, where it wrapped that closure in another closure to make it all work.
 * When the request came back, the whole thing was unwrapped. It worked quite well, but
 * was kinda complicated.
 *
 * So it was stripped. Instead, the callbacks were completely removed and the actions upon
 * return were simply hard coded.
 *
 * Fast forward to ticket #30. How the hell do you move messages in the message display mode?
 * The hardcoded callback simply calls the message list update, which is not at all what is
 * required. So, a new scheme is required.
 *
 * NEW CALLBACK SCHEME
 *
 * The callback parameter to these functions is a function that will be called when the data
 * from the server is ready, or called immediately if the data is available from cache.
 *
 * The callback function should have the prototype:
 * function callback( serverJSONObject );
 * where serverJSONObject is the JSON object returned by the server (or from the cache, as relevant).
 *
 * IMPLEMENTATION
 *
 * Instead of doing some crazy closure scheme again, this time I'm relying on a PHP feature: sessions.
 * When you use sessions in your PHP script, all requests from a specific host are serialised -
 * that is, they are processed in the order they are recieved.
 *
 * Using this ability, when a request comes in that would require a server trip, we push the callback
 * function onto an array. When the request from the server comes back, we get the first element 
 * off the array (FIFO style) and call that function with the result.
 *
 * SERVER CLASS IMPLEMENTATION
 *
 * When the server class calls the MessagesDatastore callback, that function should have this prototype:
 *  function ( result, failed )
 * where result is the JSON object the server returned, and failed is true if the server had an error.
 */

var MessagesDatastore = new Class({

	initialize: function( connectionState ) {
		// Are we in "online" or "offline" mode?
		this.online = connectionState;

		this.server = new IMAPServerConnector( this );
		// We pass the username so that it can store different data per user, even if it's
		// on the same browser profile. (Only basic security)
		this.cache = new HashCacheConnector( this, serverUser );

		// Global callback function list.
		// (May need to be split up)
		this.callbacks = Array();

		this.allMessagesMailbox = null;
		this.allMessagesSearch  = null;
	},

	addCallback: function ( callback ) {
		this.callbacks.push( callback );
	},
	getCallback: function( failed ) {
		// failed is a true/false that indicates if the request succeeded.
		// if it failed, just pop off the callback (as if it didn't exist)
		// and then return null.

		var result = this.callbacks.shift();

		if ( failed ) {
			result = null;
		}

		return result;
	},

	fetchMessageList: function( mailbox, search, page, sort ) { // TODO: better function name

		// TODO: Validity key is just the number of messages in the box... probably not a good key.
		var validityKey = this.cache.getMessageListValidity( mailbox, search, page, sort );

		var result = this.cache.getMessageList( mailbox, search, page, sort, validityKey );

		if ( result ) {
			// From cache: just directly use the data.
			Lichen.action( "list", "MessageList", "listUpdateCB", [result] );
		}

		// Now fall through and ask the server anyway. It will tell us if we need new data.
		if ( this.online ) {
			// This step is optionally asynchronous, so let it call us back.
			this.server.messageList( mailbox, search, page, sort, validityKey );
		}

		if ( !this.online && !result ) {
			// Cache miss + not online! Unable to do as the user wishes.
			Lichen.Flash.flashMessage( _("Not online, and that data is not cached.") );
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
			Lichen.action( "list", "MessageList", "listUpdateCB", [result] );
		}

		// Semi-agressive cache: ask for the next and previous pages, and cache them.
		// If we already have something, don't request it, because it gets checked when
		// its actually required.
		if ( this.online && cacheonly != "true" ) {
			if ( page != 0 && !this.cache.haveCachedMessageList( mailbox, search, page - 1, sort ) ) {
				this.server.messageList( mailbox, search, page - 1, sort,
					this.cache.getMessageListValidity( mailbox, search, page - 1, sort ), true );
			}
			if ( result.numberpages > (page + 1) && !this.cache.haveCachedMessageList( mailbox, search, page + 1, sort ) ) {
				this.server.messageList( mailbox, search, page + 1, sort,
					this.cache.getMessageListValidity( mailbox, search, page + 1, sort ), true );
			}
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
		if ( messagePage != -1 && messagePage != Lichen.MessageList.getPage() ) {
			// Update the current page.
			Lichen.MessageList.setPage( messagePage, true );

			// Fetch the adjacent pages. Only if we don't already have them.
			// We don't care too much if they are invalid; so long as we have something.
			// They will get validated when we return to the list.
			if ( this.online ) {
				if ( messagePage != 0 ) {
					var validity = this.cache.getMessageListValidity( mailbox, search, messagePage - 1, sort );
					if ( !this.cache.getMessageList( mailbox, search, messagePage - 1, sort, validity ) ) {
						this.server.messageList( mailbox, search, messagePage - 1, sort, validity, true );
					}
				}
				var validity = this.cache.getMessageListValidity( mailbox, search, messagePage + 1, sort );
				if ( !this.cache.getMessageList( mailbox, search, messagePage + 1, sort, validity ) ) {
					this.server.messageList( mailbox, search, messagePage + 1, sort, validity, true );
				}
			}
		}

		// Ok, now... return the data that we needed.
		var result = {};
		result.previous = previousMessage;
		result.next = nextMessage;

		return result;
	},

	fetchMailboxList: function( fromCacheOnly ) {
		// Ask the data store for the data.
		var validity = this.cache.getMailboxListValidity();
		var result = this.cache.getMailboxList( validity );

		if ( fromCacheOnly ) {
			return result;
		}

		if ( this.online ) {
			// Ask the server to return us the most valid data.
			this.server.mailboxList( validity );
		} else if ( result ) {
			// It was cached and we're not online, return the data immediately.
			Lichen.MailboxList.listUpdateCB( result );
		} else {
			// Not online, not in cache.
			Lichen.Flash.flashMessage( _("Unable to fetch mailbox list: not online and not cached.") );
		}
	},

	fetchMailboxListCB: function( mailboxList ) {
		// Got the response from the server, check it and store as appropriate.
		var result = this.cache.storeMailboxList( mailboxList.data, mailboxList.validity );
		Lichen.MailboxList.listUpdateCB( result );
	},
	
	fetchMailboxStatus: function( mailbox ) {
		// Return the status of a specific mailbox.
		// This only works from the data in the cache.
		// If not in cache, you're out of luck.
		var validity = this.cache.getMailboxListValidity();
		var result = this.cache.getMailboxList( validity );

		var mailboxData = null;

		if ( result ) {
			for ( var i = 0; i < result.length; i++ ) {
				if ( result[i].fullboxname == mailbox ) {
					mailboxData = result[i];
				}
			}
		} else {
			// We don't have it in the cache.
		}

		return mailboxData;
	},

	fetchMessage: function( mailbox, uid, mode ) {
		// Message caching disabled for now due to conflicts
		// with interface feedback code.

		// Return from the cache if we can.
		// mode is the type of data we want: the server only sends us what we want by default.
		// mode can be: "html", "text", "source", "all"
		var result = this.cache.getMessage( mailbox, uid, mode, true ); // TODO: The "true" is hack to never invalidate the cache.
		if ( result ) {
			Lichen.action( 'display', 'MessageDisplayer', 'showMessageCB', [result] );
		} else {
			// Cache Miss, ask the server.
			if ( this.online ) {
				this.server.messageBody( mailbox, uid, mode );
			} else {
				// We're not online.
				Lichen.Flash.flashMessage( _("Unable to view message: in offline mode, and not cached.") );
			}
		}
	},

	fetchMessageCB: function( message, mailbox, uid ) {
		// Cache this message.
		var result = this.cache.storeMessage( mailbox, uid, message.data );

		Lichen.action( "display", "MessageDisplayer", "showMessageCB", [message.data] );
	},

	fetchLargePart: function( mailbox, uid, part, index ) {
		// There was a part that was too large to fetch.
		// Go and get it now, synchronously and return it to the user.
		if ( this.online ) {
			// Fetch the part.
			var messagePart = this.server.fetchLargePart( mailbox, uid, part );
			// Hack: wrap it in a pre, because there is no HTML processing done
			// on the data before we get it.
			messagePart = "<pre>" + messagePart + "</pre>";
			// Cache it for later.
			this.cache.storeLargePart( mailbox, uid, index, messagePart );
			return messagePart;
		} else {
			return _("Not online - unable to fetch that part of the message.");
		}
	},

	nextOperationOnAllMessages: function( mailbox, search ) {
		// Tag the next operation to work on all messages.
		// The server component will need the mailbox and any search
		// parameter to know what messages to target.
		this.allMessagesMailbox = mailbox;
		this.allMessagesSearch  = search;
		if ( this.online ) {
			this.server.nextOperationOnAllMessages( mailbox, search );
		} else {
			// Operate on them locally...
		}
	},

	clearOperationOnAllMessages: function () {
		if ( this.online ) {
			this.server.clearOperationOnAllMessages();
		}
		this.allMessagesMailbox = null;
		this.allMessagesSearch  = null;
	},

	moveMessage: function( sourceMailbox, destMailbox, uid, callback ) {
		// Pass the request to the server.
		this.addCallback( callback );
		this.server.moveMessage( sourceMailbox, destMailbox, uid );
	},
	moveMessageCB: function( serverResponse, failed ) {
		this.genericServerCallback( serverResponse, failed );
	},

	deleteMessage: function( mailbox, uid, callback ) {
		// Pass the request to the server.
		this.addCallback( callback );
		this.server.deleteMessage( mailbox, uid );
	},
	deleteMessageCB: function( serverResponse, failed ) {
		this.genericServerCallback( serverResponse, failed );
	},

	twiddleFlag: function( mailbox, uid, flag, state, callback ) {
		this.addCallback( callback );
		this.server.twiddleFlag( mailbox, uid, flag, state );
	},
	twiddleFlagCB: function( serverResponse, failed ) {
		if ( !failed ) {
			// Update the cache!
			// Hack: we can't rely on the values we get here.
			this.cache.updateFlagStatus( Lichen.MessageList.getMailbox(), Lichen.MessageList.getSearch(),
				Lichen.MessageList.getPage(), Lichen.MessageList.getSortStr(),
				serverResponse.uid, serverResponse.flag, serverResponse.state );
		}
		this.genericServerCallback( serverResponse, failed );
	},

	genericServerCallback: function( serverResponse, failed ) {
		var callback = this.getCallback( failed );

		if ( callback ) {
			callback( serverResponse );
		}
	}

});
