<?php
/**
 * TCSmailinglists Plugin: mail distribution software like mailman
 *
 * @license	GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author	 Peter Spaeth <info4spaeth@gmx.de>  
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../'));
if(!defined('TCS_MAILARCHIV')) define('TCS_MAILARCHIV', '/data/media/email/');

// get all subscribers of all distribution lists
if(!defined('SUBSCRIBERS_LIST')) define('SUBSCRIBERS_LIST',
	'SELECT `tcs22user`.`user_Email`, `tcs22group`.`email` FROM `tcs22user`, `tcs22group`, `tcs22usergroupmap` '.
	'WHERE `tcs22group`.`email` IS NOT NULL AND `tcs22user`.`user_Id`=`tcs22usergroupmap`.`userid` AND `tcs22group`.`id` = `tcs22usergroupmap`.`groupid`  '.
	'AND (`tcs22user`.`Eintritt`<NOW() OR `tcs22user`.`Eintritt` IS NULL) AND (`tcs22user`.`Austritt`>NOW() OR `tcs22user`.`Austritt` IS NULL) '.
	'ORDER BY `tcs22group`.`email`, `tcs22user`.`user_Email`;');

// get all distributers of all distribution lists
if(!defined('DISTRIBUTORS_LIST')) define('DISTRIBUTORS_LIST',
	'SELECT `tcs22user`.`user_Email`, `tcs22group`.`email` FROM `tcs22user`, `tcs22group`, `tcs22usergroupmap` '.
	'WHERE `tcs22group`.`email` IS NOT NULL AND `tcs22user`.`user_Id`=`tcs22usergroupmap`.`userid` AND `tcs22group`.`gid_send` = `tcs22usergroupmap`.`groupid` '.
	'AND (`tcs22user`.`Eintritt`<NOW() OR `tcs22user`.`Eintritt` IS NULL) AND (`tcs22user`.`Austritt`>NOW() OR `tcs22user`.`Austritt` IS NULL) '.
	'ORDER BY `tcs22group`.`email`, `tcs22user`.`user_Email`;');

if(!defined('EMAIL_SIZE_ERR_MSG')) define('EMAIL_SIZE_ERR_MSG',
	"Lieber Sender,\r\n\r\ndie Email ist <#TCSemailSize#> MB gross! Maximal erlaubt sind 8 MB!");
	
require_once realpath('/usr/home/tauche/.secret/TCSpasswd.php');
require_once realpath(DOKU_INC.'/TCSenhancement/TCStools.php');

// PHP Mailer settings
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;
require_once realpath(DOKU_INC.'/TCSenhancement/PHPMailer/src/Exception.php');
require_once realpath(DOKU_INC.'/TCSenhancement/PHPMailer/src/PHPMailer.php');
require_once realpath(DOKU_INC.'/TCSenhancement/PHPMailer/src/SMTP.php');

// run every member of every distribution list
$subscribers = readDB(array(SUBSCRIBERS_LIST));

// distribution permission
$distributors = readDB(array(DISTRIBUTORS_LIST));

// echo "subscribers: ".print_r($subscribers, TRUE)."\r\n";
// echo "distributors: ".print_r($distributors, TRUE)."\r\n";

distributeEmails($subscribers, $distributors);
return;

function readDB($SQL) {
	if($DBlink = mysqli_connect(DB_HOST, DB_READUSER, DB_READPASSWORD, DATABASE)) {
//		$SQL = str_replace('<#TCSuid#>', $INFO['userinfo']['uid'], $SQL);
		foreach($SQL as &$query) {
			if($result = mysqli_query($DBlink, $query)) {
				while($ret[] = mysqli_fetch_row($result));
			}
			array_pop($ret);
			mysqli_free_result($result);
		}
		mysqli_close($DBlink);	
	}
	return $ret;
}

// 
function distributeEmails($subscribers, $distributors) {
	// run only if list is not empty
	if(count($subscribers)) {
		$email_subscriber = $subscribers[0][1];
		$members = array();
		// run through all distribution lists
		foreach($subscribers as &$subscriber) {
			// if new list look for emails and send it to members
			if($subscriber[1] != $email_subscriber) {
				getEmails($email_subscriber, $members, $distributors);
				$email_subscriber = $subscriber[1];
				$members = array();
			}
			// else add subscriber to member list
			$members[] = $subscriber[0];
		}
		getEmails($email_subscriber, $members, $distributors);
	}
	return;
}

// send email to distribution list
function sendBulkEmail($tos, $replyTo, $distributionEmail, $subject, $body, $HTMLbody, $attachments) {
//				file_put_contents('./TCSmaildistribution.log', "\r\ndistribution: ".$distributionEmail."\r\nsubject: ".$subject."\r\nbody: ".strlen($body).'!'.$body."\r\nHTMLbody".strlen($HTMLbody).'!'.$HTMLbody, FILE_APPEND);
	list($mail_usr, $domain) = explode('@', $distributionEmail);
// file_put_contents(realpath('').'/'.$mail_usr.'-'.date("YmdHis").'.txt', "HTMLbody\r\n".$HTMLbody."\r\n\r\nAltBody\r\n".$body."\r\n\r\nPath: ".realpath('')."\r\nPath2: ".realpath(dirname(__FILE__))."\r\nPath3: ".DOKU_INC);
// file_put_contents(DOKU_INC.$mail_usr.'-'.date("YmdHis").'.txt', "HTMLbody\r\n".$HTMLbody."\r\n\r\nAltBody\r\n".$body."\r\n\r\nPath: ".realpath('')."\r\nPath2: ".realpath(dirname(__FILE__))."\r\nPath3: ".DOKU_INC);
	try {
		$phpmailer = new PHPMailer(TRUE);
//		$phpmailer->SMTPDebug = SMTP::DEBUG_SERVER; 
		$phpmailer->isSMTP();
		$phpmailer->Host = 'mail.your-server.de';
		$phpmailer->SMTPAuth = TRUE;
		$phpmailer->SMTPKeepAlive = TRUE; //SMTP connection will not close after each email sent, reduces SMTP overhead
		$phpmailer->Port = 587;
		$phpmailer->SMTPSecure = 'tls';
		// Distribution über TCSnachricht, weil Züblins out-of-office-Meldungen an alle verteilt wurden.
		$phpmailer->Username = 'TCSnachricht@tauchclub-stuttgart.de';
		$phpmailer->Password = EMAIL_DISTRIBUTOR['TCSnachricht@tauchclub-stuttgart.de'];
		$phpmailer->CharSet = 'UTF-8';
		$phpmailer->Encoding = 'base64';
		$phpmailer->setFrom('TCSnachricht@tauchclub-stuttgart.de', $mail_usr);
		$phpmailer->addReplyTo($replyTo);
		$phpmailer->Subject = $subject;
		// HTML mail?
		if(strlen($HTMLbody)) {
			$phpmailer->isHTML(TRUE);
			$phpmailer->Body = $HTMLbody;
			$phpmailer->AltBody = $body;
		} else { 
			$phpmailer->isHTML(FALSE);
			$phpmailer->Body = " ";
			if(strlen($body)) {
				$phpmailer->Body = $body;
			}
		}
		// attachments?
		foreach($attachments as &$attachment) {
			if($attachment['is_attachment']) {
				$phpmailer->addStringAttachment($attachment['attachment'], $attachment['name']);
			}
		}

		// sent email to distribution list
		foreach($tos as &$to) {
			$phpmailer->clearAddresses();
			$phpmailer->addAddress($to);

			// don't send email to sender
//			if(strtolower($to) != strtolower($replyTo)) {
				// send email//
				if($phpmailer->send()) {
					echo $distributionEmail.": Message from ".$replyTo." to ".$to." with subject ".$subject." has been sent!\r\n";
				} else {
					echo $distributionEmail.": Message from ".$replyTo." to ".$to." with subject ".$subject." could not be sent! Mailer Error: ".$phpmailer->ErrorInfo."\r\n";
				}
//			}
		}
		$phpmailer->clearAddresses();
		$phpmailer->clearAttachments();
		$phpmailer->smtpClose();
		$retCode = TRUE;
	} catch (Exception $err) {
		echo $distributionEmail.": Message from ".$replyTo." to ".$to." with subject ".$subject." could not be sent!\r\nMailer Error: ".$err->getMessage()."\r\n";
		$retCode = FALSE;
	}
	return $retCode;
}

	// read emails and send each to subscribers of distribution list
	function getEmails($usr, $members, $distributors) {
		list($usr_name, $domain) = explode('@', $usr);
		$folder_archiv = DOKU_INC.TCS_MAILARCHIV.$usr_name.'/tmp/';
		$srv = '{mail.your-server.de:993/imap/ssl}'.'INBOX';
		$dir_spam = 'INBOX.spambucket';
		$dir_archiv = 'INBOX.archiv';
		$dir_error = 'INBOX.error';
		$pw = EMAIL_DISTRIBUTOR[$usr];
		$email_size_limit = 8*1024*1024;	// email size limited to 8 MB

		// read mailbox content
		$mailbox = imap_open($srv, $usr, $pw) or die('Cannot connect to mailserver: '.imap_last_error());
		if($emails = imap_search($mailbox, 'ALL', SE_UID))	{	//;			//'ON '.date('Y-m-d'));

		// read every email in mailbox
			foreach($emails as &$email) {
				$structure = imap_fetchstructure($mailbox, $email, FT_UID);
//			$header = imap_headerinfo($mailbox, $email);
//			$from = imap_utf8($header->from[0]->mailbox.'@'.$header->from[0]->host);
				$header = imap_fetch_overview($mailbox, $email, FT_UID);
//			file_put_contents(realpath('../lib/plugins/tcsmaildistributor').'/tcsmaildistributor.log', print_r($header, TRUE)."\r\n", FILE_APPEND);
				if(preg_match('/(?:<)(.+)(?:>)$/', imap_utf8($header[0]->from), $matches)) $from = $matches[1];
				else $from = imap_utf8($header[0]->from);
				$email_size = $header[0]->size;
//			file_put_contents(realpath('../lib/plugins/tcsmaildistributor').'/tcsmaildistributor.log', print_r($matches, TRUE)."\r\n", FILE_APPEND);
				// member of distribution list (subscribed and distributing permission)
//				echo "from: ".$from." size: ".$header[0]->size."\r\n";
				$HTMLmessage = '';
				$message = '';
				// without permission move email to spam directory
				if(has_permission($usr, $from, $distributors)) {
					// if email to big send information to sender and move email to error directory
					if($email_size > $email_size_limit) {
						imap_mail_move($mailbox, $header[0]->uid, $dir_error, CP_UID);
						$email_size_MB = number_format($email_size / 1024 / 1024, 1, '.', ',') ;		// calculate bytes to mega bytes
						sendBulkEmail(array($from), $from, $usr, imap_utf8("mail size error: ".$header[0]->subject), str_replace('<#TCSemailSize#>', $email_size_MB, EMAIL_SIZE_ERR_MSG), NULL, NULL);
						echo date('Y-m-d H:i:s').' '.$usr.": Message with subject: ".imap_utf8($header[0]->subject)." from ".$from." is to big (".$email_size_MB." MB). Therefore is not distributed but moved to error folder!\r\n";
					} else {
						// size limit
						// if($header->size < 5000) {  // Achtung keine Ende-Klammer
						$HTMLmessage = get_part($mailbox, $email, "TEXT/HTML");
						// if HTML body is empty, try getting text body
						if ($HTMLmessage == "") {
							$message = get_part($mailbox, $email, "TEXT/PLAIN");
						}

						$attachments = array();

						if(isset($structure->parts) && count($structure->parts)) {
							for($i = 0; $i < count($structure->parts); $i++) {
								$attachments[$i] = array(
									'is_attachment' => false,
									'filename' => '',
									'name' => '',
									'attachment' => '',
									'encoding' => '',
									'type' => '',
									'disposition' => '',
								);
								if($structure->parts[$i]->ifdparameters) {
									foreach($structure->parts[$i]->dparameters as $object) {
										if(strtolower($object->attribute) == 'filename') {
											$attachments[$i]['is_attachment'] = true;
											$attachments[$i]['filename'] = $object->value;
										}
									}
								}

								if($structure->parts[$i]->ifparameters) {
									foreach($structure->parts[$i]->parameters as $object) {
										if(strtolower($object->attribute) == 'name') {
											$attachments[$i]['is_attachment'] = true;
											$attachments[$i]['name'] = $object->value;
										}
									}
								}

								if($attachments[$i]['is_attachment']) {
									$attachments[$i]['attachment'] = imap_fetchbody($mailbox, $email, $i+1, FT_UID);
									if($structure->parts[$i]->encoding == ENCBASE64) { 		//3 = BASE64 encoding 
										$attachments[$i]['attachment'] = imap_base64($attachments[$i]['attachment']);
									}
									elseif($structure->parts[$i]->encoding == ENCQUOTEDPRINTABLE) {	//4 = QUOTED-PRINTABLE encoding
										$attachments[$i]['attachment'] = imap_qprint($attachments[$i]['attachment']);
									}

									if(empty($attachments[$i]['name'])) $attachments[$i]['name'] = $attachments[$i]['filename']; 
									if(empty($attachments[$i]['name'])) $attachments[$i]['name'] = time().'.dat'; 
								}
							}

						}
						// send email and move to archive
//						echo "sendBulkEmail(".$from.", ".$usr.", ".imap_utf8($header[0]->subject).")\r\n";
						if(sendBulkEmail($members, $from, $usr, imap_utf8($header[0]->subject), $message, $HTMLmessage, $attachments)) {
							imap_mail_move($mailbox, $header[0]->uid, $dir_archiv, CP_UID);
							echo date('Y-m-d H:i:s').' '.$usr.": Message with subject: ".imap_utf8($header[0]->subject)." from ".$from." distributed and moved to archive folder!\r\n";
						} else {
							imap_mail_move($mailbox, $header[0]->uid, $dir_error, CP_UID);
							echo date('Y-m-d H:i:s').' '.$usr.": Message with subject: ".imap_utf8($header[0]->subject)." from ".$from." not distributed but moved to error folder!\r\n";
						}	
					}
				}
				// distributing not allowed (not part of list)
				else {
					imap_mail_move($mailbox, $header[0]->uid, $dir_spam, CP_UID);
					echo date('Y-m-d H:i:s').' '.$usr.": Message with subject: ".imap_utf8($header[0]->subject)." from ".$from." moved to spam folder!\r\n";
				}
			}
		}
		imap_expunge($mailbox);
		imap_close($mailbox);
		return $maillist;
	}

	// has permission to send out of this mailbox
	function has_permission($owner, $search, $distributors) {
		$ret = FALSE;
// echo print_r($distributors, TRUE)."\r\nOwner: ".$owner."\r\nsearch: ".$search."\r\n";
		foreach($distributors as &$distributor) {
			if((strtolower($search) == strtolower($distributor[0])) && (strtolower($owner) == strtolower($distributor[1]))) $ret = TRUE;
		}
		return $ret;
	}


	// stackoverflow code extract only HTML from imap_body
	function get_part($imap, $uid, $mimetype, $structure = false, $partNumber = false)
	{
		if (!$structure) {
			$structure = imap_fetchstructure($imap, $uid, FT_UID);
		}
		if ($structure) {
			if ($mimetype == get_mime_type($structure)) {
				if (!$partNumber) {
					$partNumber = 1;
				}
				$text = imap_fetchbody($imap, $uid, $partNumber, FT_UID);
				switch ($structure->encoding) {
					case 3:
						return imap_base64($text);
					case 4:
						return imap_qprint($text);
					default:
						return $text;
				}
			}

			// multipart
			if ($structure->type == 1) {
				foreach ($structure->parts as $index => $subStruct) {
					$prefix = "";
					if ($partNumber) {
						$prefix = $partNumber . ".";
					}
					$data = get_part($imap, $uid, $mimetype, $subStruct, $prefix . ($index + 1));
					if ($data) {
						return $data;
					}
				}
			}
		}
		return '';
	}

	function get_mime_type($structure)
	{
		$primaryMimetype = ["TEXT", "MULTIPART", "MESSAGE", "APPLICATION", "AUDIO", "IMAGE", "VIDEO", "OTHER"];

		if ($structure->subtype) {
			return $primaryMimetype[(int)$structure->type] . "/" . $structure->subtype;
		}
		return "TEXT/PLAIN";
	}

?>
