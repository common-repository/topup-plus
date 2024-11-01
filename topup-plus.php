<?php
/*
Plugin Name: TopUp Plus
Plugin URI: http://www.puzich.com/wordpress-plugins/topup-plus
Description: Seamless integration of TopUp (similar to Lightview, Lightbox, Thickbox, Floatbox, Thickbox, Fancybox) to create a nice overlay to display images and videos without the need to change html.
Author: Puzich
Author URI: http://www.puzich.com
Version: 2.5.3.2
Put in /wp-content/plugins/ of your Wordpress installation
*/

if (!function_exists('is_admin')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

global $wp_version;
define('LVPISWP27', version_compare($wp_version, '2.7', '>='));
define('LVPISWP28', version_compare($wp_version, '2.8', '>='));
define('LVPISWP29', version_compare($wp_version, '2.9', '>='));


class topup_plus {
	
	// version
	var $version;

	// Nag Check Url
	var $chk_url = 'http://chk.puzich.com/';
	
	// put all options in
	var $options = array();
	
	// put all video tags in 
	var $video = array();
	
	function topup_plus() {
		$this->__construct();
	}
	
	function __construct() {
		//load language
		if (function_exists('load_plugin_textdomain'))
			load_plugin_textdomain('topupplus', '/wp-content/plugins/topup-plus/langs');
		
		// set version
		$this->version = $this->get_version();
		
		// get options	
		$this->options = get_option('topup_plus');
		(!is_array($this->options) && !empty($this->options)) ? $this->options = unserialize($this->options) : $this->options = false;
		
		// install default options
		register_activation_hook(__FILE__, array(&$this, 'install'));
		
		// uninstall features
		//register_deactivation_hook(__FILE__, array(&$this, 'uninstall'));
		
		// more setup links
		add_filter('plugin_row_meta', array(&$this, 'register_plugin_links'), 10, 2);
		
		// nagscreen at plugins page
		add_action('after_plugin_row', array(&$this, 'plugin_version_nag'));
		
		// add wp-filter
		add_filter('the_content', array(&$this, 'change_content'), 150);
		
		//add wp-action
		add_action('wp_enqueue_scripts', array(&$this, 'enqueueJS'));
		add_action('wp_enqueue_scripts', array(&$this, 'enqueueCSS'));
		add_action('wp_head', array(&$this, 'add_header'));

		add_action('admin_menu', array(&$this, 'AdminMenu'));
		
		//add wp-shortcodes
		if($this->options['load_gallery'] && LVPISWP28 == true)
			add_filter('attachment_link', array(&$this, 'direct_image_urls_for_galleries'), 10, 2);
					
		// add MCE Editor Button
		if($this->options['show_video']) {
			add_action('init', array(&$this, 'mceinit'));
			add_action('admin_print_scripts', array(&$this, 'add_admin_header'));
		}
		
		// define object targets and links
		$this->video['default']['target'] = '<a href="###EMBEDURL###" title="###TITLE###" class="top_up" toptions="type = ###MEDIATYPE###, title = \'###TITLE###\', width = ###WIDTH###, height = ###HEIGHT###"><span class="tup_previewimage" style="width:###PREVIEWWIDTH###px;"><img src="###IMAGE###" width="###PREVIEWWIDTH###" height="###PREVIEWHEIGHT###" alt="###TITLE###" /><span class="tup_playbutton" style="left: ###LEFT###px; top: ###TOP###px;"> ▶ </span></span></a><br />';
		$this->video['default']['feed']   = '<img src="###IMAGE###" width="###PREVIEWWIDTH###" height="###PREVIEWHEIGHT###" alt="###TITLE###" />';
		$this->video['default']['link']   = "<a title=\"###VIDEOTITLE###\" href=\"###LINK### \">###PROVIDER### ###SEPERATOR######TITLE###</a>";
				
		$this->video['youtube']['iphone'] = '<object width="###WIDTH###" height="###HEIGHT###"><param name="movie" value="http://www.youtube.com/v/###VIDEOID###"></param><embed src="http://www.youtube.com/v/###VIDEOID###" type="application/x-shockwave-flash" width="###WIDTH###" height="###HEIGHT###"></embed></object><br />';
	}
	
	// Plugin Links
	function register_plugin_links($links, $file) {
		$base = plugin_basename(__FILE__);
		if ($file == $base) {
			$links[] = '<a href="options-general.php?page=' . $base .'">' . __('Settings','topupplus') . '</a>';
			$links[] = '<a href="http://www.puzich.com/wordpress-plugins/topup-plus">' . __('Support','topupplus') . '</a>';
			$links[] = '<a href="http://www.puzich.com/go/donate/">' . __('Donate with PayPal','topupplus') . '</a>';
			$links[] = '<a href="http://www.puzich.com/go/wishlist/">' . __('Donate with Amazon Wishlist','topupplus') . '</a>';
		}
		return $links;
	}

	// nagscreen at plugins page, based on the code of cformsII by Oliver Seidel
	function plugin_version_nag($plugin) {
		if (preg_match('/topup-plus/i',$plugin)) {
			$checkfile = $this->chk_url . 'topup-plus.' . $this->version . '.chk';
			$this->plugin_version_get($checkfile, $this->version);
		}
	}
	
	function plugin_version_get($checkfile, $version, $tr=false) {
		$vcheck = wp_remote_fopen($checkfile);
		
		if($vcheck) {
			$status = explode('@', $vcheck);
			$theVersion = $status[1];
			$theMessage = $status[3];
			
			if( $theMessage ) {
				if($tr == true)
					echo '</tr><tr>';
			
				if(version_compare($theVersion, $version) == 0) {
					$msg = __("Notice for:", "topupplus");
				} else {
					$msg = __("Update-Notice for:", "topupplus");
				}
				
				$msg .= ' <strong> Version '.$theVersion.'</strong><br />'.$theMessage;
				echo '<td colspan="5" class="plugin-update" style="line-height:1.2em;">'.$msg.'</td>';
			}
            
				if (version_compare($theVersion, $version) == 1) {
					$checkfile = $this->chk_url . 'topup-plus.' . $theVersion . '.chk';
					$this->plugin_version_get($checkfile, $theVersion, true);
				}
        }
    }
	
	
	// Returns the plugin version
	function get_version() {
		if(!function_exists('get_plugin_data')) {
			if(file_exists(ABSPATH . 'wp-admin/includes/plugin.php')) {
				require_once(ABSPATH . 'wp-admin/includes/plugin.php'); //2.3+
			} elseif (file_exists(ABSPATH . 'wp-admin/admin-functions.php')) {
				require_once(ABSPATH . 'wp-admin/admin-functions.php'); //2.1
			} else { 
				return false;
			}
		}
		$data = get_plugin_data(__FILE__, false, false);

		return $data['Version'];
	}
	
	function install() {
		//add default options
		$default = array(
					'load_gallery' => true,
					'show_video' => true,
					'topup_title' => 'Gallery {alt} ({current} of {total})',
					'topup_readAltText' => true,
					'topup_layout' => 'dashboard',
					'topup_overlayClose' => true,
					'topup_shaded' => true,
					'topup_effect' => 'transform',
					'topup_resizable' => true,
					'video_showlink' => true,
					'video_smallink' => true,
					'video_preview_width'=> '300',
					'video_width' => '500',
					'video_separator' => '- ',
					'video_showinfeed' => true
					);
		
		if(!is_array($this->options)) {
			$this->options = array();
		}
			
		foreach($default as $k => $v) {
			if(empty($this->options[$k])) {
				$this->options[$k] = $v;
			}
		}
		
		// set options
		update_option('topup_plus', serialize($this->options));
		
		return true;
	}

	function change_content($content) {
		
		// makes a set of pictures to a gallery
		// taken from add-lightbox-title plugin! Gracias!
		$pattern['image'][0]		= "/(<a)([^\>]*?) href=('|\")([A-Za-z0-9\?=,%\/_\.\~\:-]*?)(\.bmp|\.gif|\.jpg|\.jpeg|\.png)('|\")([^\>]*?)>(.*?)<\/a>/i";
		$replacement['image'][0]	= '$1 href=$3$4$5$6$2$7>$8</a>';
		// [0] <a xyz href="...(.bmp|.gif|.jpg|.jpeg|.png)" zyx>yx</a> --> <a href="...(.bmp|.gif|.jpg|.jpeg|.png)" xyz zyx>yx</a>
		$pattern['image'][1]		= "/(<a href=)('|\")([A-Za-z0-9\?=,%\/_\.\~\:-]*?)(\.bmp|\.gif|\.jpg|\.jpeg|\.png)('|\")([^\>]*?)(>)(.*?)(<\/a>)/i";
		$replacement['image'][1]	= '$1$2$3$4$5 class="top_up" toptions="group = '. $GLOBALS['post']->ID .'"$6$7$8$9';
		// [1] <a href="...(.bmp|.gif|.jpg|.jpeg|.png)" xyz zyx>yx</a> --> <a href="...(.bmp|.gif|.jpg|.jpeg|.png)" class="top_up" rel="gallery[POST-ID]" xyz zyx>yx</a>
		$pattern['image'][2]		= "/(<a href=)('|\")([A-Za-z0-9\?=,%\/_\.\~\:-]*?)(\.bmp|\.gif|\.jpg|\.jpeg|\.png)('|\") rel=('|\")gallery([^\>]*?)('|\")([^\>]*?) rel=('|\")(gallery)([^\>]*?)('|\")([^\>]*?)(>)(.*?)(<\/a>)/i";
		$replacement['image'][2]	= '$1$2$3$4$5$9 rel=$10$11$12$13$14$15$16$17';
		// [2] <a href="...(.bmp|.gif|.jpg|.jpeg|.png)" rel="gallery[POST-ID]" xyz rel="(gallery)yxz" zyx>yx</a> --> <a href="...(.bmp|.gif|.jpg|.jpeg|.png)" xyz rel="(gallery)yxz" zyx>yx</a>  !!!
		$pattern['image'][3]		= "/(<a href=)('|\")([A-Za-z0-9\?=,%\/_\.\~\:-]*?)(\.bmp|\.gif|\.jpg|\.jpeg|\.png)('|\")([^\>]*?)(>)(.*?) title=('|\")(.*?)('|\")(.*?)(<\/a>)/i";
		$replacement['image'][3]	= '$1$2$3$4$5$6 title=$9$10$11$7$8 title=$9$10$11$12$13';
		// [3] <a href="...(.bmp|.gif|.jpg|.jpeg|.png)" xyz>yx title=yxz xy</a> --> <a href="...(.bmp|.gif|.jpg|.jpeg|.png)" xyz title=yxz>yx title=yxz xy</a>
		$pattern['image'][4]		= "/(<a href=)('|\")([A-Za-z0-9\?=,%\/_\.\~\:-]*?)(\.bmp|\.gif|\.jpg|\.jpeg|\.png)('|\")([^\>]*?) title=([^\>]*?) title=([^\>]*?)(>)(.*?)(<\/a>)/i";
		$replacement['image'][4]	= '$1$2$3$4$5$6 title=$7$9$10$11';
		// [4] <a href="...(.bmp|.gif|.jpg|.jpeg|.png)" xyz title=zxy xzy title=yxz>yx</a> --> <a href="...(.bmp|.gif|.jpg|.jpeg|.png)" xyz title=zxy xzy>yx</a>
		$content = preg_replace($pattern['image'], $replacement['image'], $content);
		
		// RegEx for Videos
		$pattern['video'][1] = "/\[(youtube|youtubehq|vimeo|bliptv|video) ([[:graph:]]+) (nolink)\]/";
		$pattern['video'][2] = "/\[(youtube|youtubehq|vimeo|bliptv|video) ([[:graph:]]+) ([[:print:]]+)\]/";
		$pattern['video'][3] = "/\[(youtube|youtubehq|vimeo|bliptv|video) ([[:graph:]]+)\]/";
		
		// does the video thing
		if($this->options['show_video']) {
			$content = preg_replace_callback($pattern['video'][1], array(&$this, 'video_callback'), $content);
			$content = preg_replace_callback($pattern['video'][2], array(&$this, 'video_callback'), $content);
			$content = preg_replace_callback($pattern['video'][3], array(&$this, 'video_callback'), $content);
		}
	
		return $content;
	}
	
	// video callback logic
	function video_callback($match) {
		$output = '';
		// insert plugin link
		if (!is_feed()) {
			switch ($match[1]) {
				case "youtube": 
				case "youtubehq": 
					if ($this->is_mobile() == true) {
						$output .= $this->video['youtube']['iphone'];
					} else {
						$output .= $this->video['default']['target']; 
					}
					break;
				case "vimeo": $output .= $this->video['default']['target']; break;
				case "bliptv": $output .= $this->video['default']['target']; break;
				default: break;
			}
			
			if ($this->options['video_showlink'] == true) {
				if ($match[3] != "nolink") {
					if ($this->options['video_smallink']) 
						$output .= "<small>";
					
					switch ($match[1]) {
						case "youtube": $output .= $this->video['default']['link']; break;
						case "youtubehq": $output .= $this->video['default']['link']; break;
						case "vimeo": $output .= $this->video['default']['link']; break;
						case "bliptv": $output .= $this->video['default']['link']; break;
						default: break;
					}
					
					if ($this->options['video_smallink']) 
						$output .= "</small>";
				}
			}
		} elseif ($this->options['video_showinfeed'] == true) { 
			$output .= $this->video['default']['feed'];
			$output .= '<br />\n';
			$output .= __('[There is a video that cannot be displayed in this feed. ', 'topupplus').'<a href="'.get_permalink().'">'.__('Visit the blog entry to see the video.]','topupplus').'</a>';
		}
		
		// postprocessing
		// first replace video_separator
		$output = str_replace("###SEPERATOR###", $this->options['video_separator'], $output);
		
		// replace video IDs and text	
		if ($match[3] != "nolink") {
			$output = str_replace("###TITLE###", $match[3], $output);
		} else {
			$output = str_replace("###TITLE###", '', $output);
		}
		$output = str_replace("###VIDEOID###", $match[2], $output);
		
		
		// replace palceholder with videodata
		$videodata = $this->get_cached_videodata($match[1], $match[2]);
		$output = str_replace("###IMAGE###", $videodata['thumbnail'], $output); // Thumbnail
		$output = str_replace("###EMBEDURL###", $videodata['embedurl'], $output); // Embed URL
		$output = str_replace("###LINK###", $videodata['link'], $output); // Link
		$output = str_replace("###VIDEOTITLE###", $videodata['title'], $output); // Video Title
		$output = str_replace("###PROVIDER###", $videodata['provider'], $output);
		if(!empty($videodata['mediatype'])) {
			$output = str_replace("###MEDIATYPE###", $videodata['mediatype'], $output);
		} else {
			$output = str_replace("###MEDIATYPE###", 'flash', $output);
		}

	 	if(!empty($videodata['height']) && !empty($videodata['width'])) {
			$output = str_replace("###WIDTH###", $this->options['video_width'], $output); // Width
			$output = str_replace("###HEIGHT###", floor($this->options['video_width'] / $videodata['width'] * $videodata['height']), $output); // Height
			$output = str_replace("###PREVIEWWIDTH###", $this->options['video_preview_width'], $output); // Preview Width
			$output = str_replace("###PREVIEWHEIGHT###", floor($this->options['video_preview_width'] / $videodata['width'] * $videodata['height']), $output); // Preview Height
			$output = str_replace("###LEFT###", floor($this->options['video_preview_width'] / 2) - 50, $output); // left
			$output = str_replace("###TOP###", floor(($this->options['video_preview_width'] / $videodata['width'] * $videodata['height']) / 2) - 25, $output); // top
		}
		
		// add HTML comment
		$output .= "\n<!-- generated by WordPress Plugin TopUp Plus $this->version -->\n";
			
		// got errors during receiving videodata? Show nice placeholder
		if ($videodata['available'] == false) {
				$output = sprintf('<img src="'. @plugins_url('topup-plus/images/novideo.png') .'" width="%s" height="%s" alt="'. __('Video not available', 'topupplus') .'" /><br />',
							$this->options['video_preview_width'],
							floor($this->options['video_preview_width'] / 640 * 360)
							);
		}	
		
		// show debug informations under the video
		if($this->options['video_debug'] == true ) {
			$debug = sprintf('<div style="background-color:#FFC0CB; border:1px solid silver; color:#110000; margin:0 0 1.5em; overflow:auto; padding: 3px;">
								<strong>Provider:</strong> %s <br />
								<strong>Title:</strong> %s <br />
								<strong>Thumbnail URL:</strong> %s <br />
								<strong>Embed URL:</strong> %s <br />
								<strong>Link:</strong> %s <br />
								<strong>Width:</strong> %s px <br />
								<strong>Height:</strong> %s px <br />
								<strong>Got data @:</strong> %s<br />
							</div>',
							$videodata['provider'],
							$videodata['title'],
							$videodata['thumbnail'],
							$videodata['embedurl'],
							$videodata['link'],
							$videodata['width'],
							$videodata['height'],
							date('d.n.Y H:i:s', $videodata['timestamp'])
							);

			$output .= $debug;
		}
		return $output;
	}
	
	// get the video data out of the cache
	function get_cached_videodata($service, $id) {
		$videodata = get_post_meta($GLOBALS['post']->ID, '_tup', true);

		// if no cached data available or data is older than 24 hours, refresh/get data from video provider
		if(empty($videodata[$service][$id]) || $videodata[$service][$id]['timestamp'] + (60 * 60 * 24) < time()  ) {
			$videodata[$service][$id] = $this->get_videodata($service, $id);
			update_post_meta($GLOBALS['post']->ID, '_tup', $videodata);
		}
		
		return $videodata[$service][$id];
		//return $this->get_videodata($service, $id);	
	}
	
	// puts the video data into cache
	function get_videodata($service, $id) {
		switch($service) {
			case "youtube":
			case "youtubehq":
				$api = sprintf('http://gdata.youtube.com/feeds/api/videos/%s', $id);
				$xml = @simplexml_load_string(wp_remote_fopen($api));
				
				if (is_object($xml)) {
					$media    = $xml->children('http://search.yahoo.com/mrss/');
					
					if($media->group->thumbnail) {
						$attribs  = $media->group->thumbnail[3]->attributes();
						
						$output['available']    = true;
						$output['provider']     = 'YouTube';
						$output['title']	    = (string) $media->group->title;
						$output['embedurl']	    = sprintf('http://www.youtube.com/v/%s', $id);
						$output['mediatype']	= 'flash';
						$output['thumbnail']    = (string) $attribs['url'];
						$output['width']        = (int) $attribs['width'];
						$output['height']       = (int) $attribs['height'];
						$output['link']         = sprintf('http://www.youtube.com/watch?v=%s', $id);
						
						// add autoplay
						$output['embedurl'] = sprintf('%s&amp;autoplay=1', $output['embedurl']);
					
						if($service == 'youtubehq')
							$output['embedurl'] = sprintf('%s&amp;ap=%%2526&amp;fmt%%3D22&amp;hd=1', $output['embedurl']);
							echo $output['embedurl'];
					
					} else {
						$output['available'] = false;
					}
					
				} else {
					$output['available'] = false;
				}
				$output['timestamp'] = time();
				
				break;
			case "vimeo":
				// check if $id is numeric
				if(!is_numeric($id)) {
					$output['available'] = false;
					return $output;
				}
					
				// Get preview image from vimeo
				$api    = sprintf('http://vimeo.com/api/v2/video/%s.xml', $id);
				$video  = @simplexml_load_string(wp_remote_fopen($api));
				$outout = array();
				$output['available']    = true;
				$output['provider']     = 'Vimeo';
				$output['title']        = (string) $video->video->title;
				$output['embedurl']	    = (string) sprintf('http://player.vimeo.com/video/%s', $id);
				$output['mediatype']	= 'iframe';
				$output['thumbnail']    = (string) $video->video->thumbnail_large;
				$output['width']        = (int) $video->video->width;
				$output['height']       = (int) $video->video->height;
				$output['link']         = sprintf('http://www.vimeo.com/%s', $id);
				$output['timestamp'] = time();
				
				// add autoplay
				$output['embedurl'] = sprintf('%s?autoplay=1', $output['embedurl']);
				
				// check response
				if(empty($output) || empty($output['width']) || empty($output['height']) || empty($output['thumbnail']) ) {
					$output['available'] = false;
					return $output;
				}
				
				break;
			case "bliptv":
				// require SimplePie
				require_once(ABSPATH . WPINC . '/feed.php');
				$api = sprintf('http://www.blip.tv/file/%s?skin=rss', $id);
				$namespace['media'] = 'http://search.yahoo.com/mrss/';
				$namespace['blip']  = 'http://blip.tv/dtd/blip/1.0';
				
				// fetch feed
				$rss = fetch_feed($api);
					
				if(is_wp_error($rss)) {
					$output['available'] == false;
					return $output;
				}
				
				// get items
				$item = $rss->get_item();
				
				// get media items
				$mediaGroup     = $item->get_item_tags($namespace['media'], 'group');
				$mediaContent   = $mediaGroup[0]['child'][$namespace['media']]['content'];
				
				// get blip items
				$blipThumbnail = $item->get_item_tags($namespace['blip'], 'thumbnail_src');
				$blipEmbedURL  = $item->get_item_tags($namespace['blip'], 'embedUrl');
					
				$output['available']    = true;
				$output['provider']     = 'Blip.TV';
				$output['title']        = (string) $rss->get_title();
				$output['embedurl']     = (string) $blipEmbedURL[0]['data'];
				$output['mediatype']	= 'flash';
				$output['thumbnail']    = (string) sprintf('http://a.images.blip.tv/%s', $blipThumbnail[0]['data']);
				$output['height']       = (int) $mediaContent[count($mediaContent)-1]['attribs']['']['height'];
				$output['width']        = (int) $mediaContent[count($mediaContent)-1]['attribs']['']['width'];
				$output['link']         = (string) $item->get_link();
				$output['timestamp'] = time();
				
				// add autoplay
				$output['embedurl'] = sprintf('%s&amp;autoStart=true', $output['embedurl']);
				
				// check response
				if(empty($output)) {
					$output['available'] = false;
					return $output;
				}
					
				break;
			case "video":
				break;
			default: break;
		}	
		return $output;
	}

	function is_mobile() {
		$uas = array ( 'iPhone', 'iPod', 'iPad', 'Android');

		foreach ( $uas as $useragent ) {
			$pattern = sprintf('/%s/', $useragent);
			if ( (bool) preg_match($pattern, $_SERVER['HTTP_USER_AGENT'])) {
				return true;
			} 
		}
		return false;
	}
	
	function add_header() {
		$path = "/wp-content/plugins/topup-plus";
		
		$script = "\n<!-- TopUp Plus Plugin $this->version -->\n";
		
		$script .= sprintf('<script type="text/javascript">
						TopUp.host = "%s/";
						TopUp.images_path = "%s/images/top_up/";
						TopUp.defaultPreset({
								title: "%s",
								readAltText: %s,
								layout: "%s",
								overlayClose: %s,
								shaded: %s,
								effect: "%s",
								resizable: %s
							});
					</script>',
						get_option('siteurl'),
						$path,
						$this->options['topup_title'],
						$this->options['topup_readAltText'],
						$this->options['topup_layout'],
						$this->options['topup_overlayClose'],
						$this->options['topup_shaded'],
						$this->options['topup_effect'],
						$this->options['topup_resizable']);
		$script .= "\n<!-- TopUp Plus Plugin $this->version -->\n";
		
		echo $script;
	}
	
	function enqueueJS() {
		//wp_enqueue_script('jquery');
		//wp_enqueue_script('jquery-ui-core');
		//wp_enqueue_script('jquery-ui-resizable');
		//wp_enqueue_script('topup', plugins_url('/topup-plus/js/top_up-min.js'), array('jquery', 'jquery-ui-core', 'jquery-ui-resizable' ), $this->version, false);
		wp_enqueue_script('topup', plugins_url('/topup-plus/js/top_up-min.js'), '', $this->version, false);
	}
	
	function enqueueCSS() {
		wp_enqueue_style('topup_plus', plugins_url('/topup-plus/style.css'), false, $this->version, 'screen');		
	}
	
	function AdminMenu() {
		add_options_page('TopUp Plus', (LVPISWP28 ? '<img src="' . @plugins_url('topup-plus/images/icon.png') . '" width="10" height="10" alt="TopUp Plus - Icon" /> ' : '') . 'TopUp Plus', 8, 'topup-plus/'.basename(__FILE__), array(&$this, 'OptionsMenu'));	
	}
	
	function AdminHtmlPrintBoxHeader($id, $title, $right = false) {
		if(LVPISWP27) {
			?>
			<div id="<?php echo $id; ?>" class="postbox">
				<h3 class="hndle"><span><?php echo $title ?></span></h3>
				<div class="inside">
			<?php
		} else {
			?>
			<fieldset id="<?php echo $id; ?>" class="dbx-box">
				<?php if(!$right): ?><div class="dbx-h-andle-wrapper"><?php endif; ?>
				<h3 class="dbx-handle"><?php echo $title ?></h3>
				<?php if(!$right): ?></div><?php endif; ?>
				
				<?php if(!$right): ?><div class="dbx-c-ontent-wrapper"><?php endif; ?>
					<div class="dbx-content">
			<?php
		}
	}

	function OptionsMenu() {
		
		if (!empty($_POST)) {
			
			// ----------
			// General Settings
			// ----------
			// option 'load_gallery'
			if($_POST['load_gallery'] == 'true') {
				$this->options['load_gallery'] = true;
			} else {
				$this->options['load_gallery'] = false;
			}
			
			// option 'show_video'
			if($_POST['show_video'] == 'true') {
				$this->options['show_video'] = true;
			} else {
				$this->options['show_video'] = false;
			}
			
			// ----------
			// TopUp Settings
			// ----------
			
			// option topup_title
			if(!empty($_POST['topup_title'])) {
				$this->options['topup_title'] = $_POST['topup_title'];
			} 
			
			// option topup_readAltText
			if($_POST['topup_readAltText'] == 1) {
				$this->options['topup_readAltText'] = 1;
			} else {
				$this->options['topup_readAltText'] = 0;
			}
			
			// option topup_layout
			if(!empty($_POST['topup_layout'])) {
				$this->options['topup_layout'] = $_POST['topup_layout'];
			} 
			
			// option topup_overlayClose
			if($_POST['topup_overlayClose'] == 1) {
				$this->options['topup_overlayClose'] = 1;
			} else {
				$this->options['topup_overlayClose'] = 0;
			}
			
			// option topup_shaded
			if($_POST['topup_shaded'] == 1) {
				$this->options['topup_shaded'] = 1;
			} else {
				$this->options['topup_shaded'] = 0;
			}
			
			// option topup_effect
			if(!empty($_POST['topup_effect'])) {
				$this->options['topup_effect'] = $_POST['topup_effect'];
			} 
			
			// option topup_resizable
			if($_POST['topup_resizable'] == 1) {
				$this->options['topup_resizable'] = 1;
			} else {
				$this->options['topup_resizable'] = 0;
			}
			
			// ----------
			// Video Settings
			// ----------
			
			// option 'video_showlink'
			if($_POST['video_showlink'] == 'true') {
				$this->options['video_showlink'] = true;
			} else {
				$this->options['video_showlink'] = false;
			}
			
			// option 'video_smallink'
			if($_POST['video_smallink'] == 'true') {
				$this->options['video_smallink'] = true;
			} else {
				$this->options['video_smallink'] = false;
			}
			
			//option 'video_separator'
			if(!empty($_POST['video_separator'])) {
				$this->options['video_separator'] = $_POST['video_separator'];
			}
			
			//option 'video_preview_width'
			if(!empty($_POST['video_preview_width'])) {
				$this->options['video_preview_width'] = $_POST['video_preview_width'];
			}
			
			//option 'video_width'
			if(!empty($_POST['video_width'])) {
				$this->options['video_width'] = $_POST['video_width'];
			}
			
			// option 'video_showinfeed'
			if($_POST['video_showinfeed'] == 'true') {
				$this->options['video_showinfeed'] = true;
			} else {
				$this->options['video_showinfeed'] = false;
			}
			
			// option 'video_debug'
			if($_POST['video_debug'] == 'true') {
				$this->options['video_debug'] = true;
			} else {
				$this->options['video_debug'] = false;
			}
		
			// update options
			update_option('topup_plus', serialize($this->options));
		
			// echo successfull update
			echo '<div id="message" class="updated fade"><p><strong>' . __('Options saved.', 'topupplus') . '</strong></p></div>';
		}
		
		?>
		<div class="wrap">
			<h2>TopUp Plus</h2>
					
				<?php // Donate ?>
				<h3><?php _e('Donation', 'topupplus'); ?></h3>
				<table>
				<tbody>
				<tr>
					<td>
						<?php _e('TopUp Plus has required a great deal of time and effort to develop. If it\'s been useful to you then you can support this development by making a small donation. This will act as an incentive for me to carry on developing it, providing countless hours of support, and including any enhancements that are suggested. If you don\'t have a clue how much you want to spend, the average of the last donations were €8. But every other amount is welcome. Please note, that PayPal takes for every transaction round about €0.50. So every donation below €1 is only a donation to PayPal ;-) Further you have the options to have a look at my amazon wishlist.', 'topupplus'); ?><br />
						<center><a href="http://www.puzich.com/go/donate"><img src="<?php echo get_option('siteurl'); ?>/wp-content/plugins/topup-plus/images/donate.gif" /> <?php _e('Donate via PayPal!', 'topupplus'); ?></a>  or <a href="http://www.puzich.com/go/wishlist/"><?php _e('make a gift with Amazon', 'lightviewplus') ?></a></center>
					</td>
				</tr>
				</tbody>
				</table>
			
				<form action="options-general.php?page=topup-plus/topup-plus.php" method="post">
					
				<h3><?php _e('General Settings', 'topupplus'); ?></h3>
				<table class="form-table">
				<tbody>
				
				
					<?php // Activate Gallery? ?>
					<?php if( LVPISWP27 == true ) : ?>
					<tr valign="top">
						<th scope="row">
							<label><?php _e('Activate Lightview for [gallery]?', 'topupplus')?></label>
						</th>
						<td>
							<select name="load_gallery" size="1">
							<option value="true" <?php if ($this->options['load_gallery'] == true ) { ?>selected="selected"<?php } ?>><?php _e('yes', 'topupplus'); ?></option>
							<option value="false" <?php if ($this->options['load_gallery'] == false ) { ?>selected="selected"<?php } ?>><?php _e('no', 'topupplus'); ?></option>
							</select>
							
							<br />
							<?php _e('If activated, it shows the wordpress gallery with topup', 'topupplus'); ?>
						</td>
					</tr>
					<?php endif; ?>
				
					<?php // Activate Movies? ?>
					<tr valign="top">
						<th scope="row">
							<label><?php _e('Activate Lightview for Videos?', 'topupplus')?></label>
						</th>
						<td>
							<select name="show_video" size="1">
							<option value="true" <?php if ($this->options['show_video'] == true ) { ?>selected="selected"<?php } ?>><?php _e('yes', 'topupplus'); ?></option>
							<option value="false" <?php if ($this->options['show_video'] == false ) { ?>selected="selected"<?php } ?>><?php _e('no', 'topupplus'); ?></option>
							</select>
							
							<br />
							<?php _e('Implements the video function. ATTENTION: It only works, if you do not have the embedded video plugin activated', 'topupplus'); ?>
						</td>
					</tr>
				</tbody>
				</table>
				
				<h3><?php _e('TopUp Options', 'topupplus'); ?></h3>
				
				<?php // --------- ?>
				
				<table class="form-table">
				<tbody>
					<?php // Image Titel ?>
					<tr valign="top">
						<th scope="row">
							<label><?php _e('Image-Title', 'topupplus')?></label>
						</th>
						<td>
							<input type="text" value="<?php echo $this->options['topup_title'] ?>" name="topup_title" id="topup_title" size="50" maxlength="50" />
							<br />
							<?php _e('Define title over images. Placeholders are {alt}, {current} and {total}.', 'topupplus'); ?>
						</td>
					</tr>
					
					<?php // Read alt-Text? ?>
					<tr valign="top">
						<th scope="row">
							<label><?php _e('Read alt-Text?', 'topupplus')?></label>
						</th>
						<td>
							<select name="topup_readAltText" size="1">
							<option value="1" <?php if ($this->options['topup_readAltText'] == 1 ) { ?>selected="selected"<?php } ?>><?php _e('yes', 'topupplus'); ?></option>
							<option value="0" <?php if ($this->options['topup_readAltText'] == 0 ) { ?>selected="selected"<?php } ?>><?php _e('no', 'topupplus'); ?></option>
							</select>
							
							<br />
							<?php _e('TopUp will use the img alt (alternative) text as title, when present of course', 'topupplus'); ?>
						</td>
					</tr>
					
					<?php // Layout ?>
					<tr valign="top">
						<th scope="row">
							<label><?php _e('Choose layout', 'topupplus'); ?></label>
						</th>
						<td>
							<select name="topup_layout" size="1">
							<option value="dashboard" <?php if ($this->options['topup_layout'] == 'dashboard' ) { ?>selected="selected"<?php } ?>><?php _e('Dashboard', 'topupplus'); ?></option>
							<option value="quicklook" <?php if ($this->options['topup_layout'] == 'quicklook' ) { ?>selected="selected"<?php } ?>><?php _e('Quicklook', 'topupplus'); ?></option>
							<option value="flatlook" <?php if ($this->options['topup_layout'] == 'flatlook' ) { ?>selected="selected"<?php } ?>><?php _e('Flatlook', 'topupplus'); ?></option>
							</select>
							
							<br />
							<?php _e('Layout in which the TopUp will appear.', 'topupplus'); ?>
						</td>
					</tr>
					
					<?php // OverlayClose ?>
					<tr valign="top">
						<th scope="row">
							<label><?php _e('Click everywhere to close', 'topupplus'); ?></label>
						</th>
						<td>
							<select name="topup_overlayClose" size="1">
							<option value="1" <?php if ($this->options['topup_overlayClose'] == 1 ) { ?>selected="selected"<?php } ?>><?php _e('yes', 'topupplus'); ?></option>
							<option value="0" <?php if ($this->options['topup_overlayClose'] == 0 ) { ?>selected="selected"<?php } ?>><?php _e('no', 'topupplus'); ?></option>
							</select>
							
							<br />
							<?php _e('Click everywhere to close TopUp', 'topupplus'); ?>
						</td>
					</tr>
					
					<?php // Shaded background ?>
					<tr valign="top">
						<th scope="row">
							<label><?php _e('Shaded Background', 'topupplus'); ?></label>
						</th>
						<td>
							<select name="topup_shaded" size="1">
							<option value="1" <?php if ($this->options['topup_shaded'] == 1 ) { ?>selected="selected"<?php } ?>><?php _e('yes', 'topupplus'); ?></option>
							<option value="0" <?php if ($this->options['topup_shaded'] == 0 ) { ?>selected="selected"<?php } ?>><?php _e('no', 'topupplus'); ?></option>
							</select>
							
							<br />
							<?php _e('Choose, if you want a shaded background.', 'topupplus'); ?>
						</td>
					</tr>
					
					<?php // Effect TopUp ?>
					<tr valign="top">
						<th scope="row">
							<label><?php _e('Choose show/hide effect', 'topupplus'); ?></label>
						</th>
						<td>
							<select name="topup_effect" size="1">
							<option value="transform" <?php if ($this->options['topup_effect'] == 'transform' ) { ?>selected="selected"<?php } ?>><?php _e('Transform', 'topupplus'); ?></option>
							<option value="appear" <?php if ($this->options['topup_effect'] == 'appear' ) { ?>selected="selected"<?php } ?>><?php _e('Appear', 'topupplus'); ?></option>
							<option value="switch" <?php if ($this->options['topup_effect'] == 'switch' ) { ?>selected="selected"<?php } ?>><?php _e('Switch', 'topupplus'); ?></option>
							<option value="show" <?php if ($this->options['topup_effect'] == 'show' ) { ?>selected="selected"<?php } ?>><?php _e('Show', 'topupplus'); ?></option>
							</select>
							
							<br />
							<?php _e('Effect of how the TopUp will show and hide.', 'topupplus'); ?>
						</td>
					</tr>
					
					<?php // Show link under videos in small? ?>
					<tr valign="top">
						<th scope="row">
							<label><?php _e('TopUp Resizable?', 'topupplus'); ?></label>
						</th>
						<td>
							<select name="topup_resizable" size="1">
							<option value="1" <?php if ($this->options['topup_resizable'] == 1 ) { ?>selected="selected"<?php } ?>><?php _e('yes', 'topupplus'); ?></option>
							<option value="0" <?php if ($this->options['topup_resizable'] == 0 ) { ?>selected="selected"<?php } ?>><?php _e('no', 'topupplus'); ?></option>
							</select>
							
							<br />
							<?php _e('TopUp will be resizable by dragging the right bottom corner of the TopUp window.', 'topupplus'); ?>
						</td>
					</tr>
				</tbody>	
				</table>
					
				<?php // -------------  ?>
				
				<h3><?php _e('Video Options', 'topupplus'); ?></h3>
				
				<table class="form-table">
				<tbody>
					
				<?php // Show link under Videos? ?>
				<tr valign="top">
					<th scope="row">
						<label><?php _e('Show Links under videos?', 'topupplus')?></label>
					</th>
					<td>
						<select name="video_showlink" size="1">
						<option value="true" <?php if ($this->options['video_showlink'] == true ) { ?>selected="selected"<?php } ?>><?php _e('yes', 'topupplus'); ?></option>
						<option value="false" <?php if ($this->options['video_showlink'] == false ) { ?>selected="selected"<?php } ?>><?php _e('no', 'topupplus'); ?></option>
						</select>

						<br />
						<?php _e('Show a link to the original site of the video', 'topupplus'); ?>
					</td>
				</tr>

				<?php // Show link under videos in small? ?>
				<tr valign="top">
					<th scope="row">
						<label><?php _e('Show a small Link under the Video?', 'topupplus'); ?></label>
					</th>
					<td>
						<select name="video_smallink" size="1">
						<option value="true" <?php if ($this->options['video_smallink'] == true ) { ?>selected="selected"<?php } ?>><?php _e('yes', 'topupplus'); ?></option>
						<option value="false" <?php if ($this->options['video_smallink'] == false ) { ?>selected="selected"<?php } ?>><?php _e('no', 'topupplus'); ?></option>
						</select>

						<br />
						<?php _e('If no, it will show a bigger text', 'topupplus'); ?>


					</td>
				</tr>
					
					
					<?php // Linktext ?>
					<tr valign="top">
						<th scope="row">
							<label><?php echo _e('Separator', 'topupplus'); ?></label>
						</th>
						<td>
							<input type="text" value="<?php echo $this->options['video_separator'] ?>" name="video_separator" id="video_separator" size="5" maxlength="3" />
							<br />
							<?php _e('Defines the separator between the service (eg. YouTube) and your comment', 'topupplus'); ?>
						</td>
					</tr>
					
					<?php // Show in Feed? ?>
					<tr valign="top">
						<th scope="row">
							<label><?php echo _e('Show in Feed?', 'topupplus'); ?></label>
						</th>
						<td>
							<select name="video_showinfeed" size="1">
							<option value="true" <?php if ($this->options['video_showinfeed'] == true ) { ?>selected="selected"<?php } ?>><?php _e('yes', 'topupplus'); ?></option>
							<option value="false" <?php if ($this->options['video_showinfeed'] == false ) { ?>selected="selected"<?php } ?>><?php _e('no', 'topupplus'); ?></option>
							</select>
							
							<br />
							<?php _e('You can choose, if you want to show the video in the feed. Currently, it is only possible to show a link to the video', 'topupplus'); ?>
						</td>
					</tr>
					
					<?php // Video Preview Image Width ?>
					<tr valign="top">
						<th scope="row">
							<label><?php _e('Video Preview Width', 'topupplus'); ?>  (250px - 800px)</label>
						</th>
						<td>
							<input type="text" value="<?php echo $this->options['video_preview_width'] ?>" name="video_preview_width" id="video_preview_width" size="5" maxlength="3" />
							<br />
							<?php _e('Choose the width of the preview images for the videos', 'topupplus'); ?>
						</td>
					</tr>
					
					<?php // Video Width ?>
					<tr valign="top">
						<th scope="row">
							<label><?php _e('Video Width', 'topupplus'); ?>  (250px - 800px)</label>
						</th>
						<td>
							<input type="text" value="<?php echo $this->options['video_width'] ?>" name="video_width" id="video_width" size="5" maxlength="3" />
							<br />
							<?php _e('You can choose, what width the video and image have', 'topupplus'); ?>
						</td>
					</tr>
					
					<?php // Video Debug ?>
					<tr valign="top">
						<th scope="row">
							<label><?php echo _e('Show Video Debug Infos', 'topupplus'); ?></label>
						</th>
						<td>
							<select name="video_debug" size="1">
							<option value="true" <?php if ($this->options['video_debug'] == true ) { ?>selected="selected"<?php } ?>><?php _e('yes', 'topupplus'); ?></option>
							<option value="false" <?php if ($this->options['video_debug'] == false ) { ?>selected="selected"<?php } ?>><?php _e('no', 'topupplus'); ?></option>
							</select>
							
							<br />
							<?php _e('Shows video informations, like embed url or image url of the video. Only for debug!', 'topupplus'); ?>
						</td>
					</tr>
					
				</tbody>	
				</table>
				
				<p class="submit">
				<input type="submit" name="Submit" value="<?php _e('Update Options »', 'topupplus'); ?>" />
				</p>
				</form>
				
				<p><small>Video Icon from <a href="http://www.famfamfam.com">famfamfam </a>. Special thanks to Jovelstefan and his plugin <a href="http://wordpress.org/extend/plugins/embedded-video-with-link/">Embedded Video with Link</a>, which inspired me.</small></p>
			
	    </div>
	<?php
	}
	
	function mcebutton($buttons) {
		array_push($buttons, "|", "topupplus");
		return $buttons;
	}

	function mceplugin($ext_plu) {
		if (is_array($ext_plu) == false) {
			$ext_plu = array();
		}
		
		$url = get_option('siteurl')."/wp-content/plugins/topup-plus/editor_plugin.js";
		$result = array_merge($ext_plu, array("topupplus" => $url));
		return $result;
	}

	function mceinit() {
		if (function_exists('load_plugin_textdomain')) load_plugin_textdomain('topupplus', dirname(__FILE__).'/langs');
		if ( 'true' == get_user_option('rich_editing') ) {
 			add_filter('mce_external_plugins', array(&$this, 'mceplugin'), 0);
			add_filter("mce_buttons", array(&$this, 'mcebutton'), 0);
		}
	}

	function add_admin_header() {
		echo "<script type='text/javascript' src='".get_option('siteurl')."/wp-content/plugins/topup-plus/topup-plus.js'></script>\n";
		
		if(LVPIS27) {
			echo '<style type="text/css">
					.inside {
						margin:12px!important;
					}	
					.inside ul {
						margin:6px 0 12px 0;
					}
			
					.inside input {
						padding:1px;
						margin:0;
					}
				</style>';
		}
	}
	
	function direct_image_urls_for_galleries( $link, $id ) {
		if ( is_admin() ) return $link;

		$mimetypes = array( 'image/jpeg', 'image/png', 'image/gif' );

		$post = get_post( $id );

		if ( in_array( $post->post_mime_type, $mimetypes ) )
			return wp_get_attachment_url( $id );
		else
			return $link;
	}

}

//initalize class
if (class_exists('topup_plus'))
	$topup_plus = new topup_plus();
	
/* 
   if function simplexml_load_file is not compiled into php
   use simplexml.class.php
*/
if(!function_exists("simplexml_load_string")) {
	require_once('libs/simplexml.class.php');
	
	function simplexml_load_string($file)
	{
		$sx = new simplexml;
		return $sx->xml_load_string($file);
	}
}
?>