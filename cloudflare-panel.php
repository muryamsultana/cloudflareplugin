<?php
/**
* Plugin Name: Cloudflare Panel
* Plugin URI: https://muryam.tlopezhost.com/
* Description: Cloudflare will integrate Cloudflare API
* Version: 1.0
* Author: Muryam
* Author URI: https://muryam.tlopezhost.com/
**/

/* DEFINE GLOBAL VARIABLES HERE */
define( 'CLOUDFLARE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once CLOUDFLARE_PLUGIN_DIR.'/cloudflare.inc.php';

/*creating plugin instance*/
$cloudflare = new cloudflarePanel();



class cloudflarePanel{
	
	public function __construct() {

  // TODO: Edit the calls here to only include the ones you want, or add more
		 /*Plugin Activation/Deactivations */
		register_activation_hook( __FILE__,array($this,'cloudflare_activation'));
		register_deactivation_hook( __FILE__,array($this,'cloudflare_deactivation'));
		register_uninstall_hook( __FILE__, array($this,'cloudfalre_uninstall'));
        
        add_action('admin_enqueue_scripts', array($this,'settings_javascript'));
		add_action('admin_menu', array($this,'cloudflare_menu')); //It create Menu on admin side
		add_action('wp_ajax_save_settings',array($this, 'cloudflare_add_settings'));//it submit form and save settings using ajax
		add_action('wp_ajax_getZoneList', array($this,'cloudflare_get_zone_list'));//
		add_action('wp_ajax_purgeAll', array($this,'cloudflare_purgeAll'));//
		add_action('wp_ajax_purgeFiles', array($this,'cloudflare_purgeFiles'));//

 
    }
	
	public  function cloudflare_activation() {

		
	}

	/* Attached to register_deactivation_hook()
	 * 
	 */
	public function cloudflare_deactivation() {
		

	}

	/* Attached to register_uninstall_hook()
	 *
	 */
	public function cloudflare_uninstall() {
		global $wpdb;
		

	}

	/* Attached to add_menu hook
	 *
	 */
	public function cloudflare_menu(){
		add_menu_page('Cloudflare Panel', 'Cloudflare Panel', 'administrator','cloudflare_settings',array($this,'cloudflare_settings_code'),'');
		add_submenu_page('cloudflare_settings','Settings', 'Settings', 'administrator','cloudflare_settings',array($this,'cloudflare_settings_code'));
		add_submenu_page('cloudflare_settings','Purge Domain', 'Purge Domain', 'administrator','cloudflare_panel',array($this,'cloudflare_purge_domain_code'));


	}

	/* call back function to display html for menu item on admin side
	 *
	 */
	 
	public function cloudflare_settings_code(){

				
	?>
	<div class="wrap">
	<div id="loading"></div>
	<h1>Cloudflare Settings</h1>

	<form method="post" action="" id="settings_form" name="settings_form">
		<?php settings_fields( 'cloudflare-plugin-settings-group' ); ?>
		<?php do_settings_sections( 'cloudflare-plugin-settings-group' ); ?>
	 
		<table class="form-table">
		
			 
			<tr valign="top">
			<th scope="row">Cloudflare Api Key</th>
			<td><input type="key" name="cloudflare_key" id="cloudflare_key" value="<?php echo esc_attr( get_option('cloudflare_key') ); ?>" /></td>
			</tr>
		
		 <tr valign="top">
			<th scope="row">Email</th>
			<td><input type="text" name="cloudflare_email" id="cloudflare_email" value="<?php echo esc_attr( get_option('cloudflare_email') );?>" /></td>
			</tr>
		
		  
		</table>
		
		<?php submit_button(); ?>

	</form>
	</div>
	<?php
		
	}


	/* call back function to display html for purge section
	 *
	 */
	 
	public function cloudflare_purge_domain_code(){
			
					
	?>
	<div class="wrap">
	<div id="loading"></div>
	<h1>Clear Cache Section(Purge)</h1>

	<form method="post" action="" id="purge_form" name="settings_form">
		<?php settings_fields( 'cloudflare-plugin-purge-group' ); ?>
		<?php do_settings_sections( 'cloudflare-plugin-purge-group' ); ?>
	 
		<table class="form-table">
			<tr valign="top">
			<th scope="row">File Path</th>
			<td><input type="text" name="purge_file" id="purge_file" value="" /><button class="primary" id="purgeFile" >Purge Files</button></td>
			</tr>
			 
			<tr valign="top">
			<th scope="row">Domain</th>
			<td>
			<select name="zones" id="zones">
			
			</select></td>
			</tr>
		
		  
		</table>
		<script type="text/javascript">
		getZoneList();
		</script>
	<button class="primary" id="purgeAll">Purge All</button>
	

	</form>
	</div>
	<?php
	}	


	//ajax call back function for admin_ajax hook

	public function cloudflare_add_settings() {


	$cloudflare_key = isset($_POST['cloudflare_key'])?$_POST['cloudflare_key']:'';
	$cloudflare_email = isset($_POST['cloudflare_email'])?$_POST['cloudflare_email']:'';
	
	update_option("cloudflare_key",$cloudflare_key);
	update_option("cloudflare_email",$cloudflare_email);

	echo json_encode(array("isError"=>0,"Msg"=>"Settings Saved Successfully"));
	wp_die(); 
	}


	//ajax call back function for admin_ajax hook

	public function cloudflare_get_zone_list() {


	$cloudflare_key = esc_attr( get_option("cloudflare_key"));
	$cloudflare_email = esc_attr( get_option('cloudflare_email'));

	 $cf = new cloudflare_api();
		//$res = $cf->getZoneList($cloudflare_email, $cloudflare_key);
		$res = $cf->getZonesList($cloudflare_email, $cloudflare_key,'tlopezhost.com');
		echo json_encode($res);
		exit;
	}

//ajax call back function for admin_ajax hook

	public function cloudflare_purgeAll() {


		$cloudflare_key = esc_attr( get_option("cloudflare_key"));
		$cloudflare_email = esc_attr( get_option('cloudflare_email'));
		
		$zoneid = esc_attr( $_POST['purgeZoneID']);
	

		 $cf = new cloudflare_api();
		 $res = $cf->purgeAll($cloudflare_email, $cloudflare_key, $zoneid);
		echo json_encode($res);
		exit;
	}

	//ajax call back function for admin_ajax hook

	public function cloudflare_purgeFiles() {


		$cloudflare_key = esc_attr( get_option("cloudflare_key"));
		$cloudflare_email = esc_attr( get_option('cloudflare_email'));
		$files = esc_attr( $_POST['files']);
		$zoneid = esc_attr( $_POST['purgeZoneID']);
		$list = explode(",", $files);
		$cf = new cloudflare_api();
		//$res = $cf->getZoneList($cloudflare_email, $cloudflare_key);
		$res = $cf->purgeFiles($cloudflare_email, $cloudflare_key, $list, $zoneid);
		echo json_encode($res);
		exit;
	}

	//add ajax code
	/* Attached to admin_footer hook
	 *
	 */
	public function settings_javascript() { 
		wp_register_script( 'customjs', plugins_url( 'cloudflare.js', __FILE__ ) );
		wp_enqueue_script( 'customjs' ); 

	}

}

?>

