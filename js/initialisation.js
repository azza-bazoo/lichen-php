/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
js/initialisation.js - functions for page startup
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


window.onload = if_init;

var msgCount = 0;
//var lastUIDconst = "";
//var lastShownUID = "";
//var listCurrentMailbox = 'INBOX';
//var listCurrentPage = 0;
//var listCurrentSearch = '';
//var listCurrentSort = userSettings['list_sortmode'];
//var mailboxCount = 0;
var activeFadeEffect = false;
var refreshTimer;
var userSettings;

var mailboxCache = Array();	// a hack

var MessageDisplayer;		// also a hack
var Flash;
var MailboxManager;
var OptionsEditor;
var Messages;
var MessageList;


// Interface initialisation, set mailbox autorefresh
function if_init() {
	//opts_get();

	// Instantiate new objects for display and storage
	Messages = new MessagesDatastore( true );
	OptionsEditor = new OptionsEditorClass('opts-wrapper');
	MailboxManager = new MailboxManagerClass();
	MessageDisplayer = new MessageDisplay( 'msg-wrapper' );
	Flash = new FlashArea('notification');

	// Load mailbox list
	// TODO: the message list display steps (started by list_show() below)
	// rely on this to have completed to get a mailbox listing for the
	// "move message to" dropdown.
	Messages.fetchMailboxList();
	refreshTimer = setTimeout( list_checkCount, 5 * 60 * 1000 );

	// Load default mailbox (inbox)
	MessageList.listUpdate();

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
}
