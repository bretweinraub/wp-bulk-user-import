<?
/*
Plugin Name: Bulk Import Members (Users)
Plugin URI: http://manojkumar.org/bulk-import-members-users/
Description: Bulk Import Members (Users) to your Wordpress MU + BuddyPress installation.
Version: 0.1
Author: Manoj Kumar
Author URI: http://manojkumar.org/
*/

/*  Copyright 2008-2009  Manoj Kumar (http://manojkumar.org/) (email : talk2manoj@gmail.com)

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/


require_once( 'bp-core.php' );
require_once( 'bp-user-import/bp-user-import-functions.php' );

function user_import_add_admin_menu() {
	global $wpdb, $bp;
	if ( is_site_admin() ) {
		//wp_enqueue_script( 'jquery.tablednd', site_url( MUPLUGINDIR . '/bp-core/js/jquery/jquery.tablednd.js' ), array( 'jquery' ), '0.4' );

		/* Add the administration tab under the "Site Admin" tab for site administrators */
		//add_submenu_page( 'wpmu-admin.php', __("Profile Fields", 'buddypress'), __("Profile Fields", 'buddypress'), 1, "xprofile_settings", "xprofile_admin" );
        add_submenu_page('wpmu-admin.php', 'Bulk Import Users', 'Bulk Import Users', 10, 'bp_user_import', 'bp_user_import');

	}
}
add_action( 'admin_menu', 'user_import_add_admin_menu' );


function fieldParseFunction($text){
	return explode("\n", trim($text));
}

