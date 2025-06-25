<?php
	
/*
Plugin Name: Basis Email Signup Form
Plugin URI: https://basisIT.net/
Description: The best email signup form that lets you collect emails and grow an email newsletter list. This is a great way to get free advertising.
Author: Glen Rose @ Basis IT
Author URI: https://basisIT.net/
Version:     1.0.0
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: basis-email-signup-form
*/


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class basisEmailSignupForm{

	function __construct(){
		
		add_action( 'admin_menu', array($this,'add_menu_page_function' )); //adding a menu top level item
		add_action('admin_init', array($this, 'settings')); //what to start on inilisation
		add_action('init', array($this, 'start_custom_session'));
		add_action('init', array($this, 'colorSet'));
		add_action('admin_init', array($this, 'handle_export_action'));
		add_filter('the_content', array($this, 'showEmailBox'));
		add_action("wp_enqueue_scripts", array($this,'mainPageAssets'));
		//add_action('admin_post_nopriv_email', array($this,'addEmail'));
		add_action('admin_post_email', array($this,'addEmail'));
		add_shortcode('show_basis_form', array($this, 'shortShowEmailBox'));

		//defining email database
		global $wpdb;
		$this->charset = $wpdb->get_charset_collate();
		$this->tablename = $wpdb->prefix . "basisEmailSignupForm";
		
		
		//on activation create table in db
		add_action('basis-email-signup-form/basis-email-signup-form.php', array($this, 'onActivate'));

	
	}

	//Static function that runs at activation of plugin
	public static function activate_plugin() {
		global $wpdb;

		//setting variables for later use 
		$charset_collate = $wpdb->get_charset_collate();
		$table_name = $wpdb->prefix . "basisEmailSignupForm";
	
		//Creating database
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta("CREATE TABLE $table_name (
		  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		  emaillist varchar(60) NOT NULL DEFAULT '',
		  email varchar(60) NOT NULL DEFAULT '',
		  PRIMARY KEY  (id)
		) $charset_collate;");

		//Creating and updating initial values
		register_setting('basis_email_form', 'email_boxtitle');
		update_option('basis-color', 'Try our newsletter and win a computer');

		register_setting('basis_email_form', 'email_textbox');
		update_option('basis-color', '<b>Fill out your email belove, and recieve our VIP-offers.</b>These offers are only for members of our newsletter.<em>We promise no spam... Only quality...</em>');

		register_setting('basis_email_form', 'email_signup');
		update_option('basis-color', 'Click her and sign up');

		register_setting('basis_email_form', 'email_form_location');
		update_option('basis-color', '1');

		register_setting('basis_email_form', 'basis-color');
		update_option('basis-color', '#F0F0F1#CCCCCC');
	}

	//adding a menu top level item
	function add_menu_page_function() {
		    $siteHandle = add_menu_page(
			'Dashboard',
			'Basis Email Signup Form',
			'manage_options',
			'myplugin-admin.php',
			array($this, 'emailSignup_callback'),
			'dashicons-email-alt2',
			250
		);
		add_submenu_page('myplugin-admin.php','Dashboard','Basis Email Signup Form','manage_options','myplugin-admin.php',array($this,'emailSignupSettings'));
		add_submenu_page('myplugin-admin.php','Dashboard Options','Emaillist','manage_options','word-filter-options',array($this,'optionsSubPage'));

		add_action("load-{$siteHandle}", array($this,'mainPageAssets'));
		
	}

	function colorSet(){
		
		$this->basis_color = get_option('basis-color');
		$this->bg_color = substr($this->basis_color, 0, 7);
		$this->border_color = substr($this->basis_color, 7, 7);
		
	}

	function emailSignupSettings(){

	}

	function optionsSubPage(){ ?>
		<?php 
		wp_enqueue_style('filterAdminCss', plugin_dir_url(__FILE__) . 'styles.css');
		$_SESSION['url_nonce']  =  (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]". "?nonce-failed";
		?>


		<div class='flex_container_white'>

			<div class="box1">
				
				<h1 class="textTitle">Emaillist</h1>
				
				<p class="text">
				In this section you can se current number of signups, add/remove emails and export the emaillist.<br /><br /><p></p>
				</p>

				<?php
				

				//calculating the number of entries
				global $wpdb;
				

				//This is the function to add an user 
				if (isset($_POST['add_entry']) AND !empty($_POST['emailtoadd']) ) {
			
						//get from post
						$email = sanitize_text_field($_POST['emailtoadd']);

						//Verify nonce
						$nonceAdd = $_POST['add_nonce'];
						$result = wp_verify_nonce($nonceAdd, 'add_entry_');
			
						if($result){
				
				
							$toInsert = array(
								'emaillist' => "standard",
								'email' => $email,
								);


							global $wpdb;

							//Checks if user exists

							$isUserinDB = $wpdb->get_results(
								$wpdb->prepare(
									"SELECT * FROM $this->tablename WHERE email = %s",
									$email
									
								)
							);

							$countOfUsers = count($isUserinDB);

							if($countOfUsers == 0) {

								//Gets data from db
								$wpdb->insert($this->tablename, $toInsert);
								echo "<div class='updated'><p>User added emaillist</p></div>";
							} else {
								echo "<div class='error'><p>User is already in the list</p></div>";
							}

						}
		
				} 
				
				//Create Nonce
				$nonceAdd = wp_create_nonce('add_entry_'); 
				
				?>


				<div>
				
								<form method='POST'>
									<p class='boxtext'><b>Write the email to add</b></p>
									<input type="hidden" name="add_nonce" value="<?php echo esc_attr($nonceAdd); ?>" />
									<input type='email' name='emailtoadd' class='textinput' placeholder='Email' required><br>
									<button class='buttonstyle-emaillist' name='add_entry'>Add email</button>
								</form>
				</div>
				<p><br></p>
				<div>
								<?php
									//This is the function to delete an user
								if (isset($_POST['delete_entry']) AND !empty($_POST['emailtodelete']) ) {
							
								//get from post
								$email = sanitize_text_field($_POST['emailtodelete']);

								//Verify nonce
								$nonce = $_POST['delete_nonce'];
								$result = wp_verify_nonce($nonce, 'delete_entry_');
								if($result){

								//Gets data from db
								$resultsFromDB = $wpdb->get_results(
									$wpdb->prepare(
										"SELECT * FROM $this->tablename WHERE email = %s",
										$email
										
									)
								);

								$howMany = count($resultsFromDB);

								

								if($howMany == 1){
									//Deleting users with that email
									$wpdb->delete(
										$this->tablename,
										array(
											'email' => $email
										),
										array(
											'%s'
										)
									);

									echo "<div class='updated'><p>$email was removed.</p></div>";
								} else {
									echo "<div class='error'><p>No email called $email was found in the emaillist</p></div>";
								}

							} else {
								echo "error";
							}
						} 

						//creating nonce for deleting
						$nonce = wp_create_nonce('delete_entry_'); 
						?>
								<form method='POST'>
									<p class='boxtext'><b>Write the email to remove</b></p>
									<input type="hidden" name="delete_nonce" value="<?php echo esc_attr($nonce); ?>" />
									<input type='email' name='emailtodelete' class='textinput' placeholder='Email' required><br>
									<button class='buttonstyle-emaillist' name='delete_entry'>Remove email</button>
								</form>
				</div>

				<p class="text">
				<?php
				$results = $wpdb->get_results( "SELECT * FROM $this->tablename" );


				$count = count( $results );
				echo "
				You currently have <b>". $count . "</b> emailaddresses in your emaillist. <br/>
				and your can export it as an csv by clicking <a href='?page=word-filter-options&action=export'><b>here</b></a>.
				";

				?>
				</p>
			
			</div>

			<div class="box2">
				
				<h1 class="textTitle">Send an email</h1>
				
				<p class="text">
				Send an email to your subscribers,<br>
				but before you start, you should know these things:<br>
				<ul>
					<li>-Only send email moderatly, otherwise the emailservers can block you.</li>
					<li>-You should configre your Wordpress SMTP-server (click here and see video)</li>
				</ul>	
				<p><br/></p>
				</p>

				<?php
				//Create Nonce
				$nonceSender = wp_create_nonce('sender_entry_'); 
				?>

				<form method='POST'>
									<p class='boxtext'><b>Email subject</b></p>
									<input type='hidden' name='nonce_sender' value="<?php echo esc_attr($nonceSender); ?>" />
									<input type='text' name='emailtitle' class='emailtitleinput' placeholder='Subject of the email' required><br>
									<p class='boxtexttext'><b>Email text</b>
									<?php
										// Initialize the WYSIWYG editor for the email body
										$emailText="";
										wp_editor($emailText, 'emailtext', [
											'textarea_name' => 'emailtext', // Name attribute for the form
											'textarea_rows' => 10,
											'media_buttons' => false, // Hide "Add Media"
											'teeny'         => true, // Use simplified toolbar
											'quicktags'     => true, // Enable HTML Quicktags
										]);
									?></p>
									<button class='buttonstyle-emaillist' name='delete_entry'>Send email to ALL subscribers</button>
				</form>

				<!-- When form is submitted -->
				<?php
						if (isset($_POST['emailtitle']) AND isset($_POST['nonce_sender'])) {

							//Verify nonce
							$nonceSender = $_POST['nonce_sender'];
							$result = wp_verify_nonce($nonceSender, 'sender_entry_');
							
							if($result){
								$emailTitle = sanitize_text_field($_POST['emailtitle']);
								$emailText =  wp_kses_post($_POST['emailtext']); // Allow safe HTML
								$this->esp_send_emails($emailTitle,$emailText);
							} else {
								echo "<div class='error'><p>Failed sending email - The error is a nonce-error</p></div>";
							}

					}		

				?>
			</div>

			<div class="box3">
						Made by <a href="http://basisit.net">BasisIT.net</a><br>
						<a href="https://www.youtube.com/@basisit3511" style="color: #cccccc;">Basis IT @ YouTube</a>
			</div>

		</div>	

		
		<?php
		
		
	}

	function start_custom_session(){
		if (!session_id()) {
			session_start();
		}	
	}

	//Gets all emails from database to mass send
			function esp_get_email_list() {
				global $wpdb;

				$results = $wpdb->get_results("SELECT email FROM $this->tablename");

				$emails = [];
				foreach ($results as $row) {
					$emails[] = $row->email;
				}
				return $emails;
			}
									


	function shortShowEmailBox(){
		
		if (isset($_GET['aio']) && $_GET['aio'] == "subscribed") { 

			$thebox = "<div class='preview-box'>
						
							<div><form action=". esc_url(admin_url('admin-post.php')) ." class='' method='POST'>
									<h3 class='boxtitle'><i>Thanks, you have succesfully signed up.</i></h2>
									
								</form>
							</div>
						</div>
						";

		} else {

			$thebox = "
				
					
					<div class='preview-box'>

							<div style='background:". esc_attr($this->bg_color) . "; border:1px solid ". esc_attr($this->border_color) ." ;' >
								<form action=". esc_url(admin_url('admin-post.php')) ." class='' method='POST'>
									<h3 class='boxtitle'>". get_option('email_boxtitle') . "</h2>
									<p class='boxtext'>". wpautop(get_option('email_textbox')) . "</p>
									<input type='email' name='emailToInput' class='textinput' placeholder='Email'><br>
									<input type='hidden' name='action' value='email'>
									<button class='buttonstyle'>". get_option('email_signup') ."</button>
								</form>
							</div>
						
					</div>



				"; 
		}

		return $thebox;

	}

	function showEmailBox($content){

		//the signup box
		
		if (isset($_GET['aio']) && $_GET['aio'] == "subscribed") { 

			$thebox = "<div class='preview-box'>
						
							<div><form action=". esc_url(admin_url('admin-post.php')) ." class='' method='POST'>
									<h3 class='boxtitle'><i>Thanks, you have succesfully signed up.</i></h2>
									
								</form>
							</div>
						</div>
						";

		} else {
			
				
			$thebox = "
		
				
					
					<div class='preview-box'>
						
							<div style='background:". esc_attr($this->bg_color) . "; border:1px solid ". esc_attr($this->border_color) ." ;' >
								<form action=". esc_url(admin_url('admin-post.php')) ." class='' method='POST'>
									<h3 class='boxtitle'>". get_option('email_boxtitle') . "</h2>
									<p class='boxtext'>".  wpautop(get_option('email_textbox')) . "</p>
									<input type='email' name='emailToInput' class='textinput' placeholder='Email'><br>
									<input type='hidden' name='action' value='email'>
									<button class='buttonstyle'>". get_option('email_signup') ."</button>
								</form>
							</div>
						
					</div>



				"; 
		}




		$_SESSION['url']  =  (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]". "?aio=subscribed";
		$_SESSION['urlnotsubscribed']  =  (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]". "?aio=notsubscribed";


		if (get_option('email_form_location') == 1){
			$content= $thebox . $content;
		}

		if (get_option('email_form_location') == 2){
			$content = $content . $thebox;


		} 
		
		return $content;
	}

	function mainPageAssets() {
		wp_enqueue_style('filterAdminCss', plugin_dir_url(__FILE__). 'styles.css');
	}

	function addEmail(){	

		if(!empty($_POST['emailToInput'])){				
				$sanitized_email = sanitize_text_field($_POST['emailToInput']);
				$toInsert = array(
							'emaillist' => "standard",
							'email' => $sanitized_email,
							);


				global $wpdb;

				//Checks if user exists
				$isUserinDB = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM $this->tablename WHERE email = %s",
						$sanitized_email
						
					)
				);

				$countOfUsers = count($isUserinDB);

				if($countOfUsers == 0) {

					//email inserted in the list
					$wpdb->insert($this->tablename, $toInsert);
					wp_redirect($_SESSION['url']);

				} else {
					//email exists and will NOT be inserted
					wp_redirect($_SESSION['urlnotsubscribed']);		
					
				}
		} else {
			wp_redirect($_SESSION['urlnotsubscribed']);	
		}		

		
	
	}


	//adding section and fields
	function settings() {
		add_settings_section('basis_email_first_section', null, null, 'basis-email-form-settings-page'); //adding a section

		add_settings_field('aio_box_title','Whats the title?', array($this, 'pagePostTITLEbox'), 'basis-email-form-settings-page', 'basis_email_first_section'); //edding field
		register_setting('basis_email_form', 'email_boxtitle', array('sanitize_callback' => 'sanitize_text_field'));

		add_settings_field('aio_box_post_page','What text should be displayed?', array($this, 'pagePostHTMLbox'), 'basis-email-form-settings-page', 'basis_email_first_section'); //edding field
		register_setting('basis_email_form', 'email_textbox', array('sanitize_callback' => 'wp_kses_post')); 

		add_settings_field('email_signup','Text displayed in submit button?', array($this, 'pagePostsubmit'), 'basis-email-form-settings-page', 'basis_email_first_section'); //edding field
		register_setting('basis_email_form', 'email_signup', array('sanitize_callback' => 'sanitize_text_field'));

		add_settings_field('email_form_location','Where should it be displayed?', array($this, 'locationHTML'), 'basis-email-form-settings-page', 'basis_email_first_section'); //edding field
        register_setting('basis_email_form', 'email_form_location', array('sanitize_callback' => 'sanitize_text_field'));

		add_settings_field('selected_color','Choose a color', array($this, 'colorSelector'), 'basis-email-form-settings-page', 'basis_email_first_section'); //edding field
        register_setting('basis_email_form', 'basis-color', array('sanitize_callback' => 'sanitize_text_field'));


	}

	function prefix_sanitize_textfield($posted) {
		$allowed_html = array(
			
			'strong' => array(),
			'em'     => array(),
			'b'      => array(),
			'br'     => array(),
			'i'      => array()
		);
	
		return wp_kses($posted, $allowed_html );
	}

	//what to show on the page
	function emailSignup_callback(){

		?>

		
		<div class='flex_container_white'>

			<div class="box1">
					
					<h1 class="textTitle">Basis Email Signup Form</h1>

					<p class="text">
					Welcome to Basis Email Signup Form. 
					
					<br/>
					<br/>A free plugin that lets you collect emails and grow an email newsletter list. <br />
					This is a great way to get free advertising.
					</p>
					
					
					<p class="text"><b>Select what you want to do using the boxes below:</b></p>

					<form action="options.php" method="POST">
						<?php 
						settings_fields("basis_email_form");
						do_settings_sections("basis-email-form-settings-page");
						submit_button();
						?>
					</form>	
					<p><br /></p>
			

				
			</div>


			<div class="box2">

			
				
					<h1 class="textTitle">Preview of your email signup form</h1>
					<p class="textCenter">This is how your form will look like on the website.</p>
				
				
				<div class="preview-box">
					<span style="background: <?php echo esc_attr($this->bg_color) ?>; border:1px solid <?php echo esc_attr($this->border_color) ?>;">	
										<h3 class='boxtitle'><?php echo get_option('email_boxtitle') ?></h3>
										<p class='boxtext'><?php echo wpautop(get_option('email_textbox')) ?></p>
										<input type='email' name='emailToInput' class='textinput' placeholder='Email'><br>
										<button class='buttonstyle'><?php echo get_option('email_signup' ) ?></button>
					</span>	
				</div>

				
				<p class="textCenter">
						Did you know that you can insert <b>[show_basis_form]</b>, <br/>
						on your posts or pages to show this box.
					
					</p>
				
					<p class="textfooter">
						This plugin is developed by BasisIT.net, and you will soon be able to get a full version:<br/>
						-Cool editor when sending emails to subscribers<br/>
						-Choose certain from templates to style your sign up box<br/>
						-Change button color<br/>
						-and much more...<br/><br/>
					
						Go to <a href="http://BasisIT.net">http://BasisIT.net</a> for more info.
					</p>

			</div>

			<div class="box3">
						Made by <a href="http://basisit.net">BasisIT.net</a><br>
						<a href="https://www.youtube.com/@basisit3511" style="color: #cccccc;">Basis IT @ YouTube</a>
			</div>

		</div>
	
	
		<?php
	}

					
	function esp_send_emails($emailTitle,$emailText) {
		$emails = $this->esp_get_email_list();

		foreach ($emails as $email) {
			$subject = $emailTitle;
			$message = nl2br($emailText); // Convert newlines to <br>
			$headers = ['Content-Type: text/html; charset=UTF-8'];
			
			
			$sent = wp_mail($email, $subject, $message, $headers);
	
			if ($sent) {
				// Display success message
				 echo "<div class='updated'><p>Email sent successfully to $email!</p></div>";
			} else {
				echo "<div class='error'><p>Failed sending email to $email!</p></div>";
			}
		}

	}
	

	function pagePostTITLEbox(){ ?>
		<input type="text" class="page_title_class" name="email_boxtitle" value="<?php echo (esc_html(get_option('email_boxtitle'))); ?>">
	<?php	
	}	

	function pagePostHTMLbox(){ 		
		$email_textbox=get_option('email_textbox');
		wp_editor($email_textbox, 'email_textbox', [
			'textarea_name' => 'email_textbox', // Name attribute for the form
			'textarea_rows' => 10,
			'media_buttons' => false, // Hide "Add Media"
			'teeny'         => true, // Use simplified toolbar
			'quicktags'     => true, // Enable HTML Quicktags
		]);	
	}

	function pagePostsubmit(){ ?>
		<input type="text" class="button_text" name="email_signup" value="<?php echo get_option('email_signup'); ?>"> 
	<?php		
	}

	function locationHTML(){?>
		<select name="email_form_location" class="button_text">
			<option value="0" <?php selected(get_option('email_form_location'), '0') ?> >Do not show</option>
			<option value="1" <?php selected(get_option('email_form_location'), '1') ?> >Beginning of post</option>
			<option value="2" <?php selected(get_option('email_form_location'), '2') ?> >End of post</option>
		
	<?php    
	}

	function colorSelector(){ ?>
		

	<div class="radio-container">
   		 <form action="/action_page.php">

		<div class="radio-group">
			<input id="blue" type="radio" name="basis-color" value="#EBF7FF#85C4EB" <?php if("#EBF7FF#85C4EB" == $this->basis_color){echo "checked='checked'";} ?>>
			<label for="blue" style="background-color: #EBF7FF;">Blue Color</label>
 		 </div>
           
  		 <div class="radio-group">
  	  		 <input id="green" type="radio" name="basis-color" value="#F4E02A#414649" <?php if("#F4E02A#414649" == $this->basis_color){echo "checked='checked'";} ?>>
			 <label for="green" style="background-color: #F4E02A;">Yellow Color</label>
 		 </div>
           
         <div class="radio-group">
			<input id="grey" type="radio" name="basis-color" value="#F0F0F1#CCCCCC" <?php if("#F0F0F1#CCCCCC" == $this->basis_color){echo "checked='checked'";} ?>>
			<label for="grey" style="background-color: #F0F0F1;">Grey Color</label>
         </div>

		 <div class="radio-group">
			<input id="white" type="radio" name="basis-color" value="#ffffff#CCCCCC" <?php if("#ffffff#CCCCCC" == $this->basis_color){echo "checked='checked'";} ?> >
			<label for="white" style="background-color: #ffffff;">White Color</label>
         </div>
  		</form>
 	 </div>
				
		
	<?php
	}


	
	function handle_export_action() {
		if (isset($_GET['page']) && $_GET['page'] === 'word-filter-options' && isset($_GET['action']) && $_GET['action'] === 'export') {
			esc_html($this->exportEmailList());
		}
	}

	Function exportEmailList(){

		// Set headers to download the file rather than displaying it
		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="' . $this->tablename . '.csv"');
		header('Pragma: no-cache');
		header('Expires: 0');

		// Open output stream
		$output = fopen('php://output', 'w');

		// Fetch data from the database
		global $wpdb;
		
		$results = $wpdb->get_results("SELECT * FROM $this->tablename", ARRAY_A); 

		// Output the column headers
		fputcsv($output, array_keys($results[0]));

		// Output each data row
		foreach ($results as $row) {
			fputcsv($output, $row);
		}
		

		// Close the output stream
		fclose($output);
		exit(); // Ensure no other output is sent

	
	}  

	
	

}




$aioEmailCollector = new basisEmailSignupForm();

// Register activation hook
register_activation_hook(__FILE__, array('basisEmailSignupForm', 'activate_plugin'));
