<?php
$preview = '';
//=== don't allow direct access 
if (!defined('BUNNY_PM_SYSTEM')) 
{
	$HTMLOUT ='';
	$HTMLOUT .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
        <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
        <head>
        <meta http-equiv="content-type" content="text/html; charset=iso-8859-1" />
        <title>ERROR</title>
        </head><body>
        <h1 style="text-align:center;">ERROR</h1>
        <p style="text-align:center;">How did you get here? silly rabbit Trix are for kids!.</p>
        </body></html>';
	echo $HTMLOUT;
	exit();
}

    $save_or_edit = (isset($_POST['edit']) ? 'edit' : (isset($_GET['edit']) ? 'edit' : 'save'));
    $save_or_edit = (isset($_POST['send']) ? 'send' : (isset($_GET['send']) ? 'send' : $save_or_edit));
            
    if (isset($_POST['buttonval']) && $_POST['buttonval'] == $save_or_edit)
        {

        //=== make sure they wrote something :P
        if (empty($_POST['subject'])) 
            stderr('Error!','To save a message in your draft folder, it must have a subject!');
        if (empty($_POST['body'])) 
            stderr('Error!','To save a message in your draft folder, it must have body text!');

                //=== check to see they have everything or...
                $body = sqlesc(trim($_POST['body']));
                $subject = sqlesc(strip_tags(trim($_POST['subject'])));
                $urgent = sqlesc((isset($_POST['urgent']) && $_POST['urgent'] == 'yes' &&  $CURUSER['class'] >= UC_STAFF) ? 'yes' : 'no');

                if ($save_or_edit === 'save')
                    {
                    sql_query('INSERT INTO messages (sender, receiver, added, msg, subject, location, draft, unread, saved) VALUES  
                                                                        ('.sqlesc($CURUSER['id']).', '.sqlesc($CURUSER['id']).','.TIME_NOW.', '.$body.', '.$subject.', \'-2\', \'yes\',\'no\',\'yes\')') or sqlerr(__FILE__, __LINE__);
                    }
                elseif ($save_or_edit === 'edit')
                    {
                    sql_query('UPDATE messages SET msg = '.$body.', subject = '.$subject.' WHERE id = '.sqlesc($pm_id)) or sqlerr(__FILE__, __LINE__);
                    }
               elseif ($save_or_edit === 'send')
                    {
                        
            //=== Try finding a user with specified name
            $res_receiver = sql_query('SELECT id, class, acceptpms, notifs, email, class, username FROM users WHERE LOWER(username)=LOWER('.sqlesc(htmlsafechars($_POST['to'])).') LIMIT 1');
            $arr_receiver = mysqli_fetch_assoc($res_receiver);

                if (!is_valid_id($arr_receiver['id'])) 
                    stderr('Error','Sorry, there is no member with that username.');    
                    
            $receiver = intval($arr_receiver['id']);

        //=== allow suspended users to PM / forward to staff only
        if ($CURUSER['suspended'] === 'yes')
            {
            $res = sql_query('SELECT class FROM users WHERE id = '.sqlesc($receiver)) or sqlerr(__FILE__, __LINE__);
            $row = mysqli_fetch_assoc($res);
        
                if ($row['class'] < UC_STAFF) 
                    stderr('Error', 'Your account is suspended, you may only contact staff members!');
            }

        //=== make sure they have space
        $res_count = sql_query('SELECT COUNT(id) FROM messages WHERE receiver = '.sqlesc($receiver).' AND location = 1') or sqlerr(__FILE__, __LINE__);
        $arr_count = mysqli_fetch_row($res_count);

            if ($arr_count[0] >= $maxbox  && $CURUSER['class'] < UC_STAFF) 
                stderr('Sorry', 'Members PM box is full.');

        //=== Make sure recipient wants this message
		if ($CURUSER['class'] < UC_STAFF)
            {
            $should_i_send_this = ($arr_receiver['acceptpms'] == 'yes' ? 'yes' : ($arr_receiver['acceptpms'] == 'no' ? 'no' : ($arr_receiver['acceptpms'] == 'friends' ? 'friends' : '')));
               
            switch($should_i_send_this)
                {
                case 'yes':
                    $r = sql_query('SELECT id FROM blocks WHERE userid = '.sqlesc($receiver).' AND blockid = '.sqlesc($CURUSER['id'])) or sqlerr(__FILE__, __LINE__);
                    $block = mysqli_fetch_row($r);
                    
                        if ($block[0] > 0) 
                            stderr('Refused', htmlsafechars($arr_receiver['username']).' has blocked PMs from you.');
                    break;
                case 'friends':
                    $r = sql_query('SELECT id FROM friends WHERE userid = '.sqlesc($receiver).' AND friendid = '.sqlesc($CURUSER['id'])) or sqlerr(__FILE__, __LINE__);
                    $friend = mysqli_fetch_row($r);
			
                        if ($friend[0] > 0)
                            stderr('Refused', htmlsafechars($arr_receiver['username']).' only accepts PMs from members in their friends list.');
                    break;		
                case 'no':
                    stderr('Refused', htmlsafechars($arr_receiver['username']).' does not accept PMs.');
                    break;		
				}
	  }

    //=== ok all is well... post the message :D
    sql_query('INSERT INTO messages (poster, sender, receiver, added, msg, subject, saved, unread, location, urgent) VALUES 
                            ('.sqlesc($CURUSER['id']).', '.sqlesc($CURUSER['id']).', '.$receiver.', '.TIME_NOW.', '.$body.', '.$subject.', \'yes\', \'yes\', 1,'.$urgent.')') or sqlerr(__FILE__, __LINE__);
      $mc1->delete_value('inbox_new_'.$receiver);
      $mc1->delete_value('inbox_new_sb_'.$receiver);
        //=== make sure it worked then...
        if (mysqli_affected_rows($GLOBALS["___mysqli_ston"]) === 0)
            stderr('Error','Messages wasn\'t sent!');
    	
        //=== if they just have to know about it right away... send them an email (if selected if profile)
	  if (strpos($arr_receiver['notifs'], '[pm]') !== false)
        {
	    $username = htmlsafechars($CURUSER['username']);
$body = <<<EOD
You have received a PM from $username!

You can use the URL below to view the message (you may have to login).

{$INSTALLER09['baseurl']}/pm_system.php

--
{$INSTALLER09['site_name']}
EOD;
	    @mail($user['email'], 'You have received a PM from '.$username.'!',
	    	$body, "From: {$INSTALLER09['site_email']}");
	  }
     
        //=== if returnto sent
        if ($returnto)
            header('Location: '.$returnto);
        else
            header('Location: pm_system.php?action=view_mailbox&sent=1');
        die();

    }
                    
            //=== Check if messages was saved as draft
            if (mysqli_affected_rows($GLOBALS["___mysqli_ston"]) === 0)
                stderr('Error','Draft wasn\'t saved!');

        header('Location: /pm_system.php?action=view_mailbox&box=-2&new_draft=1');
    die();
    }//=== end save draft

         
    //=== Code for preview Retros code
    if (isset($_POST['buttonval']) && $_POST['buttonval'] == 'preview')
        {
        $subject = htmlsafechars(trim($_POST['subject']));
        $draft = trim($_POST['body']);

    $preview = '
    <table border="0" cellspacing="0" cellpadding="5" align="center" style="max-width:800px">
    <tr>
        <td align="left" colspan="2" class="colhead"><span style="font-weight: bold;">subject: </span>'. htmlsafechars($subject).'</td>
    </tr>
    <tr>
        <td align="center" valign="top" class="one" width="80px" id="photocol">'.avatar_stuff($CURUSER).'</td>
        <td class="two" style="min-width:400px;padding:10px;vertical-align: top;text-align: left;">'.format_comment($draft).'</td>
    </tr>
    </table><br />';
        }
        else
            {
            //=== Get the info
            $res = sql_query('SELECT * FROM messages WHERE id='.sqlesc($pm_id)) or sqlerr(__FILE__,__LINE__);
            $message = mysqli_fetch_assoc($res);
            
            $subject = htmlsafechars($message['subject']);
            $draft = $message['msg'];
            }
            

    //=== print out the page
    //echo stdhead('Use Draft');

    $HTMLOUT .='<h1>Use Draft: '.$subject.'</h1>'.$top_links.$preview.'
        <form name="compose" action="pm_system.php" method="post">
        <input type="hidden" name="id" value="'.$pm_id.'" />
        <input type="hidden" name="'.$save_or_edit.'" value="1" />
        <input type="hidden" name="action" value="use_draft" />
    <table border="0" cellspacing="0" cellpadding="5" align="center" style="max-width:800px">
    <tr>
        <td class="colhead" align="left" colspan="2">use draft</td>
    </tr>
    <tr>
        <td align="right" class="one" valign="top"><span style="font-weight: bold;">To:</span></td>
        <td align="left" class="one" valign="top"><input type="text" name="to" value="'.((isset($_POST['to']) && validusername($_POST['to'], FALSE)) ? htmlsafechars($_POST['to']) : 'Enter Username').'" class="member" onfocus="this.value=\'\';" />
         [ enter the username of the member you would like to send this to ]</td>
    </tr>
    <tr>
        <td class="one" valign="top" align="right"><span style="font-weight: bold;">Subject:</span></td>
        <td class="one" valign="top" align="left"><input type="text" class="text_default" name="subject" value="'.$subject.'" /></td>
    </tr>
    <tr>
        <td class="one" valign="top" align="right"><span style="font-weight: bold;">Body:</span></td>
        <td class="one" valign="top" align="left">'.BBcode($draft, FALSE).'</td>
    </tr>
    <tr>
        <td colspan="2" align="center" class="one">'.($CURUSER['class'] >= UC_STAFF ? '
        <input type="checkbox" name="urgent" value="yes" '.((isset($_POST['urgent']) && $_POST['urgent'] === 'yes') ? ' checked="checked"' : '').' /> 
        <span style="font-weight: bold;color:red;">Mark as URGENT!</span>' : '').'
        <input type="submit" class="button" name="buttonval" value="preview" onmouseover="this.className=\'button_hover\'" onmouseout="this.className=\'button\'" />
        <input type="submit" class="button" name="buttonval" value="'.$save_or_edit.'" onmouseover="this.className=\'button_hover\'" onmouseout="this.className=\'button\'" /></td>
    </tr>
    </table></form>';
?>
