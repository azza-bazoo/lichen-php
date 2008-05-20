/**

Lichen - AJAX IMAP client
version 0.4 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
js/interface-controller.js - interface master control class
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

var InterfaceController = new Class({
	initialize: function () {
		// Available modes: "list", "compose", "display", "options", "abook"
		this.mode = "list";

		// Instantiate new objects for display and storage
		this.Messages         = new MessagesDatastore( true );
		this.OptionsEditor    = new OptionsEditorClass( 'opts-wrapper' );
		this.MailboxManager   = new MailboxManagerClass( this.Messages );
		this.MessageDisplayer = new MessageDisplay( this.Messages, 'msg-wrapper' );
		this.Flash            = new FlashArea( 'notification' );
		this.MessageList      = new MessageLister( this.Messages, 'list-wrapper' );
		this.MailboxList      = new MailboxLister( 'mailboxes' );
		this.MessageCompose   = new MessageComposer( 'comp-wrapper' );
		this.AddressBook      = new AddressBookManager( this.Messages, 'addr-wrapper' );

		this.ActionCounter    = 1;
		this.historyTrail     = Array( Array( 'list', 'MessageList', 'listUpdate' ) );
		this.historyChecker   = null;
		this.lastHash         = "";
		this.modeFeedback     = null;

		this.busyCounter      = 0;
		this.busyDiv          = "loading-box";
		this.busyIFrame       = new Element('iframe');
		this.busyIFrame.setStyle( 'display', 'none' );
		this.loadInit         = false;
	},

	onLoadInit: function () {
		// Load mailbox list
		// TODO: the message list display steps (started by list_show() below)
		// rely on this to have completed to get a mailbox listing for the
		// "move message to" dropdown.
		this.checkCount(); // This also sets up the timeout.

		// Load default mailbox (inbox)
		this.MessageList.listUpdate();

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

		// Kick off the AJAX-back-button thingy.
		//this.checkUrl();
	},

	checkCount: function () {
		this.Messages.fetchMailboxList();
		this.clearTimer();
		this.setTimer();
	},

	setTimer: function() {
		if ( !this.refreshTimer && userSettings['list_refreshtime'] != 'disabled' ) {
			this.refreshTimer = setTimeout( this.checkCount.bind( this ), userSettings['list_refreshtime'] * 60 * 1000 );
		}
	},
	clearTimer: function() {
		if ( this.refreshTimer ) {
			clearTimeout( this.refreshTimer );
			this.refreshTimer = null;
		}
	},

	action: function ( mode, controller, action, data, noRecordHistory ) {
		if ( mode && this.mode == 'compose' && mode != this.mode ) {
			// A hack, clean up the composer if we're exiting the composer.
			this.MessageCompose.cleanupComposer();
		}
		if ( mode && mode != this.mode && this.modeFeedback == null ) {
			// Ok, fade out the current interface pane.
			this.modeFeedback = new PaneTransition( this.getWrapperName( this.mode ) );
			this.busy();
		} else if ( mode && mode != this.mode && this.modeFeedback != null ) {
			// Stop the current transition and enter a mode.
			this.modeFeedback.end();
			this.modeFeedback = null;

			// TODO: Fade effect thingy.
			this.clearScreen();
			this.enterMode( mode );
			this.notbusy();
		}
		if ( mode && mode != "list" ) {
			// Disable the list time checker.
			this.clearTimer();
		} else if ( mode == "list" ) {
			// Enable the list time checker.
			this.setTimer();
		}

		//console.log( "%s / %s / %s", mode, controller, action );

		// If a fade was in effect, finish it - action gets called on the callbacks from the
		// server.

/* -- this code not ready for use yet! --
		// Record the history of what the hell we just did.
		// Ignore Callbacks; we don't care about them!
		// Oh, and don't do anything in compose mode - it currently causes duplicates.
		if ( !noRecordHistory && action.indexOf( 'CB' ) == -1 && mode != "compose" ) {
			// Create a new hashpoint.
			if ( window.location && window.location.href ) {
				var hashIndex = window.location.href.indexOf( '#' );
				var currentUrl = window.location.href;
				if ( hashIndex != -1 ) {
					currentUrl = currentUrl.substr( 0, hashIndex );
				}
				currentUrl += "#" + this.ActionCounter;
				window.location = currentUrl;
				this.ActionCounter++;
			}

			// Take a note of what this action was.
			this.historyTrail.push( Array( mode, controller, action, data ) );
		}
*/

		// Call the appropriate action.
		// This looks rather grubby.
		if ( this[controller] && this[controller][action] ) {
			if ( data ) {
				this[controller][action].apply(this[controller], data);
			} else {
				this[controller][action].apply(this[controller], []);
			}
		}
		
		// Ask the controller for this action to set the title.
		if ( this[controller] && this[controller]['getWindowTitle'] ) {
			document.title = this[controller].getWindowTitle();
		}

		// TODO: Make this "true" to force the href to visit the #href link.
		return false;
	},

