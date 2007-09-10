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

var activeFadeEffect = false;
var userSettings;

var Lichen;

tinyMCE.init({mode: 'none', theme: 'advanced'});

// Interface initialisation, set mailbox autorefresh
function if_init() {
	Lichen.onLoadInit();
}

// To support translations in JavaScript.
function _( str ) {
	return str;
}
