window.onload=if_init;
var msgCount=0;
var lastUIDconst="";
var lastShownUID="";
var listCurrentMailbox="INBOX";
var listCurrentPage=0;
var listCurrentSearch="";
var listCurrentSort=userSettings["list_sortmode"];
var mailboxCount=0;
var activeFadeEffect=false;
var refreshTimer;
var userSettings;
var mailboxCache=Array();
var MessageDisplayer;
var Flash;
var MailboxManager;
var OptionsEditor;
var Messages;
function if_init(){
Messages=new MessagesDatastore(true);
OptionsEditor=new OptionsEditorClass("opts-wrapper");
MailboxManager=new MailboxManagerClass();
MessageDisplayer=new MessageDisplay("msg-wrapper");
Flash=new FlashArea("notification");
Messages.fetchMailboxList();
refreshTimer=setTimeout(list_checkCount,5*60*1000);
list_show();
if(window.khtml){
var _1=(window.innerWidth-350)+"px";
$("list-bar").style.width=_1;
$("comp-bar").style.width=_1;
$("opts-bar").style.width=_1;
$("msg-bar").style.width=_1;
}
if(window.ie6){
for(var i=0;i<document.images.length;i++){
var _3=document.images[i];
var _4=_3.src.toUpperCase();
if(_4.substring(_4.length-3,_4.length)=="PNG"){
var _5="<span style=\"width:22px;height:22px;cursor:hand;"+"display:inline-block;vertical-align:middle;"+"filter:progid:DXImageTransform.Microsoft.AlphaImageLoader"+"(src='"+_3.src+"', sizingMethod='crop');\"></span>";
_3.outerHTML=_5;
i=i-1;
}
}
$("corner-bar").style.width=(document.body.clientWidth-128)+"px";
}
}

var FlashArea=new Class({initialize:function(_1){
this.wrapper=_1;
this.messages=Array();
this.timeouts=Array();
this.onscreen=false;
},flashMessage:function(_2,_3){
if(_3){
this.messages.push(_2+"<div class=\"detail\">"+_3+"</div>");
}else{
this.messages.push(_2);
}
this.renderFlash();
if(!this.onscreen){
$(this.wrapper).setStyle("display","block");
this.onscreen=true;
}
this.timeouts.push(window.setTimeout(this.clearFlash.bind(this),7500));
},renderFlash:function(){
var _4=this.messages.join("<br />");
$(this.wrapper).setHTML(_4);
},clearFlash:function(){
this.messages.shift();
this.timeouts.shift();
if(this.messages.length==0){
$(this.wrapper).setStyle("display","none");
this.onscreen=false;
}else{
this.renderFlash();
}
},hideFlash:function(){
this.messages=Array();
for(var i=0;i<this.timeouts.length;i++){
window.clearTimeout(this.timeouts[i]);
}
this.timeouts=Array();
this.renderFlash();
this.onscreen=false;
$(this.wrapper).setStyle("display","none");
}});
var PaneTransition=new Class({initialize:function(_6){
this.fadeOut=new Fx.Style(_6,"opacity",{duration:1500});
this.elementID=_6;
this.fadeOut.start(0.9,0);
},end:function(){
this.fadeOut.stop();
$(this.elementID).style.display="none";
$(this.elementID).setStyle("opacity",1);
}});
function if_remoteRequestStart(){
if(!window.ie){
var _7=new Array("msg-wrapper","opts-wrapper","comp-wrapper","addr-wrapper","list-wrapper");
for(var i=0;i<_7.length;i++){
if($(_7[i]).style.display!="none"){
break;
}
}
activeFadeEffect=new PaneTransition(_7[i]);
}
}
function if_remoteRequestEnd(){
if(activeFadeEffect){
activeFadeEffect.end();
activeFadeEffect=false;
}
}
function if_checkRemoteResult(_9){
var _a="";
if($type(_9)!="string"){
return _9;
}
_a=Json.evaluate(_9,true);
if(!_a){
alert("Unable to Json decode what the server sent back.\n"+"The server sent us: '"+_9+"'\n");
if_remoteRequestEnd();
return null;
}
if_remoteRequestEnd();
if(_a.resultCode!="OK"){
if(_a.resultCode=="AUTH"){
var _b=$("opts-wrapper");
var _c="";
_c+="<h3>You were logged out.</h3>";
_c+="<p>The server said: "+_a.errorMessage+"</p>";
_c+="<p>Enter your password to login again:</p>";
_c+="<input type=\"password\" name=\"relogin_pass\" id=\"relogin_pass\" />";
_c+="<button onclick=\"if_relogin(); return false\">Login</button>";
_b.setHTML(_c);
_b.setStyle("display","block");
$("relogin_pass").focus();
}else{
if(_a.imapNotices!=""){
Flash.flashMessage(_a.errorMessage,"IMAP messages: "+_a.imapNotices);
}else{
Flash.flashMessage(_a.errorMessage,"");
}
return null;
}
}else{
return _a;
}
}

function if_returnToList(_1){
if_hideWrappers();
if_hideToolbars();
$("list-bar").style.display="block";
list_show();
}
function if_returnToMessage(){
if_hideWrappers();
if_hideToolbars();
$("msg-wrapper").style.display="block";
$("msg-bar").style.display="block";
}
function if_remoteRequestFailed(_2){
if_remoteRequestEnd();
Flash.flashMessage(_2);
}
function if_relogin(){
var _3=$("relogin_pass").value;
new Ajax("index.php",{postBody:"action=relogin&user="+encodeURIComponent(serverUser)+"&pass="+encodeURIComponent(_3),onComplete:function(_4){
if_reloginCB(_4);
},onFailure:if_remoteRequestFailed}).request();
}
function if_reloginCB(_5){
var _6=if_checkRemoteResult(_5);
if(!_6){
return;
}
if(_6.error){
Flash.flashMessage(_6.error);
}else{
$("opts-wrapper").setStyle("display","none");
$("opts-wrapper").empty();
}
}
function if_logoutSilent(){
new Ajax("index.php",{postBody:"logout=0&silent=0",onComplete:function(_7){
if_logoutSilentCB(_7);
},onFailure:if_remoteRequestFailed}).request();
}
function if_logoutSilentCB(_8){
var _9=if_checkRemoteResult(_8);
if(!_9){
return;
}
Flash.flashMessage("Silently logged out.");
}
function if_hideWrappers(){
$("list-wrapper").style.display="none";
$("msg-wrapper").style.display="none";
$("opts-wrapper").style.display="none";
$("comp-wrapper").style.display="none";
}
function if_hideToolbars(){
$("list-bar").style.display="none";
$("comp-bar").style.display="none";
$("msg-bar").style.display="none";
$("opts-bar").style.display="none";
}
function if_newWin(_a){
var nw=window.open(_a);
return !nw;
}

