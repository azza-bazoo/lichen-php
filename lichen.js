// Lichen 0.3 - http://lichen-mail.org/
// Distributed under the GNU General Public License
// (see license.html for details)

window.onload = if_init;

var msgCount = 0;
var lastUIDconst = "";
var lastShownUID = "";
var listCurrentMailbox = 'INBOX';
var listCurrentPage = 0;
var listCurrentSearch = '';
var listCurrentSort = '';
var mailboxCount = 0;
var remoteRequestCount = 0;   // Number of active remote requests.
var refreshTimer;
var userSettings;

var mailboxCache = Array(); // A hack.

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
			onFailure : function( responseText ) {
				if_remoteRequestFailed( responseText );
			}
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
			onFailure : function( responseText ) {
				if_remoteRequestFailed( responseText );
			}
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
			onFailure : function( responseText ) {
				if_remoteRequestFailed( responseText );
			}
		} ).request();
	},
	messageBodyCB: function( serverResponse ) {
		// Parse the server's response.
		var result = if_checkRemoteResult( serverResponse );
		if ( !result ) { return null; } // TODO: Something other than this, here. IE: Error handling; let the user know what happened.

		this.dataStore.fetchMessageCB( result, result.data.mailbox, result.data.uid );
	}
});

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

var MessagesDatastore = new Class({
	initialize: function( online ) {
		// Are we in "online" or "offline" mode?
		this.online = online;

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

var FlashArea = new Class({
	initialize: function ( wrapper ) {
		this.wrapper = wrapper;
		this.messages = Array();
		this.timeouts = Array();
		this.onscreen = false;
		//this.slider = new Fx.Slide( wrapper );
	},
	flashMessage: function ( message, submessage ) {
		if ( submessage ) {
			this.messages.push( message + "<br /><span class=\"flash-submessage\">" + submessage + "</span>" );
		} else {
			this.messages.push( message );
		}

		this.renderFlash();

		if ( !this.onscreen ) {
			//this.slider.slideOut();
			$(this.wrapper).setStyle( 'display', 'block' );
			this.onscreen = true;
		}

		this.timeouts.push( window.setTimeout( this.clearFlash.bind( this ), 5000 ) );
	},
	renderFlash: function () {
		var flashHTML = this.messages.join( "<br />" );
		flashHTML += "<br /><a href=\"#\" onclick=\"Flash.hideFlash(); return false\">Hide</a>";
		$(this.wrapper).setHTML( flashHTML );
	},
	clearFlash: function () {
		this.messages.shift();
		this.timeouts.shift();

		if ( this.messages.length == 0 ) {
			//this.slider.slideIn();
			$(this.wrapper).setStyle( 'display', 'none' );
			this.onscreen = false;
		} else {
			this.renderFlash();
		}
	},
	hideFlash: function () {
		// Hide the flash instantly.
		// Cancel all the timeouts.
		this.messages = Array();
		for ( var i = 0; i < this.timeouts.length; i++ ) {
			window.clearTimeout( this.timeouts[i] );
		}
		this.timeouts = Array();
		this.renderFlash();
		this.onscreen = false;
		$(this.wrapper).setStyle( 'display', 'none' );
	}
});

var Flash = new FlashArea('flash');

// Interface initialisation, set mailbox autorefresh
function if_init() {
	//opts_get();
	list_fetchCount(); // Fetch the mailbox list FIRST.
	list_show();
	refreshTimer = setTimeout( list_checkCount, 5 * 60 * 1000 );
	// Workaround for a bug in KHTML
	if ( window.khtml ) {
		var tbWidth = (window.innerWidth-350) + 'px';
		$('list-bar').style.width = tbWidth;
		$('comp-bar').style.width = tbWidth;
		$('opts-bar').style.width = tbWidth;
		$('msg-bar').style.width = tbWidth;
//		var _subjWidth = window.getWidth - 411;
//		document.write("<style type=\"text/css\">td.subject { width: "+_subjWidth+"px; }</style>");
	}
	// Workaround for a bug in Konqueror
//	var _subjWidth = window.getWidth - 411;
//	document.write("<style type=\"text/css\">td.subject { width: "+_subjWidth+"px; }</style>");

	if ( window.ie6 ) {
		// PNG fix: http://homepage.ntlworld.com/bobosola/
		for( var i = 0; i < document.images.length; i ++ ) {
			var img = document.images[i];
			var imgName = img.src.toUpperCase();
			if (imgName.substring(imgName.length-3, imgName.length) == "PNG") {
				var strNewHTML = "<span style=\"width:22px;height:22px;cursor:hand;"
				+ "display:inline-block;vertical-align:middle;"
				+ "filter:progid:DXImageTransform.Microsoft.AlphaImageLoader"
				+ "(src=\'" + img.src + "\', sizingMethod='crop');\"></span>";
				img.outerHTML = strNewHTML;
				i = i-1;
			}
		}
		$('corner-bar').style.width = (document.body.clientWidth-128)+'px';
	}
}

function toggleDisplay( itemID ) {
	var el = $( itemID );

	if ( el.style.display == 'none' ) {
		el.style.display = 'block';
	} else if ( el.style.display == 'block' ) {
		el.style.display = 'none';
	} else {
		el.style.display = 'none';
	}
}

// Remote request started; display some sort of loading feedback.
// Keep a count of the remote requests.
function if_remoteRequestStart() {
	remoteRequestCount += 1;

//	$('loading').style.display = 'block';
}


// A request has completed... decrement the count, and if all requests
// have finished, remove the loading div.
function if_remoteRequestEnd() {
	if (remoteRequestCount > 0) {
		remoteRequestCount -= 1;
	}
	if (remoteRequestCount == 0) {
//		$('loading').style.display = 'none';
	}
}


// Check to see if the remote was all ok.
// Return JSON result if ok, or null if it failed.
function if_checkRemoteResult( remoteText ) {
	var result = "";

	if ( $type( remoteText ) != "string" ) {
		// Must have got an object; example if we simulate a callback
		// from the server by passing a JSON object.
		// Just return the object.
		return remoteText;
	}

	result = Json.evaluate( remoteText, true );

	if (!result) {
		alert( "Unable to Json decode what the server sent back.\n" +
			"The server sent us: '" + remoteText + "'\n" );
		if_remoteRequestEnd();
		return null;
	}

	if_remoteRequestEnd();

	if (result.resultCode != 'OK') {
		// Not ok.
		if ( result.resultCode == 'AUTH' ) {
			// Ask the user to enter their password.
			// TODO: Make everything else unusable on the page .. ?
			// TODO: Server side: make a note of what we were trying to do so that
			// we can do it again!
			var reloginBox = $('opts-wrapper');
			var reloginHTML = "";
			reloginHTML += "<h3>You were logged out.</h3>";
			reloginHTML += "<p>The server said: " + result.errorMessage + "</p>";
			reloginHTML += "<p>Enter your password to login again:</p>";
			reloginHTML += "<input type=\"password\" name=\"relogin_pass\" id=\"relogin_pass\" />";
			reloginHTML += "<button onclick=\"if_relogin(); return false\">Login</button>";

			reloginBox.setHTML( reloginHTML );
			reloginBox.setStyle( 'display', 'block' );
			$('relogin_pass').focus();
		} else {
			// Just alert the message we're given.
			Flash.flashMessage( result.errorMessage, "Imap errors: " + result.imapNotices );
			//alert( "Server says: " + result.errorMessage + "\nImap errors: " + result.imapNotices );
			return null;
		}
	} else {
		// All ok.
		return result;
	}
}


function if_remoteRequestFailed( remoteText ) {
	// For the time being...
	if_remoteRequestEnd();
	Flash.flashMessage( remoteText );
	//alert( remoteText );
}

function if_relogin() {
	if_remoteRequestStart();
	var password = $('relogin_pass').value;
	new Ajax( 'index.php', {
		postBody: 'action=relogin&user='+encodeURIComponent(serverUser)+'&pass='+encodeURIComponent(password),
		onComplete : function ( responseText ) {
			if_reloginCB( responseText );
		},
		onFailure : function( responseText ) {
			if_remoteRequestFailed( responseText );
		}
		} ).request();
}

function if_reloginCB( responseText ) {
	var result = if_checkRemoteResult( responseText );
	if (!result) return;

	// Check the result.
	if ( result.error ) {
		Flash.flashMessage( result.error );
	} else {
		// All ok, logged in. Our session cookie will be restored.
		$('opts-wrapper').setStyle( 'display', 'none' );
		$('opts-wrapper').empty();
	}
}

// For testing purposes.
function if_logoutSilent() {
	if_remoteRequestStart();
	new Ajax( 'index.php', {
		postBody: 'logout=0&silent=0',
		onComplete : function ( responseText ) {
			if_logoutSilentCB( responseText );
		},
		onFailure : function( responseText ) {
			if_remoteRequestFailed( responseText );
		}
		} ).request();
}

function if_logoutSilentCB( responseText ) {
	var result = if_checkRemoteResult( responseText );
	if (!result) return;

	Flash.flashMessage( "Silently logged out." );
}


function if_hideWrappers() {
//	$('list-status').style.display = 'none';
	$('list-wrapper').style.display = 'none';
	$('msg-wrapper').style.display = 'none';
	$('opts-wrapper').style.display = 'none';
	$('comp-wrapper').style.display = 'none';
}


function if_hideToolbars() {
	$('list-bar').style.display = 'none';
	$('comp-bar').style.display = 'none';
	$('msg-bar').style.display = 'none';
	$('opts-bar').style.display = 'none';
}


// Spawns a new window at the given URL.
function if_newWin( addr ) {
	var nw = window.open( addr );
	return !nw;
}


// Ask the server for our settings.
function opts_get() {
	if_remoteRequestStart();
	new Ajax( 'ajax.php', {
		postBody: 'request=getUserSettings',
		onComplete : function ( responseText ) {
			opts_getCB( responseText );
		},
		onFailure : function( responseText ) {
			if_remoteRequestFailed( responseText );
		}
		} ).request();
}


function opts_getCB( responseText ) {
	var result = if_checkRemoteResult( responseText );
	if (!result) return;

	userSettings = result.settings;
}


// Save our settings to the server.
function opts_save() {
	if_remoteRequestStart();
	new Ajax( 'ajax.php', {
		postBody: 'request=saveUserSettings&settings=' + encodeURIComponent( Json.toString( userSettings ) ),
		onComplete : function ( responseText ) {
			opts_saveCB( responseText );
		},
		onFailure : function( responseText ) {
			if_remoteRequestFailed( responseText );
		}
		} ).request();
}


function opts_saveCB( responseText ) {
	var result = if_checkRemoteResult( responseText );
	if (!result) return;
}

var MailboxManagerClass = new Class({
	initialize: function () {
		this.mailboxCache = null;
	},
	showManager: function () {
		if_remoteRequestStart();
		new Ajax( 'ajax.php', {
			postBody: 'request=mailboxManager',
			onComplete : this.showManagerCB.bind( this ),
			onFailure : function( responseText ) {
				if_remoteRequestFailed( responseText );
			}
			} ).request();
	},
	showManagerCB: function ( responseText ) {
		var result = if_checkRemoteResult( responseText );
		if (!result) return;

		var mailboxes = result.mailboxes;

		// Build the HTML.
		var managerHtml = "<div>";
		managerHtml += "<h3>Mailbox Manager</h3>";
		managerHtml += "<table border=\"0\">";
		managerHtml += "<tr id=\"mbm-row-\"><td><div id=\"mbm-namearea-\">[Top Level]</div></td>";
		managerHtml += "<td><div id=\"mbm-buttonarea-\">" + this._makeButtons( '', '', true ) + "</div></td></tr>";
		for ( var i = 0; i < mailboxes.length; i++ ) {
			var thisMailbox = mailboxes[i];
			managerHtml += "<tr id=\"mbm-row-" + thisMailbox.fullboxname + "\"><td>";
			managerHtml += "<div id=\"mbm-namearea-" + thisMailbox.fullboxname + "\">";
			for ( var j = 0; j < thisMailbox.folderdepth; j++ ) {
				managerHtml += "-"; // Poor man's indenting.
			}
			managerHtml += thisMailbox.mailbox;
			managerHtml += "</div></td><td><div id=\"mbm-buttonarea-" + thisMailbox.fullboxname + "\">";
			managerHtml += this._makeButtons( thisMailbox.fullboxname, thisMailbox.mailbox ) + "</div></td></tr>";
		}
		managerHtml += "</table>";

		managerHtml += "<a href=\"#\" onclick=\"MailboxManager.closeManager(); return false\">Close Pane</a>";
		managerHtml += "</div>";

		this.mailboxCache = result.mailboxes;

		$('opts-wrapper').setHTML(managerHtml);
		$('opts-wrapper').setStyle('display', 'block');
	},
	_makeButtons: function ( fullboxname, displayname, toplevel ) {
		// Internal function to generate buttons to work with each mailbox.
		// TODO: Assumes that it's instance is called "MailboxManager"
		var buttonsHtml = "<a href=\"#\" onclick=\"MailboxManager.newChild('" + fullboxname + "'); return false\">";
		buttonsHtml += "<img src=\"themes/" + userSettings.theme + "/icons/add.png\"></a>";
		if ( !toplevel ) {
			buttonsHtml += "<a href=\"#\" onclick=\"MailboxManager.renameInline('" + fullboxname + "', '" + displayname + "'); return false\">";
			buttonsHtml += "<img src=\"themes/" + userSettings.theme + "/icons/edit.png\"></a>";
			buttonsHtml += "<a href=\"#\" onclick=\"MailboxManager.changeParentInline('" + fullboxname + "', '" + displayname + "'); return false\">";
			buttonsHtml += "<img src=\"themes/" + userSettings.theme + "/icons/changeparent.png\"></a>";
			buttonsHtml += "<a href=\"#\" onclick=\"MailboxManager.mailboxDelete('" + fullboxname + "', '" + displayname + "'); return false\">";
			buttonsHtml += "<img src=\"themes/" + userSettings.theme + "/icons/remove.png\"></a>";
		}

		return buttonsHtml;
	},
	closeManager: function () {
		$('opts-wrapper').setStyle('display', 'none');
		list_checkCount();
	},
	renameInline: function ( fullboxname, boxname ) {
		// Replace the area with the name with an input control with the name,
		// plus a button to submit it.

		var nameArea = $('mbm-namearea-' + fullboxname);
		var buttonArea = $('mbm-buttonarea-' + fullboxname);

		if ( nameArea && buttonArea ) {
			var editHtml = "<input id=\"mbm-rename-" + fullboxname + "\" type=\"text\" size=\"20\" value=\"" + boxname + "\" />";
			editHtml += "<button onclick=\"MailboxManager.renameDone('" + fullboxname + "', '" + boxname + "'); return false\">Set</button>";

			// Hide the buttons for this one, replace the name area with the input box.
			nameArea.setHTML( editHtml );
			var renameBox = $('mbm-rename-' + fullboxname);
			if ( renameBox ) {
				renameBox.focus();
			}
			buttonArea.setStyle( 'display', 'none' );
		}
	},
	renameDone: function ( fullboxname, boxname ) {
		// The inline rename is completed, tell the server.

		var nameBox = $('mbm-rename-' + fullboxname);
		if ( nameBox ) {
			var newname = nameBox.value;

			if ( newname && newname != "" ) {

				if ( newname == boxname ) {
					// No change - just hide the box.
					this.serverActionCB( {action: 'rename', mailbox1: fullboxname, mailbox2: fullboxname} );
				} else {
					newname = fullboxname.substr(0, fullboxname.length - boxname.length) + newname;

					if_remoteRequestStart();
					new Ajax( 'ajax.php', {
						postBody: 'request=mailboxAction&action=rename&mailbox1=' + encodeURIComponent(fullboxname) +
							'&mailbox2=' + encodeURIComponent(newname),
						onComplete : this.serverActionCB.bind( this ),
						onFailure : function( responseText ) {
							if_remoteRequestFailed( responseText );
						}
						} ).request();
				}
			}
		}
	},
	mailboxDelete: function ( fullboxname, boxname ) {
		if ( confirm("Are you sure you want to delete '" + boxname + "'? This action is irreversable.") ) {
			if_remoteRequestStart();
			new Ajax( 'ajax.php', {
				postBody: 'request=mailboxAction&action=delete&mailbox1=' + encodeURIComponent(fullboxname),
				onComplete : this.serverActionCB.bind( this ),
				onFailure : function( responseText ) {
					if_remoteRequestFailed( responseText );
				}
				} ).request();
		}
	},
	newChild: function ( fullboxname ) {
		// Append a new entry form to the area.
		// plus a button to submit it.

		var nameArea = $('mbm-namearea-' + fullboxname);
		var buttonArea = $('mbm-buttonarea-' + fullboxname);

		if ( nameArea && buttonArea ) {
			var childHtml = "<div id=\"mbm-newchild-wrapper-" + fullboxname + "\">";
			childHtml += "New Subfolder: <input id=\"mbm-newchild-" + fullboxname + "\" type=\"text\" size=\"20\" />";
			childHtml += "<button onclick=\"MailboxManager.newChildSubmit('" + fullboxname + "'); return false\">Add</button>";
			childHtml += "<button onclick=\"MailboxManager.newChildCancel('" + fullboxname + "'); return false\">Cancel</button>";
			childHtml += "</div>";

			nameArea.innerHTML += childHtml;
			buttonArea.setStyle( 'display', 'none' );

			var newChildBox = $('mbm-newchild-' + fullboxname);
			if ( newChildBox ) {
				newChildBox.focus();
			}
		}
	},
	newChildSubmit: function ( fullboxname ) {
		// Ask the server to create the new mailbox.
		var childMailbox = $('mbm-newchild-' + fullboxname);

		if ( childMailbox && childMailbox.value != "" ) {
			if_remoteRequestStart();
			new Ajax( 'ajax.php', {
				postBody: 'request=mailboxAction&action=create&mailbox1=' + encodeURIComponent(fullboxname) +
					'&mailbox2=' + encodeURIComponent(childMailbox.value),
				onComplete : this.serverActionCB.bind( this ),
				onFailure : function( responseText ) {
					if_remoteRequestFailed( responseText );
				}
				} ).request();
		}
	},
	newChildCancel: function ( fullboxname ) {
		// Just remove the form.
		var childArea = $('mbm-newchild-wrapper-' + fullboxname);
		var buttonArea = $('mbm-buttonarea-' + fullboxname);

		if ( childArea ) {
			childArea.remove();
		}
		if ( buttonArea ) {
			buttonArea.setStyle( 'display', 'block' );
		}
	},
	changeParentInline: function ( fullboxname, boxname ) {
		// Show a drop down allowing us to change the parent.

		var nameArea = $('mbm-namearea-' + fullboxname);
		var buttonArea = $('mbm-buttonarea-' + fullboxname);

		if ( nameArea && buttonArea ) {
			var childHtml = "<div id=\"mbm-changeparent-wrapper-" + fullboxname + "\">";
			childHtml += "Move to subfolder of: ";
			childHtml += "<select id=\"mbm-changeparent-" + fullboxname + "\">";
			childHtml += "<option value=\"\">[Top Level]</option>";
			for ( var i = 0; i < this.mailboxCache.length; i++ ) {
				var thisMailbox = this.mailboxCache[i];
				childHtml += "<option value=\"" + thisMailbox.fullboxname + "\"";
				if ( boxname == thisMailbox.mailbox ) {
					childHtml += " selected=\"selected\">";
				} else {
					childHtml += ">";
				}
				for ( var j = 0; j < thisMailbox.folderdepth; j++ ) {
					childHtml += "-";
				}
				childHtml += thisMailbox.mailbox + "</option>";
			}
			childHtml += "</select>";

			childHtml += "<button onclick=\"MailboxManager.changeParentSubmit('" + fullboxname + "', '" + boxname + "'); return false\">Move</button>";
			childHtml += "<button onclick=\"MailboxManager.changeParentCancel('" + fullboxname + "', '" + boxname + "'); return false\">Cancel</button>";
			childHtml += "</div>";

			nameArea.innerHTML += childHtml;
			buttonArea.setStyle( 'display', 'none' );
		}
	},
	changeParentSubmit: function ( fullboxname, boxname ) {
		// Ask the server to create the new mailbox.
		var newParentMailbox = $('mbm-changeparent-' + fullboxname);

		if ( newParentMailbox && newParentMailbox.value != boxname ) {
			if_remoteRequestStart();
			new Ajax( 'ajax.php', {
				postBody: 'request=mailboxAction&action=move&mailbox1=' + encodeURIComponent(fullboxname) +
					'&mailbox2=' + encodeURIComponent(newParentMailbox.value),
				onComplete : this.serverActionCB.bind( this ),
				onFailure : function( responseText ) {
					if_remoteRequestFailed( responseText );
				}
				} ).request();
		}
	},
	changeParentCancel: function ( fullboxname, boxname ) {
		// Just remove the form.
		var childArea = $('mbm-changeparent-wrapper-' + fullboxname);
		var buttonArea = $('mbm-buttonarea-' + fullboxname);

		if ( childArea ) {
			childArea.remove();
		}
		if ( buttonArea ) {
			buttonArea.setStyle( 'display', 'block' );
		}
	},
	serverActionCB: function ( responseText ) {
		var result = if_checkRemoteResult( responseText );
		if (!result) return;

		switch ( result.action ) {
			case 'rename':
				// Remove the name area, show the buttons.
				// Then change the IDs of the various parts.
				var nameArea = $('mbm-namearea-' + result.mailbox1);
				var buttonArea = $('mbm-buttonarea-' + result.mailbox1);
				var nameRow = $('mbm-row-' + result.mailbox1);

				var delimiter = this.mailboxCache[0]['delimiter'];
				var startIndex = result.mailbox2.lastIndexOf( delimiter );
				var mailboxName = result.mailbox2;
				var mailboxDepth = result.mailbox2.split( delimiter );
				mailboxDepth = mailboxDepth.length - 1;
				if ( startIndex != -1 ) {
					mailboxName = result.mailbox2.substr( startIndex + 1 );
				}

				if ( nameRow ) {
					nameRow.id = 'mbm-row-' + result.mailbox2;
				}
				if ( nameArea ) {
					var nameAreaHtml = "";
					for ( var j = 0; j < mailboxDepth; j++ ) {
						nameAreaHtml += "-";
					}
					nameAreaHtml += mailboxName;
					nameArea.setHTML(nameAreaHtml);
					nameArea.id = "mbm-namearea-" + result.mailbox2;
				}
				if ( buttonArea ) {
					buttonArea.setHTML( this._makeButtons( result.mailbox2, mailboxName ) );
					buttonArea.id = "mbm-buttonarea-" + result.mailbox2;
					buttonArea.setStyle( 'display', 'block' );
				}
				break;
			case 'delete':
				var nameRow = $('mbm-row-' + result.mailbox1);
				if ( nameRow ) {
					nameRow.remove();
				}
				break;
			case 'create':
			case 'move':
				// Refresh the whole list.
				this.showManager();
				break;
		}

		this.mailboxCache = result.mailboxes;
	}
});

var MailboxManager = new MailboxManagerClass();

var OptionsEditorClass = new Class({
	initialize: function ( wrapper ) {
		this.openpanel = "";
		this.wrapper = wrapper;
	},
	showEditor: function () {
		clearTimeout( refreshTimer );
		if_remoteRequestStart();
		new Ajax( 'ajax.php', {
			postBody: 'request=settingsPanel',
			onComplete : this.showEditorCB.bind( this ),
			onFailure : function( responseText ) {
				if_remoteRequestFailed( responseText );
			}
			} ).request();
	},
	showEditorCB: function ( responseText ) {
		var result = if_checkRemoteResult( responseText );
		if (!result) return;

		if_hideWrappers();
		if_hideToolbars();

		$('opts-bar').style.display = 'block';
		$(this.wrapper).style.display = 'block';
		$(this.wrapper).setHTML(result.htmlFragment);

		this.openpanel = result.startPanel;
	},
	showPanel: function ( panelName ) {
		toggleDisplay( 'settings_' + this.openpanel );
		toggleDisplay( 'settings_' + panelName );
		this.openpanel = panelName;
	},
	closePanel: function () {
		if_hideWrappers();
		if_hideToolbars();

		// TODO: return to whatever the user had open
		$('list-bar').style.display = 'block';
		$('list-wrapper').style.display = 'block';
	//	opts_get();
	},
	generateQueryString: function( sourceForm ) {
		var inputs = $A( $(sourceForm).getElementsByTagName('input') );
		inputs.extend( $A( $(sourceForm).getElementsByTagName('select') ) );
		var results = Array();

		for ( var i = 0; i < inputs.length; i++ ) {
			var thisValue = "";
			switch ( inputs[i].type ) {
				case "checkbox":
					if ( inputs[i].checked ) {
						thisValue = "true";
					} else {
						thisValue = "false";
					}
					break;
				default:
					thisValue = inputs[i].value;
					break;
			}
			results.push( inputs[i].id + "=" + encodeURIComponent( thisValue ) );
		}

		return results.join("&");
	},
	saveOptions: function () {
		if_remoteRequestStart();
		new Ajax( 'ajax.php', {
			postBody: 'request=settingsPanelSave&' + this.generateQueryString('settings'),
			onComplete : this.saveOptionsCB.bind( this ),
			onFailure : function( responseText ) {
				if_remoteRequestFailed( responseText );
			}
			} ).request();
	},
	saveOptionsCB: function ( responseText ) {
		var result = if_checkRemoteResult( responseText );
		if (!result) return;

		if ( result.errors && result.errors.length > 0 ) {
			alert("There were some errors saving your settings.\nAny valid settings were saved.\n\n" + result.errors.join("\n"));
		} else {
			this.closePanel();
		}

		opts_getCB( result );
		list_show();
	},
	identity_add: function () {
		var editarea = $('identity-editor');

		editarea.empty();
		var htmlFragment = "<div><span>Name:</span> <input type=\"text\" size=\"30\" id=\"identity-name\" /></div>";
		htmlFragment += "<div><span>Email:</span> <input type=\"text\" size=\"30\" id=\"identity-email\" /></div>";
		htmlFragment += "<button onclick=\"return OptionsEditor.identity_add_done()\">Add</button>";
		htmlFragment += "<button onclick=\"return OptionsEditor.identity_cleareditor()\">Cancel</button>";

		editarea.setHTML( htmlFragment );

		return false;
	},
	identity_add_done: function () {
		var idname = $('identity-name').value;
		var idemail = $('identity-email').value;

		if ( idname == "" || idemail == "" ) {
			Flash.flashMessage( "Can't add an identity with a blank name or blank e-mail." );
			return false;
		}

		if_remoteRequestStart();
		new Ajax( 'ajax.php', {
			postBody: 'request=identityEditor&action=add&idname='+encodeURIComponent( idname )+'&idemail='+encodeURIComponent( idemail ),
			onComplete : this.identity_actionCB.bind( this ),
			onFailure : function( responseText ) {
				if_remoteRequestFailed( responseText );
			}
			} ).request();

		return false;
	},
	identity_setdefault: function () {
		var identitylist = $('identities-list');

		if ( identitylist.value == "" ) return false;

		var identity = identitylist.value;
		identity = identity.split(",");
		var idemail = identity.shift();
		var idname = identity.join(",");

		if_remoteRequestStart();
		new Ajax( 'ajax.php', {
			postBody: 'request=identityEditor&action=setdefault&oldid='+encodeURIComponent( idemail ),
			onComplete : this.identity_actionCB.bind( this ),
			onFailure : function( responseText ) {
				if_remoteRequestFailed( responseText );
			}
			} ).request();

		return false;
	},
	identity_edit: function () {
		var editarea = $('identity-editor');
		var identitylist = $('identities-list');

		if ( identitylist.value == "" ) return false;

		var identity = identitylist.value;
		identity = identity.split(",");
		var idemail = identity.shift();
		var idname = identity.join(",");

		editarea.empty();
		var htmlFragment = "<div><span>Name:</span> <input type=\"text\" size=\"30\" id=\"identity-name\" value=\"" + idname + "\" /></div>";
		htmlFragment += "<div><span>Email:</span> <input type=\"text\" size=\"30\" id=\"identity-email\" value=\"" + idemail + "\" /></div>";
		htmlFragment += "<button onclick=\"return OptionsEditor.identity_edit_done('" + idemail + "')\">Edit</button>";
		htmlFragment += "<button onclick=\"return OptionsEditor.identity_cleareditor()\">Cancel</button>";

		editarea.setHTML( htmlFragment );

		return false;
	},
	identity_edit_done: function ( oldemail ) {
		var idname = $('identity-name').value;
		var idemail = $('identity-email').value;

		if ( idname == "" || idemail == "" ) {
			Flash.flashMessage( "Can't edit an identity to have a blank name or blank e-mail." );
			return false;
		}

		if_remoteRequestStart();
		new Ajax( 'ajax.php', {
			postBody: 'request=identityEditor&action=edit&idname='+encodeURIComponent( idname )+'&idemail='+encodeURIComponent( idemail )+
				'&oldid='+encodeURIComponent(oldemail),
			onComplete : this.identity_actionCB.bind( this ),
			onFailure : function( responseText ) {
				if_remoteRequestFailed( responseText );
			}
			} ).request();

		return false;
	},
	identity_cleareditor: function () {
		$('identity-editor').empty();
		return false;
	},
	identity_remove: function () {
		var identitylist = $('identities-list');

		if ( identitylist.value == "" ) return false;

		var identity = identitylist.value;
		identity = identity.split(",");
		var idemail = identity[0];
		var idname = identity[1];

		if_remoteRequestStart();
		new Ajax( 'ajax.php', {
			postBody: 'request=identityEditor&action=delete&oldid='+encodeURIComponent( idemail ),
			onComplete : this.identity_actionCB.bind( this ),
			onFailure : function( responseText ) {
				if_remoteRequestFailed( responseText );
			}
			} ).request();

		return false;
	},
	identity_actionCB: function ( responseText ) {
		var result = if_checkRemoteResult( responseText );
		if (!result) return;

		this.identity_cleareditor();

		var identitieslist = $('identities-list');
		identitieslist.empty();

		// Below we deviate from normal and use the DOM to add the options
		// back to the list. I've done this because the setHTML didn't work
		// in IE. The DOM Method works in both IE and FireFox, more testing
		// is required for other browers.
		for ( var i = 0; i < result.identities.length; i++ ) {
			var thisId = result.identities[i];
			var value = thisId.address + "," + thisId.name;
			var display = thisId.name + " &lt;" + thisId.address + "&gt;";
			if ( thisId.isdefault ) display += " (default)";

			var option = new Element('option');
			option.value = value;
			option.setHTML( display );

			identitieslist.adopt(option);
		}
	}
});

var OptionsEditor = new OptionsEditorClass('opts-wrapper');

var MessageDisplay = new Class({
	initialize: function( wrapperdiv ) {
		this.wrapperdiv = wrapperdiv;
		this.displayMode = "";
		this.displayModeUID = "";
	},
	showMessage: function( mailbox, uid, mode ) {
		// Rather than having fetchMessage send us the mode back,
		// cache it in our instance data. We have to mark which UID we were
		// talking about, though.
		this.displayMode = mode;
		this.displayModeUID = uid;
		var requestMode = mode;

		// Hack to fetch the text data but display it as monospaced.
		if ( mode == "monospace" ) {
			requestMode = "text";
		}

		// Go off and fetch the data.
		Messages.fetchMessage( mailbox, uid, requestMode );
	},
	showMessageCB: function( message, mode ) {
		// Got the data about the message. Render it and begin the display.
		if ( $('mr-'+message.uid) ) {
			$('mr-'+message.uid).removeClass('new');
		}

		if ( !mode && this.displayModeUID == message.uid ) {
			mode = this.displayMode;
		}

		clearTimeout( refreshTimer );
		if_hideWrappers();
		if_hideToolbars();
		$(this.wrapperdiv).empty();
		$(this.wrapperdiv).setHTML( this._render( message, mode ) );
		$(this.wrapperdiv).style.display = 'block';
		$('msg-bar').style.display = 'block';
		lastShownUID = message.uid;
	},
	_render: function( message, forceType ) {
		// Find the next/previous messages.
		var adjacentMessages = Messages.fetchAdjacentMessages( listCurrentMailbox, listCurrentSearch, listCurrentPage, listCurrentSort, message.uid );

		var htmlFragment = "<div class=\"header-bar\"><img src=\"themes/" + userSettings.theme + "/top-corner.png\" alt=\"\" id=\"top-corner\" />";

		var messageNavBar = "<div class=\"header-left\"><a class=\"list-return\" href=\"#inbox\" onclick=\"if_returnToList(lastShownUID);return false\">back to " + listCurrentMailbox + "</a></div>";

		messageNavBar += "<div class=\"header-right\">";
		if ( adjacentMessages.previous ) {
			messageNavBar += "<a href=\"#\" onclick=\"return showMsg('" + message.mailbox + "','" + adjacentMessages.previous.uid + "')\">previous</a> | ";
		}
		if ( adjacentMessages.next ) {
			messageNavBar += "<a href=\"#\" onclick=\"return showMsg('" + message.mailbox + "','" + adjacentMessages.next.uid + "')\">next message</a>";
		}
		messageNavBar += "</div>";

		htmlFragment += messageNavBar + "</div>";

		htmlFragment += "<h1 class=\"msg-head-subject\">" + message.subject + "</h1>";
		htmlFragment += "<p class=\"msg-head-line2\">from <span class=\"msg-head-sender\">" + message.from + "</span> ";
		htmlFragment += "at <span class=\"msg-head-date\">" + message.localdate + "</span></p>";

		htmlFragment += "<div>";
		var viewLinks = Array();
		if ( message.texthtml.length > 0 || message.texthtmlpresent ) {
			viewLinks.push( "<a href=\"#\" onclick=\"return showMsg('" + message.mailbox + "','" + message.uid + "', 'html')\">HTML View</a>" );
			if ( message.htmlhasremoteimages ) {
				viewLinks.push( "<a href=\"#\" onclick=\"return MessageDisplayer.enableRemoteImages()\">Show Remote Images</a>" );
			}
		}
		if ( message.textplain.length > 0 || message.textplainpresent ) {
			viewLinks.push( "<a href=\"#\" onclick=\"return showMsg('" + message.mailbox + "','" + message.uid + "', 'text')\">Text View</a>" );
			viewLinks.push( "<a href=\"#\" onclick=\"return showMsg('" + message.mailbox + "','" + message.uid + "', 'monospace')\">Monospace View</a>" );
		}
		viewLinks.push( "<a href=\"#\" onclick=\"return showMsg('" + message.mailbox + "','" + message.uid + "', 'source')\">Source</a>" );

		htmlFragment += viewLinks.join( " | " );
		htmlFragment += "</div>";

		// TODO: Clean up this multistage IF. Its a bit IFFY.
		if ( message.texthtml.length > 0 && forceType != "text" && forceType != "monospace" && forceType != "source" ) {
			// Display HTML in preference.
			for ( var i = 0; i < message.texthtml.length; i++ ) {
				htmlFragment += "<div class=\"html-message\">";
				htmlFragment += message.texthtml[i]; // This is sanitised on the server.
				htmlFragment += "</div>";
			}
		} else if ( forceType == "source" ) {
			// Display the message source.
			htmlFragment += "<div class=\"plain-message-monospace\">";
			htmlFragment += "<pre>";
			htmlFragment += message.source;
			htmlFragment += "</pre>";
			htmlFragment += "</div>";
		} else {
			// Display the text parts.
			for ( var i = 0; i < message.textplain.length; i++ ) {
				htmlFragment += "<div class=\"plain-message";
				if ( forceType == "monospace" ) {
					htmlFragment += " plain-message-monospace";
				}
				htmlFragment += "\">";
				htmlFragment += message.textplain[i]; // This is linkified/cleaned on the server.
				htmlFragment += "</div>";
			}
		}

		if ( message.attachments.length > 0 && forceType != "source" ) {
			htmlFragment += "Attachments: <ul class=\"attachments\">";

			for ( var i = 0; i < message.attachments.length; i++ ) {
				var thisAttach = message.attachments[i];
				// Skip attachments that are internal-only.
				if ( thisAttach.filename == "" ) continue;
				htmlFragment += "<li>";
				var attachUrl = "message.php?mailbox=" + encodeURIComponent( message.mailbox ) +
					"&uid=" + encodeURIComponent( message.uid ) + "&filename=" + encodeURIComponent( thisAttach.filename );
				htmlFragment += "<a href=\"" + attachUrl + "\" onclick=\"return if_newWin('" + attachUrl + "')\">";
				htmlFragment += thisAttach.filename + "</a>";
				htmlFragment += " (type " + thisAttach.type + ", size ~" + thisAttach.size + " bytes)";

				if ( thisAttach.type.substr( 0, 5 ) == "image" ) {
					htmlFragment += "<br />";
					htmlFragment += "<img src=\"" + attachUrl + "\" alt=\"" + attachUrl + "\" />";
				}
			}

			htmlFragment += "</ul>";
		}

		htmlFragment += "<div class=\"footer-bar\"><img src=\"themes/" + userSettings.theme + "/bottom-corner.png\" alt=\"\" id=\"bottom-corner\" />" + messageNavBar + "</div>";

		return htmlFragment;
	},
	enableRemoteImages: function () {
		// Remote images have the class "remoteimage", and are disabled by prepending a
		// "_" to the url, breaking it. Strip this break.
		// TODO: this is the correct way to do it:
		// var remoteImages = $A( $ES( 'img.remoteimage' ) );
		var remoteImages = $('msg-wrapper').getElementsByTagName('img');

		for ( var i = 0; i < remoteImages.length; i++ ) {
			if ( remoteImages[i].src.substr( 7, 1 ) == "_" ) {
				var fixedUrl = "http://" + remoteImages[i].src.substr( 8 );
				remoteImages[i].src = fixedUrl;
			}
		}

		return false;
	}
});

// TODO: This is just a hack to make it work...
var MessageDisplayer = new MessageDisplay( 'msg-wrapper' );
function showMsg( mailbox, uid, mode ) {
	MessageDisplayer.showMessage( mailbox, uid, mode );
	return false;
}

// Show the form for a new message. If provided, prefill the
// recipient(s) and subject fields, and quote the given message.
function comp_showForm( mode, quoteUID, mailto ) {
	clearTimeout( refreshTimer );

	var postbody = "request=createComposer&mailbox=" + encodeURIComponent(listCurrentMailbox);
	if ( mode )     postbody += "&mode=" + mode;
	if ( quoteUID ) postbody += "&uid=" + encodeURIComponent(quoteUID);
	if ( mailto )   postbody += "&mailto=" + encodeURIComponent(mailto);
	if_remoteRequestStart();
	new Ajax( 'ajax.php', {
		postBody: postbody,
		onComplete : function ( responseText ) {
			comp_showCB( responseText );
		},
		onFailure : function( responseText ) {
			if_remoteRequestFailed( responseText );
		}
		} ).request();
}


// Callback for displaying message compose form.
function comp_showCB( responseText ) {
	var result = if_checkRemoteResult( responseText );
	if (!result) return;

	if_hideWrappers();
	if_hideToolbars();

	$('comp-wrapper').setHTML( result.htmlFragment );
	$('comp-wrapper').style.display = 'block';
	$('comp-bar').style.display = 'block';
}


// Check the input data in the new message form, and then
// issue an XHR to send the message.
function comp_send( retto, saveDraft ) {
	if_remoteRequestStart();
	var parameters = "request=sendMessage&";
	if ( saveDraft ) parameters += "draft=save&";
	parameters += $('composer').toQueryString();
	new Ajax( 'ajax.php', {
		postBody: parameters,
		onComplete : function( responseText ) {
			comp_sendCB( responseText, retto );
		},
		onFailure : function( responseText ) {
			if_remoteRequestFailed( responseText );
		}
		} ).request();
}


// Callback for message sending: return to the mailbox listing
// and give the user a feedback message.
function comp_sendCB( responseText, retto ) {
//	$('list-status').setHTML( statusMsg );
//	$('list-status').style.display = 'block';
	var result = if_checkRemoteResult( responseText );
	if (!result) return;

	// Helpful message in result.message... display it?

	if ( result.draftMode ) {
		if ( $('comp-draftuid') ) {
			$('comp-draftuid').value = result.draftUid;
		}
		// TODO: No alert. Really.
		//alert("Draft saved at [insert timestamp here]");
		Flash.flashMessage("Draft saved at [insert timestamp here]");
	} else {
		if (retto) {
			if_returnToMessage();
		} else {
			if_returnToList( false );
		}
		Flash.flashMessage( result.message );
	}
}


function if_returnToList( leavingUID ) {
	list_checkCount(); // Check the list is up to date and reset the timer.
	if ( leavingUID && $('mr-'+leavingUID) ) {
		$('mr-'+leavingUID).removeClass('new');
	}
	if_hideWrappers();
	if_hideToolbars();
	$('list-wrapper').style.display = 'block';
	$('list-bar').style.display = 'block';
	list_show();
}


function if_returnToMessage() {
	if_hideWrappers();
	if_hideToolbars();
	$('msg-wrapper').style.display = 'block';
	$('msg-bar').style.display = 'block';
}


function list_fetchCount() {
	Messages.fetchMailboxList();
}


function list_checkCount() {
	list_fetchCount();
	refreshTimer = setTimeout( list_checkCount, 5 * 60 * 1000 );
}


function list_buildMailboxList( mailboxes ) {

	$('mailboxes').empty();

	var containerContents = "<li id=\"mb-header\"><span class=\"s-head\">Mailboxes</span> [<a href=\"#manage-mailboxes\" onclick=\"MailboxManager.showManager();return false\">edit</a>]</li>";

	for ( var i = 0; i < mailboxes.length; i++ ) {

		containerContents += "<li id=\"mb-" + mailboxes[i].fullboxname;
		if ( listCurrentMailbox == mailboxes[i].mailbox ) {
			containerContents += "\" class=\"mb-active";
		}
		containerContents += "\">";

		// This is a really bad way to indent.
		for ( var j = 0; j < mailboxes[i].folderdepth; j++ ) {
			containerContents += "&nbsp;&nbsp;";
		}

		if ( mailboxes[i].selectable ) {
			containerContents += "<a href=\"#\" onclick=\"return if_selectmailbox('" + mailboxes[i].fullboxname + "')\" class=\"mb-click\">";
		}

		containerContents += "<span class=\"mailbox\">" + mailboxes[i].mailbox + "</strong> ";


		containerContents += "<span id=\"" + mailboxes[i].fullboxname + "_unread\">";
		if (mailboxes[i].unseen > 0 || userSettings['boxlist_showtotal']) {
			containerContents += "(" + mailboxes[i].unseen;
			if ( userSettings['boxlist_showtotal'] ) containerContents += "/" + mailboxes[i].messages;
			containerContents += ")";
		}
		containerContents += "</span>";

		if ( mailboxes[i].selectable ) {
			containerContents += "</a>";
		}
		containerContents += "</li>";
	}

	containerContents += "</ul>";

	$('mailboxes').setHTML( containerContents );
	mailboxCount = mailboxes.length;
}


function list_countCB( mailboxes ) {
	//var result = if_checkRemoteResult( responseText );
	//if (!result) return;

	var msgCounts = mailboxes;
	mailboxCache = mailboxes; // This is a hack to allow list_createPageBar() to have a mailbox list.

	if (mailboxCount != msgCounts.length) {
		// A mailbox has been added/removed.
		// Build the interface list.
		list_buildMailboxList( msgCounts );
		// And stop...
		return;
	}
	var i = 0;

	for (i = 0; i < msgCounts.length; i++) {
		if ( listCurrentMailbox == msgCounts[i].mailbox ) {
			// Do we need to update the list?
			if ( lastUIDconst != "" && lastUIDconst != msgCounts[i].uidConst ) {
				list_show();
			} else if ( msgCount != 0 && msgCount != msgCounts[i].messages ) {
				// TODO: this should be a less intrusive "add new
				// message rows", not a complete refresh.
				list_show();
			}
			lastUIDconst = msgCounts[i].uidConst;
			msgCount = msgCounts[i].messages;

			// Update the highlight in the mailbox list, and the page title
			$('mb-'+listCurrentMailbox).addClass('mb-active');
			document.title = listCurrentMailbox +' ('+msgCounts[i].unseen+' unread, '+msgCounts[i].messages+' total)';
		}

		var countbox = msgCounts[i].fullboxname + "_unread";
		var countresult = "";
		// If non-zero, update the unread messages.
		if ( msgCounts[i].unseen > 0 || userSettings['boxlist_showtotal'] ) {
			countresult = "(" + msgCounts[i].unseen;
			if ( userSettings['boxlist_showtotal'] ) countresult += "/" + msgCounts[i].messages;
			countresult += ")";
		}
		countContainer = $( countbox );
		if ( countContainer ) {
			countContainer.setHTML( countresult );
		} else {
			// Doesn't exist - thus; renamed or new mailbox.
			// Recreate the list.
			list_buildMailboxList( msgCounts );
			// Stop this function.
			return;
		}
	}
}


function list_twiddleFlag( uid, flag, state ) {
	var postbody = "request=setFlag";
	postbody += "&flag=" + encodeURIComponent( flag );
	postbody += "&mailbox=" + listCurrentMailbox;
	postbody += "&uid=" + encodeURIComponent( uid );
	if ( state ) {
		postbody += "&state=" + state;
	}
	new Ajax( 'ajax.php', {
		postBody: postbody,
		onComplete : function ( responseText ) {
			list_twiddleFlagCB( responseText );
		},
		onFailure : function( responseText ) {
			if_remoteRequestFailed( responseText );
		}
		} ).request();
}


function list_twiddleFlagCB( responseText ) {
	var result = if_checkRemoteResult( responseText );
	if (!result) return;

	var uidsAffected = result.uid.split(',');

	if ( result.flag == 'seen' ) {
		for (var i = 0; i < uidsAffected.length; i++ ) {
			var messageRow = $('mr-' + uidsAffected[i]);
			if ( messageRow ) {
				if ( !result.state ) {
					messageRow.addClass('new');
				} else {
					messageRow.removeClass('new');
				}
			}
		}
	}

	if ( result.flag == 'flagged' ) {
		for ( var i = 0; i < uidsAffected.length; i++ ) {
			var flagIcon = $( result.flag + '_' + uidsAffected[i] );
			if ( flagIcon ) {
				if ( result.state ) {
					flagIcon.src = 'themes/' + userSettings.theme + '/icons/flag.png';
				} else {
					flagIcon.src = 'themes/' + userSettings.theme + '/icons/flag_off.png';
				}
			}
		}
	}

	if ( uidsAffected.length > 1 ) {
		Flash.flashMessage( "Updated " + uidsAffected.length + " messages." );
	}

	// Update the flag indicator ...?
	//alert( "Set " + result.uid + " " + result.flag + " to " + result.state );
}

// Change mailboxes: reset tracking variables and draw a new list.
function if_selectmailbox( mailbox ) {
	lastUIDconst = "";
	msgCount = 0;

	// Remove this mailbox's selection highlight in the listing
	$('mb-'+listCurrentMailbox).removeClass('mb-active');

	listCurrentPage = 0;
	listCurrentSearch = "";

	list_show( mailbox, 0 );

	return false;
}

function list_sort( sort ) {
	// Clear the old sort image.
//	var oldImg = $('sort_' + listCurrentSort + '_dir');
//	if ( oldImg ) {
//		oldImg.src = 'themes/' + userSettings.theme + 'icons/blank.png';
//	}

	if ( sort == listCurrentSort ) {
		// Already sorting by this, reverse direction.
		if ( listCurrentSort.substr( listCurrentSort.length - 2, 2 ) == "_r" ) {
			// Already in reverse. Go forward.
			listCurrentSort = sort;
		} else {
			// Going forward. Reverse it.
			listCurrentSort = sort + "_r";
		}
	} else {
		// Not already set, set it - ascending first.
		listCurrentSort = sort;
	}

	// Trigger an update of the list.
	// The list callback will set the correct sort icon.
	list_show();
}

var Messages = new MessagesDatastore( true );

function list_show( mailbox, page ) {
	var sendmailbox = "";
	var sendpage = listCurrentPage;
	var searchQuery = listCurrentSearch;
	var sendSort = listCurrentSort;
	if ( mailbox ) {
		sendmailbox = mailbox;
	} else {
		sendmailbox = listCurrentMailbox;
	}
	if ( page != null ) {
		sendpage = page;
	} else {
		sendpage = listCurrentPage;
	}

	// Fetch the data on those messages.
	Messages.fetchMessageList( sendmailbox, listCurrentSearch, sendpage, sendSort );
}


// Callback function to draw a message listing after
// fetching a JSON string for the mailbox.
function list_showCB( responseText ) {

	//var result = if_checkRemoteResult( responseText );
	//if ( !result ) { return false; }
	result = responseText;

	var messages = result.messages;
	listCurrentMailbox = result.mailbox;
	listCurrentPage = result.thispage.toInt();
	listCurrentSearch = result.search;
	listCurrentSort = result.sort;

	$('list-wrapper').empty();
//	var msgTable = new Element('table', { 'id': 'tablefoo' });
	var tableContents = "";
	var pageSwitchBar = list_createPageBar( result );

	tableContents += "<div class=\"header-bar\"><img src=\"themes/" + userSettings.theme + "/top-corner.png\" alt=\"\" id=\"top-corner\" />" + pageSwitchBar + "</div>";

	if ( result.search != "" ) {
		tableContents += "<div class=\"header-bar\">Search results for \"" + result.search + "\". " +
			"<a href=\"#clearsearch\" onclick=\"doQuickSearch(null, true); return false\">Clear Search</a></div>";
	}

	tableContents += "<table><tr>";
	tableContents += "<th></th>"; // Message checkbox
	tableContents += "<th></th>"; // Toggle flagged button

	tableContents += "<th><a href=\"#sort-from\" onclick=\"list_sort('from');return false\">sender</a></th>";
	tableContents += "<th><a href=\"#sort-subject\" onclick=\"list_sort('subject');return false\">subject</a></th>";
	if ( userSettings.list_showsize ) {
		tableContents += "<th><a href=\"#sort-size\" onclick=\"list_sort('size');return false\">size</a></th>";
	}
	tableContents += "<th><a href=\"#sort-date\" onclick=\"list_sort('date');return false\">Date</a></th>";
	tableContents += "</tr>";

	if ( messages.length == 0 ) {
		tableContents += "<tr><td>No messages in this mailbox.</td></tr>";
	}

	// Hack: use a better loop later, but this avoids scoping problems.
	for ( var i = 0; i < messages.length; i ++ ) {

		var thisMsg = messages[i];
		var uid = thisMsg.uid;
//		var msgRow = createListRow( thisMsg );
		var thisRow = "<tr id=\"mr-"+thisMsg.uid+"\" class=\"";

		if ( i % 2 == 1 ) {
			thisRow += "odd";
		} else {
			thisRow += "even";
		}

		if ( thisMsg.readStatus == 'U' ) {
			thisRow += " new";
		}

		thisRow += "\">";

		thisRow += "<td><input type=\"checkbox\" class=\"msg-select\" name=\"s-" + thisMsg.uid + "\" id=\"s-" + thisMsg.uid + "\" value=\"" + thisMsg.uid + "\" onclick=\"list_messageCheckboxClicked();\" /></td>";

		var flagImage = thisMsg.flagged ? "themes/" + userSettings.theme + "/icons/flag.png" : "themes/" + userSettings.theme + "/icons/flag_off.png";
		thisRow += "<td><a href=\"#\" onclick=\"list_twiddleFlag('" + thisMsg.uid + "', 'flagged', 'toggle');return false\" title=\"Flag this message\">";
		thisRow += "<img src=\"" + flagImage + "\" id=\"flagged_" + thisMsg.uid + "\" /></a></td>";

		if ( thisMsg.fromName == "" ) {
			thisRow += "<td><div class=\"sender\" onclick=\"showMsg('"+listCurrentMailbox+"','"+thisMsg.uid+"',0)\"";
			if ( thisMsg.fromAddr.length > 22 ) {
				// Temporary hack to decide if tooltip is needed
				thisRow += " title=\"" + thisMsg.fromAddr + "\"";
			}
			thisRow += ">" + thisMsg.fromAddr + "</div></td>";
		} else {
			thisRow += "<td><div class=\"sender\" onclick=\"showMsg('"+listCurrentMailbox+"','"+thisMsg.uid+"',0)\" title=\"" + thisMsg.fromAddr + "\">" + thisMsg.fromName + "</div></td>";
		}

		thisRow += "<td><div class=\"subject\" onclick=\"showMsg('"+listCurrentMailbox+"','"+thisMsg.uid+"',0)\"";
		if ( window.khtml ) {
//			thisRow += " style=\"width:" + (window.innerWidth-480) + "px\"";
		}
		if ( window.ie6 ) {
//			thisRow += " style=\"width:" + (document.body.clientWidth-470) + "px\"";
		}
		thisRow += ">" + thisMsg.subject + "<span class=\"messagePreview\">";
		if ( userSettings['list_showpreviews'] ) {
			thisRow += thisMsg.preview;
		}
		thisRow += "</span></div></td>";

		if ( userSettings['list_showsize'] ) {
			thisRow += "<td>" + thisMsg.size + "</td>";
		}

		thisRow += "<td><div class=\"date\">" + thisMsg.dateString + "</div></td>";

		thisRow += "</tr>";

		tableContents += thisRow;

//		msgTable.adopt( msgRow );
//		new Drag.Base(msgRow, {});

	}

	tableContents += "</table>";
	tableContents += "<div class=\"footer-bar\"><img src=\"themes/" + userSettings.theme + "/bottom-corner.png\" alt=\"\" id=\"bottom-corner\" />" + pageSwitchBar + "</div>";

	$('list-wrapper').setHTML( tableContents );

	// Set the appropriate sort icon based on the current sort.
	if ( listCurrentSort.substr( listCurrentSort.length - 2, 2 ) == "_r" ) {
		// Reverse.
		var newImg = $('sort_' + listCurrentSort.substr( 0, listCurrentSort.length - 2 ) + '_dir');
		if ( newImg ) newImg.src = 'themes/' + userSettings.theme + '/icons/sortup.png';
	} else {
		// Forward.
		var newImg = $('sort_' + listCurrentSort + '_dir');
		if ( newImg ) newImg.src = 'themes/' + userSettings.theme + '/icons/sortdown.png';
	}

	// Update the list counts.
	list_fetchCount();
}


// Given a parsed result object for a mailbox message listing, generate a
// string with the text-only toolbar to display above and below the list.
function list_createPageBar( resultObj ) {
	var newPageBar = "";

	var thisPage = resultObj.thispage.toInt() + 1;
	var pageCount = resultObj.numberpages.toInt();

	var lastMsgThisPage = thisPage * resultObj.pagesize.toInt();
	if ( lastMsgThisPage > resultObj.numbermessages.toInt() ) {
		lastMsgThisPage = resultObj.numbermessages.toInt();
	}

	newPageBar += "<div class=\"header-left\">select: <a href='#' onclick='list_selectMessages(\"all\"); return false'>all</a> |";
	newPageBar += "<a href='#' onclick='list_selectMessages(\"none\"); return false'>none</a> | ";
	newPageBar += "<a href='#' onclick='list_selectMessages(\"invert\"); return false'>invert</a>";
	newPageBar += "&nbsp;<select onchange=\"list_withSelected(this)\" style=\"font-size: inherit;\">";
	newPageBar += "<option value=\"noop\">-- with selected --</option>";
	newPageBar += "<option value=\"markseen\">mark as read</option>";
	newPageBar += "<option value=\"markunseen\">mark as unread</option>";
	newPageBar += "<option value=\"flag\">flag messages</option>";
	newPageBar += "<option value=\"unflag\">unflag messages</option>";
	newPageBar += "<option value=\"noop\">-- Move to folder:</option>";

	// TODO: Make this work properly - currently using a cache of mailbox lists, rather than getting a fresh list.
	for ( var i = 0; i < mailboxCache.length; i++ ) {
		newPageBar += "<option value=\"move-" + mailboxCache[i].fullboxname + "\">";
		for ( var j = 0; j < mailboxCache[i].folderdepth; j++ ) {
			newPageBar += "-";
		}
		newPageBar += mailboxCache[i].mailbox;
		newPageBar += "</option>";
	}

	newPageBar += "</select>";
	newPageBar += "</div>";

	newPageBar += "<div class=\"header-right\">";

	if ( thisPage > 2 ) {
		newPageBar += "<a href=\"#\" onclick=\"list_show(listCurrentMailbox, 0); return false\">first</a> | ";
	}
	if ( thisPage > 1 ) {
		newPageBar += "<a href=\"#\" onclick=\"list_show(listCurrentMailbox, " + (thisPage-2) + "); return false\">previous</a> | ";
	}

	// TODO: CSS Hack below to inherit font size.
	newPageBar += "<strong><select onchange=\"list_show(null, this.value);\" style=\"font-size: inherit;\">";
	var pageSize = resultObj.pagesize.toInt();
	var maxMessages = resultObj.numbermessages.toInt();
	var pageCounter = 0;
	for ( var i = 1; i < resultObj.numbermessages.toInt(); i += pageSize ) {
		newPageBar += "<option value=\"" + pageCounter + "\"";
		if ( thisPage == (pageCounter + 1) ) newPageBar += " selected=\"selected\"";
		newPageBar += ">" + i + " to ";
		if ( (pageCounter + 1) * pageSize > maxMessages ) {
			newPageBar += maxMessages;
		} else {
			newPageBar += (pageCounter + 1) * pageSize;
		}
		newPageBar += "</option>";
		pageCounter++;
	}
	newPageBar += "</select>";

	//+ (resultObj.thispage.toInt() * resultObj.pagesize.toInt() + 1) + " to " + lastMsgThisPage
	newPageBar += " of " + resultObj.numbermessages.toInt() + "</strong>";

	if ( pageCount - thisPage > 0 ) {
		newPageBar += " | <a href=\"#\" onclick=\"list_show(listCurrentMailbox, " + thisPage + "); return false\">next</a>";
	}
	if ( pageCount - thisPage > 1 ) {
		newPageBar += " | <a href=\"#\" onclick=\"list_show(listCurrentMailbox, " + (pageCount-1) + "); return false\">last</a>";
	}

	newPageBar += "</div>";

	return newPageBar;
}


function list_getSelectedMessages() {
	var selectedMessages = Array();

	var inputElements = $A( $('list-wrapper').getElementsByTagName('input') );

	for ( var i = 0; i < inputElements.length; i++ ) {
		if ( inputElements[i].checked ) {
			selectedMessages.push( inputElements[i].value );
		}
	}

	return selectedMessages;
}

function list_selectMessages( mode ) {
	var inputElements = $A( $('list-wrapper').getElementsByTagName('input') );

	for ( var i = 0; i < inputElements.length; i++ ) {
		switch ( mode ) {
			case 'all':
				inputElements[i].checked = true;
				break;
			case 'none':
				inputElements[i].checked = false;
				break;
			case 'invert':
				inputElements[i].checked = !inputElements[i].checked;
				break;
		}
	}
}

function list_withSelected( sourceBox, textAction ) {
	var selectedMessages = list_getSelectedMessages();

	var action = "noop";
	if ( !sourceBox && textAction ) {
		action = textAction;
	} else {
		action = sourceBox.value;
	}

	var mailbox = "";
	if ( action.substr( 0, 5 ) == "move-" ) {
		mailbox = action.substr( 5 );
		action = "move";
	}

	switch ( action ) {
		case 'noop':
			// Do nothing! Gracefully!
			break;
		case 'markseen':
			list_twiddleFlag( selectedMessages.join(","), 'seen', 'true' );
			break;
		case 'markunseen':
			list_twiddleFlag( selectedMessages.join(","), 'seen', 'false' );
			break;
		case 'flag':
			list_twiddleFlag( selectedMessages.join(","), 'flagged', 'true' );
			break;
		case 'unflag':
			list_twiddleFlag( selectedMessages.join(","), 'flagged', 'false' );
			break;
		case 'move':
			// TODO: if_moveMessages gets a list of messages to work on...
			// meaning that we do this twice if we call this...
			if_moveMessages( mailbox );
			break;
	}

	if ( sourceBox ) {
		sourceBox.selectedIndex = 0;
	}
}


// onclick handler for the checkbox next to "sender" in a message list
// Currently does nothing but will later highlight active rows, etc.
function list_messageCheckboxClicked() {
	return false;
}

function if_moveMessages( target ) {
	var selectedMessages = list_getSelectedMessages();
	var selectedCount = selectedMessages.length;
	selectedMessages = selectedMessages.join(",");

	if ( target == 'LICHENDELETE' ) {
		// Confirm that the user really wants to do that.
		// TODO: Config option to not to prompt.
		// TODO: Does strange stuff to the drop, because it's not finished yet.
		if ( !confirm( "Are you sure you want to delete " + selectedCount + " messages? This is permanent." ) ) return;
	}

	new Ajax( 'ajax.php', {
		postBody: 'request=moveMessage&mailbox=' + encodeURIComponent(listCurrentMailbox) +
			'&destbox=' + encodeURIComponent(target) +
			'&uid=' + encodeURIComponent(selectedMessages),
		onComplete : function( responseText ) {
			if_moveMessagesCB( responseText );
		},
		onFailure : function( responseText ) {
			if_remoteRequestFailed( responseText );
		}
		} ).request();
}


function if_moveMessagesCB( responseText ) {
	var result = if_checkRemoteResult( responseText );
	if (!result) return;

	Flash.flashMessage( result.message );

	// Update the lists...
	list_show();
}


// Takes a message data object (as produced by a JSON call) and returns
// a DOM table-row element with all of its details.
// Deprecated in favour of (faster) innerHTML fiddling.
/* function createListRow( msgData ) {
	var thisRow = new Element('div', { 'id': 'mr-'+msgData.uid, 'class': 'msg-row' } );

	var fromDiv = new Element('div', {
		'class': 'sender',
		'events': { 'click': showMsg.bind( listCurrentMailbox, msgData.uid, 0 ) }
		} );
	if ( msgData.fromName == "" ) {
		fromDiv.appendText( msgData.fromAddr );
		if ( msgData.fromAddr.length > 22 ) {
			// Crude, but we can't measure width before adopting the element.
			fromDiv.setProperty( 'title', msgData.fromAddr );
		}
	} else {
		fromDiv.appendText( msgData.fromName );
		if ( msgData.fromName.length > 22 ) {
			fromDiv.setProperty( 'title', msgData.fromName+" - "+msgData.fromAddr );
		} else {
			fromDiv.setProperty( 'title', msgData.fromAddr );
		}
	}
	thisRow.adopt( fromDiv );

	var subjDiv = new Element('div', {
		'class': 'subject',
		'events': { 'click': showMsg.bind( listCurrentMailbox, msgData.uid, 0 ) }
		} );
	subjDiv.appendText( msgData.subject );
	var preview = new Element('span', { 'class': 'messagePreview' } );
	preview.appendText( msgData.preview );
	subjDiv.adopt( preview );
	thisRow.adopt( subjDiv );
	if ( window.khtml ) {
//		subjTD.style.width = (window.getWidth-411)+'px';
	}

	var dateDiv = new Element('div', { 'class': 'date' } );
	dateDiv.appendText( msgData.dateString );
	thisRow.adopt( dateDiv );

	return thisRow;
} */


// Replaces the contents of the message list with the results
// for an IMAP "TEXT" search using the current mailbox.
function doQuickSearch( mode, clearSearch ) {
	if ( clearSearch ) {
		$('qsearch').value = "";
	}
	Messages.fetchMessageList( listCurrentMailbox, $('qsearch').value, 0, '' );
	return false;
}


// Should be able to use mootools' Function.bind() instead
// http://www.ejball.com/EdAtWork/PermaLink.aspx?guid=14c020c7-20a6-47d7-b445-9c3ecda153b2
/*function bindArgs(fn) {
	var args = [];
	for (var n = 1; n < arguments.length; n++)
		args.push(arguments[n]);
	return function () { return fn.apply(this, args); };
}*/


// AJAX File upload using a iframe.
// Based on an example at http://www.webtoolkit.info/ajax-file-upload.html
function asyncUploadFile( sourceForm ) {
	// Create a hidden iframe to do the work.
	var iframeName = 'asyncUpload' + Math.floor(Math.random() * 99999);

	var hiddenDiv = new Element('div');
	$(hiddenDiv).setHTML("<iframe style='display: none;' src='about:blank' id='" + iframeName +
		"' name='" + iframeName +
		"' onload='asyncUploadCompleted(\"" + iframeName +  "\")'></iframe>");

	$('comp-wrapper').adopt( hiddenDiv );

	// Now retarget the form to this new iFrame.
	$(sourceForm).setProperty( 'target', iframeName );

	if_remoteRequestStart();

	// Force the form to upload.
	return true;
}


function asyncUploadCompleted( frameName ) {
	var hiddenIframe = $(frameName);

	// It seems IE calls this function twice, the second
	// time the iframe doesn't exist anymore, and thus it bombs.
	// So short circuit this
	if ( hiddenIframe == null ) return;

	if ( hiddenIframe.contentWindow != null ) {
		hiddenIframe = hiddenIframe.contentWindow.document;
	} else if ( hiddenIframe.contentDocument != null ) {
		hiddenIframe = hiddenIframe.contentDocument;
	} else {
		hiddenIframe = window.frames[frameName].document;
	}

	if (hiddenIframe.location.href == "about:blank") return;

	// Workaround for Firefox: point the iframe at a harmless URI
	// so it doesn't seem like it's still loading.
	hiddenIframe.location.href = "about:blank";

	// Parse the result.
	var result = if_checkRemoteResult( hiddenIframe.body.innerHTML );
	if (!result) return;

	// Destroy the iframe (by removing its parent DIV)
	// and update our list of attachments.
	$(frameName).getParent().remove();

	var displayAttach = new Element('li');
	displayAttach.setHTML( result.filename + " (" + result.type + ", " + result.size + " bytes)" +
		" (<a href=\"#\" onclick=\"comp_removeAttachment('" + escape(result.filename) + "');return false\">remove</a>)" );
	$('comp-attachlist').adopt( displayAttach );

	var uploadedFile = new Element('input');
	uploadedFile.type = 'hidden';
	uploadedFile.name = 'comp-attach[]';
	uploadedFile.value = result.filename;
	$('composer').adopt( uploadedFile );

	$('comp-attachfile').value = "";
}

function comp_removeAttachment( filename ) {
	// Remove an attachment from the composer.
	var attachmentFilenames = $A( $('composer').getElementsByTagName('input') );
	var attachmentListElements = $A( $('comp-attachlist').getElementsByTagName('li') );

	var foundFilename = false;

	// We encoded filename in the removal link, so undo that now.
	filename = unescape( filename );

	for ( var i = 0; i < attachmentFilenames.length; i++ ) {
		var thisfile = attachmentFilenames[i];

		if ( thisfile.type == "hidden" && thisfile.value == filename ) {
			// This is the hidden form element for this file.
			// Destroy it.
			foundFilename = true;
			thisfile.remove();
		}
	}


	for ( var i = 0; i < attachmentListElements.length; i++ ) {
		var thisfile = attachmentListElements[i];

		if ( thisfile.getText().contains( filename ) ) {
			// TODO: This is not foolproof.
			thisfile.remove();
		}
	}

	// Ask the server to remove the file.
	if ( foundFilename ) {
		new Ajax( 'ajax.php', {
			postBody: 'request=removeAttachment&filename='+encodeURIComponent(filename),
			onComplete : function ( responseText ) {
				comp_attachmentDeleted( responseText );
			},
			onFailure : function( responseText ) {
				if_remoteRequestFailed( responseText );
			}
			} ).request();
	}
}

// This is a stub - so errors get reported, but that's it.
function comp_attachmentDeleted( responseText ) {
	var result = if_checkRemoteResult( responseText );
}
