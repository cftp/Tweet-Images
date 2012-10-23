<?php

/*
Plugin Name: Tweet Images
Plugin URI: http://simonwheatley.co.uk/wordpress/tweet-images
Description: Allows you to post images for tweets to your blog from your Twitter client. Creates a post per image.
Version: 0.9
Author: Simon Wheatley
Author URI: http://simonwheatley.co.uk/wordpress/
*/
 
/*  Copyright 2010 Simon Wheatley

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

require_once( 'plugin.php' );
require_once( 'class-bitly.php' );

// @TODO: Keep some Tweet Images posts spare, with the shorturl and in a draft state

/**
 * 
 * 
 * @package WPTwitPics
 * @author Simon Wheatley
 **/
class WPTwitPics extends WPTwitPics_Plugin {

	/**
	 * A flag to record if we are processing an upload already,
	 * helps avoid a really weird race condition.
	 *
	 * @var bool
	 **/
	protected $processing;

	/**
	 * The current version, used to cache bust for JS and CSS,
	 * and to know when to flush rewrite rules, update DB, etc.
	 *
	 * @var int
	 **/
	protected $version;

	/**
	 * Initiate!
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function __construct() {
		$this->setup( 'wpti' );
		if ( is_admin() ) {
			$this->register_activation( __FILE__ );
			$this->add_action( 'admin_init' );
			$this->add_action( 'admin_menu' );
			$this->add_action( 'admin_notices' );
			$this->add_action( 'edit_user_profile', 'user_profile' );
			$this->add_action( 'load-settings_page_wpti_settings', 'load_settings' );
			$this->add_action( 'profile_update' );
			$this->add_action( 'show_user_profile', 'user_profile' );
		}
		$this->add_action( 'init', 'init_early', 0 );
		$this->add_action( 'parse_query' );
		$this->add_filter( 'get_shortlink', null, 999, 3 );
		$this->add_filter( 'query_vars' );

		$this->processing = false;
		$this->version = 1;
	}
	
	// HOOKS AND ALL THAT
	// ==================
	
	/**
	 * Hooks the WP plugin activation hook.
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function activate() {
		// Nothing to see yet.
		// @TODO: Move the rewrite rules here? wp_redirect
	}
	
	/**
	 * undocumented function
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function admin_init() {
		register_setting( $this->name, $this->name );
		$this->maybe_upgrade();
	}

	/**
	 * Hooks the WP admin_notices action to:
	 * * Warn the admin if they don't have pretty permalinks activated
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function admin_notices() {
		global $wp_rewrite;
		if ( ! $wp_rewrite->using_mod_rewrite_permalinks() && current_user_can( 'manage_options' ) ) {
			echo "<div class='error'><p>";
			printf( __( 'Warning: you have not enabled <a href="%s">non-default permalinks</a> which the Tweet Images plugin requires for uploading to work.', $this->name ), admin_url( '/options-permalink.php' ) );
			echo "</p></div>";
		}
	}
	
	/**
	 * Hooks a dynamic action on load of the settings page.
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function load_settings() {
		wp_enqueue_style( 'wpti_admin', $this->url( '/css/admin.css' ), array(), '0.0001', 'screen' );
	}
	
	/**
	 * Hooks the admin_menu action to add items into the
	 * admin menus.
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function admin_menu() {
		add_options_page( __( 'Tweet Images', 'wpti' ), __( 'Tweet Images', 'wpti' ), 'manage_options', 'wpti_settings', array( & $this, 'settings' ) );
	}
	
	/**
	 * Callback function which provides HTML for the admin settings page.
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function settings() {
		$vars = array();
		$vars[ 'options' ] = $this->get_all_options();
		$this->render_admin( 'settings.php', $vars );
	}

	/**
	 * Hooks the WP user_profile action to insert some additional fields.
	 * 
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function user_profile() {
		global $profileuser;
		$user = new WP_User( $profileuser->ID );
		if ( ! $user->has_cap( 'publish_posts' ) )
			return;
		if ( empty( $user->wpti_secret ) ) {
			$user->wpti_secret = md5( uniqid() );
			update_user_meta( $user->ID, 'wpti_secret', $user->wpti_secret );
		}

		$vars = array();
		$vars[ 'api_endpoint' ] = home_url( "/tweetimage/1/{$user->user_login}/{$user->wpti_secret}/upload.xml" );
		$vars[ 'allow_tweet_image' ] = get_user_meta( $user->ID, 'wpti_allow_tweet_image', true );
		$this->render_admin( 'user-profile.php', $vars );
	}

	/**
	 * Hooks the WP profile_update action to process the additional fields
	 * added in user_profile above.
	 * 
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function profile_update() {
		$do_update = (bool) @ $_POST[ '_wpti_nonce' ];
		check_admin_referer( 'wpti_update', '_wpti_nonce' );
		$user_id = (int) @ $_POST[ 'user_id' ];
		update_user_meta( $user_id, 'wpti_allow_tweet_image', (bool) @ $_POST[ 'wpti_allow_tweet_image' ] );
		$regenerate = (bool) @ $_POST[ 'wpti_regenerate_secret' ];
		if ( $regenerate ) {
			update_user_meta( $user_id, 'wpti_secret', md5( uniqid() ) );
			$this->set_admin_notice( __( 'Your "Tweet Images secret" has been regenerated, remember to copy/paste the new "Services URL" into your Twitter apps.', 'wpti' ) );
		}
	}
	
	/**
	 * Hooks the WP init action.
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function init_early() {
		$this->add_rewrite_rules();
		add_image_size( 'twitpic-thumb', 160, 160, false );
	}
	
	/**
	 * Hook the WP parse_query action, check if we're supposed to be 
	 * processing an upload or not.
	 *
	 * @param object $query A WP_Query object 
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function parse_query( $query ) {
		// Check the request relates to us
		if ( ! (bool) get_query_var( 'tweetimage' ) || $this->processing )
			return;
		// We are supposed to be processing an upload, so... 
		$this->processing = true;
		$this->process_upload();
		exit;
	}

	/**
	 * Hooks the WP query_vars filter to add in our 'twitter_image' query var, which
	 * will flag that we want to avoid pagination.
	 *
	 * @param array $public_query_vars An array of GET vars which should be 
	 * acceptable to WP_Query.
	 * @return array An (amended) array of queryvars
	 * @author Simon Wheatley
	 **/
	public function query_vars( $public_query_vars ) {
		$public_query_vars[] = 'tweetimage';
		$public_query_vars[] = 'wpti_user';
		$public_query_vars[] = 'wpti_secret';
		$public_query_vars[] = 'wpti_action';
		$public_query_vars[] = 'wpti_format';
		return $public_query_vars;
	}
	