var MailboxManagerClass=new Class({initialize:function(){
this.mailboxCache=null;
},renameInline:function(_1,_2){
if($("mbm-namearea-"+_1)){
var _3="<input id=\"mbm-rename-"+_1+"\" type=\"text\" size=\"20\" value=\""+_2+"\" />";
_3+="<button onclick=\"MailboxManager.renameDone('"+_1+"', '"+_2+"');return false\">save</button> <button onclick=\"MailboxManager.renameCancel('"+_1+"');return false\">cancel</button> ";
$("mbm-namearea-"+_1).setHTML(_3);
var _4=$("mbm-rename-"+_1);
if(_4){
_4.focus();
}
}
},renameDone:function(_5,_6){
var _7=$("mbm-rename-"+_5);
if(_7){
var _8=_7.value;
if(_8&&_8!=""){
if(_8==_6){
this.serverActionCB({action:"rename",mailbox1:_5,mailbox2:_5});
}else{
_8=_5.substr(0,_5.length-_6.length)+_8;
new Ajax("ajax.php",{postBody:"request=mailboxAction&action=rename&mailbox1="+encodeURIComponent(_5)+"&mailbox2="+encodeURIComponent(_8),onComplete:this.serverActionCB.bind(this),onFailure:if_remoteRequestFailed}).request();
}
}
}
},renameCancel:function(_9){
var _a=$("mbm-rename-"+_9);
if(_a){
this.serverActionCB({action:"rename",mailbox1:_9,mailbox2:_9});
}
},mailboxDelete:function(_b,_c){
if(confirm("Are you sure you want to delete '"+_c+"'?\nAll messages in this mailbox will be deleted.\nThis cannot be undone.")){
new Ajax("ajax.php",{postBody:"request=mailboxAction&action=delete&mailbox1="+encodeURIComponent(_b),onComplete:this.serverActionCB.bind(this),onFailure:if_remoteRequestFailed}).request();
}
},newChild:function(_d){
var _e=$("mbm-changearea-"+_d);
if(_e){
var _f="<div id=\"mbm-newchild-wrapper-"+_d+"\">";
_f+="New Subfolder: <input id=\"mbm-newchild-"+_d+"\" type=\"text\" size=\"20\" />";
_f+="<button onclick=\"MailboxManager.newChildSubmit('"+_d+"'); return false\">Add</button>";
_f+="<button onclick=\"MailboxManager.newChildCancel('"+_d+"'); return false\">Cancel</button>";
_f+="</div>";
_e.setHTML(_f);
var _10=$("mbm-newchild-"+_d);
if(_10){
_10.focus();
}
}
},newChildSubmit:function(_11){
var _12=$("mbm-newchild-"+_11);
if(_12&&_12.value!=""){
new Ajax("ajax.php",{postBody:"request=mailboxAction&action=create&mailbox1="+encodeURIComponent(_11)+"&mailbox2="+encodeURIComponent(_12.value),onComplete:this.serverActionCB.bind(this),onFailure:if_remoteRequestFailed}).request();
}
},newChildCancel:function(_13){
var _14=$("mbm-newchild-wrapper-"+_13);
if(_14){
_14.remove();
}
},changeParentInline:function(_15,_16){
var _17=$("mbm-changearea-"+_15);
if(_17){
var _18="<div id=\"mbm-changeparent-wrapper-"+_15+"\">";
_18+="Move to subfolder of: ";
_18+="<select id=\"mbm-changeparent-"+_15+"\">";
_18+="<option value=\"\">[Top Level]</option>";
for(var i=0;i<this.mailboxCache.length;i++){
var _1a=this.mailboxCache[i];
_18+="<option value=\""+_1a.fullboxname+"\"";
if(_16==_1a.mailbox){
_18+=" selected=\"selected\">";
}else{
_18+=">";
}
for(var j=0;j<_1a.folderdepth;j++){
_18+="-";
}
_18+=_1a.mailbox+"</option>";
}
_18+="</select>";
_18+="<button onclick=\"MailboxManager.changeParentSubmit('"+_15+"', '"+_16+"'); return false\">Move</button>";
_18+="<button onclick=\"MailboxManager.changeParentCancel('"+_15+"', '"+_16+"'); return false\">Cancel</button>";
_18+="</div>";
_17.setHTML(_18);
}
},changeParentSubmit:function(_1c,_1d){
var _1e=$("mbm-changeparent-"+_1c);
if(_1e&&_1e.value!=_1d){
new Ajax("ajax.php",{postBody:"request=mailboxAction&action=move&mailbox1="+encodeURIComponent(_1c)+"&mailbox2="+encodeURIComponent(_1e.value),onComplete:this.serverActionCB.bind(this),onFailure:if_remoteRequestFailed}).request();
}
},changeParentCancel:function(_1f,_20){
var _21=$("mbm-changeparent-wrapper-"+_1f);
if(_21){
_21.remove();
}
},serverActionCB:function(_22){
var _23=if_checkRemoteResult(_22);
if(!_23){
return;
}
OptionsEditor.showEditor("mailboxes");
if(_23.mailboxes){
this.mailboxCache=_23.mailboxes;
}
}});

