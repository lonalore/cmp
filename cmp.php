<?php
/*
Plugin Name: CaptureMyPage
Plugin URI: http://capturemypage.com
Description: This plugin allows user to create a website screenshot in the content using only a website URL.
Version: 1.0.0
Author: Lóna Lore
Author URI: http://lonalore.hu
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

define('CMP_SERVICE_KEY', get_option('cmp_service_key', ''));
define('CMP_ENDPOINT', 'http://api.capturemypage.com/api/v2/');

class CaptureMyPage {

	var $imageName;

	function CaptureMyPage() {
		$this->__construct();
	}
		
	function __construct() {
		global $wp_version;
		
		if ($wp_version < 3.5) {
			if (basename($_SERVER['PHP_SELF']) != "media-upload.php") {return;}
		} else {
			if (basename($_SERVER['PHP_SELF']) != "media-upload.php" && basename($_SERVER['PHP_SELF']) != "post.php" && basename($_SERVER['PHP_SELF']) != "post-new.php") {return;}
		}
		
		add_filter("media_upload_tabs",array(&$this,"build_tab"));
		add_action("media_upload_captureMyPage", array(&$this, "menu_handle"));
	}

	function build_tab($tabs) {
		$newtab = array('captureMyPage' => __('Website Screenshot', 'wp-plugin-cmp-07'));
		return $this->array_insert($tabs, $newtab, 2);
	}

	function array_insert(&$array, $insert, $position) {
		settype($array, "array");
		settype($insert, "array");
		settype($position, "int");

		// If pos is start, just merge them.
		if ($position == 0) {
			$array = array_merge($insert, $array);
		} else {
			// If pos is end just merge them.
			if ($position >= (count($array) - 1)) {
				$array = array_merge($array, $insert);
			} else {
				// Split into head and tail, then merge head+inserted bit+tail.
				$head = array_slice($array, 0, $position);
				$tail = array_slice($array, $position);
				$array = array_merge($head, $insert, $tail);
			}
		}
		return $array;
	}

	function menu_handle() {
		wp_iframe(array($this, 'media_process'));
	}

	function media_process() {

		if ($_POST['url']) {
			$url = $_POST['url'];
			$url = stripslashes($url);

			if (isset($_POST['predefined']) && $_POST['predefined'] == 'desktop') {
				$options = $this->default_desktop_options();
			}
			elseif (isset($_POST['predefined']) && $_POST['predefined'] == 'tablet_landscape') {
				$options = $this->default_tablet_landscape_options();
			}
			elseif (isset($_POST['predefined']) && $_POST['predefined'] == 'tablet_portrait') {
				$options = $this->default_tablet_portrait_options();
			}
			elseif (isset($_POST['predefined']) && $_POST['predefined'] == 'phone_landscape') {
				$options = $this->default_phone_landscape_options();
			}
			elseif (isset($_POST['predefined']) && $_POST['predefined'] == 'phone_portrait') {
				$options = $this->default_phone_portrait_options();
			}
			else {
				$options = $this->default_options();
			}

			// Set Website URL.
			$options['params']['url'] = $url;
			// Cache.
			$options['params']['useCache'] = (isset($_POST['cache'])) ? (bool) $_POST['cache'] : TRUE;

			// Custom settings is selected.
			if (isset($_POST['predefined']) && $_POST['predefined'] == 'custom') {

				if (isset($_POST['renderDelay']) && (int) $_POST['renderDelay'] > 0) {
					$options['params']['renderDelay'] = (int) $_POST['renderDelay'];
				}

				if (isset($_POST['userAgent']) && !empty($_POST['userAgent'])) {
					$options['params']['userAgent'] = $_POST['userAgent'];
				}

				if (isset($_POST['streamType']) && !empty($_POST['streamType'])) {
					$options['params']['streamType'] = $_POST['streamType'];
				}

				if (isset($_POST['quality']) && (int) $_POST['quality'] > 0) {
					$options['params']['quality'] = (int) $_POST['quality'];
				}

				if (isset($_POST['timeout']) && (int) $_POST['timeout'] > 0) {
					$options['params']['timeout'] = (int) $_POST['timeout'];
				}

				if (isset($_POST['windowSize_w']) && (int) $_POST['windowSize_w'] > 0) {
					$options['params']['windowSize']['width'] = (int) $_POST['windowSize_w'];
				}

				if (isset($_POST['windowSize_h']) && (int) $_POST['windowSize_h'] > 0) {
					$options['params']['windowSize']['height'] = (int) $_POST['windowSize_h'];
				}

				if (isset($_POST['shotSize_w']) && !empty($_POST['shotSize_w'])) {
					$options['params']['shotSize']['width'] = $_POST['shotSize_w'];
				}

				if (isset($_POST['shotSize_h']) && !empty($_POST['shotSize_h'])) {
					$options['params']['shotSize']['height'] = $_POST['shotSize_h'];
				}

				if (isset($_POST['shotOffset_l']) && (int) $_POST['shotOffset_l'] > 0) {
					$options['params']['shotOffset']['left'] = (int) $_POST['shotOffset_l'];
				}

				if (isset($_POST['shotOffset_r']) && (int) $_POST['shotOffset_r'] > 0) {
					$options['params']['shotOffset']['right'] = (int) $_POST['shotOffset_r'];
				}

				if (isset($_POST['shotOffset_t']) && (int) $_POST['shotOffset_t'] > 0) {
					$options['params']['shotOffset']['top'] = (int) $_POST['shotOffset_t'];
				}

				if (isset($_POST['shotOffset_b']) && (int) $_POST['shotOffset_b'] > 0) {
					$options['params']['shotOffset']['bottom'] = (int) $_POST['shotOffset_b'];
				}
			}

			// Build query string.
			$query = http_build_query($options);
			// Make request to CMP API.
			$response = $this->fetch_content(CMP_ENDPOINT . '?' . $query);

			if ($response) {
				$result = json_decode($response, TRUE);

				if ((int) $result['error'] === 0) {
					$imageurl = $result['data'];
				}
				else {
					$error = '<div id="message" class="error"><p>' . $result['message'] . '</p></div>';
				}
			}

			if (isset($imageurl)) {
				$uploads = wp_upload_dir();
				$post_id = isset($_GET['post_id'])? (int) $_GET['post_id'] : 0;
				$ext = pathinfo(basename($imageurl) , PATHINFO_EXTENSION);
				$newfilename = $_POST['newfilename'] ? $_POST['newfilename'] . "." . $ext : basename($imageurl);

				$filename = wp_unique_filename($uploads['path'], $newfilename, $unique_filename_callback = NULL);
				$wp_filetype = wp_check_filetype($filename, NULL);
				$fullpathfilename = $uploads['path'] . "/" . $filename;

				try {
					if (!substr_count($wp_filetype['type'], "image")) {
						throw new Exception(basename($imageurl) . ' is not a valid image. ' . $wp_filetype['type']	. '');
					}

					$image_string = $this->fetch_content($imageurl);

					$fileSaved = file_put_contents($uploads['path'] . "/" . $filename, $image_string);
					if (!$fileSaved) {
						throw new Exception("The file cannot be saved.");
					}

					$attachment = array(
						 'post_mime_type' => $wp_filetype['type'],
						 'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
						 'post_content' => '',
						 'post_status' => 'inherit',
						 'guid' => $uploads['url'] . "/" . $filename
					);

					$attach_id = wp_insert_attachment($attachment, $fullpathfilename, $post_id);
					if (!$attach_id) {
						throw new Exception("Failed to save record into database.");
					}

					require_once(ABSPATH . "wp-admin" . '/includes/image.php');

					$attach_data = wp_generate_attachment_metadata($attach_id, $fullpathfilename);
					wp_update_attachment_metadata($attach_id, $attach_data);
				} catch (Exception $e) {
					$error = '<div id="message" class="error"><p>' . $e->getMessage() . '</p></div>';
				}
			}
		}
		else {
			if (isset($_POST['submit'])) {
				$error = '<div id="message" class="error"><p>' . __('URL field is required', 'wp-plugin-cmp-11') . '</p></div>';
			}
		}

		media_upload_header();

		if (!function_exists("curl_init") && !ini_get("allow_url_fopen")) {
			$err_msg = __('<b>cURL</b> or <b>allow_url_fopen</b> needs to be enabled. Please consult your server Administrator.', 'wp-plugin-cmp-05');
			echo '<div id="message" class="error"><p>' . $err_msg . '</p></div>';
		} elseif (isset($error) && $error) {
			echo $error;
		} else {
			if ((isset($fileSaved) && $fileSaved) && (isset($attach_id) && $attach_id)) {
				echo '<div id="message" class="updated"><p>' . __('Screenshot created and saved successfully.', 'wp-plugin-cmp-06') . '</p></div>';
			}
		}

		$form = '<style type="text/css">';
		$form .= '.custom-settings { display: none; }';
		$form .= '.help-text { font-size: 9px; }';
		$form .= '</style>';

		$form .= '<form action="" method="post" id="image-form" class="media-upload-form type-form">';
		$form .= '<h3 class="media-title">' . __('Create a Website Screenshot', 'wp-plugin-cmp-01') . '</h3>';
		$form .= '<table width="100%" cellpadding="3" cellspacing="0">';

		// URL.
		$form .= '<tr>';
		$form .= '<td width="20%">' . __('Website URL:', 'wp-plugin-cmp-02') . '</td>';
		$form .= '<td><input id="src" type="text" name="url" /></td>';
		$form .= '</tr>';

		// Save as.
		$form .= '<tr>';
		$form .= '<td width="20%">' . __('Save screenshot as (optional):', 'wp-plugin-cmp-03') . '</td>';
		$form .= '<td><input type="text" name="newfilename" /></td>';
		$form .= '</tr>';

		// Settings.
		$form .= '<tr>';
		$form .= '<td width="20%">' . __('Predefined settings:', 'wp-plugin-cmp-12') . '</td>';
		$form .= '<td>';

		$form .= '<div class="radio">';
		$form .= '<label>';
		$form .= '<input type="radio" name="predefined" id="predefined_1" class="predefined" value="dekstop" checked>';
		$form .= __('Desktop', 'wp-plugin-cmp-13');
		$form .= '</label>';
		$form .= '</div>';

		$form .= '<div class="radio">';
  		$form .= '<label>';
		$form .= '<input type="radio" name="predefined" id="predefined_2" class="predefined" value="tablet_landscape">';
        $form .= __('Tablet (landscape)', 'wp-plugin-cmp-14');
		$form .= '</label>';
		$form .= '</div>';

		$form .= '<div class="radio">';
		$form .= '<label>';
		$form .= '<input type="radio" name="predefined" id="predefined_3" class="predefined" value="tablet_portrait">';
		$form .= __('Tablet (portrait)', 'wp-plugin-cmp-15');
		$form .= '</label>';
		$form .= '</div>';

		$form .= '<div class="radio">';
		$form .= '<label>';
		$form .= '<input type="radio" name="predefined" id="predefined_4" class="predefined" value="phone_landscape">';
		$form .= __('Phone (landscape)', 'wp-plugin-cmp-16');
		$form .= '</label>';
		$form .= '</div>';

		$form .= '<div class="radio">';
		$form .= '<label>';
		$form .= '<input type="radio" name="predefined" id="predefined_5" class="predefined" value="phone_portrait">';
		$form .= __('Phone (portrait)', 'wp-plugin-cmp-17');
		$form .= '</label>';
		$form .= '</div>';

		$form .= '<div class="radio">';
		$form .= '<label>';
		$form .= '<input type="radio" name="predefined" id="predefined_6" class="predefined" value="custom">';
		$form .= __('Custom settings', 'wp-plugin-cmp-18');
		$form .= '</label>';
		$form .= '</div>';

		$form .= '</td>';
		$form .= '</tr>';

		// Cache.
		$form .= '<tr>';
		$form .= '<td width="20%">' . __('Use cache?', 'wp-plugin-cmp-22') . '</td>';
		$form .= '<td>';

		$form .= '<div class="radio">';
		$form .= '<label>';
		$form .= '<input type="radio" name="cache" id="cache_1" value="1" checked>';
		$form .= __('Yes', 'wp-plugin-cmp-19');
		$form .= '</label>';
		$form .= '</div>';

		$form .= '<div class="radio">';
		$form .= '<label>';
		$form .= '<input type="radio" name="cache" id="cache_2" value="0">';
		$form .= __('No', 'wp-plugin-cmp-20');
		$form .= '</label>';
		$form .= '</div>';

		$form .= '<span class="help-text">' . __("Try to load thumbnail from cache.", 'wp-plugin-cmp-41') . '</span>';

		$form .= '</td>';
		$form .= '</tr>';

		// Custom settings.
		$form .= '<tr class="custom-settings">';
		$form .= '<td colspan="2"><strong>' . __('Custom Settings', 'wp-plugin-cmp-21') . '</strong></td>';
		$form .= '</tr>';

		// Custom settings - renderDelay.
		$form .= '<tr class="custom-settings">';
		$form .= '<td width="20%">' . __('Render Delay:', 'wp-plugin-cmp-23') . '</td>';
		$form .= '<td>';
		$form .= '<input type="text" name="renderDelay" id="renderDelay" value="3000" size="5" />';
		$form .= '<br />';
		$form .= '<span class="help-text">' . __("Number of milliseconds to wait after a page loads before getting the HTML source.", 'wp-plugin-cmp-40') . '</span>';
		$form .= '</td>';
		$form .= '</tr>';

		// Custom settings - userAgent.
		$form .= '<tr class="custom-settings">';
		$form .= '<td width="20%">' . __('User-Agent:', 'wp-plugin-cmp-42') . '</td>';
		$form .= '<td>';
		$form .= '<input type="text" name="userAgent" id="userAgent" value="Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2062.120 Safari/537.36" />';
		$form .= '<br />';
		$form .= '<span class="help-text">' . __("Custom user-agent.", 'wp-plugin-cmp-43') . '</span>';
		$form .= '</td>';
		$form .= '</tr>';

		// Custom settings - streamType.
		$form .= '<tr class="custom-settings">';
		$form .= '<td width="20%">' . __('Stream Type:', 'wp-plugin-cmp-24') . '</td>';
		$form .= '<td>';
		$form .= '<select name="streamType">';
		$form .= '<option value="jpg">jpg</option>';
		$form .= '<option value="jpeg">jpeg</option>';
		$form .= '<option value="png">png</option>';
		$form .= '</select>';
		$form .= '<br />';
		$form .= '<span class="help-text">' . __("If streaming is used, this designates the file format of the streamed rendering. Possible values are 'png', 'jpg', and 'jpeg'.", 'wp-plugin-cmp-39') . '</span>';
		$form .= '</td>';
		$form .= '</tr>';

		// Custom settings - quality.
		$form .= '<tr class="custom-settings">';
		$form .= '<td width="20%">' . __('Quality:', 'wp-plugin-cmp-25') . '</td>';
		$form .= '<td>';
		$form .= '<input type="text" name="quality" id="quality" value="100" size="5" />';
		$form .= '<br />';
		$form .= '<span class="help-text">' . __("JPEG compression quality. A higher number will look better, but creates a larger file. Quality setting has no effect when streaming.", 'wp-plugin-cmp-38') . '</span>';
		$form .= '</td>';
		$form .= '</tr>';

		// Custom settings - timeout.
		$form .= '<tr class="custom-settings">';
		$form .= '<td width="20%">' . __('Timeout:', 'wp-plugin-cmp-26') . '</td>';
		$form .= '<td>';
		$form .= '<input type="text" name="timeout" id="timeout" value="0" size="5" />';
		$form .= '<br />';
		$form .= '<span class="help-text">' . __("Number of milliseconds to wait before killing the process and assuming webshotting has failed. (0 is no timeout.)", 'wp-plugin-cmp-37') . '</span>';
		$form .= '</td>';
		$form .= '</tr>';

		// Custom settings - windowSize.
		$form .= '<tr class="custom-settings">';
		$form .= '<td width="20%">' . __('Window Size:', 'wp-plugin-cmp-27') . '</td>';
		$form .= '<td>';
		$form .= '<input type="text" name="windowSize_w" id="windowSize_w" value="1024" size="5" />';
		$form .= ' x ';
		$form .= '<input type="text" name="windowSize_h" id="windowSize_h" value="768" size="5" />';
		$form .= '<br />';
		$form .= '<span class="help-text">' . __("The dimensions of the browser window. screenSize is an alias for this property.", 'wp-plugin-cmp-36') . '</span>';
		$form .= '</td>';
		$form .= '</tr>';

		// Custom settings - shotSize.
		$form .= '<tr class="custom-settings">';
		$form .= '<td width="20%">' . __('Shot Size:', 'wp-plugin-cmp-28') . '</td>';
		$form .= '<td>';
		$form .= '<select name="shotSize_w"><option value="window">window</option><option value="all">all</option></select>';
		$form .= ' x ';
		$form .= '<select name="shotSize_h"><option value="window">window</option><option value="all">all</option></select>';
		$form .= '<br />';
		$form .= '<span class="help-text">' . __("The area of the page document, starting at the upper left corner, to render. Possible values are 'screen', 'all', and a number defining a pixel length. 'window' causes the length to be set to the length of the window (i.e. the shot displays what is initially visible within the browser window). 'all' causes the length to be set to the length of the document along the given dimension.", 'wp-plugin-cmp-35') . '</span>';
		$form .= '</td>';
		$form .= '</tr>';

		// Custom settings - shotOffset.
		$form .= '<tr class="custom-settings">';
		$form .= '<td width="20%">' . __('Shot Offset:', 'wp-plugin-cmp-29') . '</td>';
		$form .= '<td>';
		$form .= '<input type="text" name="shotOffset_l" id="shotOffset_l" value="0" size="5" /> (' . __('left', 'wp-plugin-cmp-30') . ')<br />';
		$form .= '<input type="text" name="shotOffset_r" id="shotOffset_r" value="0" size="5" /> (' . __('right', 'wp-plugin-cmp-31') . ')<br />';
		$form .= '<input type="text" name="shotOffset_t" id="shotOffset_t" value="0" size="5" /> (' . __('top', 'wp-plugin-cmp-32') . ')<br />';
		$form .= '<input type="text" name="shotOffset_b" id="shotOffset_b" value="0" size="5" /> (' . __('bottom', 'wp-plugin-cmp-33') . ')<br />';
		$form .= '<span class="help-text">' . __("The left and top offsets define the upper left corner of the screenshot rectangle. The right and bottom offsets allow pixels to be removed from the shotSize dimensions (e.g. a shotSize height of 'all' with a bottom offset of 30 would cause all but the last 30 rows of pixels on the site to be rendered).", 'wp-plugin-cmp-34') . '</span>';
		$form .= '</td>';
		$form .= '</tr>';

		// Submit button.
		$form .= '<tr>';
		$form .= '<td colspan="2" align="center"><input type="submit" name="submit" class="button" value="' . __('Create Screenshot', 'wp-plugin-cmp-04') . '" /></td>';
		$form .= '</tr>';

		$form .= '</table>';
		$form .= '</form>';

		// Script to show/hide custom settings.
		$form .= '<script type="text/javascript">';
		$form .= 'jQuery(document).ready(function() { jQuery(".predefined").click(function() { if (jQuery(this).attr("id") == "predefined_6") { jQuery(".custom-settings").show(); } else {jQuery(".custom-settings").hide(); }}); });';
		$form .= '</script>';

		echo $form;

		if (isset($attach_id) && $attach_id)	{
			$this->media_upload_type_form("image", (isset($errors) ? $errors : NULL), $attach_id);
		}
	}

	function fetch_content($url) {
		if (function_exists("curl_init")) {
			return $this->curl_fetch_content($url);
		} elseif (ini_get("allow_url_fopen")) {
			return $this->fopen_fetch_content($url);
		} else {
			return FALSE;
		}
	}

	function curl_fetch_content($url) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$data = curl_exec($ch);
		curl_close($ch);
		return $data;
	}
	
	function fopen_fetch_content($url) {
		$data = file_get_contents($url, FALSE, (isset($context) ? $context : NULL));
		return $data;
	}

	function media_upload_type_form($type = 'file', $errors = NULL, $id = NULL) {
		$post_id = isset($_REQUEST['post_id'])? intval($_REQUEST['post_id']) : 0;

		$form_action_url = admin_url("media-upload.php?type=$type&tab=type&post_id=$post_id");
		$form_action_url = apply_filters('media_upload_form_url', $form_action_url, $type);

		$html = '<form enctype="multipart/form-data" method="post" action="' . esc_attr($form_action_url) . '" class="media-upload-form type-form validate" id="' . $type . '-form">';
		$html .= '<input type="submit" class="hidden" name="save" value="" />';
		$html .= '<input type="hidden" name="post_id" id="post_id" value="' . (int) $post_id . '" />';
		$html .= wp_nonce_field('media-form');
		
		$html .= '<script type="text/javascript">
		//<![CDATA[
		jQuery(function($){
			var preloaded = $(".media-item.preloaded");
			if (preloaded.length > 0) {
				preloaded.each(function(){prepareMediaItem({id:this.id.replace(/[^0-9]/g, "")},"");});
			}
			updateMediaForm();
		});
		//]]>
		</script>';

		if ($id) {
			if (!is_wp_error($id)) {
				$html .= '<div id="media-items">';
				add_filter('attachment_fields_to_edit', 'media_post_single_attachment_fields_to_edit', 10, 2);
				$html .= get_media_items($id, $errors);
				$html .= '</div>';
				$html .= '<p class="savebutton ml-submit">';
				$html .= '<input type="submit" class="button" name="save" value="' . esc_attr_e('Save all changes') . '" />';
				$html .= '</p>';
			} else {
				$html .= '<div id="media-upload-error">'.esc_html($id->get_error_message()).'</div>';
			}
		}

		$html .= '</form>';
		echo $html;
	}

	function default_options() {
		// Make a request with action "get/thumbnail".
		return array(
			'action'     => 'get/thumbnail',
			'servicekey' => CMP_SERVICE_KEY,
			'params'     => array(
				// URL of website we want to get the HTML source.
				'url'         => '',
				// Number of milliseconds to wait after a page loads before taking the
				// screenshot.
				'renderDelay' => 3000,
				// Custom user-agent.
				'userAgent'   => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2062.120 Safari/537.36',
				// If streaming is used, this designates the file format of the streamed
				// rendering. Possible values are 'png', 'jpg', and 'jpeg'.
				'streamType'  => 'jpg',
				// JPEG compression quality. A higher number will look better, but creates a
				// larger file. Quality setting has no effect when streaming.
				'quality'     => 100,
				// Number of milliseconds to wait before killing the process and assuming
				// webshotting has failed. (0 is no timeout.)
				'timeout'     => 0,
				// The dimensions of the browser window. screenSize is an alias for this
				// property.
				'windowSize'  => array(
					'width'  => 1280,
					'height' => 800,
				),
				// The area of the page document, starting at the upper left
				// corner, to render. Possible values are 'screen', 'all', and a number
				// defining a pixel length.
				//
				// 'window' causes the length to be set to the length of the window (i.e. the
				// shot displays what is initially visible within the browser window).
				//
				// 'all' causes the length to be set to the length of the document along the
				// given dimension.
				'shotSize'    => array(
					'width'  => 'window',
					'height' => 'window',
				),
				// The left and top offsets define the upper left corner of the
				// screenshot rectangle. The right and bottom offsets allow pixels to be
				// removed from the shotSize dimensions (e.g. a shotSize height of 'all' with
				// a bottom offset of 30 would cause all but the last 30 rows of pixels on
				// the site to be rendered).
				'shotOffset'  => array(
					'left'   => 0,
					'right'  => 0,
					'top'    => 0,
					'bottom' => 0,
				),
				// When taking the screenshot, adds custom CSS rules if defined.
				'customCSS'   => '',
				'useCache'    => TRUE,
			),
		);
	}

	function default_desktop_options() {
		// Make a request with action "get/thumbnail".
		return array(
			'action'     => 'get/thumbnail',
			'servicekey' => CMP_SERVICE_KEY,
			'params'     => array(
				// URL of website we want to get the HTML source.
				'url'         => '',
				// Number of milliseconds to wait after a page loads before taking the
				// screenshot.
				'renderDelay' => 3000,
				// Custom user-agent.
				'userAgent'   => 'Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/43.0.2357.124 Safari/537.36',
				// If streaming is used, this designates the file format of the streamed
				// rendering. Possible values are 'png', 'jpg', and 'jpeg'.
				'streamType'  => 'jpg',
				// JPEG compression quality. A higher number will look better, but creates a
				// larger file. Quality setting has no effect when streaming.
				'quality'     => 100,
				// Number of milliseconds to wait before killing the process and assuming
				// webshotting has failed. (0 is no timeout.)
				'timeout'     => 0,
				// The dimensions of the browser window. screenSize is an alias for this
				// property.
				'windowSize'  => array(
					'width'  => 1280,
					'height' => 800,
				),
				// The area of the page document, starting at the upper left
				// corner, to render. Possible values are 'screen', 'all', and a number
				// defining a pixel length.
				//
				// 'window' causes the length to be set to the length of the window (i.e. the
				// shot displays what is initially visible within the browser window).
				//
				// 'all' causes the length to be set to the length of the document along the
				// given dimension.
				'shotSize'    => array(
					'width'  => 'window',
					'height' => 'window',
				),
				// The left and top offsets define the upper left corner of the
				// screenshot rectangle. The right and bottom offsets allow pixels to be
				// removed from the shotSize dimensions (e.g. a shotSize height of 'all' with
				// a bottom offset of 30 would cause all but the last 30 rows of pixels on
				// the site to be rendered).
				'shotOffset'  => array(
					'left'   => 0,
					'right'  => 0,
					'top'    => 0,
					'bottom' => 0,
				),
				// When taking the screenshot, adds custom CSS rules if defined.
				'customCSS'   => '',
				'useCache'    => TRUE,
			),
		);
	}

	function default_tablet_landscape_options() {
		// Make a request with action "get/thumbnail".
		return array(
			'action'     => 'get/thumbnail',
			'servicekey' => CMP_SERVICE_KEY,
			'params'     => array(
				// URL of website we want to get the HTML source.
				'url'         => '',
				// Number of milliseconds to wait after a page loads before taking the
				// screenshot.
				'renderDelay' => 3000,
				// Custom user-agent.
				'userAgent'   => 'Mozilla/5.0 (iPad; CPU OS 5_1_1 like Mac OS X) AppleWebKit/534.46 (KHTML, like Gecko) Version/5.1 Mobile/9B206 Safari/7534.48.3',
				// If streaming is used, this designates the file format of the streamed
				// rendering. Possible values are 'png', 'jpg', and 'jpeg'.
				'streamType'  => 'jpg',
				// JPEG compression quality. A higher number will look better, but creates a
				// larger file. Quality setting has no effect when streaming.
				'quality'     => 100,
				// Number of milliseconds to wait before killing the process and assuming
				// webshotting has failed. (0 is no timeout.)
				'timeout'     => 0,
				// The dimensions of the browser window. screenSize is an alias for this
				// property.
				'windowSize'  => array(
					'width'  => 800,
					'height' => 600,
				),
				// The area of the page document, starting at the upper left
				// corner, to render. Possible values are 'screen', 'all', and a number
				// defining a pixel length.
				//
				// 'window' causes the length to be set to the length of the window (i.e. the
				// shot displays what is initially visible within the browser window).
				//
				// 'all' causes the length to be set to the length of the document along the
				// given dimension.
				'shotSize'    => array(
					'width'  => 'window',
					'height' => 'window',
				),
				// The left and top offsets define the upper left corner of the
				// screenshot rectangle. The right and bottom offsets allow pixels to be
				// removed from the shotSize dimensions (e.g. a shotSize height of 'all' with
				// a bottom offset of 30 would cause all but the last 30 rows of pixels on
				// the site to be rendered).
				'shotOffset'  => array(
					'left'   => 0,
					'right'  => 0,
					'top'    => 0,
					'bottom' => 0,
				),
				// When taking the screenshot, adds custom CSS rules if defined.
				'customCSS'   => '',
				'useCache'    => TRUE,
			),
		);
	}

	function default_tablet_portrait_options() {
		// Make a request with action "get/thumbnail".
		return array(
			'action'     => 'get/thumbnail',
			'servicekey' => CMP_SERVICE_KEY,
			'params'     => array(
				// URL of website we want to get the HTML source.
				'url'         => '',
				// Number of milliseconds to wait after a page loads before taking the
				// screenshot.
				'renderDelay' => 3000,
				// Custom user-agent.
				'userAgent'   => 'Mozilla/5.0 (iPad; CPU OS 5_1_1 like Mac OS X) AppleWebKit/534.46 (KHTML, like Gecko) Version/5.1 Mobile/9B206 Safari/7534.48.3',
				// If streaming is used, this designates the file format of the streamed
				// rendering. Possible values are 'png', 'jpg', and 'jpeg'.
				'streamType'  => 'jpg',
				// JPEG compression quality. A higher number will look better, but creates a
				// larger file. Quality setting has no effect when streaming.
				'quality'     => 100,
				// Number of milliseconds to wait before killing the process and assuming
				// webshotting has failed. (0 is no timeout.)
				'timeout'     => 0,
				// The dimensions of the browser window. screenSize is an alias for this
				// property.
				'windowSize'  => array(
					'width'  => 600,
					'height' => 800,
				),
				// The area of the page document, starting at the upper left
				// corner, to render. Possible values are 'screen', 'all', and a number
				// defining a pixel length.
				//
				// 'window' causes the length to be set to the length of the window (i.e. the
				// shot displays what is initially visible within the browser window).
				//
				// 'all' causes the length to be set to the length of the document along the
				// given dimension.
				'shotSize'    => array(
					'width'  => 'window',
					'height' => 'window',
				),
				// The left and top offsets define the upper left corner of the
				// screenshot rectangle. The right and bottom offsets allow pixels to be
				// removed from the shotSize dimensions (e.g. a shotSize height of 'all' with
				// a bottom offset of 30 would cause all but the last 30 rows of pixels on
				// the site to be rendered).
				'shotOffset'  => array(
					'left'   => 0,
					'right'  => 0,
					'top'    => 0,
					'bottom' => 0,
				),
				// When taking the screenshot, adds custom CSS rules if defined.
				'customCSS'   => '',
				'useCache'    => TRUE,
			),
		);
	}

	function default_phone_landscape_options() {
		// Make a request with action "get/thumbnail".
		return array(
			'action'     => 'get/thumbnail',
			'servicekey' => CMP_SERVICE_KEY,
			'params'     => array(
				// URL of website we want to get the HTML source.
				'url'         => '',
				// Number of milliseconds to wait after a page loads before taking the
				// screenshot.
				'renderDelay' => 3000,
				// Custom user-agent.
				'userAgent'   => 'Mozilla/5.0 (iPhone; CPU iPhone OS 6_1_3 like Mac OS X) AppleWebKit/536.26 (KHTML, like Gecko) Mobile/10B329',
				// If streaming is used, this designates the file format of the streamed
				// rendering. Possible values are 'png', 'jpg', and 'jpeg'.
				'streamType'  => 'jpg',
				// JPEG compression quality. A higher number will look better, but creates a
				// larger file. Quality setting has no effect when streaming.
				'quality'     => 100,
				// Number of milliseconds to wait before killing the process and assuming
				// webshotting has failed. (0 is no timeout.)
				'timeout'     => 0,
				// The dimensions of the browser window. screenSize is an alias for this
				// property.
				'windowSize'  => array(
					'width'  => 480,
					'height' => 320,
				),
				// The area of the page document, starting at the upper left
				// corner, to render. Possible values are 'screen', 'all', and a number
				// defining a pixel length.
				//
				// 'window' causes the length to be set to the length of the window (i.e. the
				// shot displays what is initially visible within the browser window).
				//
				// 'all' causes the length to be set to the length of the document along the
				// given dimension.
				'shotSize'    => array(
					'width'  => 'window',
					'height' => 'window',
				),
				// The left and top offsets define the upper left corner of the
				// screenshot rectangle. The right and bottom offsets allow pixels to be
				// removed from the shotSize dimensions (e.g. a shotSize height of 'all' with
				// a bottom offset of 30 would cause all but the last 30 rows of pixels on
				// the site to be rendered).
				'shotOffset'  => array(
					'left'   => 0,
					'right'  => 0,
					'top'    => 0,
					'bottom' => 0,
				),
				// When taking the screenshot, adds custom CSS rules if defined.
				'customCSS'   => '',
				'useCache'    => TRUE,
			),
		);
	}

	function default_phone_portrait_options() {
		// Make a request with action "get/thumbnail".
		return array(
			'action'     => 'get/thumbnail',
			'servicekey' => CMP_SERVICE_KEY,
			'params'     => array(
				// URL of website we want to get the HTML source.
				'url'         => '',
				// Number of milliseconds to wait after a page loads before taking the
				// screenshot.
				'renderDelay' => 3000,
				// Custom user-agent.
				'userAgent'   => 'Mozilla/5.0 (iPhone; CPU iPhone OS 6_1_3 like Mac OS X) AppleWebKit/536.26 (KHTML, like Gecko) Mobile/10B329',
				// If streaming is used, this designates the file format of the streamed
				// rendering. Possible values are 'png', 'jpg', and 'jpeg'.
				'streamType'  => 'jpg',
				// JPEG compression quality. A higher number will look better, but creates a
				// larger file. Quality setting has no effect when streaming.
				'quality'     => 100,
				// Number of milliseconds to wait before killing the process and assuming
				// webshotting has failed. (0 is no timeout.)
				'timeout'     => 0,
				// The dimensions of the browser window. screenSize is an alias for this
				// property.
				'windowSize'  => array(
					'width'  => 320,
					'height' => 480,
				),
				// The area of the page document, starting at the upper left
				// corner, to render. Possible values are 'screen', 'all', and a number
				// defining a pixel length.
				//
				// 'window' causes the length to be set to the length of the window (i.e. the
				// shot displays what is initially visible within the browser window).
				//
				// 'all' causes the length to be set to the length of the document along the
				// given dimension.
				'shotSize'    => array(
					'width'  => 'window',
					'height' => 'window',
				),
				// The left and top offsets define the upper left corner of the
				// screenshot rectangle. The right and bottom offsets allow pixels to be
				// removed from the shotSize dimensions (e.g. a shotSize height of 'all' with
				// a bottom offset of 30 would cause all but the last 30 rows of pixels on
				// the site to be rendered).
				'shotOffset'  => array(
					'left'   => 0,
					'right'  => 0,
					'top'    => 0,
					'bottom' => 0,
				),
				// When taking the screenshot, adds custom CSS rules if defined.
				'customCSS'   => '',
				'useCache'    => TRUE,
			),
		);
	}
}

new CaptureMyPage();

add_action('admin_menu', 'cmp_create_menu');

function cmp_create_menu() {
	// Create new top-level menu.
	add_menu_page( __("CMP Settings", 'wp-plugin-cmp-08'), __("CMP Settings", 'wp-plugin-cmp-08'), 'manage_options', __FILE__, 'cmp_plugin_settings_page');
	// Call register settings function.
	add_action('admin_init', 'cmp_plugin_settings');
}

function cmp_plugin_settings() {
	// Register our settings.
	register_setting('cmp-plugin-settings-group', 'cmp_service_key');
}

function cmp_plugin_settings_page() {
	echo '<div class="wrap">';
	echo '<h2>' . __("CMP Settings page", 'wp-plugin-cmp-10') . '</h2>';
	echo '<form method="post" action="options.php">';

	settings_fields('cmp-plugin-settings-group');
	do_settings_sections('cmp-plugin-settings-group');

	echo '<table class="form-table">';
	echo '<tr valign="top">';
	echo '<th scope="row">' . __("Service Key", 'wp-plugin-cmp-09') . '</th>';
	echo '<td><input type="text" name="cmp_service_key" value="' . esc_attr(get_option('cmp_service_key')) . '" /></td>';
	echo '</tr>';
	echo '</table>';

	submit_button();

	echo '</form>';
	echo '</div>';
}
