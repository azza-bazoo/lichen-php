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
		this.Messages = new MessagesDatastore( true );
		this.OptionsEditor = new OptionsEditorClass( 'opts-wrapper' );
		this.MailboxManager = new MailboxManagerClass();
		this.MessageDisplayer = new MessageDisplay( 'msg-wrapper' );
		this.Flash = new FlashArea( 'notification' );
		this.MessageList = new MessageLister( 'list-wrapper' );
	
		// Load mailbox list
		// TODO: the message list display steps (started by list_show() below)
		// rely on this to have completed to get a mailbox listing for the
		// "move message to" dropdown.
		this.Messages.fetchMailboxList();
		this.refreshTimer = setTimeout( this.checkCount.bind( this ), 5 * 60 * 1000 );

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
		this.refreshTimer = setTimeout( this.checkCount.bind( this ), 5 * 60 * 1000 );
	},

	action: function ( mode, action, data ) {
		if ( mode != this.mode ) {
			// Changing mode.
			// TODO: Fade effect thingy.
			this.clearScreen();
			this.enterMode( mode );
		}
		// Pass off the action somehow.
	},

	actionReady: function () {
		// This function is called to tell us that the callbacks
		// for the action (as kicked off by action() above) are done
		// and we can stop any fade effect or anything like that.
	},

	clearScreen: function () {
		// Clear all the interface elements, ready to enter another mode.
		$('list-bar').setStyle( 'display', 'none' );
		$('comp-bar').setStyle( 'display', 'none' );
		$('msg-bar').setStyle( 'dipslay', 'none' );
		$('opts-bar').setStyle( 'display', 'none');

		$('list-wrapper').setStyle( 'display', 'none' );
		$('msg-wrapper').setStyle( 'display', 'none' );
		$('opts-wrapper').setStyle( 'display', 'none' );
		$('comp-wrapper').setStyle( 'display', 'none' );
	},

	enterMode: function ( mode ) {
		if ( mode != this.mode ) {
			this.mode = mode;
			switch ( mode ) {
				case "list";
					$('list-bar').setStyle( 'display', 'block' );
					$('list-wrapper').setStyle( 'display', 'block' );
					break;
				case "compose";
					$('comp-bar').setStyle( 'display', 'block' );
					$('comp-wrapper').setStyle( 'display', 'block' );
					break;
				case "display";
					$('msg-bar').setStyle( 'display', 'block' );
					$('msg-wrapper').setStyle( 'display', 'block' );
					break;
				case "options";
					$('opts-bar').setStyle( 'display', 'block' );
					$('opts-wrapper').setStyle( 'display', 'block' );
					break;
				case "default";
					// Righto...
					this.enterMode("list");
					break;
			}
		}
	}
});