function list_checkCount(){
Messages.fetchMailboxList();
refreshTimer=setTimeout(list_checkCount,5*60*1000);
}
function list_buildMailboxList(_1){
$("mailboxes").empty();
var _2="<li id=\"mb-header\"><span class=\"s-head\">Mailboxes</span> [<a href=\"#manage-mailboxes\" onclick=\"OptionsEditor.showEditor('mailboxes');return false\">edit</a>]</li>";
for(var i=0;i<_1.length;i++){
_2+="<li id=\"mb-"+_1[i].fullboxname;
if(listCurrentMailbox==_1[i].mailbox){
_2+="\" class=\"mb-active";
}
_2+="\">";
if(_1[i].selectable){
_2+="<a href=\"#\" onclick=\"return if_selectmailbox('"+_1[i].fullboxname+"')\" class=\"mb-click\">";
}
for(var j=0;j<_1[i].folderdepth;j++){
_2+="&nbsp;&nbsp;";
}
_2+="<span class=\"mailbox\">"+_1[i].mailbox+"</strong> ";
_2+="<span id=\"mb-unread-"+_1[i].fullboxname+"\">";
if(_1[i].unseen>0||userSettings["boxlist_showtotal"]){
_2+="("+_1[i].unseen;
if(userSettings["boxlist_showtotal"]){
_2+="/"+_1[i].messages;
}
_2+=")";
}
_2+="</span>";
if(_1[i].selectable){
_2+="</a>";
}
_2+="</li>";
}
_2+="</ul>";
$("mailboxes").setHTML(_2);
mailboxCount=_1.length;
}
function list_countCB(_5){
var _6=_5;
mailboxCache=_5;
if(mailboxCount!=_6.length){
list_buildMailboxList(_6);
return;
}
var i=0;
for(i=0;i<_6.length;i++){
if(listCurrentMailbox==_6[i].fullboxname){
if(lastUIDconst!=""&&lastUIDconst!=_6[i].uidConst){
list_show();
}else{
if(msgCount!=0&&msgCount!=_6[i].messages){
list_show();
}
}
lastUIDconst=_6[i].uidConst;
msgCount=_6[i].messages;
$("mb-"+listCurrentMailbox).addClass("mb-active");
document.title=_6[i].mailbox+" ("+_6[i].unseen+" unread, "+_6[i].messages+" total)";
}
var _8="";
if(_6[i].unseen>0||userSettings["boxlist_showtotal"]){
_8="("+_6[i].unseen;
if(userSettings["boxlist_showtotal"]){
_8+="/"+_6[i].messages;
}
_8+=")";
}
if($("mb-unread-"+_6[i].fullboxname)){
$("mb-unread-"+_6[i].fullboxname).setHTML(_8);
}else{
list_buildMailboxList(_6);
return;
}
}
}
function if_selectmailbox(_9){
lastUIDconst="";
msgCount=0;
if_remoteRequestStart();
$("mb-"+listCurrentMailbox).removeClass("mb-active");
if($("list-wrapper").style.display=="none"){
if_hideWrappers();
if_hideToolbars();
$("list-bar").style.display="block";
}
listCurrentPage=0;
listCurrentSearch="";
list_show(_9,0);
return false;
}

var MessagesDatastore=new Class({initialize:function(_1){
this.online=_1;
this.server=new IMAPServerConnector(this);
this.cache=new HashCacheConnector(this,serverUser);
},fetchMessageList:function(_2,_3,_4,_5){
var _6=this.cache.getMessageListValidity(_2,_3,_4,_5);
var _7=this.cache.getMessageList(_2,_3,_4,_5,_6);
if(_7){
list_showCB(_7);
}
if(this.online){
this.server.messageList(_2,_3,_4,_5,_6);
if(_4!=0){
this.server.messageList(_2,_3,_4-1,_5,this.cache.getMessageListValidity(_2,_3,_4-1,_5),true);
}
this.server.messageList(_2,_3,_4+1,_5,this.cache.getMessageListValidity(_2,_3,_4+1,_5),true);
}
if(!this.online&&!_7){
Flash.flashMessage("Not online, and that data is not cached.");
}
},fetchMessageListCB:function(_8,_9,_a,_b,_c,_d,_e){
if(_b==-1){
return;
}
var _f=this.cache.storeMessageList(_9,_a,_b,_c,_8.data,_8.validityKey);
if(_e!="true"){
list_showCB(_f);
}
},fetchAdjacentMessages:function(_10,_11,_12,_13,uid){
var _15=-1;
var _16=-1;
var _17=-1;
var _18=null;
var _19=null;
var _1a=Array();
_1a.push(this.cache.getMessageList(_10,_11,_12,_13,this.cache.getMessageListValidity(_10,_11,_12,_13)));
_1a.push(this.cache.getMessageList(_10,_11,_12+1,_13,this.cache.getMessageListValidity(_10,_11,_12+1,_13)));
_1a.push(this.cache.getMessageList(_10,_11,_12-1,_13,this.cache.getMessageListValidity(_10,_11,_12-1,_13)));
for(var j=0;j<_1a.length;j++){
if(_1a[j]==null){
continue;
}
for(var i=0;i<_1a[j].messages.length;i++){
if(_1a[j].messages[i].uid==uid){
_15=i;
_16=_1a[j].thispage.toInt();
_17=j;
break;
}
}
if(_15!=-1){
break;
}
}
if(_16!=_12){
return this.fetchAdjacentMessages(_10,_11,_16,_13,uid);
}
if(_15!=-1&&_16!=-1){
if(_15>0){
_18=_1a[_17].messages[_15-1];
}
if(_15==0){
if(_1a[2]!=null){
_18=_1a[2].messages[_1a[2].messages.length-1];
}
}
if(_15<(_1a[_17].messages.length-1)){
_19=_1a[_17].messages[_15+1];
}
if(_15==(_1a[_17].messages.length-1)){
if(_1a[1]!=null){
_19=_1a[1].messages[0];
}
}
}
if(_16!=-1&&_16!=listCurrentPage){
listCurrentPage=_16;
if(this.online){
if(_16!=0){
this.server.messageList(_10,_11,_16-1,_13,this.cache.getMessageListValidity(_10,_11,_16-1,_13),true);
}
this.server.messageList(_10,_11,_16+1,_13,this.cache.getMessageListValidity(_10,_11,_16+1,_13),true);
}
}
var _1d={};
_1d.previous=_18;
_1d.next=_19;
return _1d;
},fetchMailboxList:function(){
var _1e=this.cache.getMailboxListValidity();
var _1f=this.cache.getMailboxList(_1e);
if(this.online){
this.server.mailboxList(_1e);
}else{
if(_1f){
list_countCB(_1f);
}else{
Flash.flashMessage("Unable to fetch mailbox list: not online and not cached.");
}
}
},fetchMailboxListCB:function(_20){
var _21=this.cache.storeMailboxList(_20.data,_20.validity);
list_countCB(_21);
},fetchMessage:function(_22,uid,_24){
if(this.online){
this.server.messageBody(_22,uid,_24);
}else{
Flash.flashMessage("Unable to view message: in offline mode, and not cached.");
}
},fetchMessageCB:function(_25,_26,uid){
var _28=this.cache.storeMessage(_26,uid,_25.data);
MessageDisplayer.showMessageCB(_25.data);
}});

