<?php
/**

Lichen - AJAX IMAP client
version 0.3 by Hourann Bosci and Daniel Foote
http://lichen-mail.org/

--------------------------------------------------------------------------------
include/htmlrender.php - HTML renderer for the HTML version of Lichen
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

/* ------------------------------------------------------------------------
 * Notes on how these work.
 *
 * - Function names start with render_
 * - Each function takes two parameters: $reqResults, which is the results
 *   of whatever command was executed, and $reqPars, which is the request
 *   parameters that can be used to rebuild a get-style URL.
 *
 */

function helper_calculateSortDirection( &$data ) {
	// Precalculation: figure out the sort direction and the raw sort.
	if ( strpos( $data['sort'], "_r" ) !== false ) {
		$data['sortAsc'] = false;
		$data['sort'] = substr( $data['sort'], 0, -2 );
	} else {
		$data['sortAsc'] = true;
	}
}

// ------------------------------------------------------------------------
//
// Render a message list.
//
function render_messageList( $requestData, $requestParams ) {
	global $USER_SETTINGS;

	helper_calculateSortDirection( $requestData );

	// Capture the output - faster than strcatting.
	ob_start();

	echo htmlList_createPageBar( $requestData, $requestParams, true );

	if ( $requestData['search'] != "" ) {
		echo "<div class=\"list-notification\"><strong>Search results for &#8220;",
			$requestData['search'], "&#8221;</strong> ",
			"[<a href=\"#clearsearch\" onclick=\"return Lichen.action('list','MessageList','setSearch',[''])\">clear search</a>]</div>";
	}

	echo "<table id=\"list-data-tbl\">";

	// To work around imperfect CSS layout implementations, we manually
	// calculate the width of the subject column.
	// TODO: respond to the window being resized
	// TODO: This works in JS, but not in raw HTML!
	//var subjColWidth = window.getWidth() - 515;
	//if ( userSettings.list_showsize ) {
	//	subjColWidth = window.getWidth() - 590;
	//}
	$subjColWidth = 400; // TODO: This is a hack!

	echo "<colgroup><col class=\"mcol-checkbox\" /><col class=\"mcol-flag\" /><col class=\"mcol-sender\" /><col class=\"mcol-subject\" style=\"width:", $subjColWidth, "px\" />";
	if ( $USER_SETTINGS['list_showsize'] ) {
		echo "<col class=\"mcol-size\" />";
	}
	echo "<col class=\"mcol-date\" /></colgroup>";

	echo "<thead><tr class=\"list-sortrow\">";
	
	// Set an icon to indicate the current sort.
	$sortImg = "<img class=\"list-sort-marker\" src=\"themes/" . $USER_SETTINGS['theme'];
	if ( !$requestData['sortAsc'] ) {
		$sortImg .= "/icons/sort_decrease.png\" />";
	} else {
		$sortImg .= "/icons/sort_incr.png\" />";
	}

	// TODO: maybe select all/none instead of invert
	echo "<th><input type=\"checkbox\" id=\"list-check-main\" value=\"0\" onclick=\"Lichen.MessageList.selectMessages(0)\" /></th><th></th>";

	function getNextSort( $sort, $sortAsc, $sortCol ) {
		$result = $sortCol;
		if ( $sort == $sortCol && $sortAsc ) {
			$result .= "_r";
		}
		return $result;
	}

	echo "<th class=\"list-sortlabel\"><a href=\"ajax.php?",
		genLinkQuery( $requestParams, array( 'sort' => getNextSort( $requestData['sort'], $requestData['sortAsc'], "from" ) ) ),
		"\" id=\"list-sort-from\">sender</a>", ($requestData['sort'] == "from" ? $sortImg : "" ), "</th>";
	echo "<th class=\"list-sortlabel\"><a href=\"ajax.php?",
		genLinkQuery( $requestParams, array( 'sort' => getNextSort( $requestData['sort'], $requestData['sortAsc'], "subject" ) ) ),
		"\" id=\"list-sort-subject\">subject</a>", ($requestData['sort'] == "subject" ? $sortImg : "" ), "</th>";
	if ( $USER_SETTINGS['list_showsize'] ) {
		echo "<th class=\"list-sortlabel\"><a href=\"ajax.php?",
			genLinkQuery( $requestParams, array( 'sort' => getNextSort( $requestData['sort'], $requestData['sortAsc'], "size" ) ) ),
			"\" id=\"list-sort-size\">size</a>", ($requestData['sort'] == "size" ? $sortImg : "" ), "</th>";
	}
	echo "<th class=\"list-sortlabel\"><a href=\"ajax.php?",
		genLinkQuery( $requestParams, array( 'sort' => getNextSort( $requestData['sort'], $requestData['sortAsc'], "date" ) ) ),
		"\" id=\"list-sort-date\">date</a>", ($requestData['sort'] == "date" ? $sortImg : "" ), "</th>";
	echo "</tr></thead><tbody>";

	$messages = $requestData['messages'];
	if ( count( $messages ) == 0 ) {
		echo "<tr><td colspan=\"5\" class=\"list-nothing\">No messages in this mailbox.</td></tr>";
	}

	// Hack: use a better loop later, but this avoids scoping problems.
	$i = 0;
	foreach ( $messages as $thisMsg ) {
		$uid = $thisMsg['uid'];

		$thisRow = "<tr id=\"mr-".htmlentities($uid)."\" class=\"";

		if ( $i % 2 == 1 ) {
			$thisRow .= "odd";
		} else {
			$thisRow .= "even";
		}

		if ( $thisMsg['readStatus'] == 'U' ) {
			$thisRow .= " new";
		}

		$thisRow .= "\">";

		$thisRow .= "<td><input type=\"checkbox\" class=\"msg-select\" name=\"s-" . $thisMsg['uid'] . "\" id=\"s-" . $thisMsg['uid'] . "\" value=\"" . $thisMsg['uid'] . "\" onclick=\"Lichen.MessageList.messageCheckboxClicked();\" /></td>";

		$flagImage = $thisMsg['flagged'] ? "/icons/flag.png" : "/icons/flag_off.png";
		$thisRow .= "<td><img src=\"themes/" . $USER_SETTINGS['theme'] . $flagImage . "\" id=\"f-" . $thisMsg['uid'] . "\" alt=\"\" onclick=\"Lichen.MessageList.twiddleFlag('" . $thisMsg['uid'] . "', 'flagged', 'toggle')\" title=\"Flag this message\" class=\"list-flag\" /></td>";

		$displayUrl = "ajax.php?" . genLinkQuery( $requestParams, array( 'msg' => $thisMsg['uid'], 'reqmode' => 'disp', 'request' => 'getMessage' ) );

		$thisRow .= "<td class=\"sender\" ";
		if ( $thisMsg['fromName'] == "" ) {
			if ( strlen( $thisMsg['fromAddr'] ) > 22 ) {
				// Temporary hack to decide if tooltip is needed
				$thisRow .= " title=\"" . $thisMsg['fromAddr'] . "\"";
			}
			$thisRow .= "><div class=\"sender\"><a href=\"{$displayUrl}\">" . $thisMsg['fromAddr'] . "</a>";
		} else {
			$thisRow .= " title=\"" . $thisMsg['fromAddr'] . "\"><div class=\"sender\"><a href=\"{$displayUrl}\">" . $thisMsg['fromName'] . "</a>";
		}
		$thisRow .= "</div></td>";

		$thisRow .= "<td class=\"subject\"><div class=\"subject\"><a href=\"{$displayUrl}\">" . $thisMsg['subject'] . "</a>";

		if ( $USER_SETTINGS['list_showpreviews'] ) {
			$thisRow .= "<span class=\"messagePreview\">" . $thisMsg['preview'] . "</span>";
		}

		$thisRow .= "</div></td>";

		if ( $USER_SETTINGS['list_showsize'] ) {
			$thisRow .= "<td class=\"size\"><div class=\"size\">" . $thisMsg['size'] . "</div></td>";
		}

		$thisRow .= "<td class=\"date\"><div class=\"date\">" . $thisMsg['dateString'] . "</div></td>";

		$thisRow .= "</tr>";

		echo $thisRow;

	}

	echo "</tbody></table>";
	echo htmlList_createPageBar( $requestData, $requestParams, false );

	return ob_get_clean();
}