/* -- also not ready for use yet! --
	checkUrl: function () {
		//console.log( 'Checking: "%s" "%s"', window.location.hash, this.lastHash );
		//if ( window.location && window.location.hash ) {
			if ( window.location.hash != this.lastHash ) {
				// Location has changed. Replay the action at that time.
				var actionIndex = "";
				if ( window.location.hash == "" ) {
					actionIndex = 0;
				} else {
					actionIndex = window.location.hash.substr( 1 );
					actionIndex = actionIndex.toInt();
				}
				//console.log( "%d is %s", actionIndex, this.historyTrail[actionIndex] );

				this.lastHash = window.location.hash;

				if ( this.historyTrail[actionIndex] ) {
					//console.log('History replay:');
					this.action( this.historyTrail[actionIndex][0], this.historyTrail[actionIndex][1],
						this.historyTrail[actionIndex][2], this.historyTrail[actionIndex][3], true );
				}

				//this.historyTrail.pop();
			}
		//}

		this.historyChecker = setTimeout( this.checkUrl.bind( this ), 200 ); // Check every 200ms.
	},
*/

	clearScreen: function () {
		// Clear all the interface elements, ready to enter another mode.
		$('list-bar').setStyle( 'display', 'none' );
		$('comp-bar').setStyle( 'display', 'none' );
		$('msg-bar').setStyle( 'display', 'none' );
		$('opts-bar').setStyle( 'display', 'none');

		$('list-wrapper').setStyle( 'display', 'none' );
		$('msg-wrapper').setStyle( 'display', 'none' );
		$('opts-wrapper').setStyle( 'display', 'none' );
		$('comp-wrapper').setStyle( 'display', 'none' );
		$('addr-wrapper').setStyle( 'display', 'none' );

		$('list-wrapper').empty();
		$('msg-wrapper').empty();
		$('opts-wrapper').empty();
		$('comp-wrapper').empty();
		$('addr-wrapper').setStyle( 'display', 'none' );
	},
	
	getWrapperName: function ( mode ) {
		var wrapper = 'list-wrapper';
		switch ( mode ) {
			case 'list':
				wrapper = 'list-wrapper';
				break;
			case 'compose':
				wrapper = 'comp-wrapper';
				break;
			case 'display':
				wrapper = 'msg-wrapper';
				break;
			case 'options':
				wrapper = 'opts-wrapper';
				break;
			case 'abook':
				wrapper = 'addr-wrapper';
				break;
		}

		return wrapper;
	},

	enterMode: function ( mode ) {
		if ( mode != this.mode ) {
			this.mode = mode;
			switch ( mode ) {
				case "list":
					$('list-bar').setStyle( 'display', 'block' );
					$('list-wrapper').setStyle( 'display', 'block' );
					break;
				case "compose":
					$('comp-bar').setStyle( 'display', 'block' );
					$('comp-wrapper').setStyle( 'display', 'block' );
					break;
				case "display":
					$('msg-bar').setStyle( 'display', 'block' );
					$('msg-wrapper').setStyle( 'display', 'block' );
					break;
				case "options":
					$('opts-bar').setStyle( 'display', 'block' );
					$('opts-wrapper').setStyle( 'display', 'block' );
					break;
				case "abook":
					// TODO: No toolbar at the moment?
					$('addr-wrapper').setStyle( 'display', 'block' );
					break;
				default:
					// Righto...
					this.enterMode("list");
					break;
			}
		}
	},

	getMode: function () {
		return this.mode;
	},

	busy: function () {
		if ( !this.loadInit ) {
			$( this.busyDiv ).adopt( this.busyIFrame );
			this.loadInit = true;
		}

		this.busyCounter += 1;

		if ( this.busyCounter > 0 ) {
			//$(this.busyDiv).setStyle('display', 'block');
			this.busyIFrame.src = 'wait.php';
		}
	},

	notbusy: function () {
		if ( this.busyCounter > 0 ) {
			this.busyCounter -= 1;
		}

		if ( this.busyCounter < 1 ) {
			//$(this.busyDiv).setStyle('display', 'none');
			this.busyIFrame.src = 'about:blank';
		}
	}
});

var Lichen = new InterfaceController();

