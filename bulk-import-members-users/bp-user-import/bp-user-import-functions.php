<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */


/*
 * Here I am using the same function as wpmu_signup_user to avoid any hacking to the core
 */
function my_wpmu_signup_user($user, $user_email, $meta = '') {
	global $wpdb;

	// Format data
	$user = preg_replace( "/\s+/", '', sanitize_user( $user, true ) );
	$user_email = sanitize_email( $user_email );
	$key = substr( md5( time() . rand() . $user_email ), 0, 16 );
	$meta = serialize($meta);

	$wpdb->insert( $wpdb->signups, array(
		'domain' => '',
		'path' => '',
		'title' => '',
		'user_login' => $user,
		'user_email' => $user_email,
		'registered' => current_time('mysql', true),
		'activation_key' => $key,
		'meta' => $meta
	) );

	//wpmu_signup_user_notification($user, $user_email, $key, $meta);
    my_wpmu_activate_signup($key);
}


function my_wpmu_activate_signup($key) {
	global $wpdb;

	$signup = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $wpdb->signups WHERE activation_key = %s", $key) );

	if ( empty($signup) )
		return new WP_Error('invalid_key', __('Invalid activation key.'));

	if ( $signup->active )
		return new WP_Error('already_active', __('The blog is already active.'), $signup);

	$meta = unserialize($signup->meta);
	$user_login = $wpdb->escape($signup->user_login);
	$user_email = $wpdb->escape($signup->user_email);
	wpmu_validate_user_signup($user_login, $user_email);
	$password = generate_random_password();

	$user_id = username_exists($user_login);

	if ( ! $user_id )
		$user_id = wpmu_create_user($user_login, $password, $user_email);
	else
		$user_already_exists = true;

	if ( ! $user_id )
		return new WP_Error('create_user', __('Could not create user'), $signup);

	$now = current_time('mysql', true);

	if ( empty($signup->domain) ) {
		$wpdb->update( $wpdb->signups, array('active' => 1, 'activated' => $now), array('activation_key' => $key) );
		if ( isset($user_already_exists) )
			return new WP_Error('user_already_exists', __('That username is already activated.'), $signup);
		//Code inserted by Manoj Kumar
        if ($_POST['mail_notifications']!='none'){
            wpmu_welcome_user_notification($user_id, $password, $meta);
        }
        //Manoj Kumar coed ended
        //Orininal : wpmu_welcome_user_notification($user_id, $password, $meta);

        add_user_to_blog('1', $user_id, 'subscriber');
		do_action('wpmu_activate_user', $user_id, $password, $meta);
		return array('user_id' => $user_id, 'password' => $password, 'meta' => $meta);
	}

	wpmu_validate_blog_signup($signup->domain, $signup->title);
	$blog_id = wpmu_create_blog($signup->domain, $signup->path, $signup->title, $user_id, $meta, $wpdb->siteid);

	// TODO: What to do if we create a user but cannot create a blog?
	if ( is_wp_error($blog_id) ) {
		// If blog is taken, that means a previous attempt to activate this blog failed in between creating the blog and
		// setting the activation flag.  Let's just set the active flag and instruct the user to reset their password.
		if ( 'blog_taken' == $blog_id->get_error_code() ) {
			$blog_id->add_data($signup);
			$wpdb->update( $wpdb->signups, array('active' => 1, 'activated' => $now), array('activation_key' => $key) );
		}

		return $blog_id;
	}

	$wpdb->update( $wpdb->signups, array('active' => 1, 'activated' => $now), array('activation_key' => $key) );

    //Code inserted by Manoj Kumar
	if ($_POST['mail_notifications']!='none'){
        wpmu_welcome_notification($blog_id, $user_id, $password, $signup->title, $meta);
    }
    //Manoj Kumar coed ended
    //Orininal : wpmu_welcome_notification($blog_id, $user_id, $password, $signup->title, $meta);
	do_action('wpmu_activate_blog', $blog_id, $user_id, $password, $signup->title, $meta);

	return array('blog_id' => $blog_id, 'user_id' => $user_id, 'password' => $password, 'title' => $signup->title, 'meta' => $meta);
}

?>
