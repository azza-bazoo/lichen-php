<?php
//------------------------------------------------------------------------------
//
// Lichen configuration file
//
// To get started, set these options and save this file as lichen-config.php
//
//------------------------------------------------------------------------------

// Name and external domain of the SMTP server that Lichen will use to send
// mail (the domain is the part after @ in your return e-mail address).
$SMTP_SERVER = "localhost";
$SMTP_DOMAIN = $_SERVER['SERVER_NAME'];

// Location to store user settings and uploaded attachments.
// This directory must be writable by the web server, and ideally should not
// be inside your web root -- see the installation guide.
$LICHEN_DATA = "./data/";

// Name of the IMAP server from which Lichen will read mail.
$IMAP_SERVER = "localhost";


//------------------------------------------------------------------------------
//
// Advanced options
//
//------------------------------------------------------------------------------

// Names of the three special IMAP folders.
$SPECIAL_FOLDERS['inbox']  = "INBOX";
$SPECIAL_FOLDERS['sent']   = "Sent";
$SPECIAL_FOLDERS['drafts'] = "Drafts";
$SPECIAL_FOLDERS['trash']  = "Trash";

// By default, unencrypted port 143 will be used when the IMAP server is
// localhost, and SSL on port 993 will be used for any other server.
// To override this, set $IMAP_FORCE_NO_SSL to true and specify a port number.
// If you use TLS instead of SSL, set $IMAP_USE_TLS to true. If you do use SSL
// but have a self-signed certificate, set $IMAP_CHECK_CERTS to false.
$IMAP_PORT         = 0;
$IMAP_FORCE_NO_SSL = false;
$IMAP_USE_TLS      = false;
$IMAP_CHECK_CERTS  = true;

// Port to use when connecting to the SMTP server -- usually 25, 465 (secure),
// or 587 -- and whether or not to use SSL for this connection.
// If $SMTP_AUTH is true, Lichen will authenticate before sending mail using
// the same credentials as for the IMAP server.
$SMTP_PORT    = 25;
$SMTP_USE_SSL = false;
$SMTP_USE_TLS = false;
$SMTP_AUTH    = true;

// Date formats to use for new/old messages in mailbox lists, and when showing
// a single message. The letters are those used by PHP's date function; see
// http://php.net/manual/en/function.date.php
$DATE_FORMAT_NEW = 'g:i A l';
$DATE_FORMAT_OLD = 'j M g:i A';
$DATE_FORMAT_LONG = 'D&\nb\sp;j&\nb\sp;F&\nb\sp;Y';
$DATE_FORMAT_MSG = 'g:i A D&\nb\sp;j&\nb\sp;F&\nb\sp;Y';

// Maximum size of attachment uploads, in bytes. This is only a guide for the
// client browser; the actual limit is set by the values upload_max_filesize
// and post_max_size in your php.ini file.
$UPLOAD_ATTACHMENT_MAX = 10485760;

// To override the default settings for new users, define parameters here.
// To force changes to be merged with existing user settings next time they
// log in, increment defaults_version.
$DEFAULT_SETTINGS = array(
	"defaults_version" => 2
);

?>
