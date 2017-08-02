<?php
/*
Plugin Name: WP Roids
Description: Fast AF caching! Plus minifies your site's HTML, CSS & Javascript
Version: 2.2.0
Author: Phil Meadows
Author URI: http://www.philmeadows.com
Copyright: Copyright (C) Philip K Meadows
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0-standalone.html

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/

// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;

if( ! class_exists( 'WPRoidsPhil' ) )
{
	
	class WPRoidsPhil
	{
		
		private static $instance = NULL;
		private $debug;
		private $className;
		private $pluginName;
		private $textDomain;
		private $cacheDir;
		private $assetsCache;
		private $assetsCacheFolder;
		private $postsCache;
		private $postsCacheFolder;
		private $fileTypes;
		private $earlyAssets;
		private $lateAssets;
		private $uri;
		private $protocol;
		private $domainName;
		private $siteUrl;
		private $rewriteBase;
		private $rootDir;
		private $styleFile;
		private $coreScriptFile;
		private $scriptFile;
		private $timestamp;
		private $jsDeps;
		private $nonceAction;
		private $nonceName;
		
		/**
		* Our constructor
		*/
		public function __construct()
		{
			// set to "TRUE" to view debug info in WP Roids' settings page (and site footer if you uncomment below function)
			// THEN uncomment any lines like `// this->writeLog(...)` to determine what you wish to log
			$this->debug = FALSE;
			
			// ONLY USE IF DESPERATE! Prints data to bottom of PUBLIC pages!
			//if( $this->debug === TRUE ) add_action( 'wp_footer', array( $this, 'wpRoidsDebug'), 100 );
			
			$this->className = get_class();
			$this->pluginName = 'WP Roids';
			$this->textDomain = 'pkmwprds';
			if( ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 )
			{		
				$this->protocol = 'https://';
			}
			else
			{
				$this->protocol = 'http://';
			}
			$this->rootDir = $_SERVER['DOCUMENT_ROOT'] . str_replace( $this->protocol . $_SERVER['HTTP_HOST'], '', site_url() );
			// fix for ridiculous 1&1 directory lookup bug
			if( strpos( $this->rootDir, '/kunden' ) !== FALSE )
			{
				$this->rootDir = str_replace( '/kunden', '', $this->rootDir );
			}
			$this->fileTypes = array( 'css', 'core-js', 'js' );
			$this->earlyAssets = array( 'css', 'core-js' );
			$this->lateAssets = array( 'js' );
			$this->domainName = $_SERVER['HTTP_HOST'];
			$this->rewriteBase = str_replace( $this->protocol . $this->domainName, '', $this->siteUrl );
			if( strpos( $this->domainName, 'www.' ) === 0 )
			{
				$this->domainName = substr( $this->domainName, 4 );
			}
			$this->siteUrl = site_url();
			$this->uri = str_replace( $this->rewriteBase, '', $_SERVER['REQUEST_URI'] );
			$this->cacheDir = __DIR__ . '/wp-roids-cache';
			$this->assetsCache = $this->cacheDir . '/' . 'assets' . $this->rewriteBase;
			$this->assetsCacheFolder = str_replace( $this->rootDir . '/', '', $this->assetsCache );
			$this->postsCache = $this->cacheDir . '/' . 'posts' . $this->rewriteBase;
			$this->postsCacheFolder = str_replace( $this->rootDir . '/', '', $this->postsCache );
			$this->styleFile = $this->textDomain . '-styles.min';
			$this->coreScriptFile = $this->textDomain . '-core.min';
			$this->scriptFile = $this->textDomain . '-scripts.min';
			$this->timestamp = '-' . substr( time(), 0, 8 );
			$this->jsDeps = array();
			$this->nonceAction = 'do_' . $this->textDomain;
			$this->nonceName = $this->textDomain . '_nonce';
			
			// do we have the necessary stuff?
			if( ! $this->checkRequirements() )
			{
				// ensures .htaccess is reset cleanly
				$this->deactivate();
				return FALSE;
			}
			
			// install
			register_activation_hook( __FILE__, array( $this, 'install' ) );
			add_action( 'init', array( $this, 'sentry' ) );
			add_action( $this->textDomain . '_hourly_purge', array( $this, 'flushPostCache' ) );
			remove_action( 'wp_head', 'wp_generator' );
			add_action( 'get_header', array( $this, 'minifyPost' ) );
			add_action( 'wp_head', array( $this, 'cacheThisPost'), 10000 );
			add_action( 'wp_enqueue_scripts', array( $this, 'doAllAssets' ), 9500 );
			add_filter( 'script_loader_src', array( $this, 'removeScriptVersion' ), 15, 1 );
			add_filter( 'style_loader_src', array( $this, 'removeScriptVersion' ), 15, 1 );
			add_action( 'wp', array( $this, 'htaccessFallback'), 10500 );
			
			// add links below plugin description on Plugins Page table
			// see: https://developer.wordpress.org/reference/hooks/plugin_row_meta/
			add_filter( 'plugin_row_meta', array( $this, 'pluginMetaLinks' ), 10, 2 );
			
			// some styles for the admin page
			add_action( 'admin_enqueue_scripts', array( $this, 'loadAdminScripts' ) );
			
			// add a link to the Admin Menu
			add_action( 'admin_menu', array( $this, 'adminMenu' ) );
			
			// add settings link
			// see: https://codex.wordpress.org/Plugin_API/Filter_Reference/plugin_action_links_(plugin_file_name)
			add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'pluginActionLinks' ) );
			
			// add admin bar link
			add_action('admin_bar_menu', array( $this, 'adminBarLinks' ), 1000 );
			
			// individual caching actions
			add_action( 'save_post', array( $this, 'cacheDecider') );
			add_action( 'comment_post', array( $this, 'cacheComment') );
			
			// cache flushing actions
			add_action( 'activated_plugin', array( $this, 'flushWholeCache' ), 10, 2 );
			add_action( 'deactivated_plugin', array( $this, 'flushWholeCache' ), 10, 2 );
			add_action( 'switch_theme', array( $this, 'reinstall' ), 1000 );
			add_action( 'wp_create_nav_menu', array( $this, 'flushPostCache' ) );
			add_action( 'wp_update_nav_menu', array( $this, 'flushPostCache' ) );
			add_action( 'wp_delete_nav_menu', array( $this, 'flushPostCache' ) );
	        add_action( 'create_term', array( $this, 'flushPostCache' ) );
	        add_action( 'edit_terms', array( $this, 'flushPostCache' ) );
	        add_action( 'delete_term', array( $this, 'flushPostCache' ) );
	        add_action( 'add_link', array( $this, 'flushPostCache' ) );
	        add_action( 'edit_link', array( $this, 'flushPostCache' ) );
	        add_action( 'delete_link', array( $this, 'flushPostCache' ) );
	        
	        // deactivate
			register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
			
		} // END __construct()
		
		/**
		* DEV use only!
		* 
		* @return string: Your debug info
		*/
		private function writeLog( $message )
		{
			if( $this->debug === TRUE )
			{
				$fh = fopen( __DIR__ . '/log.txt', 'ab' );
				fwrite( $fh, date('d/m/Y H:i:s') . ': ' . $message . "\n__________\n\n" );
				fclose( $fh );
			}
		}
		public function wpRoidsDebug()
		{
			if( $this->debug === TRUE )
			{
				$output = array('wpRoidsDebug! I\'ll be adding stuff here soon...');
				if( file_exists( __DIR__ . '/log.txt' ) )
				{
					$output['logfile'] = file_get_contents( __DIR__ . '/log.txt' );
				}
				echo '<pre style="padding:12px;overflow-y:scroll;height:350px;background:#fafafa;color:#4d4d4d;font-family:Consolas,Courier,monospace;font-size:12px;"><span style="font-family:Helvetica,Arial,sans-serif;font-size:14px;font-weight:bold;">WP Roids Debug</span><br><br>'. print_r( $output, TRUE ) .'</pre>';				
			}
		}
		/**
		* END DEV use only!
		*/		
		
		/**
		* Check dependencies
		*/
		public function checkRequirements()
		{
			$requirementsMet = TRUE;
			require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
			require_once( ABSPATH . '/wp-includes/pluggable.php' );
			
			// we need cURL active
			if( ! in_array( 'curl', get_loaded_extensions() ) )
			{
				// $this->writeLog( 'cURL NOT available!');
				add_action( 'admin_notices', array( $this, 'messageCurlRequired' ) );
				$requirementsMet = FALSE;
			}
			
			// .htaccess needs to be writable, some security plugins disable this
			// only perform this check when an admin is logged in, or it'll deactivate the plugin :/
			if( current_user_can( 'install_plugins' ) )
			{		
				$htaccess = $this->rootDir . '/.htaccess';
				$current = file_get_contents( $htaccess );
				$starttext = '### BEGIN WP Roids - DO NOT REMOVE THIS LINE';
				$endtext = '### END WP Roids - DO NOT REMOVE THIS LINE' . "\n\n";
				if( strpos( $current, $starttext ) === FALSE && strpos( $current, $endtext ) === FALSE )
				{
					$fh = fopen( $htaccess, 'wb' );
					$isHtaccessWritable = fwrite( $fh, $current );
					fclose( $fh );
					if( $isHtaccessWritable === FALSE )
					{				
						// $this->writeLog( '.htaccess NOT writable!');
						add_action( 'admin_notices', array( $this, 'messageHtNotWritable' ) );
						$requirementsMet = FALSE;
					}
				}
			}
			
			// we do not want conflicting plugins active
			if(
				is_plugin_active( 'wp-super-cache/wp-cache.php' )
				|| is_plugin_active( 'w3-total-cache/w3-total-cache.php' )
				|| is_plugin_active( 'wp-fastest-cache/wpFastestCache.php' )
				|| is_plugin_active( 'comet-cache/comet-cache.php' )
				|| is_plugin_active( 'cache-enabler/cache-enabler.php' )
				|| is_plugin_active( 'simple-cache/simple-cache.php' )
				|| is_plugin_active( 'cachify/cachify.php' )
				|| is_plugin_active( 'wp-speed-of-light/wp-speed-of-light.php' )
				|| is_plugin_active( 'autoptimize/autoptimize.php' )
			)
			{
				// $this->writeLog( 'Conflicting plugin detected!');
				add_action( 'admin_notices', array( $this, 'messageConflictDetected' ) );
				$requirementsMet = FALSE;
			}
			
			// kill plugin activation
			if( $requirementsMet === FALSE ) deactivate_plugins( plugin_basename( __FILE__ ) );
			
			return $requirementsMet;
			
		} // END checkRequirements()
		
		/**
		* Called on plugin activation - sets things up
		* @return void
		*/
		public function install()
		{
			// $this->writeLog( $this->pluginName . ' install() running');
			
			// create cache directories
			if( ! is_dir( $this->cacheDir ) ) mkdir( $this->cacheDir, 0755 );
			
			// .htaccess
			$htaccess = $this->rootDir . '/.htaccess';
			if( file_exists( $htaccess ) )
			{
				$desiredPerms = fileperms( $htaccess );
				chmod( $htaccess, 0644 );
				$current = file_get_contents( $htaccess );
				$starttext = '### BEGIN WP Roids - DO NOT REMOVE THIS LINE';
				$endtext = '### END WP Roids - DO NOT REMOVE THIS LINE' . "\n\n";
				if( strpos( $current, $starttext ) === FALSE && strpos( $current, $endtext ) === FALSE )
				{
					// take a backup
					$backup = __DIR__ . '/ht-backup.txt';
					$fh = fopen( $backup, 'wb' );
					fwrite( $fh, $current );
					fclose( $fh );
					chmod( $backup, 0600 );
					
					// edit .htaccess
					$assetsCacheFolder = str_replace( $this->rootDir . '/', '', $this->assetsCache );
					$fullPostsCacheFolder = str_replace( $_SERVER['DOCUMENT_ROOT'] . '/', '', $this->postsCache );
					$fullPostsCacheFolder = ltrim( str_replace( $this->rootDir, '', $fullPostsCacheFolder ), '/' );
					$postsCacheFolder = str_replace( $this->rootDir . '/', '', $this->postsCache );
					$additional = str_replace( '[[DOMAIN_NAME]]', $this->domainName, file_get_contents( __DIR__ . '/ht-template.txt' ) );
					$additional = str_replace( '[[WP_ROIDS_REWRITE_BASE]]', $this->rewriteBase, $additional );
					$additional = str_replace( '[[WP_ROIDS_ASSETS_CACHE]]', $assetsCacheFolder, $additional );
					$additional = str_replace( '[[WP_ROIDS_FULL_POSTS_CACHE]]', $fullPostsCacheFolder, $additional );
					$additional = str_replace( '[[WP_ROIDS_ALT_FULL_POSTS_CACHE]]', $this->postsCache, $additional );
					$additional = str_replace( '[[WP_ROIDS_POSTS_CACHE]]', $postsCacheFolder, $additional );
					$startpoint = strpos( $current, '# BEGIN WordPress' );
					$new = substr_replace( $current, $additional . "\n\n", $startpoint, 0 );
					$fh = fopen( $htaccess, 'wb' );
					fwrite( $fh, $new );
					fclose( $fh );
					chmod( $htaccess, $desiredPerms );
					// $this->writeLog( '.htaccess rewritten with: "' . $new . '"');
				}
    		
			} // END if htaccess
			
			// set event to flush posts cache every hour
			wp_schedule_event( time(), 'hourly', $this->textDomain . '_hourly_purge' );
			
		} // END install()
		
		/**
		* Ongoing check all is healthy
		* 
		* @return void
		*/
		public function sentry()
		{
			if( current_user_can( 'install_plugins' ) )
			{
				$requirementsMet = TRUE;
				require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
				if( is_plugin_active( plugin_basename( __FILE__ ) )  )
				{
					// check .htaccess is still legit for us
					// $this->writeLog( 'sentry running: ' . $this->pluginName . ' is active!' );	
					$htaccess = $this->rootDir . '/.htaccess';
					$current = file_get_contents( $htaccess );
					
					$myRules = TRUE;
					$starttext = '### BEGIN WP Roids - DO NOT REMOVE THIS LINE';
					$endtext = '### END WP Roids - DO NOT REMOVE THIS LINE' . "\n\n";
					if( strpos( $current, $starttext ) === FALSE && strpos( $current, $endtext ) === FALSE ) $myRules = FALSE;
					$newCookieCheck = 'RewriteCond %{HTTP:Cookie} !^.*(comment_author_|wordpress_logged_in|wp-postpass_|woocommerce).*$';
					if( strpos( $current, $newCookieCheck ) === FALSE ) $myRules = FALSE;
					
					$myOldRules = FALSE;
					$oldstarttext = '# BEGIN WP Roids - DO NOT REMOVE THIS LINE';
					$oldendtext = '# END WP Roids - DO NOT REMOVE THIS LINE' . "\n\n";
					if( strpos( $current, $oldstarttext ) !== FALSE && strpos( $current, $oldendtext ) !== FALSE ) $myOldRules = TRUE;
					
					if( $myRules === FALSE || ( $myRules === FALSE && $myOldRules === TRUE ) )
					{
						$requirementsMet = FALSE;
						// $this->writeLog( 'sentry running: .htaccess is missing rules!' );	
					}
				
					// check cache directories
					if( ! is_dir( $this->cacheDir ) )
					{
						$requirementsMet = FALSE;
						// $this->writeLog( 'sentry running: cache folder not found!' );
					}
					
					if( $requirementsMet === FALSE )
					{
						$this->deactivate();
						$this->install();
					}
					
				} // END we are active
			} // END current user is admin
		} // END sentry()
		
		/**
		* Called on any plugin activation - resets things up and returns to request origin
		* @return void
		*/
		public function reinstall()
		{
			deactivate_plugins( plugin_basename( __FILE__ ) );
			activate_plugins( plugin_basename( __FILE__ ), $this->protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		} // END reinstall()
		
		/**
		* Fired when a page is browsed
		* @return void
		*/
		public function htaccessFallback()
		{
			global $post, $wp_query;
			// $this->writeLog( 'htaccessFallback running... $wp_query = "' . print_r( $wp_query, TRUE ) . '"' );
			$viableBrowse = TRUE;
			if( strpos( $wp_query->request, 'SQL_CALC_FOUND_ROWS' ) !== FALSE ) $viableBrowse = FALSE;
			if( ! $post instanceof WP_Post ) $viableBrowse = FALSE;
			if( ! empty( $_COOKIE ) )
			{
				$negativeCookieStrings = array( 'comment_author_', 'wordpress_logged_in', 'postpass_', 'woocommerce' );
				foreach( $_COOKIE as $cookieKey => $cookieValue )
				{
					foreach( $negativeCookieStrings as $negativeCookieString )
					{
						if( strpos( $cookieKey, $negativeCookieString ) !== FALSE )
						{
							$viableBrowse = FALSE;
							break;
						}
					}
				}
			}
			if( $_POST ) $viableBrowse = FALSE;
			if( $_SERVER['QUERY_STRING'] !== '' ) $viableBrowse = FALSE;
			if( $this->isViablePost( $post ) && $viableBrowse === TRUE )
			{
				// does a cache file exist?
				$thePermalink = get_permalink( $post->ID );
				$isHome = FALSE;
				if( $thePermalink === site_url() . '/' ) $isHome = TRUE;
				
				if( $isHome === FALSE )
				{
					$cacheFilePath = str_replace( site_url(), '', $thePermalink );
					$fullCacheFilePath = $this->postsCache . '/' . rtrim( ltrim( $cacheFilePath, '/' ), '/' );
					$cacheFile = $fullCacheFilePath . '/index.html';	
				}
				else
				{
					$cacheFile = $this->postsCache . '/index.html';
				}
				
				if( file_exists( $cacheFile ) )
				{
					// cache file exists, yet .htaccess did NOT rewrite :/
					// $this->writeLog( 'htaccessFallback() invoked for file: "' . $cacheFile . '"!');
					$fileModified = @filemtime( $cacheFile );
					if( $fileModified !== FALSE )
					{
						$oldestAllowed = ( time() - 300 );
						if( intval( $fileModified ) > intval( $oldestAllowed ) )
						{
							// file is cool, go get it
							$cacheContent = file_get_contents( $cacheFile );
							$cacheContent .= "\n" . '<!-- WP Roids cache file served by PHP script as .htaccess rewrite failed.' . "\n";
							if( $wp_query->is_home() || $wp_query->is_front_page() )
							{
								$cacheContent .= 'BUT! This is your home page, SOME hosts struggle with .htaccess rewrite on the home page only.' . "\n" . 'Check one of your inner Posts/Pages and see what the comment is there... -->';
							}
							else
							{
								$cacheContent .= 'Contact your host for explanation -->';
							}
							
							die( $cacheContent );
						}
						else
						{
							$this->cachePost( $post->ID );
						}
					}
					else
					{
						$this->cachePost( $post->ID );
					}
					
				} // END cache file exists
				
			} // END isViablePost
			
		} // END htaccessFallback()
		
		/**
		* Fired when a page not in the cache is browsed
		* @return void
		*/
		public function cacheThisPost()
		{
			global $post;
			if( $this->isViablePost( $post ) && ! $_POST )
			{
				$start = microtime( TRUE );
				$this->cachePost( $post->ID );
				// $this->writeLog('cacheThisPost on "' . $post->post_title . '" took ' . number_format( microtime( TRUE ) - $start, 5 ) . ' sec' );
			}
			
		} // END cacheThisPost()
		
		/**
		* Is the request a cacheworthy Post/Page?
		* @param bool $assets: Whether this is being called by the asset crunching functionality
		* @param obj $post: Instance of WP_Post object
		* 
		* @return bool
		*/
		private function isViablePost( $post, $assets = FALSE )
		{
			if( is_object( $post ) )
			{
				if( function_exists( 'is_woocommerce' ) )
				{
					$isWoocommerce = FALSE;
					$wcNopes = get_option('_transient_woocommerce_cache_excluded_uris');
					foreach( $wcNopes as $wcNope )
					{
						$postId = (string) $post->ID;
						if( strpos( $wcNope, '"p=' . $postId . '"' ) !== FALSE )
						{
							$isWoocommerce = TRUE;
							break;
						}
					}
				}
				else
				{
					$isWoocommerce = FALSE;
				}
				
				$noCookies = TRUE;
				$negativeCookieStrings = array( 'comment_author_', 'wordpress_logged_in', 'postpass_', 'woocommerce' );
				foreach( $_COOKIE as $cookieKey => $cookieValue )
				{
					foreach( $negativeCookieStrings as $negativeCookieString )
					{
						if( strpos( $cookieKey, $negativeCookieString ) !== FALSE )
						{
							$noCookies = FALSE;
							break;
						}
					}
				}
				
				if( ! is_admin()
					&& ( $assets === TRUE || ( ! $_POST && ! isset( $_POST['X-WP-Roids'] ) ) ) 
					&& $noCookies === TRUE
					&& ! post_password_required() 
					&& ( is_singular() || is_archive() ) 
					&& $isWoocommerce === FALSE 
					&& ! is_404() 
					&& get_post_status( $post->ID ) === 'publish' 
					)
				{
					// $this->writeLog( 'isViablePost running... Post ID: ' . $post->ID . ' "' . $post->post_title . '" was considered viable' );
					return TRUE;
				}
			}
			
			// $this->writeLog( 'isViablePost running... Post ID: ' . $post->ID . ' "' . $post->post_title . '" was NOT considered viable' );
			return FALSE;
			
		} // END isViablePost()
		
		/**
		* Caches a Post/Page
		* @param int $ID: a Post/Page ID
		* 
		* @return bool: TRUE on success, FALSE on fail
		*/
		public function cachePost( $ID )
		{
			$start = microtime( TRUE );
			if( get_post_status( $ID ) === 'publish' )
			{
				// $this->writeLog( 'cachePost running...' );
				$thePermalink = get_permalink( $ID );
				$isHome = FALSE;
				if( $thePermalink === site_url() . '/' ) $isHome = TRUE;
				// $this->writeLog( '$isHome = "' . print_r( $isHome, TRUE ) . '"' . "\n" );
				
				if( $isHome === FALSE )
				{
					$cacheFile = str_replace( site_url(), '', $thePermalink );
					$cacheFilePath = $this->postsCache . '/' . rtrim( ltrim( $cacheFile, '/' ), '/' );
					$newfile = $cacheFilePath . '/index.html';	
				}
				else
				{
					$cacheFilePath = $this->postsCache;
					$newfile = $cacheFilePath . '/index.html';
				}
				
				$data = array( 'X-WP-Roids' => TRUE );
		        $curlOptions = array(
		            CURLOPT_URL => $thePermalink,
					CURLOPT_POST => TRUE,
					CURLOPT_POSTFIELDS => $data,
			        CURLOPT_HEADER => FALSE,
		            CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'],
		            CURLOPT_RETURNTRANSFER => TRUE,
		        );
				$ch = curl_init();
	    		curl_setopt_array( $ch, $curlOptions );
	    		$html = curl_exec( $ch );
	    		curl_close( $ch );
	    		
			    // add a wee note
			    $executionTime = number_format( microtime( TRUE ) - $start, 5 );
			    $htmlComment = "\n" . '<!-- Static HTML cache file generated at ' . gmdate("M d Y H:i:s") . ' GMT by ' . $this->pluginName . ' plugin in ' . $executionTime . ' sec -->';
				if( ! is_dir( $cacheFilePath ) )
				{
					mkdir( $cacheFilePath, 0755, TRUE );
				}
				// write the static HTML file
				$fh = fopen( $newfile, 'wb' );
				fwrite( $fh, $html . $htmlComment );
				fclose( $fh );
				if( file_exists( $newfile ) )
				{
					// $this->writeLog( '"' . $newfile . '" written' );					
					// $this->writeLog('cachePost took ' . $executionTime . ' sec' );
					return TRUE;
				}
				else
				{
					// $this->writeLog( '"' . $newfile . '"  was NOT written, grrrrr' );
					// $this->writeLog('cachePost took ' . $executionTime . ' sec' );
					return FALSE;
				}
				
			}
			
		} // END cachePost()
		
		/**
		* Fired on "save_post" action
		* @param int $ID: the Post ID
		* 
		* @return void
		*/
		public function cacheDecider( $ID )
		{
			$postObj = get_post( $ID );
			if( $postObj instanceof WP_Post )
			{
				switch( $postObj->post_status )
				{
					case 'publish':
						if( $postObj->post_password === '' )
						{
							$this->flushPostCache();
							$this->cachePost( $ID );
						}
						else
						{
							$this->flushPostCache();
						}						
						break;
					case 'inherit':
						$this->flushPostCache();
						$this->cachePost( $postObj->post_parent );
						break;
					case 'private':
					case 'trash':
						$this->flushPostCache();
						break;
					default:
						// there ain't one ;)
						break;
				}
				// $this->writeLog( 'cacheDecider triggered! Got a WP_Post obj. Status was: ' . $postObj->post_status );
			}
		} // END cacheDecider()
		
		/**
		* Deletes cached version of Post/Page
		* @param int $ID: a Post/Page ID
		* 
		* @return void
		*/
		public function deleteCachePost( $ID )
		{
			// $this->writeLog( 'deleteCachePost triggered' );
			$thePermalink = get_permalink( $ID );
			$isHome = FALSE;
			if( $thePermalink === site_url() . '/' ) $isHome = TRUE;
			
			if( $isHome === FALSE )
			{
				// $this->writeLog( 'deleteCachePost - NOT the home page' );
				$cacheFile = str_replace( site_url(), '', str_replace( '__trashed', '', $thePermalink ) );
				$cacheFilePath = $this->postsCache . '/' . rtrim( ltrim( $cacheFile, '/' ), '/' );
				$assetFilePath = $this->assetsCache . '/' . rtrim( ltrim( $cacheFile, '/' ), '/' );
				$killfile = $cacheFilePath . '/index.html';	
			}
			else
			{
				// $this->writeLog( 'deleteCachePost - IS the home page' );
				$cacheFilePath = $this->postsCache;
				$assetFilePath = $this->assetsCache . '/' . str_replace( $this->protocol . $_SERVER['HTTP_HOST'], '', site_url() );
				$killfile = $cacheFilePath . '/index.html';
			}
			// $this->writeLog( 'deleteCachePost - $cacheFilePath = "' . $cacheFilePath . '"' );
			// $this->writeLog( 'deleteCachePost - $killfile = "' . $killfile . '"' );
			
			if( is_dir( $cacheFilePath ) )
			{
				// $this->writeLog( 'deleteCachePost - $cacheFilePath is_dir = TRUE' );
				unlink( $killfile );
				$this->recursiveRemoveEmptyDirectory( $cacheFilePath );
			}
			
			if( is_dir( $assetFilePath ) )
			{
				$scriptFile = $this->scriptFile;
				$filenameArray = glob( $assetFilePath . '/' . $scriptFile . "*" );
				if( count( $filenameArray) === 1 ) unlink( $filenameArray[0] );
				$this->recursiveRemoveEmptyDirectory( $assetFilePath );
			}
			
		} // END deleteCachePost()
		
		/**
		* Checks if new comment is approved and caches Post if so
		* @param int $commentId: The Comment ID
		* @param bool $commentApproved: 1 if approved OR 0 if not
		* 
		* @return void
		*/
		public function cacheComment( $commentId, $commentApproved )
		{
			if( $commentApproved === 1 )
			{
				$theComment = get_comment( $commentId );
				if( is_object( $theComment ) ) $this->cachePost( $theComment->comment_post_ID );
			}
			
		} // END cacheComment()
		
		/**
		* Minifies HTML string
		* @param string $html: Some HTML
		* 
		* @return string $html: Minified HTML
		*/
		private function minifyHTML( $html )
		{
			// see: http://stackoverflow.com/questions/5312349/minifying-final-html-output-using-regular-expressions-with-codeigniter			
			$regex = '~(?>[^\S ]\s*|\s{4,})(?=[^<]*+(?:<(?!/?(?:textarea|pre|script|span|a)\b)[^<]*+)*+(?:<(?>textarea|pre|script|span|a)\b|\z))~Six';
			// minify
			$html = preg_replace( $regex, NULL, $html );
			
		    // remove html comments, but not conditionals
		    $html = preg_replace( "~<!--(?!<!)[^\[>].*?-->~", NULL, $html );
		    
		    if( $html === NULL )
		    {
				// $this->writeLog( 'minifyHTML fail!' );
		    	exit( 'PCRE Error! File too big.');
		    }
			global $post, $wp_query;
			if( $this->isViablePost( $post ) && ! $_POST && strpos( $wp_query->request, 'SQL_CALC_FOUND_ROWS' ) === FALSE )
			{
				$html .= "\n" . '<!-- Minified web page generated at ' . gmdate("M d Y H:i:s") . ' GMT by ' . $this->pluginName . ' plugin' . "\n" . 'This page is NOT a cached static HTML file YET, but it will be on its next request :) -->';
			}
			if( ( ! $this->isViablePost( $post ) && ! $_POST ) || strpos( $wp_query->request, 'SQL_CALC_FOUND_ROWS' ) !== FALSE )
			{
				$html .= "\n" . '<!-- Minified web page generated at ' . gmdate("M d Y H:i:s") . ' GMT by ' . $this->pluginName . ' plugin' . "\n" . 'This page is NOT a cached static HTML file for one of a few possible reasons:' . "\n\t" . '* It is not a WordPress Page/Post' . "\n\t" . '* It is an Archive (list of Posts) Page' . "\n\t" . '* You are logged in to this WordPress site' . "\n\t" . '* It has received HTTP POST data' . "\n\t" . '* It may be a WooCommerce Shop page -->';
			}
		    return $html;
		}
		
		public function minifyPost()
		{
			ob_start( array( $this, 'minifyHTML' ) );
		}
		
		/**
		* Wipes the assets cache
		* @return void
		*/
		public function flushAssetCache()
		{
			if( is_dir( $this->assetsCache ) )
			{
				$this->recursiveRemoveDirectory( $this->assetsCache );
				mkdir( $this->assetsCache, 0755, TRUE );
				// $this->writeLog( 'flushAssetCache executed' );
			}
		} // END flushAssetCache()
		
		/**
		* Wipes the posts cache
		* @return void
		*/
		public function flushPostCache()
		{
			if( is_dir( $this->postsCache ) )
			{
				$this->recursiveRemoveDirectory( $this->postsCache );
				mkdir( $this->postsCache, 0755, TRUE );
				// $this->writeLog( 'flushPostCache executed' );
			}
		} // END flushPostCache()
		
		/**
		* Wipes the posts cache
		* @return void
		*/
		public function flushWholeCache()
		{
			if( is_dir( $this->cacheDir ) )
			{
				$this->recursiveRemoveDirectory( $this->cacheDir );
				mkdir( $this->cacheDir, 0755 );
				// $this->writeLog( 'flushWholeCache run' );
			}
		} // END flushPostCache()
		
		public function doAllAssets()
		{
			$this->doAssets( $this->earlyAssets );
			$this->doAssets( $this->lateAssets );
			// $this->writeLog( 'doAllAssets run' );
		}
		
		/**
		* Control function for minifying assets
		* 
		* @return void
		*/
		private function doAssets( array $fileTypes )
		{
			global $post;
			if( $this->isViablePost( $post, TRUE ) )
			{
				$flushPostsCache = FALSE;
				foreach( $fileTypes as $fileType )
				{
					$files = $this->getAssets( $fileType );
					if( $this->refreshRequired( $files, $fileType ) === TRUE )
					{
						// $this->writeLog( 'refreshRequired TRUE on file type "'. $fileType .'"' );
						if( $fileType === 'js' )
						{
							if( isset( $_POST['X-WP-Roids'] ) && $_POST['X-WP-Roids'] == TRUE )
							{
								$this->deleteCachePost( $post->ID );
								// $this->writeLog( 'Post ID "' . $post->ID . '" flushed' );
							}
						}
						else
						{
							if( isset( $_POST['X-WP-Roids'] ) && $_POST['X-WP-Roids'] == TRUE )
							{
								$this->flushPostCache();
								// $this->writeLog( 'Post cache flushed' );
							}
						}
						
						$this->refresh( $files, $fileType );
					}
					else
					{
						// $this->writeLog( 'refreshRequired FALSE on file type "'. $fileType .'"' );
					}
					
					$this->requeueAssets( $files, $fileType );
					
				} // END foreach $fileType
				
			} // END if not admin		
			
		} // END doAssets()
		
		/**
		* 
		* @param string $type: Either 'css' or 'js'
		* 
		* @return array $filenames: List of CSS or JS assets. Format: $handle => $src
		*/
		private function getAssets( $type )
		{
			$output = array();
			$siteUrl = str_replace( ['https:','http:'], '', site_url() );
			$path = rtrim( ABSPATH, '/' );
			switch( $type )
			{
				case 'css':
					global $wp_styles;
					$wpAssets = $wp_styles;
					break;
				case 'core-js':
				case 'js':
					global $wp_scripts;
					$wpAssets = $wp_scripts;
					$deps = array();
					break;
			}
			
			foreach( $wpAssets->registered as $wpAsset )
			{
			// nope: core files (apart from 'jquery-core' & 'jquery-migrate'), remote files, unqueued files & files w/o src
				if( ( ( $type === 'css' 
					|| ( $type === 'js' 
						/*&& strpos( $wpAsset->src, '/plugins/woocommerce/') === FALSE
						&& strpos( $wpAsset->src, '/s2member/') === FALSE
						&& strpos( $wpAsset->handle, 'wordfence' ) === FALSE*/
						)
					)
					&& ( 
						strpos( $wpAsset->src, 'wp-admin' ) === FALSE
						&& strpos( $wpAsset->src, 'wp-includes' ) === FALSE
						&& ( strpos( $wpAsset->src, $this->domainName ) !== FALSE || strpos( $wpAsset->src, '/wp' ) === 0 )
						&& strpos( $wpAsset->handle, $this->textDomain ) === FALSE
						&& ( in_array( $wpAsset->handle, $wpAssets->queue ) 
							|| ( isset( $wpAssets->in_footer ) && in_array( $wpAsset->handle, $wpAssets->in_footer ) )
							)
						&& ! empty( $wpAsset->src )
						&& ! is_bool( $wpAsset->src )
						)
					)
					||
					( $type === 'core-js' 
						&& ( $wpAsset->handle === 'jquery-core' || $wpAsset->handle === 'jquery-migrate' )
						&& strpos( $wpAsset->handle, $this->textDomain ) === FALSE
						)
				)
				{
					// prepend the relational files
					if( ( strpos( $wpAsset->handle, 'jquery' ) === 0 && strpos( $wpAsset->src, $this->domainName ) === FALSE ) || strpos( $wpAsset->src, '/wp' ) === 0 )
					{
						$wpAsset->src = $siteUrl . $wpAsset->src;
					}
					
					// we need the file path for checking file update timestamps later on in refreshRequired()
					$filePath = str_replace( [site_url(), $siteUrl], $path, $wpAsset->src );
					
					// now rebuild the url from filepath
					$src = str_replace( $path, site_url(), $filePath );
					
					// add file to minification array list
					$output[$wpAsset->handle] = array( 'src' => $src, 'filepath' => $filePath, 'deps' => $wpAsset->deps, 'args' => $wpAsset->args, 'extra' => $wpAsset->extra );
					
					// if javascript we need all the dependencies for later in enqueueAssets()
					if( $type === 'js' )
					{
						foreach( $wpAsset->deps as $dep )
						{
							if( ! in_array( $dep, $deps ) ) $deps[] = $dep;
						}						
					}
					
					// $this->writeLog('type "' . $type . '" getAssets file: "'.$wpAsset->handle.'" was considered okay to cache/minify');
				} // END if considered ok to minify/cache
				
			} // END foreach registered asset
			
			if( $type === 'js' )
			{
				// set the class property that stores javascript dependencies
				$this->jsDeps = $deps;
				// $this->writeLog( 'getAssets $this->jsDeps = ' . print_r( $this->jsDeps, TRUE ) );
			}
			// $this->writeLog( 'getAssets $output = ' . print_r( $output, TRUE ) );
			return $output;
			
		} // END getAssets()
		
		/**
		* 
		* @param array $filenames: List of CSS or JS assets. Format: $handle => $file
		* @param array $file: Format: $src (http://) => $filepath (directory handle)
		* 
		* @return bool $refresh: Whether we need to recompile our asset file for this type
		*/
		private function refreshRequired( $filenames, $type )
		{
			$refresh = FALSE;
			if( ! is_dir( $this->assetsCache ) ) return TRUE;
			clearstatcache(); // ensures filemtime() is up to date		
			switch( $type )
			{
				case 'css':
					$filenameArray = glob( $this->assetsCache . '/' .  $this->styleFile . "*" );
					break;
				case 'core-js':
					$filenameArray = glob( $this->assetsCache . '/' . $this->coreScriptFile . "*" );
					break;
				case 'js':
					$filenameArray = glob( $this->assetsCache . $this->uri . $this->scriptFile . "*" );
					break;
			}
			
			// $this->writeLog( 'refreshRequired $filenameArray = "' . print_r( $filenameArray, TRUE ) . '"' );
			
			// there is no plugin generated file, so we must refresh/generate
			if( empty( $filenameArray ) || count( $filenameArray ) !== 1 )
			{
				$refresh = TRUE;
			}			
			// if the plugin generated file exists, we need to check if any inside the $filenames minification array are newer
			else
			{
				$outputFile = $filenameArray[0];
				$editTimes = array();
				$outputFileArray = array( 'filepath' => $outputFile );
				array_push( $filenames, $outputFileArray );
				foreach( $filenames as $file )
				{
					$modified = @filemtime( $file['filepath'] );
					if( $modified === FALSE )
					{
						// $this->writeLog( 'refreshRequired filemtime FALSE on file ' . $file['filepath'] );
						$modified = time();
					}
					$editTimes[$modified] = $file;
				}
				krsort( $editTimes );
				// $this->writeLog( 'refreshRequired $editTimes array = "' . print_r( $editTimes, TRUE ) . '"' );
				$latest = array_shift( $editTimes );
				if( $latest['filepath'] !== $outputFileArray['filepath'] )
				{
					$refresh = TRUE;
					@unlink( $outputFile );
				}
			}
			return $refresh;
			
		} // END refreshRequired()
		
		/**
		* 
		* @param array $filenames: List of CSS or JS assets. Format: $handle => $file
		* @param array $file: Format: $src (http://) => $filepath (directory handle)
		* @param string $type: Either 'css' or 'js'
		* 
		* @return bool on success
		*/
		private function refresh( $filenames, $type )
		{
			if( ! is_dir( $this->assetsCache ) )
			{
				mkdir( $this->assetsCache, 0755, TRUE );
			}	
			$output = "<?php\n";
			switch( $type )
			{
				case 'css':
					$output .= "header( 'Content-Type: text/css' );\n";
					$outputFile = $this->assetsCache . '/' . $this->styleFile . $this->timestamp;
					break;
				case 'core-js':
					$output .= "header( 'Content-Type: text/javascript' );\n";
					$outputFile = $this->assetsCache . '/' . $this->coreScriptFile . $this->timestamp;
					break;
				case 'js':
					$output .= "header( 'Content-Type: text/javascript' );\n";
					$outputFile = $this->assetsCache . $this->uri . $this->scriptFile . $this->timestamp;
					if( ! is_dir( $this->assetsCache . $this->uri ) )
					{
						mkdir( $this->assetsCache . $this->uri, 0755, TRUE );
					}
					break;
			} // END switch type	
			$theCode = '';
			foreach( $filenames as $handle => $file )
			{
				$fileDirectory = dirname( $file['filepath'] );
				$fileDirectory = realpath( $fileDirectory );	
	        	$contentDir = $this->rootDir;
	        	$contentUrl = $this->siteUrl;
				// cURL b/c if CSS dynamically generated w. PHP, file_get_contents( $file['filepath'] ) will return code, not CSS
				// AND using file_get_contents( $file['src'] ) will return 403 unauthourised
		        $curlOptions = array(
		            CURLOPT_URL => $file['src'],
		            CURLOPT_HEADER => FALSE,
		            CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'],
		            CURLOPT_RETURNTRANSFER => TRUE,
		        );
				$ch = curl_init();
        		curl_setopt_array( $ch, $curlOptions );
        		$code = curl_exec( $ch );
        		curl_close( $ch );
        		
        		// is there code? do stuff
        		if( strlen( $code ) !== 0 )
        		{
        			// if conditional e.g. IE CSS get rid of it, let WP do it's thing
        			if( $type === 'css' && ! empty( $file['extra']['conditional'] ) )
        			{
						unset( $filenames[$handle] );
						break;
					}			
					
					// if inline CSS stuff included, add to code
					if( $type === 'css' && ! empty( $file['extra']['after'] ) )
					{
						$code .= "\n" . $file['extra']['after'][0];
					}
					     			 		
	        		// CSS with relative background-image(s) but NOT "data:" / fonts set etc., convert them to absolute
	        		if( $type === 'css' && strpos( $code, 'url' ) !== FALSE )
	        		{
					    $code = preg_replace_callback(
					        '~url\(\s*(?![\'"]?data:)\/?(.+?)[\'"]?\s*\)~i',
					        function( $matches ) use ( $fileDirectory, $contentDir, $contentUrl )
					        {
					        	$filePath = $fileDirectory . '/' . str_replace( ['"', "'"], '', ltrim( rtrim( $matches[0], ');' ), 'url(' ) );
					        	return "url('" . esc_url( str_replace( $contentDir, $contentUrl, $filePath ) ) . "')";
					        },
					        $code
					    );
					} // END relative -> absolute
					
					// if a CSS media query file, wrap in width params
					if( $type === 'css' && strpos( $file['args'], 'width' ) !== FALSE )
					{
						$code = '@media ' . $file['args'] . ' { ' . $code . ' } ';
					}
					
					// fix URLs with // prefix so not treated as comments
					$code = str_replace( ['href="//','src="//','movie="//'], ['href="http://','src="http://','movie="http://'], $code );
					// braces & brackets
					$bracesBracketsLookup = [' {', ' }', '{ ', '; ', "( '", "' )", ' = ', '{ $', '{ var'];
					$bracesBracketsReplace = ['{', '}', '{', ';', "('", "')", '=', '{$', '{var'];
					
					if( $type === 'css' )
					{
						// regex adapted from: http://stackoverflow.com/q/9329552 
						$comments = '~\/\*[^*]*\*+([^/*][^*]*\*+)*\/~';
						$replace = NULL;
					}
					
					if( $type === 'js' )
					{
						// regex adapted from: http://stackoverflow.com/a/31907095
						// added rule for only two "//" to avoid stripping base64 lines
						// added rule for optional whitespace after "//" as some peeps do not space
						$comments = '~(?:(?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:(?<!\:|\\\|\'|\"|\/)\/\/(?!\/+)\s?.*))~';
						$replace = NULL;
					}
					
					// strip comments
					if( $type === 'css' || $type === 'js' ) $code = preg_replace( $comments, $replace, $code );
					
					// strip spaces in braces
					$code = str_replace( $bracesBracketsLookup, $bracesBracketsReplace, $code );
					
					// minify if not already - crude lookup if "min" in filename :/
					if( strpos( $file['filepath'], 'min' ) === FALSE )
					{							
						// strip excessive newlines
						$code = preg_replace( '/\r/', "\n", $code );
						$code = preg_replace( '/\n+/', "\n", $code );
						
						// strip whitespace
						$code = preg_replace( '/\s+/', ' ', $code );
					
					} // END if not already "min"
					
					$code = ltrim( $code, "\n" ) . "\n";
					
					$theCode .= $code;
					
					unset( $filenames[$handle] );
					
				} // END if code
				
			} // END foreach $filenames
					
			if( $type === 'css' && strpos( $theCode, '@charset "UTF-8";' ) !== FALSE )
			{
				$theCode = '@charset "UTF-8";' . "\n" . str_replace( '@charset "UTF-8";', '', $theCode );
			}
			
			$output .= "header( 'Last-Modified: ".gmdate( 'D, d M Y H:i:s' )." GMT' );\nheader( 'Expires: ".gmdate( 'D, d M Y H:i:s', strtotime( '+1 year' ) )." GMT' );\n?>\n";
			
			$outputFile .= '.php';
			$fh = fopen( $outputFile, 'wb' );
			fwrite( $fh, $output . $theCode );
			fclose( $fh );
			// $this->writeLog( 'Asset "' . $outputFile . '" written' );
			return $filenames;
			
		} // END refresh()
		
		/**
		* Dequeues all the assets we are replacing
		* @param array $filenames: List of CSS or JS assets. Format: $handle => $file
		* @param array $file: Format: $src (http://) => $filepath (directory handle)
		* @param string $type: Either 'css' or 'js'
		* 
		* @return bool on success
		*/
		private function requeueAssets( $filenames, $type )
		{
			switch( $type )
			{
				case 'css':
					foreach( $filenames as $handle => $file )
					{
						wp_dequeue_style( $handle );
						if( strpos( $handle, $this->textDomain ) === FALSE )
						{
							wp_deregister_style( $handle );
							// $this->writeLog( 'CSS deregistered = "' . $handle . '"' );
						}
					}
					$styles = glob( $this->assetsCache . '/' . $this->styleFile . "*" );
					$styles = ltrim( str_replace( $this->rootDir, '', $styles[0] ), '/' );
					$styles = str_replace( '.php', '.css', $styles );
					wp_enqueue_style( $this->textDomain . '-styles', esc_url( site_url( $styles ) ), array(), NULL );
					// $this->writeLog( 'CSS enqueued = "' . site_url( $styles ) . '"' );
					break;
				case 'core-js':
					foreach( $filenames as $handle => $file )
					{
						wp_deregister_script( $handle );
						// $this->writeLog( 'Old core JS dequeued = "' . $handle . '"' );
					}
					$coreScripts = glob( $this->assetsCache . '/' . $this->coreScriptFile . "*" );
					$coreScripts = ltrim( str_replace( $this->rootDir, '', $coreScripts[0] ), '/' );
					$coreScripts = str_replace( '.php', '.js', $coreScripts );
					wp_enqueue_script( $this->textDomain . '-core', esc_url( site_url( $coreScripts ) ), array(), NULL );
					wp_deregister_script( 'jquery' );
					wp_deregister_script( 'jquery-migrate' );
					wp_register_script( 'jquery', '', array( $this->textDomain . '-core' ), NULL, TRUE );
					// $this->writeLog( 'New core JS enqueued' );
					break;
				case 'js':
					$inlineJs = '';
					foreach( $filenames as $handle => $file )
					{
						// check for inline data
						if( ! empty( $file['extra']['data'] ) )
						{
							$inlineJs .= $file['extra']['data'] . "\n";
						}
						
						if( strpos( $handle, $this->textDomain ) === FALSE )
						{
							wp_dequeue_script( $handle );
							// $this->writeLog( 'JS script dequeued = "' . $handle . '"' );
						}
					}
					$scripts = glob( $this->assetsCache . $this->uri . $this->scriptFile . "*" );
					$scripts = ltrim( str_replace( $this->rootDir, '', $scripts[0] ), '/' );
					$scripts = str_replace( '.php', '.js', $scripts );
					wp_enqueue_script( $this->textDomain . '-scripts', esc_url( site_url( $scripts ) ), $this->jsDeps, NULL, TRUE );
					if( $inlineJs !== '' )
					{
						wp_add_inline_script( $this->textDomain . '-scripts', $inlineJs, 'before' );
					}
					// $this->writeLog( 'JS script enqueued = "' . $this->textDomain . '-scripts" with deps = "' . print_r( $this->jsDeps, TRUE ) . '"' );
					break;
					
			} // END switch type
			
			return TRUE;
			
		} // END requeueAssets()
		
		/**
		* Removes query strings from asset URLs
		* @param string $src: the src of an asset file
		* 
		* @return string: the src with version query var removed
		*/
		public function removeScriptVersion( $src )
		{
			$parts = explode( '?ver', $src );
			return $parts[0];
			
		} // END removeScriptVersion()
		
		/**
		* Deletes a directory and its contents
		* @param string $directory: directory to empty
		* 
		* @return void
		*/
		private function recursiveRemoveDirectory( $directory )
		{
		    if( ! is_dir( $directory ) )
		    {
		        throw new InvalidArgumentException( "$directory must be a directory" );
		    }
		    
		    if( substr( $directory, strlen( $directory ) - 1, 1 ) != '/' )
		    {
		        $directory .= '/';
		    }
		    
		    $files = glob( $directory . "*" );
		    
		    if( ! empty( $files ) )
		    {
			    foreach( $files as $file )
			    {
			        if( is_dir( $file ) )
			        {
			            $this->recursiveRemoveDirectory( $file );
			        }
			        else
			        {
			            unlink( $file );
			        }
			    }				
			}
		    rmdir( $directory );
		    
		} // END recursiveRemoveDirectory()
		
		/**
		* Deletes a directory and its contents
		* @param string $directory: directory to empty
		* 
		* @return void
		*/
		private function recursiveRemoveEmptyDirectory( $directory )
		{
		    if( ! is_dir( $directory ) )
		    {
		        throw new InvalidArgumentException( "$directory must be a directory" );
		    }
		    
		    if( substr( $directory, strlen( $directory ) - 1, 1 ) != '/' )
		    {
		        $directory .= '/';
		    }
		    
		    $files = glob( $directory . "*" );
		    
		    if( ! empty( $files ) )
		    {
			    foreach( $files as $file )
			    {
			        if( is_dir( $file ) )
			        {
			            $this->recursiveRemoveEmptyDirectory( $file );
			        }
			    }				
			}
			else
			{
				rmdir( $directory );
			}		    
		    
		} // END recursiveRemoveEmptyDirectory()
		
		/**
		* Display a message
		*/
		private function notice( $message, $type = 'error' )
		{
			switch( $type )
			{
				case 'error':
					$glyph = 'thumbs-down';
					$color = '#dc3232';
					break;
				case 'updated':
					$glyph = 'thumbs-up';
					$color = '#46b450';
					break;
				case 'warning':
					$glyph = 'megaphone';
					$color = '#ff7300';
					break;
			}
			$output = '<div id="message" class="notice is-dismissible '.$type.'"><p><span style="color: '.$color.';" class="dashicons dashicons-'.$glyph.'"></span>&nbsp;&nbsp;&nbsp;<strong>';
			$output .= __( $message , $this->textDomain );
			$output .= '</strong></p></div>';
			return $output;
        } // END notice()
        
        public function messageCurlRequired()
        {
        	$message = 'Sorry, '.$this->pluginName.' requires the cURL PHP extension installed on your server. Please resolve this';
			echo $this->notice( $message );
		} // END messageCurlRequired()
        
        public function messageHtNotWritable()
        {
        	$message = 'Sorry, ' . $this->pluginName . ' requires ".htaccess" to be writable to activate/deactivate. Some security plugins disable this. Please allow the file to be writable, for a moment. You can re-apply your security settings after activating/deactivating ' . $this->pluginName;
			echo $this->notice( $message );
		} // END messageHtNotWritable()
        
        public function messageConflictDetected()
        {
        	$message = 'Sorry, ' . $this->pluginName . ' requires no other caching/minification plugins be active. Please deactivate any existing plugin(s) of this nature';
			echo $this->notice( $message );
		} // END messageConflictDetected()
        
        public function messageCacheFlushed()
        {
        	$message = 'Groovy! ' . $this->pluginName . ' cache has been flushed';
			echo $this->notice( $message, 'updated' );
		} // END messageCacheFlushed()
		
		/**
		* Add links below plugin description
		* @param array $links: The array having default links for the plugin
		* @param string $file: The name of the plugin file
		* 
		* @return array $links: The new links array
		*/
		public function pluginMetaLinks( $links, $file )
		{
			if ( $file == plugin_basename( dirname( __FILE__ ) . '/wp-roids.php' ) )
			{
				$links[] = '<a href="http://philmeadows.com/say-thank-you/" target="_blank" title="Opens in new window">' . __( 'Say "Thank You"', $this->textDomain ) . '</a>';
			}
			return $links;
			
		} // END pluginMetaLinks()
		
		/**
		* Add links when viewing "Plugins"
		* @param array $links: The links that appear by "Deactivate" under the plugin name
		* 
		* @return array $links: Our new set of links
		*/
		public function pluginActionLinks( $links )
		{
			$mylinks = array(
				'<a href="' . esc_url( admin_url( 'edit.php?page=' . $this->textDomain ) ) . '">Settings</a>',
				$this->flushCacheLink(),
				);
			return array_merge( $links, $mylinks );
			
		} // END pluginActionLinks()
		
		/**
		* Generates a clickable "Flush Cache" link
		* 
		* @return string HTML
		*/
		private function flushCacheLink( $linkText = 'Flush Cache' )
		{
			$url = admin_url( 'admin.php?page=' . $this->textDomain );
			$link = wp_nonce_url( $url, $this->nonceAction, $this->nonceName );
			return sprintf( '<a class="flush-link" href="%1$s">%2$s</a>', esc_url( $link ), $linkText );
		}
		
		public function adminBarLinks( $adminBar )
		{
			if( current_user_can( 'install_plugins' ) )
			{
				$url = admin_url( 'admin.php?page=' . $this->textDomain );
				$link = wp_nonce_url( $url, $this->nonceAction, $this->nonceName );
				$adminBar->add_menu(
					[ 'id' => $this->textDomain . '-flush',
					'title' => 'Flush ' . $this->pluginName . ' Cache',
					'href'  => esc_url( $link ),
					] );
			}
		}
		
		/**
		* Called on plugin deactivation - cleans everything up as if we were never here :)
		* @return void
		*/
		public function deactivate()
		{			
			// .htaccess needs to be writable, some security plugins disable this
			// only perform this check when an admin is logged in, or it'll deactivate the plugin :/
			if( current_user_can( 'install_plugins' ) )
			{		
				$htaccess = $this->rootDir . '/.htaccess';
				$current = file_get_contents( $htaccess );
				$starttext = '### BEGIN WP Roids - DO NOT REMOVE THIS LINE';
				$endtext = '### END WP Roids - DO NOT REMOVE THIS LINE' . "\n\n";
				if( strpos( $current, $starttext ) !== FALSE && strpos( $current, $endtext ) !== FALSE )
				{
					// .htaccess needs editing
					$desiredPerms = fileperms( $htaccess );
					chmod( $htaccess, 0644 );
					$fh = fopen( $htaccess, 'wb' );
					$isHtaccessWritable = fwrite( $fh, $current );
					fclose( $fh );
					if( $isHtaccessWritable === FALSE )
					{				
						// $this->writeLog( '.htaccess NOT writable!');
						add_action( 'admin_notices', array( $this, 'messageHtNotWritable' ) );
						return FALSE;
					}
					else
					{
						// restore the .htaccess file
						$pos = strpos( $current, $starttext );
						$startpoint = $pos === FALSE ? NULL : $pos;
						$pos = strrpos( $current, $endtext, $startpoint );
						$endpoint = $pos === FALSE ? NULL : $pos + strlen( $endtext );
						if( $startpoint !== NULL && $endpoint !== NULL )
						{
							$restore = substr_replace( $current, '', $startpoint, $endpoint - $startpoint);
							$fh = fopen( $htaccess, 'wb' );
							fwrite( $fh, $restore );
							fclose( $fh );
							chmod( $htaccess, $desiredPerms );
						}
					}
				} // END .htaccess needs editing
				
				// remove 1.* versions' code
				$current = file_get_contents( $htaccess );
				$starttext = '# BEGIN WP Roids - DO NOT REMOVE THIS LINE';
				$endtext = '# END WP Roids - DO NOT REMOVE THIS LINE' . "\n\n";
				if( strpos( $current, $starttext ) !== FALSE && strpos( $current, $endtext ) !== FALSE )
				{
					// .htaccess needs editing
					$desiredPerms = fileperms( $htaccess );
					chmod( $htaccess, 0644 );
					$fh = fopen( $htaccess, 'wb' );
					$isHtaccessWritable = fwrite( $fh, $current );
					fclose( $fh );
					if( $isHtaccessWritable === FALSE )
					{				
						// $this->writeLog( '.htaccess NOT writable!');
						add_action( 'admin_notices', array( $this, 'messageHtNotWritable' ) );
						return FALSE;
					}
					else
					{
						// restore the .htaccess file
						$pos = strpos( $current, $starttext );
						$startpoint = $pos === FALSE ? NULL : $pos;
						$pos = strrpos( $current, $endtext, $startpoint );
						$endpoint = $pos === FALSE ? NULL : $pos + strlen( $endtext );
						if( $startpoint !== NULL && $endpoint !== NULL )
						{
							$restore = substr_replace( $current, '', $startpoint, $endpoint - $startpoint);
							$fh = fopen( $htaccess, 'wb' );
							fwrite( $fh, $restore );
							fclose( $fh );
							chmod( $htaccess, $desiredPerms );
						}
					}
				} // END .htaccess needs editing
					
				$backup = __DIR__ . '/ht-backup.txt';
				if( file_exists( $backup ) )
				{
					unlink( $backup );
				}
				
				$log = __DIR__ . '/log.txt';
				if( file_exists( $log ) )
				{
					unlink( $log );
				}
				
				// remove cache
				if( is_dir( $this->cacheDir ) ) $this->recursiveRemoveDirectory( $this->cacheDir );
				
				// kill the schedule
				wp_clear_scheduled_hook( $this->textDomain . '_hourly_purge' );
			
			} // END if user can activate plugins	
			
		} // END deactivate()
		
		/**
		* Called on uninstall - actually does nothing at present
		* @return void
		*/
		public static function uninstall()
		{
			$theClass = self::instance();
			$theClass->deactivate();			
			
		} // END uninstall()
		
		/**
		* Create or return instance of this class
		*/
		public static function instance()
		{
			$className = get_class();
			if( ! isset( self::$instance ) && ! ( self::$instance instanceof $className ) && ( self::$instance === NULL ) )
			{
				self::$instance = new $className;
			}
			return self::$instance;
			
		} // END instance()
		
		/**
		* load admin scripts
		*/
		public function loadAdminScripts()
		{
			wp_enqueue_style( $this->textDomain.'-admin-webfonts', 'https://fonts.googleapis.com/css?family=Roboto:400,700|Roboto+Condensed', array(), NULL );
			wp_enqueue_style( $this->textDomain.'-admin-styles', plugins_url( 'css-admin.css' , __FILE__ ), array(), NULL );
			
		} // END loadAdminScripts()
		
		/**
		* add admin menu
		*/
		public function adminMenu()
		{
			// see https://developer.wordpress.org/reference/functions/add_menu_page
			add_menu_page( $this->pluginName, $this->pluginName, 'install_plugins', $this->textDomain, array( $this, 'adminPage' ), 'dashicons-dashboard', '80.01' );
			
		} // END adminMenu()
		
		/**
		* our admin page
		*/
		public function adminPage()
		{
			if( isset( $_GET[$this->nonceName] ) && wp_verify_nonce( $_GET[$this->nonceName], $this->nonceAction ) )
			{
				$this->flushWholeCache();
				$this->messageCacheFlushed();
			}		
			?>
			<div class="wrap">
				<h1><span class="dashicons dashicons-dashboard"></span>&nbsp;<?php echo $this->pluginName ;?></h1>
				<p>					
					<?php
					if( ! isset( $_GET[$this->nonceName] ) )
					{
						echo $this->flushCacheLink( 'Empty the cache!' );
					}					
					?>
				</p>
				<?php
				if( $this->debug === TRUE ) $this->wpRoidsDebug();
				?>
				<h4 class="left">Fast AF Minification and Caching for WordPress<sup>&reg;</sup></h4>
				<p class="right like">&hearts; <small>Like this plugin?&nbsp;&nbsp;&nbsp;</small><a href="http://philmeadows.com/say-thank-you" target="_blank" title="Opens in new tab/window">Say &quot;Thanks&quot;</a>&nbsp;&nbsp;<a href="https://wordpress.org/support/plugin/wp-roids/reviews/" target="_blank" title="Opens in new tab/window">&#9733;&#9733;&#9733;&#9733;&#9733; Review</a></p>
				<div class="clear"></div>
				<div class="pkm-panel pkm-panel-default">
					<h2>Settings? There Are None&hellip;</h2>
					<p><big>&hellip;for now. WP Roids <em>should</em> work out of the box, the intention being to, &quot;Keep It Simple, Stupid&quot; <abbr>(KISS)</abbr></big> <sup>[<a href="https://en.wikipedia.org/wiki/KISS_principle" target="_blank" title="&quot;Keep It Simple, Stupid&quot; | Opens in new tab/window">?</a>]</sup></p>
					<h3>To Check WP Roids Is Working&hellip;</h3>
					<ul>
						<li>View the source code <sup>[<a href="http://www.computerhope.com/issues/ch000746.htm" target="_blank" title="How to view your website source code | Opens in new tab/window">?</a>]</sup> of a Page/Post <strong>when you are logged out of WordPress<sup>&reg;</sup> and have refreshed the Page/Post TWICE</strong></li>
						<li>At the very bottom, <strong>you should see an HTML comment</strong> like this: <code>&lt;!-- Static HTML cache file generated at <?php echo date( 'M d Y H:i:s T' ); ?> by WP Roids plugin --&gt;</code></li>
					</ul>
				</div>
				<div class="pkm-panel pkm-panel-primary">
					<h2>Polite Request</h2>
					<p><big>I've made WP Roids available <strong>completely FREE of charge</strong>. No extra costs for upgrades, support etc. <strong>It's ALL FREE!</strong></big><br>It takes me a LOT of time to code, test, re-code etc. Time which I am not paid for. To that end, I kindly ask the following from you guys:</p>
					<ul>
						<li>
							<h3>Non-Profit / Non-Commercial Users</h3>
							<p><big>Please consider <a href="https://wordpress.org/support/plugin/wp-roids/reviews/" target="_blank" title="Opens in new tab/window">giving WP Roids a 5 Star &#9733;&#9733;&#9733;&#9733;&#9733; Review</a> to boost its popularity</big></p>
						</li>
						<li>
							<h3>Business / Commercial Website Owners</h3>
							<p><big>As above, but a small cash donation via my <a href="http://philmeadows.com/say-thank-you" target="_blank" title="Opens in new tab/window">&quot;Thank You&quot; Page</a> would also be gratefully appreciated</big></p>
						</li>
						<li>
							<h3>WordPress<sup>&reg;</sup> Developers</h3>
							<p><big>Again, as above. However, I would LOVE a (suggested) donation of &#36;25 USD</big><br>
							You can always bill it to your client! ;)
							</p>
						</li>
					</ul>
					<p><big>Thanks for your time and support!</big></p>
					<p><big>Phil :)</big></p>
					<p style="text-align: center;">
						<a class="gratitude" href="http://philmeadows.com/say-thank-you" target="_blank" title="Opens in new tab/window">Say &quot;Thanks&quot;</a>
						<a class="gratitude" href="https://wordpress.org/support/plugin/wp-roids/reviews/" target="_blank" title="Opens in new tab/window">&#9733;&#9733;&#9733;&#9733;&#9733; Review</a>
					</p>
				</div>
				<div class="pkm-panel pkm-panel-warning">
					<h2>&quot;It Broke My Site! Waaaaaa! :(&quot;</h2>
					<p>I've tested WP Roids on several sites I've built and it works fine.</p>
					<p>However, I cannot take in account conflicts with the thousands of Plugins and Themes from other sources, some of which <em>may</em> be poorly coded.</p>
					<p><strong>If this happens to you, please do the following steps, having your home page open in another browser. Or log out after each setting change if using the same browser. After each step refresh your home page TWICE</strong></p>
					<ol>
						<li>Switch your site's theme to "Twenty Seventeen". If it then works, you have a moody theme</li>
						<li>If still broken, disable all plugins except WP Roids. If WP Roids starts to work, we have a plugin conflit</li>
						<li>Reactivate each plugin one by one and refresh your home page each time time until it breaks</li>
						<li><a href="https://wordpress.org/support/plugin/wp-roids" target="_blank" title="Opens in new tab/window">Log an issue on the Support Page</a> and tell me as much as you can about what happened</li>
					</ol>

					<p>I will respond to issues as quickly as possible</p>
				</div>
			</div>
			<?php
			
		} // END adminPage()
		
	} // END class WPRoidsPhil
	
	// fire her up!
	WPRoidsPhil::instance();
	
} // END if class_exists()
