<?php
/**
 * Plugin Name: Add user 2 in 1
 * Plugin URI: http://wplabels.com
 * Description: Merge these 2 forms add user in just 1 
 * Author: Huu Ha
 * Author URI: https://www.freelancer.com/u/huuhabn.html
 * Version: 1.00
 * License: GPL2
 */

require_once( ABSPATH . '/wp-admin/includes/admin.php' );
if ( is_multisite() ) {
	function admin_created_user_email_2( $text ) {
		$roles = get_editable_roles();
		$role = $roles[ $_REQUEST['role'] ];
		/* translators: 1: Site name, 2: site URL, 3: role */
		return sprintf( __( 'Hi,
You\'ve been invited to join \'%1$s\' at
%2$s with the role of %3$s.
If you do not want to join this site please ignore
this email. This invitation will expire in a few days.

Please click the following link to activate your user account:
%%s' ), get_bloginfo( 'name' ), home_url(), wp_specialchars_decode( translate_user_role( $role['name'] ) ) );
	}
	add_filter( 'wpmu_signup_user_notification_email', 'admin_created_user_email_2' );

	function admin_created_user_subject_2( $text ) {
		return sprintf( __( '[%s] Your site invite' ), get_bloginfo( 'name' ) );
	}
}


/**
* Add user
*/
class addUsers
{
	public static function getInstance() {
        static $_instance = false;

        if ( !$_instance ) {
            $_instance = new addUsers();
        }
        return $_instance;
    }
	
	function __construct()
	{
		add_action( 'wp_ajax_add_user_2i1', array($this, 'add_user_2i1') );

		if ( is_admin() ) {
            add_action( 'admin_enqueue_scripts', array($this, 'scripts') );
        }

		if ( is_multisite() ) {
		    add_action( 'admin_notices', array($this, 'add_new_user_form'));
		} else {
		    add_action( 'admin_notices', array($this, 'add_new_user_form'));
		}
	}

   function add_user_2i1(){
   		global $wpdb;
		$user_details = null;
		$json = array();
		$redirect = 'users.php';

		if ( false !== strpos($_REQUEST[ 'email' ], '@') ) {

			$user_details = get_user_by('email', $_REQUEST[ 'email' ]);

	   		if ( $user_details ) {

				// Adding an existing user to this blog
				$new_user_email = $user_details->user_email;
				
				$username = $user_details->user_login;
				$user_id = $user_details->ID;
				if ( ( $username != null && !is_super_admin( $user_id ) ) && ( array_key_exists($blog_id, get_blogs_of_user($user_id)) ) ) {
					echo json_encode(array('success'=>false,'message'=>'That user is already a member of this site.'));exit;
				} else {
					if ( isset( $_POST[ 'noconfirmation' ] ) && is_super_admin() ) {
						add_existing_user_to_blog( array( 'user_id' => $user_id, 'role' => $_REQUEST[ 'role' ] ) );
						echo json_encode(array('reload'=>admin_url( 'users.php'),'success'=>true,'message'=>'User has been added to your site.'));exit;
					} else {
						$newuser_key = substr( md5( $user_id ), 0, 5 );
						add_option( 'new_user_' . $newuser_key, array( 'user_id' => $user_id, 'email' => $user_details->user_email, 'role' => $_REQUEST[ 'role' ] ) );

						$roles = get_editable_roles();
						$role = $roles[ $_REQUEST['role'] ];
						/* translators: 1: Site name, 2: site URL, 3: role, 4: activation URL */
						$message = __( 'Hi,

			You\'ve been invited to join \'%1$s\' at
			%2$s with the role of %3$s.

			Please click the following link to confirm the invite:
			%4$s' );
						wp_mail( $new_user_email, sprintf( __( '[%s] Joining confirmation' ), wp_specialchars_decode( get_option( 'blogname' ) ) ), sprintf( $message, get_option( 'blogname' ), home_url(), wp_specialchars_decode( translate_user_role( $role['name'] ) ), home_url( "/newbloguser/$newuser_key/" ) ) );
						echo json_encode(
								array(
									'success'=>true,
									'message'=>'Invitation email sent to user. A confirmation link must be clicked for them to be added to your site.'
									)
								);
						exit;
						
					}
				}
				wp_redirect( $redirect );
				die();
			} elseif(!isset($_REQUEST['user_login']) || empty($_REQUEST['user_login'])){
				echo json_encode(
							array(
								'success'=>false,
								'message'=>'Please enter username for this email.',
								'show_input_username' =>true
								)
							);
					exit;
			}else{

				if ( ! current_user_can('create_users') ){
					echo json_encode(array('success'=>false,'message'=>'You can\'t create user.'));exit;
				}

				if ( ! is_multisite() ) {
					$user_id = edit_user();

					if ( is_wp_error( $user_id ) ) {
						echo json_encode(
								array(
									'success'=>false,
									'message'=>'error'
									)
								);
						exit;
					} else {
						echo json_encode(
								array(
									'success'=>true,
									'message'=>'Invitation email sent to user. A confirmation link must be clicked for them to be added to your site.'
									)
								);
						exit;
					}
				} else {
					// Adding a new user to this site
					$user_details = wpmu_validate_user_signup( $_REQUEST[ 'user_login' ], $_REQUEST[ 'email' ] );
					if ( is_wp_error( $user_details[ 'errors' ] ) && !empty( $user_details[ 'errors' ]->errors ) ) {
						$add_user_errors = $user_details[ 'errors' ];
					} else {
						
						$new_user_login = apply_filters( 'pre_user_login', sanitize_user( wp_unslash( $_REQUEST['user_login'] ), true ) );
						if ( isset( $_POST[ 'noconfirmation' ] ) && is_super_admin() ) {
							add_filter( 'wpmu_signup_user_notification', '__return_false' ); // Disable confirmation email
						}
						wpmu_signup_user( $new_user_login, $_REQUEST[ 'email' ], array( 'add_to_blog' => $wpdb->blogid, 'new_role' => $_REQUEST[ 'role' ] ) );

						if ( isset( $_POST[ 'noconfirmation' ] ) && is_super_admin() ) {
							$key = $wpdb->get_var( $wpdb->prepare( "SELECT activation_key FROM {$wpdb->signups} WHERE user_login = %s AND user_email = %s", $new_user_login, $_REQUEST[ 'email' ] ) );

							wpmu_activate_signup( $key );
							echo json_encode(
									array(
										'success'=>true,
										'message'=>'User has been added to your site.',
										'reload' =>admin_url( 'users.php')
										)
									);
							exit;
						}else{
							echo json_encode(
									array(
										'success'=>true,
										'message'=>'Invitation email sent to new user. A confirmation link must be clicked before their account is created.',
										'reload' =>admin_url( 'users.php')
										)
									);
							exit;
						}
						wp_redirect( $redirect );
						die();
					}
				}
			}
		}

		echo json_encode($json);exit;

   }


    function scripts() {
        wp_enqueue_script( 'jquery' );

        wp_enqueue_script( 'add_user_script', plugins_url( 'script.js', __FILE__ ), array('jquery'), false, true );

        wp_localize_script( 'add_user_script', 'adduser_var', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            '_nonce' => wp_create_nonce( 'add_user_nonce' ),
        ) );
    }

 	
	function add_new_user_form(){
		global $pagenow;
		global $wp_roles;

	    $all_roles = $wp_roles->roles;
	    // echo '<pre>';
	    // print_r($all_roles);
	    // echo '</pre>';
	    // die;

		if ($pagenow == 'users.php') { ?>
		 <div class="updated">
		 	<form action="" method="post" name="add-user-2i1" id="add-user-2i1">
			<table class="form-table" style="width:600px;">
				<tbody>
					<tr class="form-field form-required">
						<td width="50" scope="row"><label for="adduser-email">E-mail</label></td>
						<td width="250"><input name="email" type="text" id="adduser-email" class="wp-suggest-user ui-autocomplete-input" size="30" value=""></td>
						<td width="150">
							<select name="role" id="adduser-role">

							<?php foreach($all_roles as $key=>$role){ ?>
								<option value="<?php echo $key; ?>"><?php echo $role['name']; ?></option>
							<?php } ?>
							</select>
						</td>
						<td width="50" scope="row">
						<input type="hidden" name="action" value="add_user_2i1">
						<!-- <input type="hidden" name="noconfirmation" id="adduser-noconfirmation" value="1" > -->

						<input type="submit" name="add_user" id="add_user" class="button button-primary menu-save" value="Add user">
						</td>

					</tr>
					<tr>
						<td><label for="adduser-noconfirmation"><?php _e('Skip Confirmation Email') ?></label></td>
						<td colspan="4"><label for="adduser-noconfirmation"><input type="checkbox" name="noconfirmation" id="adduser-noconfirmation" value="1" checked="checked"/> <?php _e( 'Add the user without sending an email that requires their confirmation.' ); ?></label></td>

					</tr>
					<tr id="add-username" style="display:none">
						<td>Username</td>
						<td colspan="4"><input name="user_login" type="text" id="user_login" value="" aria-required="true"></td>
					</tr>
				
				</tbody>
			</table>
			</form>
		</div>
		<?php

		}
	}
}

add_action( 'plugins_loaded', 'add_user_loader',50 );

function add_user_loader() {
    $instance = addUsers::getInstance();
}