var GoogleCacheConnector=new Class({initialize:function(_1,_2){
}});
var HashCacheConnector=new Class({initialize:function(_3,_4){
this.messagelists={};
this.messagelistsvalidity={};
this.messages={};
this.mailboxlist={};
this.mailboxlistvalidity=null;
this.dataStore=_3;
},storeMessageList:function(_5,_6,_7,_8,_9,_a){
var _b=_5+_6+_7+_8;
if(_a&&this.messagelistsvalidity[_b]&&_a==this.messagelistsvalidity[_b]){
return this.messagelists[_b];
}else{
this.messagelists[_b]=_9;
this.messagelistsvalidity[_b]=_a;
return _9;
}
},getMessageList:function(_c,_d,_e,_f,_10){
var _11=_c+_d+_e+_f;
if(this.messagelists[_11]&&this.messagelistsvalidity[_11]&&_10&&this.messagelistsvalidity[_11]==_10){
return this.messagelists[_11];
}else{
return null;
}
},getMessageListValidity:function(_12,_13,_14,_15){
var _16=_12+_13+_14+_15;
if(this.messagelistsvalidity[_16]!=null){
return this.messagelistsvalidity[_16];
}else{
return null;
}
},storeMessage:function(_17,uid,_19,_1a){
var _1b=_17+uid;
if(this.messages[_1b]){
var _1c=this.messages[_1b];
if(_1c.texthtmlpresent&&_1c.texthtml.length==0&&_19.texthtml.length>0){
_1c.texthtml=_19.texthtml;
}
if(_1c.textplainpresent&&_1c.textplain.length==0&&_19.textplain.length>0){
_1c.textplain=_19.textplain;
}
if(_1c.source==null&&_19.source!=null){
_1c.source=_19.source;
}
}else{
this.messages[_1b]=_19;
}
return this.messages[_1b];
},getMessage:function(_1d,uid,_1f,_20){
var _21=_1d+uid;
var _22=null;
if(this.messages[_21]&&_20){
var _23=false;
var _24=this.messages[_21];
switch(_1f){
case "html":
if(_24.texthtmlpresent&&_24.texthtml.length!=0){
_23=true;
}
break;
case "text":
if(_24.textplainpresent&&_24.textplain.length!=0){
_23=true;
}
break;
case "source":
if(_24.source){
_23=true;
}
break;
case "all":
break;
default:
_23=true;
break;
}
if(_23){
_22=this.messages[_21];
}
}
return _22;
},storeMailboxList:function(_25,_26){
if(_26&&_26==this.mailboxlistvalidity){
return this.mailboxlist;
}else{
this.mailboxlist=_25;
this.mailboxlistvalidity=_26;
return _25;
}
},getMailboxList:function(_27){
if(_27&&_27==this.mailboxlistvalidity){
return this.mailboxlist;
}else{
return null;
}
},getMailboxListValidity:function(){
return this.mailboxlistvalidity;
}});

var IMAPServerConnector=new Class({initialize:function(_1){
this.dataStore=_1;
},messageList:function(_2,_3,_4,_5,_6,_7){
new Ajax("ajax.php",{postBody:"request=mailboxContentsList&mailbox="+encodeURIComponent(_2)+"&page="+_4+"&search="+encodeURIComponent(_3)+"&sort="+encodeURIComponent(_5)+"&validity="+encodeURIComponent(_6)+"&cacheonly="+encodeURIComponent(_7),onComplete:this.messageListCB.bind(this),onFailure:if_remoteRequestFailed}).request();
},messageListCB:function(_8){
var _9=if_checkRemoteResult(_8);
if(!_9){
return null;
}
this.dataStore.fetchMessageListCB(_9,_9.data.mailbox,_9.data.search,_9.data.thispage,_9.data.sort,_9.validity,_9.cacheonly);
},mailboxList:function(_a){
new Ajax("ajax.php",{postBody:"request=getMailboxList&validity="+encodeURIComponent(_a),onComplete:this.mailboxListCB.bind(this),onFailure:if_remoteRequestFailed}).request();
},mailboxListCB:function(_b){
var _c=if_checkRemoteResult(_b);
if(!_c){
return null;
}
this.dataStore.fetchMailboxListCB(_c);
},messageBody:function(_d,_e,_f){
new Ajax("ajax.php",{postBody:"request=getMessage&mailbox="+encodeURIComponent(_d)+"&msg="+encodeURIComponent(_e)+"&mode="+encodeURIComponent(_f),onComplete:this.messageBodyCB.bind(this),onFailure:if_remoteRequestFailed}).request();
},messageBodyCB:function(_10){
var _11=if_checkRemoteResult(_10);
if(!_11){
return null;
}
this.dataStore.fetchMessageCB(_11,_11.data.mailbox,_11.data.uid);
}});