	/**
	 * Hooks the WP get_shortlink filter to override the shortlink if we 
	 * have been given a Bit.ly login/api_key in the settings and if the
	 * post the shortlink is for is a Tweet Image page.
	 *
	 * @param string $shortlink The (filtered) shortlink URL
	 * @param int $id A post or blog id.  Default is 0, which means the current post or blog.
	 * @param string $context Whether the id is a 'blog' id, 'post' id, or 'media' id. If 'post', the post_type of the post is consulted. If 'query', the current query is consulted to determine the id and context. Default is 'post'.
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function get_shortlink( $shortlink, $id, $context ) {
		if ( ! $this->get_option( 'bitly_login' ) || ! $this->get_option( 'bitly_api_key' ) )
			if ( $shortlink )
				return $shortlink;
			else
				return get_permalink( $id );
		$post = get_post( $id );
		$is_tweet_image = get_post_meta( $post->ID, '_tweet_image', true );
		if ( ! $is_tweet_image )
			return $shortlink;
		$cache = get_post_meta( $id, '_tweet_image_shortlink', true );
		if ( $cache ) {
			extract( $cache );
			// Cache is good for 24 hours
			if ( $timestamp + ( 24* 60 * 60 ) > time() )
				return $bitly_shortlink;
		}
		$bitly = new WPTI_Bitly( $this->get_option( 'bitly_login' ), $this->get_option( 'bitly_api_key' ) );
		$permalink = get_permalink( $id );
		$bitly_shortlink = $bitly->shorten( $permalink );
		if ( is_wp_error( $bitly_shortlink ) ) {
			$this->log( $bitly_shortlink->get_error_message() );
			// HTTP error probably, try to use an out of date cache instead
			if ( $cache ) {
				extract( $cache );
				return $bitly_shortlink;
			}
			// If there's no cache, we've failed so just return 
			// the passed $shortlink param
			return $shortlink;
		}
		// Cache it
		$timestamp = time();
		$cache = compact( "bitly_shortlink", "timestamp" );
		update_post_meta( $id, '_tweet_image_shortlink', $cache );
		return $bitly_shortlink;
	}

	// UTILITIES
	// =========

	/**
	 * Process an upload. Expected post fields are:
	 * * message - The message
	 * Expected files are:
	 * * media - The uploaded file
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	protected function process_upload() {
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		require_once( ABSPATH . 'wp-admin/includes/media.php' );

		// Check credentials
		$user_login = get_query_var( 'wpti_user' );
		$secret = get_query_var( 'wpti_secret' );
		$userdata = get_user_by( 'login', $user_login );
		$user = new WP_User( $userdata->ID );

		// Check the user exists
		if ( ! $user )
			$this->simple_die( __( 'ERROR: User account not found.', 'wpti' ) );
			
		// Check this user is allowed/allowing uploads
		$allowed = get_user_meta( $user->ID, 'wpti_allow_tweet_image', true );
		if ( ! $allowed )
			$this->simple_die( __( 'ERROR: This user is not allowing uploads.', 'wpti' ), 403 );

		// Check the secret
		$stored_secret = get_user_meta( $user->ID, 'wpti_secret', true );
		if ( $secret != $stored_secret )
			$this->simple_die( __( 'ERROR: Incorrect secret.', 'wpti' ), 403 );

		// Can this user publish posts?
		if ( ! $user->has_cap( 'publish_posts' ) )
			$this->simple_die( __( 'ERROR: This user cannot publish posts.', 'wpti' ) );

		// Check there's an upload
		if( ! isset( $_FILES[ 'media' ] ) )
			$this->simple_die( __( 'ERROR: You need to upload an image as "media".', 'wpti' ) );

		$message = @ $_POST[ 'message' ];
		
		// Create the post to attach the tweet and image to
		$post_data = array(
			'post_author' => $user->ID,
			'post_content' => '', // Blank, for the moment
			'post_status' => 'draft',
			'post_title' => $this->truncate( $message, 40 ),
		);
		$post_data[ 'post_name' ] = uniqid();
		$post_id = wp_insert_post( apply_filters( 'wpti_insert_post_data', $post_data ) );
		if ( is_wp_error( $post_id ) )
			$this->simple_die( $post_id->get_error_message() );

		// Flag the post as created with Tweet Images in the post_meta
		add_post_meta( $post_id, '_tweet_image', true );
		
		// Set the post format to 'image' so themes can do their thing
		if ( function_exists( 'set_post_format' ) )
			set_post_format( $post_id, 'image' );
		
		// Handle the attachment upload
		$attachment_id = media_handle_upload( 'media', $post_id, $post_data, array( 'upload_error_handler' => 'wpti_error_handler', 'test_form' => false ) );
		if ( is_wp_error( $attachment_id ) ) {
			wp_trash_post( $post_id );
			$this->simple_die( $attachment_id->get_error_message() );
		}
		
		$hashtag_regex = '/(^|\s)#(\w*[a-zA-Z_]+\w*)/';
		
		// Add the hashtags as post tags (do this before adulterating the message text with HTML)
		preg_match_all( $hashtag_regex, $message, $preg_output );
		$tags = $preg_output[ 2 ];
		$image_size = apply_filters( 'wpti_post_tag', $tags, $message, $post_id );
		wp_set_object_terms( $post_id, $tags, apply_filters( 'wpti_hashtag_tax', 'post_tag' ) );
		
		// Make URLs in message clickable
		$message = make_clickable( $message );

		// En-link-ify all the hashtags in the message text
		$message = preg_replace( $hashtag_regex, '\1<a href="http://search.twitter.com/search?q=%23\2">#\2</a>', $message );

		// Add the image into the post body
		// Devs: You can hook the 'wpti_image_size' filter below to customise the 
		// image size you need placed in the post.
		$image_size = apply_filters( 'wpti_image_size', 'medium' );
		$img = wp_get_attachment_image( $attachment_id, $image_size );
		$fullsize_url = wp_get_attachment_url( $attachment_id );
		// Devs: You can hook the wpti_post_content filter below to customise the 
		// content of the Tweet Image post. Note that the post has been created 
		// at this point.
		$content = apply_filters( 'wpti_post_content', '<div class="tweet-pic"><a href="' . esc_attr( $fullsize_url ) . '">' . $img . '</a></div><p class="tweet">' . $message . '</p>', $message, $post_id, $attachment_id );
		$post_data = array(
			'ID' => $post_id,
			'post_author' => $user->ID,
			'post_content' => $content,
			'post_status' => 'inherit',
		);
		// Devs: Hook the 'wpti_update_post_data' filter to add to or amend the post_data used to create the post
		// Apologies, I ended up changing this from wpti_post_data to the more appropriate wpti_update_post_data. SW
		$post_data = apply_filters( 'wpti_update_post_data', $post_data, $message, $attachment_id );
		wp_update_post( $post_data );
		
		// Devs: Hook the 'wpti_post_category' filter below to specify the category or categories
		// you want the post in, please return an array of strings.
		$cat_ids = (array) $this->get_option( 'categories' );
		$cats = array();
		foreach ( $cat_ids as $cid ) {
			$cat = get_term( $cid, 'category' );
			$cats[] = $cat->slug;
		}
		if ( $categories = apply_filters( 'wpti_post_category', $cats ) )
			$categories = wp_set_object_terms( $post_id, $categories, 'category' );
		if ( is_wp_error( $categories ) )
			$this->simple_die( sprintf( __( 'ERROR: Something went wrong when we tried to set the category, the error message was: ', 'wpti' ), $categories->get_error_message() ) );

		list( $thumb_url, $thumb_width, $thumb_height ) = wp_get_attachment_image_src( $attachment_id, 'twitpic-thumb' );

		$file = get_post_meta( $attachment_id, '_wp_attached_file', true );
		// If the file is relative, prepend upload dir
		if ( 0 !== strpos($file, '/') && !preg_match('|^.:\\\|', $file) && ( ($uploads = wp_upload_dir()) && false === $uploads['error'] ) )
			$image_url = $uploads['baseurl'] . "/$file";
		
		// Finally, publish the post
		wp_publish_post( $post_id );
		do_action( 'wpti_published_post', $post_id, $attachment_id );

		// Output based on TwitPic upload, also includes MEDIAURL element as
		// requested in http://developer.atebits.com/tweetie-iphone/custom-image/
		status_header( 200 );
		Header( 'Content-type: application/xml; charset=' . get_option( 'blog_charset' ) );
		 // MEDIAURL is the only element Tweetie/Twitter for iPhone cares about,
		// and bizarrely it's not in the TwitPic API v2 Upload method.
		// For some reason, I'm getting errors when I output an XML declaration to Tweetbot, 
		// so I leave it out.
		$output = '<mediaurl>' . esc_url( wp_get_shortlink( $post_id, 'post' ) ) . '</mediaurl>';
		echo $output;

		// error_log( "XML: $output" );
		// error_log( "In Headers: " . print_r( apache_request_headers(), true ) );
		// error_log( "Server: " . print_r( $_SERVER, true ) );
		// error_log( "Post: " . print_r( $_POST, true ) );
		exit;
	}
	
	/**
	 * Rewrite rules for the URL for posting the images
	 *
	 * @param  
	 * @return void
	 * @author Simon Wheatley
	 **/
	protected function add_rewrite_rules() {
		$regex = 'tweetimage/1/([^/]+)/([^/]+)/([^/^\.]+).([^/^\.]+)$';
		$redirect = 'index.php?tweetimage=1&wpti_user=$matches[1]&wpti_secret=$matches[2]&wpti_action=$matches[3]&wpti_format=$matches[4]';
		add_rewrite_rule( $regex, $redirect, 'top' );
	}
	
