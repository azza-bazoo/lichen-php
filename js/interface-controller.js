/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
js/interface-controller.js - The overclass that looks after all the interface components.
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
		// Available modes: "list", "compose", "display", "options"
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
	},

	checkCount: function () {
		this.Messages.fetchMailboxList();
		this.clearTimer();
		this.setTimer();
	},

	setTimer: function() {
		if ( !this.refreshTimer ) {
			this.refreshTimer = setTimeout( this.checkCount.bind( this ), 5 * 60 * 1000 );
		}
	},
	clearTimer: function() {
		if ( this.refreshTimer ) {
			clearTimeout( this.refreshTimer );
			this.refreshTimer = null;
		}
	},

	action: function ( mode, controller, action, data ) {
		if ( mode && this.mode == 'compose' && mode != this.mode ) {
			// A hack, clean up the composer if we're exiting the composer.
			this.MessageCompose.cleanupComposer();
		}
		if ( mode && mode != this.mode ) {
			// Changing mode.
			// TODO: Fade effect thingy.
			this.clearScreen();
			this.enterMode( mode );
		}
		if ( mode && mode != "list" ) {
			// Disable the list time checker.
			this.clearTimer();
		} else if ( mode == "list" ) {
			// Enable the list time checker.
			this.setTimer();
		}

		// If a fade was in effect, finish it - action gets called on the callbacks from the
		// server.

		// Call the appropriate action.
		// This looks rather grubby.
		if ( this[controller] && this[controller][action] ) {
			this[controller][action].apply(this[controller], data);
		}

		// TODO: Make this "true" to force the href to visit the #href link.
		return false;
	},

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
		
		$('list-wrapper').empty();
		$('msg-wrapper').empty();
		$('opts-wrapper').empty();
		$('comp-wrapper').empty();
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
				default:
					// Righto...
					this.enterMode("list");
					break;
			}
		}
	}
});

var Lichen = new InterfaceController();