function list_sort(_1){
if(listCurrentSort.substr(listCurrentSort.length-2,2)=="_r"){
$("list-sort-"+listCurrentSort.substring(0,listCurrentSort.length-2)).getParent().getElementsByTagName("img")[0].remove();
}else{
$("list-sort-"+listCurrentSort).getParent().getElementsByTagName("img")[0].remove();
}
if(_1==listCurrentSort){
if(listCurrentSort.substr(listCurrentSort.length-2,2)=="_r"){
listCurrentSort=_1;
}else{
listCurrentSort=_1+"_r";
}
}else{
listCurrentSort=_1;
}
userSettings["list_sortmode"]=_1;
list_show();
}
function list_show(_2,_3){
var _4="";
var _5=listCurrentPage;
var _6=listCurrentSearch;
var _7=listCurrentSort;
if(_2){
_4=_2;
}else{
_4=listCurrentMailbox;
}
if(_3!=null){
_5=_3;
}else{
_5=listCurrentPage;
}
Messages.fetchMessageList(_4,listCurrentSearch,_5,_7);
}
function list_switchPage(_8){
list_show(listCurrentMailbox,_8);
}
function list_showCB(_9){
result=_9;
var _a=result.messages;
listCurrentMailbox=result.mailbox;
listCurrentPage=result.thispage.toInt();
listCurrentSearch=result.search;
listCurrentSort=result.sort;
$("list-wrapper").empty();
$("list-wrapper").style.display="block";
var _b="";
_b+=list_createPageBar(result,true);
if(result.search!=""){
_b+="<div class=\"list-notification\"><strong>Search results for &#8220;"+result.search+"&#8221;</strong> "+"[<a href=\"#clearsearch\" onclick=\"doQuickSearch(null, true);return false\">clear search</a>]</div>";
}
_b+="<table>";
var _c=window.getWidth()-515;
if(userSettings.list_showsize){
_c=window.getWidth()-590;
}
_b+="<colgroup><col class=\"mcol-checkbox\" /><col class=\"mcol-flag\" /><col class=\"mcol-sender\" /><col class=\"mcol-subject\" style=\"width:"+_c+"px\" />";
if(userSettings.list_showsize){
_b+="<col class=\"mcol-size\" />";
}
_b+="<col class=\"mcol-date\" /></colgroup>";
_b+="<thead><tr class=\"list-sortrow\"><th></th><th></th>";
_b+="<th class=\"list-sortlabel\"><a href=\"#sort-from\" id=\"list-sort-from\" onclick=\"list_sort('from');return false\">sender</a></th>";
_b+="<th class=\"list-sortlabel\"><a href=\"#sort-subject\" id=\"list-sort-subject\" onclick=\"list_sort('subject');return false\">subject</a></th>";
if(userSettings.list_showsize){
_b+="<th class=\"list-sortlabel\"><a href=\"#sort-size\" id=\"list-sort-size\" onclick=\"list_sort('size');return false\">size</a></th>";
}
_b+="<th class=\"list-sortlabel\"><a href=\"#sort-date\" id=\"list-sort-date\" onclick=\"list_sort('date');return false\">date</a></th>";
_b+="</tr></thead><tbody>";
if(_a.length==0){
_b+="<tr><td colspan=\"5\" class=\"list-nothing\">No messages in this mailbox.</td></tr>";
}
for(var i=0;i<_a.length;i++){
var _e=_a[i];
var _f=_e.uid;
var _10="<tr id=\"mr-"+_e.uid+"\" class=\"";
if(i%2==1){
_10+="odd";
}else{
_10+="even";
}
if(_e.readStatus=="U"){
_10+=" new";
}
_10+="\">";
_10+="<td><input type=\"checkbox\" class=\"msg-select\" name=\"s-"+_e.uid+"\" id=\"s-"+_e.uid+"\" value=\""+_e.uid+"\" onclick=\"list_messageCheckboxClicked();\" /></td>";
var _11=_e.flagged?"/icons/flag.png":"/icons/flag_off.png";
_10+="<td><img src=\"themes/"+userSettings.theme+_11+"\" id=\"flagged_"+_e.uid+"\" alt=\"\" onclick=\"list_twiddleFlag('"+_e.uid+"', 'flagged', 'toggle')\" title=\"Flag this message\" class=\"list-flag\" /></td>";
_10+="<td class=\"sender\" onclick=\"showMsg('"+listCurrentMailbox+"','"+_e.uid+"',0)\"";
if(_e.fromName==""){
if(_e.fromAddr.length>22){
_10+=" title=\""+_e.fromAddr+"\"";
}
_10+="><div class=\"sender\">"+_e.fromAddr;
}else{
_10+=" title=\""+_e.fromAddr+"\"><div class=\"sender\">"+_e.fromName;
}
_10+="</div></td>";
_10+="<td class=\"subject\" onclick=\"showMsg('"+listCurrentMailbox+"','"+_e.uid+"',0)\"><div class=\"subject\">"+_e.subject;
if(userSettings.list_showpreviews){
_10+="<span class=\"messagePreview\">"+_e.preview+"</span>";
}
_10+="</div></td>";
if(userSettings.list_showsize){
_10+="<td class=\"size\"><div class=\"size\">"+_e.size+"</div></td>";
}
_10+="<td class=\"date\"><div class=\"date\">"+_e.dateString+"</div></td>";
_10+="</tr>";
_b+=_10;
}
_b+="</tbody></table>";
_b+=list_createPageBar(result,false);
$("list-wrapper").setHTML(_b);
if(listCurrentSort.substr(listCurrentSort.length-2,2)=="_r"){
var _12=new Element("img",{"class":"list-sort-marker","src":"themes/"+userSettings.theme+"/icons/sort_decrease.png"});
$("list-sort-"+listCurrentSort.substring(0,listCurrentSort.length-2)).getParent().adopt(_12);
}else{
var _13=new Element("img",{"class":"list-sort-marker","src":"themes/"+userSettings.theme+"/icons/sort_incr.png"});
$("list-sort-"+listCurrentSort).getParent().adopt(_13);
}
Messages.fetchMailboxList();
}
function list_createPageBar(_14,_15){
var _16="";
if(_15){
_16+="<div class=\"list-header-bar\"><img src=\"themes/"+userSettings.theme+"/top-corner.png\" alt=\"\" class=\"top-corner\" />";
}else{
_16+="<div class=\"list-footer-bar\"><img src=\"themes/"+userSettings.theme+"/bottom-corner.png\" alt=\"\" class=\"bottom-corner\" />";
}
var _17=_14.thispage.toInt()+1;
var _18=_14.numberpages.toInt();
var _19=_17*_14.pagesize.toInt();
if(_19>_14.numbermessages.toInt()){
_19=_14.numbermessages.toInt();
}
_16+="<div class=\"header-left\">";
_16+="<select onchange=\"list_withSelected(this)\">";
_16+="<option value=\"noop\" selected=\"selected\">move selected to ...</option>";
for(var i=0;i<mailboxCache.length;i++){
_16+="<option value=\"move-"+mailboxCache[i].fullboxname+"\">";
for(var j=0;j<mailboxCache[i].folderdepth;j++){
_16+="-";
}
_16+=mailboxCache[i].mailbox;
_16+="</option>";
}
_16+="</select>";
_16+=" &nbsp; <input type=\"button\" onclick=\"if_deleteMessages();return false\" value=\"delete\" />";
_16+=" &nbsp; <input type=\"button\" onclick=\"list_withSelected(null, 'flag');return false\" value=\"flag\" />";
_16+=" &nbsp; <input type=\"button\" onclick=\"list_withSelected(null, 'markseen');return false\" value=\"mark read\" /><br />";
if(!_15){
_16+="select: <a href='#' onclick='list_selectMessages(\"all\"); return false'>all</a> | ";
_16+="<a href='#' onclick='list_selectMessages(\"none\"); return false'>none</a> | ";
_16+="<a href='#' onclick='list_selectMessages(\"invert\"); return false'>invert</a>";
}
_16+="</div><div class=\"header-right\">";
if(_14.numberpages>1){
if(_17>1){
_16+="<a href=\"#\" onclick=\"list_switchPage("+(_17-2)+"); return false\">previous</a> | ";
}
_16+="<select onchange=\"list_switchPage(this.value);\">";
var _1c=_14.pagesize.toInt();
var _1d=_14.numbermessages.toInt();
var _1e=0;
for(var i=1;i<=_14.numbermessages.toInt();i+=_1c){
_16+="<option value=\""+_1e+"\"";
if(_17==(_1e+1)){
_16+=" selected=\"selected\"";
}
_16+=">"+i+" to ";
if((_1e+1)*_1c>_1d){
_16+=_1d;
}else{
_16+=(_1e+1)*_1c;
}
_16+="</option>";
_1e++;
}
_16+="</select>";
_16+=" of "+_14.numbermessages.toInt();
if(_18-_17>0){
_16+=" | <a href=\"#\" onclick=\"list_switchPage("+_17+"); return false\">next</a>";
}
}else{
if(_14.numbermessages>0&&!_15){
_16+="showing 1 to "+_14.numbermessages+" of "+_14.numbermessages;
}
}
_16+="</div></div>";
return _16;
}
function list_getSelectedMessages(){
var _20=Array();
var _21=$A($("list-wrapper").getElementsByTagName("input"));
for(var i=0;i<_21.length;i++){
if(_21[i].checked){
_20.push(_21[i].value);
}
}
return _20;
}
function list_selectMessages(_23){
var _24=$A($("list-wrapper").getElementsByTagName("input"));
for(var i=0;i<_24.length;i++){
switch(_23){
case "all":
_24[i].checked=true;
break;
case "none":
_24[i].checked=false;
break;
case "invert":
_24[i].checked=!_24[i].checked;
break;
}
}
}
function list_withSelected(_26,_27){
var _28=list_getSelectedMessages();
var _29="noop";
if(!_26&&_27){
_29=_27;
}else{
_29=_26.value;
}
var _2a="";
if(_29.substr(0,5)=="move-"){
_2a=_29.substr(5);
_29="move";
}
switch(_29){
case "noop":
break;
case "markseen":
list_twiddleFlag(_28.join(","),"seen","true");
break;
case "markunseen":
list_twiddleFlag(_28.join(","),"seen","false");
break;
case "flag":
list_twiddleFlag(_28.join(","),"flagged","true");
break;
case "unflag":
list_twiddleFlag(_28.join(","),"flagged","false");
break;
case "move":
if_moveMessages(_2a);
break;
}
if(_26){
_26.selectedIndex=0;
}
}
function list_messageCheckboxClicked(){
return false;
}
function doQuickSearch(_2b,_2c){
if(_2c){
$("qsearch").value="";
}
Messages.fetchMessageList(listCurrentMailbox,$("qsearch").value,0,"");
return false;
}