	/**
	 * Exits, throws 500 (or whatever status your pass), and shows a simple text message.
	 *
	 * @param string $msg The message to display 
	 * @param int $status The status code (default 500)
	 * @return void
	 * @author Simon Wheatley
	 **/
	protected function simple_die( $msg, $status = 500 ) {
		$this->log( "WPTI died: $msg" );
		status_header( $status );
		echo $msg;
		exit;
	}
	
	/**
	 * Smarty truncate modifier plugin
	 *
	 * Type:     modifier<br>
	 * Name:     mb_truncate<br>
	 * Purpose:  Truncate a string to a certain length if necessary,
	 *           optionally splitting in the middle of a word, and
	 *           appending the $etc string or inserting $etc into the middle.
	 *           This version also supports multibyte strings.
	 * @link http://smarty.php.net/manual/en/language.modifier.truncate.php
	 *          truncate (Smarty online manual)
	 * @author   Guy Rutenberg <guyrutenberg@gmail.com> based on the original 
	 *           truncate by Monte Ohrt <monte at ohrt dot com>
	 * @param string
	 * @param integer
	 * @param string
	 * @param string
	 * @param boolean
	 * @param boolean
	 * @return string
	 */
	protected function truncate( $string, $length = 80, $etc = '...', $charset='UTF-8', $break_words = false, $middle = false ) {
		if ($length == 0)
			return '';
		if ( strlen( $string ) > $length ) {
			$length -= min( $length, strlen( $etc ) );
			if (!$break_words && !$middle) {
				$string = preg_replace('/\s+?(\S+)?$/', '', mb_substr($string, 0, $length+1, $charset));
			}
			if( ! $middle ) {
				return mb_substr( $string, 0, $length, $charset ) . $etc;
			} else {
				return mb_substr( $string, 0, $length/2, $charset ) . $etc . mb_substr( $string, -$length/2, $charset );
			}
		} else {
			return $string;
		}
	}
	