function bp_user_import() {
  // Plugin content here will only be accessible by site admins
 

  $RecordErrors="";
  $ErrorCount=0;
  $complete=0;
  $Result = "";

  if (isset($_POST['data_submit'])) {
     ?><div id="message" class="updated fade"><p>
     <?php echo "Processing has been complete";?></p></div>
    
     <?php


        //Data Split is based on dd-import-users

		$delimiter = (string)$_POST['delimiter'];

		// Get form data into an array
		$user_data_temp = array();
		if(trim((string)$_POST["textarea_data"]) != ""){
			$user_data_temp = array_merge($user_data_temp, fieldParseFunction(((string) ($_POST["textarea_data"]))));
		}
		else{
			$Result .= "<p>There is no data in field. Please try again!</p>";
            echo "<div class='error'>".$Result."</div>";
		}

        //print_r($user_data_temp);

		$user_data = array();
		$i = 0;

		foreach ($user_data_temp as $ut) {

			if (trim($ut) != '') {

				// split out username, fullname and email
				if (! (list($my_user_name, $my_user_fullname, $my_user_email) = @split($delimiter, $ut, 3))){
					$Result .= "<p>Regex ".$delimiter." not valid.</p>";
                    echo "<div class='error'>".$Result."</div>";
				}

				// split out firstname and lastname from fullname
				list($my_user_fname, $my_user_lname)  = @split(' ', $my_user_fullname, 2);

				$my_user_name = trim($my_user_name);
				$my_user_fname = trim($my_user_fname);
				$my_user_lname = trim($my_user_lname);
				$my_user_email = trim($my_user_email);

				if (($my_user_name != '') && ($my_user_email != '')) {

					$user_data[$i]['username'] = $my_user_name;
					$user_data[$i]['firstname'] = $my_user_fname;
					$user_data[$i]['lastname'] = $my_user_lname;
					$user_data[$i]['email'] = $my_user_email;
					$i++;

				}

			}

        }// Close foreach


        $errors = array();
		$complete = 0;

		foreach ($user_data as $ud) {

			// check for errors
            //I am using core Wordpress Mu function to avoid any hacking
         //wpmu_validate_user_signup(username, email-id)
            //It will return error, if there is some issue
            //Also in Buddy Press there is one filter added at 209 to this function for
            //validating all xprofile fileds, so if you want more functionality check following function
            //xprofile_validate_signup_fields
            //add_filter('wpmu_validate_user_signup', 'xprofile_validate_signup_fields', 10, 1 );


			$u_errors = 0;

			$user_line = '<b>' . htmlspecialchars($ud['username']) . '|' . htmlspecialchars($ud['email']) . '</b>';


            $Result = wpmu_validate_user_signup($ud['username'],$ud['email']);

            extract($Result);

			


             if (!$errors->get_error_code()) {

                    // populate user data hash
                    $user_data = array( 'user_login' => $ud['username'], 'user_email' => $ud['email'], 'role' => $the_role, 'user_pass' => $password );

                    // Add first/last name if defined
                    if ( ($ud['firstname'] != '') && ($ud['lastname'] != '') ) {
                        $user_data['first_name'] = $ud['firstname'];
                        $user_data['last_name'] = $ud['lastname'];
                    }

                    $xprofile_meta1['field_1']=$ud['firstname']." ".$ud['lastname'];
                    $xprofile_meta1['xprofile_field_ids']="1,";
                    $xprofile_meta1['avatar_image_resized']=false;
                    $xprofile_meta1['avatar_image_original']=false;

                    $_SESSION['xprofile_meta']="";

                    $_SESSION['xprofile_meta']=$xprofile_meta1;

                    if ($_POST['mail_notifications']=='all'){
                        wpmu_signup_user($user_data['user_login'], $user_data['user_email'], apply_filters( "add_signup_meta", array()) );
                    }else{
                        my_wpmu_signup_user($user_data['user_login'], $user_data['user_email'], apply_filters( "add_signup_meta", array()) );
                    }

                    $complete++;

             }else{


                     if ( $errors->get_error_message('user_name') && $errors->get_error_message('user_email') && $errors->get_error_message('user_email_used')==null) {
                        $RecordErrors =$RecordErrors.$errors->get_error_message('user_name') .' - '. $errors->get_error_message('user_email') ." In ".$user_line."<br />";
                     }
                     if ( $errors->get_error_message('user_name') && $errors->get_error_message('user_email') && $errors->get_error_message('user_email_used')) {
                        $RecordErrors =$RecordErrors.$errors->get_error_message('user_name') .' - '. $errors->get_error_message('user_email') .' - '. $errors->get_error_message('user_email_used')." In ".$user_line."<br />";
                     }
                     if ( $errors->get_error_message('user_name') && $errors->get_error_message('user_email') == null ) {
                        $RecordErrors =$RecordErrors.$errors->get_error_message('user_name')." In ".$user_line."<br />";
                     }
                     if ( $errors->get_error_message('user_name') == null && $errors->get_error_message('user_email') ) {
                        $RecordErrors =$RecordErrors.$errors->get_error_message('user_email')." In ".$user_line."<br />";
                     }

                     $ErrorCount++;
            }


         }

         if ($ErrorCount>0){

            echo '<div class="error">'.$ErrorCount.' Error <br />'.$RecordErrors.'</div>';

        }
        if ($complete>0){

            echo '<div class="updated fade">'.$complete.' Record has been Sucsessfully imported <br />'.$RecordErrors.'</div>';

        }



    }



  ?>
  <div class="wrap">
  <div id="icon-users" class="icon32"><br /></div>
    <h2 id="add-new-user">Bulk Import Members (Users) to BuddyPress Site</h2>
        <p><?php _e('1. You can import UserName, FirstName, Lastname and Email. Where FirstName + LastName becomes FullName for BuddyPress', 'buddypress') ?></p>
        <p><?php _e('2. Delimit your data with "[|]" (pipe sign) like username|firstname lastname|email@domainname.com', 'buddypress') ?></p>
        <p><?php _e('3. Paste your data in the box below', 'buddypress') ?></p>
        <p><?php _e('4. Choose any of the 3 Email notification options', 'buddypress') ?></p>
        <p><?php _e('5. Click on the import user button', 'buddypress') ?></p>

		<p>For any suggestion, help or bug, please visit <a href="http://manojkumar.org/bulk-import-members-users-plugin-for-buddypress-site/">http://manojkumar.org/bulk-import-members-users-plugin-for-buddypress-site/</a></p>

        <form enctype="multipart/form-data" method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>"  >
            <input type="hidden" name="data_submit" id="data_submit" value="true" />


            <div class="form-table">
                <input type="hidden" name="delimiter" value="[|]" />
                    <table id="group_1" class="widefat">
                            <thead>
                                <tr class="nodrag">
                                    <th scope="col" colspan="2"><strong>Copy and Paste this line below to check&nbsp;&nbsp;&raquo;&nbsp;&nbsp;</strong>username|firstname lastname|email@domainname.com</th>
                                </tr>

                            </thead>
                            <tbody id="the-list">
                               <tr class="header nodrag"  >
                                    <td colspan="2"><textarea name="textarea_data" cols="90" rows="12" style="width:100%"></textarea></td>
                               </tr>
                               <tr valign="top">
                                    <th scope="row"><?php _e('E-mail Notifications') ?></th>

                                    <td>
                                        <label><input name="mail_notifications" type="radio" id="mail_notifications1" value='all' checked='checked' /> <?php _e('Send Activation Mail.'); ?></label><br />
                                        <label><input name="mail_notifications" type="radio" id="mail_notifications2" value='withpass'  /> <?php _e('Automatically activate account and send mail with user name and password only.'); ?></label><br />
                                        <label><input name="mail_notifications" type="radio" id="mail_notifications3" value='none' /> <?php _e('Automatically activate account and do not send any mail.'); ?></label><br />
                                    </td>
                                </tr>

                            </tbody>
                </table>
            </div>


            <div class="submit">
                <input type="submit" name="data_submit" value="<?php _e('Import Users'); ?> &raquo;" />
            </div>
        </form>
  

  </div><?php
}

?>