var MessageDisplay=new Class({initialize:function(_1){
this.wrapperdiv=_1;
this.displayMode="";
this.displayModeUID="";
},showMessage:function(_2,_3,_4){
this.displayMode=_4;
this.displayModeUID=_3;
var _5=_4;
if(_4=="monospace"){
_5="text";
}
Messages.fetchMessage(_2,_3,_5);
},showMessageCB:function(_6,_7){
if($("mr-"+_6.uid)){
$("mr-"+_6.uid).removeClass("new");
}
if(!_7&&this.displayModeUID==_6.uid){
_7=this.displayMode;
}
clearTimeout(refreshTimer);
if_hideWrappers();
if_hideToolbars();
$(this.wrapperdiv).empty();
$(this.wrapperdiv).setHTML(this._render(_6,_7));
$(this.wrapperdiv).style.display="block";
$("msg-bar").style.display="block";
lastShownUID=_6.uid;
var _8=$("btn-draft");
if(listCurrentMailbox==specialFolders["drafts"]){
_8.setStyle("display","inline");
}else{
_8.setStyle("display","none");
}
},_render:function(_9,_a){
var _b=Messages.fetchAdjacentMessages(listCurrentMailbox,listCurrentSearch,listCurrentPage,listCurrentSort,_9.uid);
var _c="<div class=\"list-header-bar\"><img src=\"themes/"+userSettings.theme+"/top-corner.png\" alt=\"\" class=\"top-corner\" />";
var _d="<div class=\"header-left\"><a class=\"list-return\" href=\"#inbox\" onclick=\"if_returnToList(lastShownUID);return false\">back to "+listCurrentMailbox+"</a></div>";
_d+="<div class=\"header-right\">";
if(_b.previous){
_d+="<a href=\"#\" onclick=\"return showMsg('"+_9.mailbox+"','"+_b.previous.uid+"')\">previous";
if(_b.next){
_d+="</a> | ";
}else{
_d+=" message</a>";
}
}
if(_b.next){
_d+="<a href=\"#\" onclick=\"return showMsg('"+_9.mailbox+"','"+_b.next.uid+"')\">next message</a>";
}
_d+="</div></div>";
_c+=_d;
_c+="<select id=\"msg-switch-view\" onchange=\"MessageDisplayer.switchView()\">";
_c+="<option value=\"noop\">switch view ...</option>";
if(_9.texthtml.length>0||_9.texthtmlpresent){
_c+="<option value=\"html\">HTML part</option>";
}
if(_9.textplain.length>0||_9.textplainpresent){
_c+="<option value=\"text\">text part</option>";
_c+="<option value=\"text-mono\">monospace text</option>";
}
_c+="<option value=\"source\">message source</option>";
_c+="</select>";
_c+="<h1 class=\"msg-head-subject\">"+_9.subject+"</h1>";
_c+="<p class=\"msg-head-line2\">from <span class=\"msg-head-sender\">"+_9.from+"</span> ";
_c+="at <span class=\"msg-head-date\">"+_9.localdate+"</span></p>";
if(_9.htmlhasremoteimages){
_c+="<div class=\"msg-notification\">";
_c+="Remote images are not displayed. [<a href=\"#\" onclick=\"return MessageDisplayer.enableRemoteImages()\">show images</a>]";
_c+="</div>";
}
if(_9.texthtml.length>0&&_a!="text"&&_a!="monospace"&&_a!="source"){
for(var i=0;i<_9.texthtml.length;i++){
_c+="<div class=\"html-message\">";
_c+=_9.texthtml[i];
_c+="</div>";
}
}else{
for(var i=0;i<_9.textplain.length;i++){
_c+="<div class=\"plain-message";
if(_a=="monospace"){
_c+=" plain-message-monospace";
}
_c+="\">";
_c+=_9.textplain[i];
_c+="</div>";
}
}
if(_9.attachments.length>0&&_a!="source"){
_c+="<ul class=\"attachments\">";
for(var i=0;i<_9.attachments.length;i++){
var _11=_9.attachments[i];
if(_11.filename==""){
continue;
}
_c+="<li>";
var _12="message.php?mailbox="+encodeURIComponent(_9.mailbox)+"&uid="+encodeURIComponent(_9.uid)+"&filename="+encodeURIComponent(_11.filename);
_c+="<a href=\""+_12+"\" onclick=\"return if_newWin('"+_12+"')\">";
_c+=_11.filename+"</a>";
_c+=" <span class=\"msg-attach-meta\">type "+_11.type+", size ~"+_11.size+" bytes</span>";
if(_11.type.substr(0,5)=="image"){
_c+="<br />";
_c+="<img src=\""+_12+"\" alt=\""+_12+"\" />";
}
}
_c+="</ul>";
}
_c+="<div class=\"footer-bar\"><img src=\"themes/"+userSettings.theme+"/bottom-corner.png\" alt=\"\" class=\"bottom-corner\" />"+_d+"</div>";
return _c;
},switchView:function(){
switch($("msg-switch-view").value){
case "html":
showMsg(listCurrentMailbox,lastShownUID,"html");
break;
case "text":
showMsg(listCurrentMailbox,lastShownUID,"text");
break;
case "text-mono":
showMsg(listCurrentMailbox,lastShownUID,"monospace");
break;
case "source":
if_newWin("message.php?source&mailbox="+encodeURIComponent(listCurrentMailbox)+"&uid="+encodeURIComponent(lastShownUID));
break;
}
$("msg-switch-view").value="noop";
},enableRemoteImages:function(){
var _13=$("msg-wrapper").getElementsByTagName("img");
for(var i=0;i<_13.length;i++){
if(_13[i].src.substr(7,1)=="_"){
var _15="http://"+_13[i].src.substr(8);
_13[i].src=_15;
}
}
return false;
}});
function showMsg(_16,uid,_18){
if_remoteRequestStart();
MessageDisplayer.showMessage(_16,uid,_18);
return false;
}