	/**
	 * Custom logging method
	 *
	 * @param string $msg The message to log 
	 * @return void
	 * @author Simon Wheatley
	 **/
	protected function log( $msg ) {
		if ( ! defined( 'WPTI_LOG' ) )
			define( 'WPTI_LOG', uniqid() );
		error_log( WPTI_LOG . ": $msg" );
	}

	/**
	 * Checks the DB structure is up to date, whether rewrite rules 
	 * need flushing, etc.
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	protected function maybe_upgrade() {
		global $wpdb;
		$version = get_option( 'wptp_version', 0 );
		
		if ( $version == $this->version )
			return;

		error_log( "WPTP: Current version: v$version" );
		
		if ( $version < 1 ) {
			flush_rewrite_rules();
			error_log( "WPTP: Flushed rewrite rules" );
		}

		// N.B. Remember to increment the version property above when you add a new IF, 
		// as otherwise that upgrade will run every time!

		error_log( "WPTP: Done upgrade" );
		update_option( 'wptp_version', $this->version );
	}
	
} // END WPTwitPics class 

$wp_twitpics = new WPTwitPics();

function wpti_error_handler( &$file, $message ) {
	error_log( "File: " . print_r( $file, true ) );
	error_log( "Message: " . print_r( $message, true ) );
	return array( 'error'=>$message );
}

?>