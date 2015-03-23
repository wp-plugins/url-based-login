<?php
/**
 * @package url-based-login
 * @version 1.1
 */
/*
Plugin Name: URL Based Login
Plugin URL: https://wordpress.org/plugins/url-based-login/ 
Description: URL Based Login is a plugin which allows you to directly login from an allowed URL. You can create multiple login URLs which can get access to the specified user. So if you want to allow someone to login but you do not want to share the login details just give them a URL to login.
Version: 1.1
Author: Udit Bhansali
License: GPLv3 or later 
*/

/*
Copyright (C) 2013  Udit Bhansali (email : udit@ymail.com)
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

if(!function_exists('add_action')){
	echo 'You are not allowed to access this page directly.';
	exit;
}

define('ubl_version', '1.0');

// This function adds a link in admin toolbar
function ubl_admin_bar() {
	global $wp_admin_bar;

	$wp_admin_bar->add_node(array(
		'id'    => 'ubl-link',
		'title' => 'Logged in by URL Based Login',
		'href'  => 'http://www.UditBhansali.name'
	));

}

// Ok so we are now ready to go
register_activation_hook( __FILE__, 'url_based_login_activation');

function url_based_login_activation(){

global $wpdb;

$sql = "
--
-- Table structure for table `".$wpdb->prefix."url_based_login`
--

CREATE TABLE IF NOT EXISTS `".$wpdb->prefix."url_based_login` (
  `uid` int(10) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `login_hash` varchar(255) NOT NULL,
  `status` tinyint(2) NOT NULL DEFAULT '1',
  `date` int(10) NOT NULL,
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";

$wpdb->query($sql);

add_option('ubl_version', ubl_version);

}

add_action( 'plugins_loaded', 'url_based_login_update_check' );

function url_based_login_update_check(){

global $wpdb;

	$sql = array();
	$current_version = get_option('ubl_version');

	if($current_version < ubl_version){
		foreach($sql as $sk => $sv){
			$wpdb->query($sv);
		}

		update_option('ubl_version', ubl_version);
	}

}

function ubl_triger_login(){
	
	global $wpdb;
	
		
	@$login_hash = ubl_sanitize_variables($_GET['hash']);
	$query = "SELECT * FROM ".$wpdb->prefix."url_based_login WHERE `login_hash` = '".$login_hash."' AND `status` = 1";
	$result = ubl_selectquery($query);
	$username = $result['username'];
	
	if(!is_user_logged_in() && !empty($username)){

		// What is the user id ?
		$user = get_userdatabylogin($username);
		$user_id = $user->ID;
				
		// Lets login
		wp_set_current_user($user_id, $username);
		wp_set_auth_cookie($user_id);
		do_action('wp_login', $username);
	}
	
	// Did we login the user ?
	if(!empty($username)){
		add_action('wp_before_admin_bar_render', 'ubl_admin_bar');
	}
}

add_action('init', 'ubl_triger_login');
add_action('admin_menu', 'url_based_login_admin_menu');

function ubl_getip(){
	if(isset($_SERVER["REMOTE_ADDR"])){
		return $_SERVER["REMOTE_ADDR"];
	}elseif(isset($_SERVER["HTTP_X_FORWARDED_FOR"])){
		return $_SERVER["HTTP_X_FORWARDED_FOR"];
	}elseif(isset($_SERVER["HTTP_CLIENT_IP"])){
		return $_SERVER["HTTP_CLIENT_IP"];
	}
}

function ubl_selectquery($query){
	global $wpdb;
	
	$result = $wpdb->get_results($query, 'ARRAY_A');
	return current($result);
}

function url_based_login_admin_menu() {
	global $wp_version;

	// Modern WP?
	if (version_compare($wp_version, '3.0', '>=')) {
	    add_options_page('URL Based Login', 'URL Based Login', 'manage_options', 'url-based-login', 'url_based_login_option_page');
	    return;
	}

	// Older WPMU?
	if (function_exists("get_current_site")) {
	    add_submenu_page('wpmu-admin.php', 'URL Based Login', 'URL Based Login', 9, 'url-based-login', 'url_based_login_option_page');
	    return;
	}

	// Older WP
	add_options_page('URL Based Login', 'URL Based Login', 9, 'url-based-login', 'url_based_login_option_page');
}

function ubl_sanitize_variables($variables = array()){
	
	if(is_array($variables)){
		foreach($variables as $k => $v){
			$variables[$k] = trim($v);
			$variables[$k] = escapeshellcmd($v);
			$variables[$k] = mysql_real_escape_string($v);
		}
	}else{
		$variables = mysql_real_escape_string(escapeshellcmd(trim($variables)));
	}
	
	return $variables;
}

function ubl_valid_ip($ip){

	if(!ip2long($ip)){
		return false;
	}	
	return true;
}

function ubl_is_checked($post){

	if(!empty($_POST[$post])){
		return true;
	}	
	return false;
}

function ubl_report_error($error = array()){

	if(empty($error)){
		return true;
	}
	
	$error_string = '<b>Please fix the below errors :</b> <br />';
	
	foreach($error as $ek => $ev){
		$error_string .= '* '.$ev.'<br />';
	}
	
	echo '<div id="message" class="error"><p>'
					. __($error_string, 'url-based-login')
					. '</p></div>';
}

function ubl_objectToArray($d){
  if(is_object($d)){
    $d = get_object_vars($d);
  }
  
  if(is_array($d)){
    return array_map(__FUNCTION__, $d); // recursive
  }elseif(is_object($d)){
    return ubl_objectToArray($d);
  }else{
    return $d;
  }
}

function url_based_login_option_page(){

	global $wpdb;
	
	$siteurl = get_option('siteurl');
	
	if(!current_user_can('manage_options')){
		wp_die('Sorry, but you do not have permissions to change settings.');
	}

	/* Make sure post was from this page */
	if(count($_POST) > 0){
		check_admin_referer('url-based-login-options');
	}
	
	if(isset($_GET['delid'])){
		
		$delid = (int) ubl_sanitize_variables($_GET['delid']);
		
		$wpdb->query("DELETE FROM ".$wpdb->prefix."url_based_login WHERE `uid` = '".$delid."'");
		echo '<div id="message" class="updated fade"><p>'
			. __('Login URL has been deleted successfully', 'url-based-login')
			. '</p></div>';	
	}
	
	if(isset($_GET['statusid'])){
		
		$statusid = (int) ubl_sanitize_variables($_GET['statusid']);
		$setstatus = ubl_sanitize_variables($_GET['setstatus']);
		$_setstatus = ($setstatus == 'disable' ? 0 : 1);
		
		$wpdb->query("UPDATE ".$wpdb->prefix."url_based_login SET `status` = '".$_setstatus."' WHERE `uid` = '".$statusid."'");
		echo '<div id="message" class="updated fade"><p>'
			. __('Login URL has been '.$setstatus.'d successfully', 'url-based-login')
			. '</p></div>';	
	}
	
	if(isset($_POST['add_login_hash'])){
		global $url_based_login_options;

		$url_based_login_options['username'] = $_POST['username'];

		$url_based_login_options = ubl_sanitize_variables($url_based_login_options);
		
		$user = get_user_by('login', $url_based_login_options['username']);
		
		if(empty($user)){
			$error[] = 'The username does not exist.';
		}
		
		if(empty($error)){
			
			$options['username'] = $url_based_login_options['username'];
			$options['login_hash'] = md5(uniqid($options['username'], true));
			$options['status'] = (ubl_is_checked('status') ? 1 : 0);
			$options['date'] = date('Ymd');
			
			$wpdb->insert($wpdb->prefix.'url_based_login', $options);
			
			if(!empty($wpdb->insert_id)){
				$query = "SELECT * FROM ".$wpdb->prefix."url_based_login WHERE `uid` = '".$wpdb->insert_id."'";
				$result = ubl_selectquery($query);
				$login_hash = $result['login_hash'];
				$login_url = $siteurl.'/?hash='.$login_hash;
				
				echo '<div id="message" class="updated fade"><p>'
					. __('Login URL added successfully. You can use the following Login URL: <br />
							<a href="'.$login_url.'">'.$login_url.'</a>', 'url-based-login')
					. '</p></div>';
			}else{
				echo '<div id="message" class="updated fade"><p>'
					. __('There were some errors while adding Login URL', 'url-based-login')
					. '</p></div>';			
			}
			
		}else{
			ubl_report_error($error);
		}
	}
	
	$urlranges = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."url_based_login;", 'ARRAY_A');
	
	// A list of all users
	$_users = get_users();	
	
	?>
	<div class="wrap">
	  <h2><?php echo __('URL Based Login Settings','url-based-login'); ?></h2>
	  <form action="options-general.php?page=url-based-login" method="post">
		<?php wp_nonce_field('url-based-login-options'); ?>
	    <table class="form-table">
		  <tr>
			<th scope="row" valign="top"><?php echo __('Username','url-based-login'); ?></th>
			<td>
            	<select name="username">
            	<?php
					foreach($_users as $uk => $uv){
						$_users[$uk] = ubl_objectToArray($uv);
						echo '<option value="'.$_users[$uk]['data']['user_login'].'" '.($url_based_login_options['username'] == $_users[$uk]['data']['user_login'] ? 'selected="selected"' : '').'>'.$_users[$uk]['data']['user_login'].'</option>';
					}					
				?>
                </select>&nbsp;&nbsp;
			  <?php echo __('Username to be logged in as when accessed from the below IP range','url-based-login'); ?> <br />
			</td>
		  </tr>
		  <tr>
			<th scope="row" valign="top"><?php echo __('Active','url-based-login'); ?></th>
			<td>
			  <input type="checkbox" <?php if(!isset($_POST['add_iprange']) || ubl_is_checked('status')) echo 'checked="checked"'; ?> name="status" /> <?php echo __('Select the checkbox to set this range as active','url-based-login'); ?> <br />
			</td>
		  </tr>
		</table><br />
		<input name="add_login_hash" class="button action" value="<?php echo __('Create Login URL','url-based-login'); ?>" type="submit" />		
	  </form>
	</div>	
	<?php
	
	if(!empty($urlranges)){
		?>
		<br /><br />
		<table class="wp-list-table widefat fixed users">
			<tr>
				<th scope="row" valign="top"><?php echo __('Username','url-based-login'); ?></th>
				<th scope="row" valign="top"><?php echo __('Login URL','url-based-login'); ?></th>
				<th scope="row" valign="top"><?php echo __('Options','url-based-login'); ?></th>
			</tr>
			<?php
				
				$login_hash = $result['login_hash'];
				$login_url = $siteurl.'/?hash='.$login_hash;
				foreach($urlranges as $ik => $iv){
					$status_button = (!empty($iv['status']) ? 'disable' : 'enable');
					echo '
					<tr>
						<td>
							'.$iv['username'].'
						</td>
						<td>
							<a href="'.$siteurl.'/?hash='.$iv['login_hash'].'">'.$siteurl.'/?hash='.$iv['login_hash'].'</a>
						</td>
						<td>
							<a class="submitdelete" href="options-general.php?page=url-based-login&delid='.$iv['uid'].'" onclick="return confirm(\'Are you sure you want to delete this Login URL ?\')">Delete</a>&nbsp;&nbsp;
							<a class="submitdelete" href="options-general.php?page=url-based-login&statusid='.$iv['uid'].'&setstatus='.$status_button.'" onclick="return confirm(\'Are you sure you want to '.$status_button.' this Login URL ?\')">'.ucfirst($status_button).'</a>
						</td>
					</tr>';
				}
			?>
		</table>
		<?php
	}
}	

// Sorry to see you going
register_uninstall_hook( __FILE__, 'url_based_login_deactivation');

function url_based_login_deactivation(){

global $wpdb;

$sql = "DROP TABLE ".$wpdb->prefix."url_based_login;";
$wpdb->query($sql);

delete_option('ubl_version'); 

}
?>