function list_twiddleFlag(_1,_2,_3){
var _4="request=setFlag";
_4+="&flag="+encodeURIComponent(_2);
_4+="&mailbox="+listCurrentMailbox;
_4+="&uid="+encodeURIComponent(_1);
if(_3){
_4+="&state="+_3;
}
new Ajax("ajax.php",{postBody:_4,onComplete:function(_5){
list_twiddleFlagCB(_5);
},onFailure:function(_6){
if_remoteRequestFailed(_6);
}}).request();
}
function list_twiddleFlagCB(_7){
var _8=if_checkRemoteResult(_7);
if(!_8){
return;
}
var _9=_8.uid.split(",");
if(_8.flag=="seen"){
for(var i=0;i<_9.length;i++){
var _b=$("mr-"+_9[i]);
if(_b){
if(!_8.state){
_b.addClass("new");
}else{
_b.removeClass("new");
}
}
}
}
if(_8.flag=="flagged"){
for(var i=0;i<_9.length;i++){
var _d=$(_8.flag+"_"+_9[i]);
if(_d){
if(_8.state){
_d.src="themes/"+userSettings.theme+"/icons/flag.png";
}else{
_d.src="themes/"+userSettings.theme+"/icons/flag_off.png";
}
}
}
}
if(_9.length>1){
Flash.flashMessage("Updated "+_9.length+" messages.");
}
}
function if_moveMessages(_e){
var _f=list_getSelectedMessages();
var _10=_f.length;
_f=_f.join(",");
new Ajax("ajax.php",{postBody:"request=moveMessage&mailbox="+encodeURIComponent(listCurrentMailbox)+"&destbox="+encodeURIComponent(_e)+"&uid="+encodeURIComponent(_f),onComplete:function(_11){
if_moveMessagesCB(_11);
},onFailure:function(_12){
if_remoteRequestFailed(_12);
}}).request();
}
function if_deleteMessages(){
var _13=list_getSelectedMessages();
var _14=_13.length;
_13=_13.join(",");
new Ajax("ajax.php",{postBody:"request=deleteMessage&mailbox="+encodeURIComponent(listCurrentMailbox)+"&uid="+encodeURIComponent(_13),onComplete:function(_15){
if_moveMessagesCB(_15);
},onFailure:function(_16){
if_remoteRequestFailed(_16);
}}).request();
}
function if_moveMessagesCB(_17){
var _18=if_checkRemoteResult(_17);
if(!_18){
return;
}
Flash.flashMessage(_18.message);
list_show();
}

function comp_showForm(_1,_2,_3){
clearTimeout(refreshTimer);
var _4="request=createComposer&mailbox="+encodeURIComponent(listCurrentMailbox);
if(_1){
_4+="&mode="+_1;
}
if(_2){
_4+="&uid="+encodeURIComponent(_2);
}
if(_3){
_4+="&mailto="+encodeURIComponent(_3);
}
if_remoteRequestStart();
new Ajax("ajax.php",{postBody:_4,onComplete:function(_5){
comp_showCB(_5);
},onFailure:if_remoteRequestFailed}).request();
}
function comp_showCB(_6){
var _7=if_checkRemoteResult(_6);
if(!_7){
return;
}
if_hideWrappers();
if_hideToolbars();
$("comp-wrapper").setHTML(_7.htmlFragment);
$("comp-wrapper").style.display="block";
$("comp-bar").style.display="block";
}
function comp_send(_8,_9){
if(_9){
Flash.flashMessage("Saving draft ...");
}else{
Flash.flashMessage("Sending message ...");
}
var _a="request=sendMessage&";
if(_9){
_a+="draft=save&";
}
_a+=$("compose").toQueryString();
new Ajax("ajax.php",{postBody:_a,onComplete:function(_b){
comp_sendCB(_b,_8);
},onFailure:if_remoteRequestFailed}).request();
}
function comp_sendCB(_c,_d){
var _e=if_checkRemoteResult(_c);
if(!_e){
return;
}
if(_e.draftMode){
if($("comp-draftuid")){
$("comp-draftuid").value=_e.draftUid;
}
var d=new Date();
var hr=d.getHours();
var min=d.getMinutes();
if(min<10){
min="0"+min;
}
if(hr==0){
hr="12";
}
if(hr>12){
hr=hr-12;
min+=" PM";
}else{
min+=" AM";
}
Flash.flashMessage("Draft saved at "+hr+":"+min);
}else{
if(_d){
if_returnToMessage();
}else{
if_returnToList(false);
}
Flash.flashMessage(_e.message);
}
}

function asyncUploadFile(_1){
var _2="asyncUpload"+Math.floor(Math.random()*99999);
var _3=new Element("div");
$(_3).setHTML("<iframe style='display: none;' src='about:blank' id='"+_2+"' name='"+_2+"' onload='asyncUploadCompleted(\""+_2+"\")'></iframe>");
$("comp-wrapper").adopt(_3);
$(_1).setProperty("target",_2);
return true;
}
function asyncUploadCompleted(_4){
var _5=$(_4);
if(_5==null){
return;
}
if(_5.contentWindow!=null){
_5=_5.contentWindow.document;
}else{
if(_5.contentDocument!=null){
_5=_5.contentDocument;
}else{
_5=window.frames[_4].document;
}
}
if(_5.location.href=="about:blank"){
return;
}
_5.location.href="about:blank";
var _6=if_checkRemoteResult(_5.body.innerHTML);
if(!_6){
return;
}
$(_4).getParent().remove();
var _7=new Element("li");
_7.setHTML(_6.filename+" ("+_6.type+", "+_6.size+" bytes)"+" (<a href=\"#\" onclick=\"comp_removeAttachment('"+escape(_6.filename)+"');return false\">remove</a>)");
$("comp-attachlist").adopt(_7);
var _8=new Element("input");
_8.type="hidden";
_8.name="comp-attach[]";
_8.value=_6.filename;
$("compose").adopt(_8);
$("comp-attachfile").value="";
}
function comp_removeAttachment(_9){
var _a=$A($("compose").getElementsByTagName("input"));
var _b=$A($("comp-attachlist").getElementsByTagName("li"));
var _c=false;
_9=unescape(_9);
for(var i=0;i<_a.length;i++){
var _e=_a[i];
if(_e.type=="hidden"&&_e.value==_9){
_c=true;
_e.remove();
}
}
for(var i=0;i<_b.length;i++){
var _10=_b[i];
if(_10.getText().contains(_9)){
_10.remove();
}
}
if(_c){
new Ajax("ajax.php",{postBody:"request=removeAttachment&filename="+encodeURIComponent(_9),onComplete:function(_11){
comp_attachmentDeleted(_11);
},onFailure:function(_12){
if_remoteRequestFailed(_12);
}}).request();
}
}
function comp_attachmentDeleted(_13){
var _14=if_checkRemoteResult(_13);
}