// Given a parsed result object for a mailbox message listing, generate a
// string with the text-only toolbar to display above and below the list.
function htmlList_createPageBar( $requestData, $requestParams, $isTopBar ) {
	global $USER_SETTINGS;

	$newPageBar = "";

	if ( $isTopBar ) {
		$newPageBar .= "<div class=\"list-header-bar\"><img src=\"themes/" . $USER_SETTINGS['theme'] . "/top-corner.png\" alt=\"\" class=\"top-corner\" />";
	} else {
		$newPageBar .= "<div class=\"list-footer-bar\"><img src=\"themes/" . $USER_SETTINGS['theme'] . "/bottom-corner.png\" alt=\"\" class=\"bottom-corner\" />";
	}

	$thisPage  = $requestData['thispage'] + 1; // $requestData['thispage'] starts at zero.
	$pageCount = $requestData['numberpages'];

	$lastMsgThisPage = $thisPage * $requestData['pagesize'];
	if ( $lastMsgThisPage > $requestData['numbermessages'] ) {
		$lastMsgThisPage = $requestData['numbermessages'];
	}

	$newPageBar .= "<div class=\"header-left\">";
	$newPageBar .= "<select onchange=\"Lichen.MessageList.withSelected(this)\">";
	$newPageBar .= "<option value=\"noop\" selected=\"selected\">move selected to ...</option>";

	// Build a list of mailboxes.
	foreach ( $requestData['mailboxList'] as $mailbox ) {
		$newPageBar .= "<option value=\"move-" . htmlentities( $mailbox['fullboxname'] ) . "\">";
		$newPageBar .= str_repeat( "-", $mailbox['folderdepth'] );
		$newPageBar .= $mailbox['mailbox'];
		$newPageBar .= "</option>";
	}

	$newPageBar .= "</select>";
	$newPageBar .= " &nbsp; <input type=\"button\" onclick=\"Lichen.MessageList.deleteMessages();return false\" value=\"delete\" />";
	$newPageBar .= " &nbsp; <input type=\"button\" onclick=\"Lichen.MessageList.withSelected(null, 'flag');return false\" value=\"flag\" />";
	$newPageBar .= " &nbsp; <input type=\"button\" onclick=\"Lichen.MessageList.withSelected(null, 'markseen');return false\" value=\"mark read\" /><br />";
	if ( !$isTopBar ) {
		$newPageBar .= "select: <a href=\"#\" onclick=\"Lichen.MessageList.selectMessages(1);return false\">all</a> | ";
		$newPageBar .= "<a href=\"#\" onclick=\"Lichen.MessageList.selectMessages(2);return false\">none</a> | ";
		$newPageBar .= "<a href=\"#\" onclick=\"Lichen.MessageList.selectMessages(3);return false\">read</a> | ";
		$newPageBar .= "<a href=\"#\" onclick=\"Lichen.MessageList.selectMessages(4);return false\">unread</a> | ";
		$newPageBar .= "<a href=\"#\" onclick=\"Lichen.MessageList.selectMessages(5);return false\">flagged</a> | ";
		$newPageBar .= "<a href=\"#\" onclick=\"Lichen.MessageList.selectMessages(6);return false\">unflagged</a> | ";
		$newPageBar .= "<a href=\"#\" onclick=\"Lichen.MessageList.selectMessages(0);return false\">invert</a>";
	}

	$newPageBar .= "</div><div class=\"header-right\">";

	if ( $requestData['numberpages'] > 1 ) {
		$lowerPageLabel = "previous";
		$upperPageLabel = "next";


		if ( $requestData['sort'] == "date" ) {
			if ( $requestData['sortAsc'] ) {
				$lowerPageLabel = "earlier";
				$upperPageLabel = "later";
			} else {
				$lowerPageLabel = "later";
				$upperPageLabel = "earlier";
			}
		}

	// 	if ( thisPage > 2 ) {
	// 		newPageBar += "<a href=\"#\" onclick=\"MessageList.firstPage(); return false\">first</a> | ";
	// 	}
		if ( $thisPage > 1 ) {
			$newPageBar .= "<a href=\"ajax.php?" . genLinkQuery( $requestParams, array( 'page' => $requestData['thispage'] - 1 ) ) . "\">" . $lowerPageLabel . "</a> | ";
		}

		$newPageBar .= genLinkForm( $requestParams, array(), array( "page" ), "pageChanger", "ajax.php" );

		$newPageBar .= "<select id=\"page\" name=\"page\">";
		$pageSize = $requestData['pagesize'];
		$maxMessages = $requestData['numbermessages'];
		$pageCounter = 0;
		for ( $i = 1; $i <= $requestData['numbermessages']; $i += $pageSize ) {
			$newPageBar .= "<option value=\"" . $pageCounter . "\"";
			if ( $thisPage == ($pageCounter + 1) ) $newPageBar .= " selected=\"selected\"";
			$newPageBar .= ">" . $i . " to ";
			if ( ($pageCounter + 1) * $pageSize > $maxMessages ) {
				$newPageBar .= $maxMessages;
			} else {
				$newPageBar .= ($pageCounter + 1) * $pageSize;
			}
			$newPageBar .= "</option>";
			$pageCounter++;
		}
		$newPageBar .= "</select>";
		$newPageBar .= "<input type=\"submit\" value=\"Go\" />";
		$newPageBar .= "</form>";

		// (resultObj.thispage * resultObj.pagesize + 1) + " to " + lastMsgThisPage
		$newPageBar .= " of " . $requestData['numbermessages'];

		if ( $pageCount - $thisPage > 0 ) {
			$newPageBar .= " | <a href=\"ajax.php?" . genLinkQuery( $requestParams, array( 'page' => $requestData['thispage'] + 1 ) ) . "\">" . $upperPageLabel . "</a>";
		}
	// 	if ( pageCount - thisPage > 1 ) {
	// 		newPageBar += " | <a href=\"#\" onclick=\"MessageList.lastPage(); return false\">last</a>";
	// 	}
	} else if ( $requestData['numbermessages'] > 0 && !$isTopBar ) {
		$newPageBar .= "showing 1 to " . $requestData['numbermessages'] . " of " . $requestData['numbermessages'];
	}

	$newPageBar .= "</div></div>";

	return $newPageBar;
}