var OptionsEditorClass=new Class({initialize:function(_1){
this.wrapper=_1;
},showEditor:function(_2){
clearTimeout(refreshTimer);
if_remoteRequestStart();
new Ajax("ajax.php",{postBody:"request=settingsPanel&tab="+encodeURIComponent(_2),onComplete:this.showEditorCB.bind(this),onFailure:if_remoteRequestFailed}).request();
},showEditorCB:function(_3){
var _4=if_checkRemoteResult(_3);
if(!_4){
return;
}
if_hideWrappers();
if_hideToolbars();
$("opts-bar").style.display="block";
$(this.wrapper).style.display="block";
$(this.wrapper).setHTML(_4.htmlFragment);
if(_4.mailboxes){
MailboxManager.mailboxCache=_4.mailboxes;
}
},closePanel:function(){
if_hideWrappers();
if_hideToolbars();
$("list-bar").style.display="block";
$("list-wrapper").style.display="block";
Messages.fetchMailboxList();
},generateQueryString:function(_5){
var _6=$A($(_5).getElementsByTagName("input"));
_6.extend($A($(_5).getElementsByTagName("select")));
var _7=new Array();
for(var i=0;i<_6.length;i++){
var _9="";
switch(_6[i].type){
case "checkbox":
if(_6[i].checked){
_9="true";
}else{
_9="false";
}
break;
default:
_9=_6[i].value;
break;
}
_7.push(_6[i].id+"="+encodeURIComponent(_9));
}
return _7.join("&");
},saveOptions:function(){
if_remoteRequestStart();
new Ajax("ajax.php",{postBody:"request=settingsPanelSave&"+this.generateQueryString("opts-settings"),onComplete:this.saveOptionsCB.bind(this),onFailure:if_remoteRequestFailed}).request();
},saveOptionsCB:function(_a){
var _b=if_checkRemoteResult(_a);
if(!_b){
return;
}
if(_b.errors&&_b.errors.length>0){
alert("There were some errors saving your settings.\nAny valid settings were saved.\n\n"+_b.errors.join("\n"));
}else{
this.closePanel();
}
opts_getCB(_b);
list_show();
},identity_add:function(){
$("opts-identity-name").value="";
$("opts-identity-address").value="";
if(!$("opts-identity-new")){
var _c=new Element("option",{"id":"opts-identity-new"});
_c.appendText("new identity");
$("opts-identity-list").adopt(_c);
$("opts-identity-list").value="new identity";
}
$("opts-identity-save").onclick=OptionsEditor.identity_add_done.bind(this);
return false;
},identity_add_done:function(){
var _d=$("opts-identity-name").value;
var _e=$("opts-identity-address").value;
if(_d==""||_e==""){
Flash.flashMessage("Can't add an identity with a blank name or blank e-mail.");
return false;
}
new Ajax("ajax.php",{postBody:"request=identityEditor&action=add&idname="+encodeURIComponent(_d)+"&idemail="+encodeURIComponent(_e),onComplete:this.identity_actionCB.bind(this),onFailure:if_remoteRequestFailed}).request();
return false;
},identity_edit:function(){
if($("opts-identity-new")){
$("opts-identity-new").remove();
}
var _f=$("opts-identity-list");
if(_f.value==""){
return false;
}
var _10=_f.value;
var _11=_10.split(",");
var _12=_11.shift();
var _13=_11.join(",");
$("opts-identity-name").value=_13;
$("opts-identity-address").value=_12;
$("opts-identity-save").onclick=function(){
return OptionsEditor.identity_edit_done(_12);
};
return false;
},identity_edit_done:function(_14){
var _15=$("opts-identity-name").value;
var _16=$("opts-identity-address").value;
if(_15==""||_16==""){
Flash.flashMessage("Can't edit an identity to have a blank name or blank e-mail.");
return false;
}
new Ajax("ajax.php",{postBody:"request=identityEditor&action=edit&idname="+encodeURIComponent(_15)+"&idemail="+encodeURIComponent(_16)+"&oldid="+encodeURIComponent(_14),onComplete:this.identity_actionCB.bind(this),onFailure:if_remoteRequestFailed}).request();
return false;
},identity_setdefault:function(){
var _17=$("opts-identity-list");
if(_17.value==""){
return false;
}
var _18=_17.value;
_18=_18.split(",");
var _19=_18.shift();
var _1a=_18.join(",");
new Ajax("ajax.php",{postBody:"request=identityEditor&action=setdefault&oldid="+encodeURIComponent(_19),onComplete:this.identity_actionCB.bind(this),onFailure:if_remoteRequestFailed}).request();
return false;
},identity_remove:function(){
var _1b=$("opts-identity-list");
if(_1b.value==""){
return false;
}
var _1c=_1b.value;
_1c=_1c.split(",");
var _1d=_1c[0];
var _1e=_1c[1];
new Ajax("ajax.php",{postBody:"request=identityEditor&action=delete&oldid="+encodeURIComponent(_1d),onComplete:this.identity_actionCB.bind(this),onFailure:if_remoteRequestFailed}).request();
return false;
},identity_actionCB:function(_1f){
var _20=if_checkRemoteResult(_1f);
if(!_20){
return;
}
OptionsEditor.showEditor("identities");
}});
function opts_get(){
new Ajax("ajax.php",{postBody:"request=getUserSettings",onComplete:function(_21){
opts_getCB(_21);
},onFailure:if_remoteRequestFailed}).request();
}
function opts_getCB(_22){
var _23=if_checkRemoteResult(_22);
if(!_23){
return;
}
userSettings=_23.settings;
}