// ------------------------------------------------------------------------
//
// Render a mailbox list. This always occurs.
//
function render_mailboxList( $requestData, $requestParams ) {
	global $mailbox;
	global $USER_SETTINGS;

	ob_start();

	echo "<li id=\"mb-header\"><span class=\"s-head\">Mailboxes</span> [<a href=\"#manage-mailboxes\" onclick=\"return Lichen.action('options','OptionsEditor','showEditor',['mailboxes'])\">edit</a>]</li>";

	foreach ( $requestData['mailboxList'] as $thisMailbox ) {
		echo "<li id=\"mb-" , $thisMailbox['fullboxname'];
		if ( $mailbox == $thisMailbox['fullboxname'] ) {
			echo "\" class=\"mb-active";
		}
		echo "\">";

		if ( $thisMailbox['selectable'] ) {
			echo "<a href=\"ajax.php?" ,
				genLinkQuery( $requestParams, array( "mailbox" => $thisMailbox['fullboxname'], "page" => 0, "search" => "" ) ) ,
				"\" class=\"mb-click\">";
		}

		// Indent the mailbox name. This is crude.
		echo str_repeat( "&nbsp;&nbsp;", $thisMailbox['folderdepth'] );

		echo "<span class=\"mailbox\">", $thisMailbox['mailbox'];

		echo "&nbsp;<span id=\"mb-unread-", $thisMailbox['fullboxname'], "\">";
		if ( $thisMailbox['unseen'] > 0 || $USER_SETTINGS['boxlist_showtotal'] ) {
			echo "(", $thisMailbox['unseen'];
			if ( $USER_SETTINGS['boxlist_showtotal'] ) echo "/", $thisMailbox['messages'];
			echo ")";
		}
		echo "</span>";
		echo "</span>";

		if ( $thisMailbox['selectable'] ) {
			echo "</a>";
		}
		echo "</li>\n";
	}

	return ob_get_clean();
}

// ------------------------------------------------------------------------
//
// Display a message
//
function render_displayMessage( $requestData, $requestParams ) {
	global $USER_SETTINGS;
	global $mailbox;
	
	helper_calculateSortDirection( $requestData );

	ob_start();
	
	echo "<div class=\"list-header-bar\"><img src=\"themes/", $USER_SETTINGS['theme'], "/top-corner.png\" alt=\"\" class=\"top-corner\" />";

	$messageNavBar = "<div class=\"header-left\"><a class=\"list-return\" href=\"ajax.php?" . 
		genLinkQuery( $requestParams, array( 'reqmode' => 'list' ), array( 'request' ) ) .
		"\">back to ". htmlentities( $mailbox ) . "</a></div>";
			
	$previousMessageLabel = "previous";
	$nextMessageLabel = "next";

	if ( $requestData['sort'] == "date" ) {
		if ( $requestData['sortAsc'] ) {
			$previousMessageLabel = "earlier";
			$nextMessageLabel = "later";
		} else {
			$previousMessageLabel = "later";
			$nextMessageLabel = "earlier";
		}
	}

	$messageNavBar .= "<div class=\"header-right\">\n";
	$previousMessage = $requestData['previousmessage'];
	$nextMessage = $requestData['nextmessage'];
	
	if ( $previousMessage ) {
		$messageNavBar .= "<a href=\"ajax.php?" .
			genLinkQuery( $requestParams, array( 'msg' => $previousMessage['uid'], 'reqmode' => 'disp', 'request' => 'getMessage' ) ) .
			"\">" . $previousMessageLabel;
		if ( $nextMessage ) {
			$messageNavBar .= " message</a> | ";
		} else {
			$messageNavBar .= " message</a>";
		}
	}
	if ( $nextMessage ) {
		$messageNavBar .= "<a href=\"ajax.php?" .
			genLinkQuery( $requestParams, array( 'msg' => $nextMessage['uid'], 'reqmode' => 'disp', 'request' => 'getMessage' ) ) .
			"\">" . $nextMessageLabel . " message</a>";
	}

	$messageNavBar .= "</div></div>\n";

	echo $messageNavBar;

	echo genLinkForm( $requestParams, array(), array( 'mode' ), "typeChanger", "ajax.php" );
	echo "<select id=\"mode\" name=\"mode\">";
	echo "<option value=\"auto\">switch view ...</option>";

	$message = $requestData['data'];

	if ( $message['texthtmlpresent'] ) {
		echo "<option value=\"html\">HTML part</option>";
	}
	if ( $message['textplainpresent'] ) {
		echo "<option value=\"text\">text part</option>";
		echo "<option value=\"text-mono\">monospace text</option>";
	}
	echo "<option value=\"source\">message source</option>";
	echo "</select>";
	echo "<input type=\"submit\" value=\"Change\" />";
	echo "</form>";

	echo "<h1 class=\"msg-head-subject\">", htmlentities( $message['subject'] ), "</h1>";
	echo "<p class=\"msg-head-line2\">from <span class=\"msg-head-sender\">", htmlentities( $message['from'] ), "</span> ";
	echo "at <span class=\"msg-head-date\">", $message['localdate'], "</span></p>";

	if ( isset( $message['htmlhasremoteimages'] ) && $message['htmlhasremoteimages'] ) {
		echo "<div class=\"msg-notification\">";
		echo "Remote images are not displayed. [<a href=\"#\" onclick=\"return Lichen.MessageDisplayer.enableRemoteImages()\">show images</a>]";
		echo "</div>";
	}

	// TODO: Clean up this multistage IF. Its a bit IFFY.
	if ( count( $message['texthtml'] ) > 0 && $requestParams['mode'] != "text" &&
	       $requestParams['mode'] != "text-mono" && $requestParams['mode'] != "source" ) {
		// Display HTML in preference.
		foreach ( $message['texthtml'] as $htmlpart ) {
			echo "<div class=\"html-message\">";
			echo $htmlPart;
			echo "</div>";
		}
	} else {
		// Display the text parts.
		$i = 0;
		foreach ( $message['textplain'] as $plainpart ) {
			echo "<div id=\"plainmsg-", $i, "\" class=\"plain-message";
			if ( $requestParams['mode'] == "text-mono" ) {
				echo " plain-message-monospace";
			}
			echo "\">";
			
			// Hack: if the message part was too large, the server will not have returned it.
			// Include a link to make this happen.
			// TODO: Make this work in the HTML version... the buffering kills it!
			if ( substr( $message['textplain'][$i], 0, 14 ) == "LICHENTOOLARGE" ) {
				$messagePart = explode( ")", substr( $message['textplain'][$i], 15 ) );
				$messagePart = $messagePart[0];
				echo "<a href=\"#\" onclick=\"Lichen.MessageDisplayer.getLargePart('",
					$message['uid'], "', '", $message['mailbox'], "','", $messagePart, "',",
					$i, ");return false\">This message part was too large to return directly. Click here to load it.</a>";
			} else {
				echo $message['textplain'][$i]; // This is linkified/cleaned on the server.
			}
			echo "</div>";

			$i++;
		}
	}

	if ( count( $message['attachments'] ) > 0 && $requestParams['mode'] != "source" ) {
		echo "<ul class=\"attachments\">\n";

		foreach ( $message['attachments'] as $thisAttach ) {
			// Skip attachments that are internal-only.
			if ( $thisAttach['filename'] == "" ) continue;
			echo "<li>";
			$attachUrl = "message.php?mailbox=" . urlencode( $message['mailbox'] ) .
				"&uid=" . urlencode( $message['uid'] ) . "&filename=" . urlencode( $thisAttach['filename'] );
			echo "<a href=\"", $attachUrl, "\" onclick=\"return if_newWin('", $attachUrl, "')\">";
			echo htmlentities( $thisAttach['filename'] ), "</a>";
			echo " <span class=\"msg-attach-meta\">type ", $thisAttach['type'], ", size ~", $thisAttach['size'], " bytes</span>";

			if ( substr( $thisAttach['type'], 0, 5 ) == "image" ) {
				echo "<br />";
				echo "<img src=\"", $attachUrl, "\" alt=\"", $attachUrl, "\" />";
			}

			echo "\n";
		}

		echo "</ul>\n";
	}

	// Huh? If we include the closing DIV below, then we're closing a DIV we never opened. What the?
	echo "<div class=\"footer-bar\"><img src=\"themes/", $USER_SETTINGS['theme'], "/bottom-corner.png\" alt=\"\" class=\"bottom-corner\" />", $messageNavBar;//, "</div>";

	return ob_get_clean();
}

?>
