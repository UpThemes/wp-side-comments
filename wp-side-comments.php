<?php

	/*
	Plugin Name: WP Side Comments
	Plugin URI: http://ctlt.ubc.ca/
	Description: Based on aroc's Side Comments .js to enable inline commenting
	Author: CTLT Dev, Richard Tape
	Author URI: http://ctlt.ubc.ca
	Version: 0.1.4
	*/

	if( !defined( 'ABSPATH' ) ){
		die( '-1' );
	}

	define( 'CTLT_WP_SIDE_COMMENTS_PLUGIN_URL', get_template_directory_uri() . '/inc/wp-side-comments/' );


	class CTLT_WP_Side_Comments
	{

		/**
		 * Set up our actions and filters
		 *
		 * @since 0.1
		 *
		 * @param null
		 * @return null
		 */

		public function __construct()
		{

			// Load the necessary js/css
			add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts__loadScriptsAndStyles' ) );

			// Add a filter to the post container
			add_filter( 'post_class', array( $this, 'post_class__addSideCommentsClassToContainer' ) );

			// Filter the_content to add our specific inline classes
			add_filter( 'the_content', array( $this, 'the_content__addSideCommentsClassesToContent' ) );

			// Set up AJAX handlers for the create a new comment action
			add_action( 'wp_ajax_add_side_comment', array( $this, 'wp_ajax_add_side_comment__AJAXHandler' ) );
			//add_action( 'wp_ajax_nopriv_add_side_comment', array( $this, 'wp_ajax_nopriv_add_side_comment__redirectToLogin' ) );
			add_action( 'wp_ajax_nopriv_add_side_comment', array( $this, 'wp_ajax_add_side_comment__AJAXHandler' ) );

			// Set up AJAX handlers for comment deltion
			add_action( 'wp_ajax_delete_side_comment', array( $this, 'wp_ajax_delete_side_comment__AJAXHandler' ) );
			add_action( 'wp_ajax_nopriv_delete_side_comment', array( $this, 'wp_ajax_nopriv_delete_side_comment__redirectToLogin' ) );

		}/* __construct() */


		/**
		 * Register and enqueue the necessary scripts and styles
		 *
		 * @since 0.1
		 *
		 * @param null
		 * @return null
		 */

		public function wp_enqueue_scripts__loadScriptsAndStyles()
		{

			// Ensure we're on a post where we want to load our scripts/styles
			$validScreen = $this->weAreOnAValidScreen();

			if( !$validScreen ){
				return;
			}

			wp_register_style( 'side-comments-style', CTLT_WP_SIDE_COMMENTS_PLUGIN_URL . 'includes/css/side-comments.css' );
			wp_register_script( 'side-comments-script', CTLT_WP_SIDE_COMMENTS_PLUGIN_URL . 'includes/js/side-comments.js', array ( 'jquery' ) );
			wp_register_script( 'wp-side-comments-script', CTLT_WP_SIDE_COMMENTS_PLUGIN_URL . 'includes/js/wp-side-comments.js', array ( 'jquery', 'side-comments-script' ), null, true );

			wp_enqueue_style( 'side-comments-style' );
			wp_enqueue_script( 'side-comments-script' );
			wp_enqueue_script( 'wp-side-comments-script' );

			if( isset( $theme ) )
			{
				wp_register_style( 'side-comments-theme', $theme );
				wp_enqueue_style( 'side-comments-theme' );
			}

			// Need to get some data for our JS, which we pass to it via localization
			$data = $this->getCommentsData();

			// ENsure we have a nonce for AJAX purposes
			$data['nonce'] = wp_create_nonce( 'side_comments_nonce' );

			// We also need the admin url as we need to send an AJAX request to it
			$data['ajaxURL'] = admin_url( 'admin-ajax.php' );

			$data['containerSelector'] = apply_filters( 'wp_side_comments_container_css_selector', '.commentable-container' );

			wp_localize_script( 'wp-side-comments-script', 'commentsData', $data );

		}/* wp_enqueue_scripts__loadScriptsAndStyles() */


		/**
		 * Filter the post_class which is output on the containing element of the post
		 *
		 * @since 0.1
		 *
		 * @param array $classes current post container classes
		 * @return array $classes modified post container classes (with our extra side comments classes)
		 */

		public function post_class__addSideCommentsClassToContainer( $classes )
		{

			// Ensure we're on a post where we want to load our scripts/styles
			$validScreen = $this->weAreOnAValidScreen();

			if( !$validScreen ){
				return $classes;
			}

			if( !$classes || !is_array( $classes ) ){

				$classes = array();

			}

			$classes[] = 'commentable-container';
			$classes[] = 'container';

			return $classes;

		}/* post_class__addSideCommentsClassToContainer() */


		/**
		 * Add our required classes and attributes to paragraph tags in the_content
		 *
		 * @since 0.1
		 *
		 * @param string $content the post content
		 * @return string $content modified post content with our classes/attributes
		 */

		public function the_content__addSideCommentsClassesToContent( $content )
		{

			// Ensure we're on a post where we want to load our scripts/styles
			$validScreen = $this->weAreOnAValidScreen();

			if( !$validScreen ){
				return $content;
			}

			$regex = '|<p>|';

			return preg_replace_callback( $regex, array( $this, '_addAttributesToParagraphCallback' ), $content );

		}/* the_content__addSideCommentsClassesToContent() */


		/**
		 * The callback function for the preg_replace_callback function run on the_content
		 * This adds a class and an incremental integer to a data attribute
		 *
		 * @since 0.1
		 *
		 * @param array $matches the matches of the regex from the_content
		 * @return string add a class and data attribute to the individual matches
		 */

		private function _addAttributesToParagraphCallback( $matches )
		{

			static $i = 1;

			return sprintf( '<p class="commentable-section" data-section-id="%d">', $i++ );

		}/* _addAttributesToParagraphCallback() */


		/**
		 * side-comments.js requires data to be passed to the JS. This method gathers the information
		 * which is then passed to wp_localize_script(). We need information about the user and the comments
		 * for the page we're looking at
		 *
		 * @since 0.1
		 *
		 * @param int $postID - the ID of the post for which we wish to get comment data
		 * @return array $commentData - an associative array of comment data and user data
		 */

		public function getCommentsData( $postID = false )
		{

			// Fetch the post ID if we haven't been passed one
			if( !$postID ){

				global $post;
				$postID = ( isset( $post->ID ) ) ? $post->ID : false;

			}

			if( !$postID ){
				return false;
			}

			$commentsForThisPost = $this->getPostCommentData( $postID );

			$detailsAboutCurrentUser = $this->getCurrentUserDetails();

			// start fresh
			$commentData = array();

			// Add our data if we have it
			if( $commentsForThisPost && is_array( $commentsForThisPost ) ){
				$commentData['comments'] = $commentsForThisPost;
			}

			if( $detailsAboutCurrentUser && is_array( $detailsAboutCurrentUser ) ){
				$commentData['user'] = $detailsAboutCurrentUser;
			}

			$commentData['postID'] = $postID;

			// Ship it.
			return $commentData;

		}/* getCommentsData() */


		/**
		 * Get data for a single post's comments.
		 * When data is saved, the section is saved as comment meta (key = 'section' and value = integer of the section)
		 *
		 * @since 0.1
		 *
		 * @param string $param description
		 * @return string|int returnDescription
		 */

		public static function getPostCommentData( $postID = false )
		{

			// Fetch the post ID if we haven't been passed one
			if( !$postID ){

				global $post;
				$postID = ( isset( $post->ID ) ) ? $post->ID : false;

			}

			if( !$postID ){
				return false;
			}

			// Build our args for get_comments
			$getCommentArgs = array(
				'post_id' => $postID,
				'status' => 'approve'
			);

			$comments = get_comments( $getCommentArgs );

			// Do we have any?
			if( !$comments || !is_array( $comments ) || empty( $comments ) ){
				return false;
			}

			// Start fresh
			$sideCommentData = array();

			foreach( $comments as $key => $commentData )
			{

				$thisCommentID = $commentData->comment_ID;

				$section = get_comment_meta( $thisCommentID, 'side-comment-section', true );

				if( !isset( $sideCommentData[$section] ) ){
					$sideCommentData[$section] = array();
				}

				$sideCommentData[$section][] = array(
					'authorAvatarUrl' => self::get_avatar_url( $commentData->comment_author_email ),
					'authorName' => $commentData->comment_author,
					'comment' => $commentData->comment_content,
					'authorID' => $commentData->user_id
				);

			}

			return $sideCommentData;

		}/* getPostCommentData() */


		/**
		 * Get data about the current user that we will need in side-comments js
		 *
		 * @since 0.1
		 *
		 * @param null
		 * @return array $userDetails data about the user
		 */

		public static function getCurrentUserDetails()
		{

			$userID = get_current_user_id();

			if( !$userID ){
				return self::getDefaultuserDetails();
			}

			return self::getUserDetails( $userID );

		}/* getCurrentUserDetails() */


		/**
		 * Default user details (Temp method)
		 *
		 *
		 * @since 0.1
		 * @todo Remove this in favour of using the correct comment_form() method to produce the markup for the comments form
		 *
		 * @param string $param description
		 * @return string|int returnDescription
		 */

		public static function getDefaultuserDetails()
		{

			// Build our output
			$userDetails = array(
				'name' => __( 'Anonymous', 'wp-side-comments' ),
				'avatar' => self::get_avatar_url( 'test@test.com' ),
				'id' => 9999
			);

			return $userDetails;

		}/* getDefaultuserDetails() */


		/**
		 * Get data about a specified user ID
		 *
		 * @since 0.1
		 *
		 * @param int $userID The ID of a specific user
		 * @return array $userDetails details about the specified user
		 */

		public static function getUserDetails( $userID = false )
		{

			if( !$userID ){
				return false;
			}

			$user = get_user_by( 'id', $userID );

			if( !$user ){
				return false;
			}

			// We need name, ID and avatar url
			$name = ( isset( $user->user_nicename ) ) ? $user->user_nicename : $user->user_login;

			$avatarURL = self::get_avatar_url( $user->user_email );

			if( ! isset( $avatarURL ) || ! $avatarURL ){
				$avatarURL = includes_url( 'images/blank.gif' );
			}

			// Build our output
			$userDetails = array(
				'name' => $name,
				'avatar' => $avatarURL,
				'id' => $userID
			);

			return apply_filters( 'wp_side_comments_user_details', $userDetails, $user );

		}/* getUserDetails() */


		/**
		 * I hate this method. But, at the moment, there's no proper way to get the URL only for the avatar,
		 * so we're relegated to using HTML parsing. Yay. I really hope the patch for this makes it into
		 * 4.0 ( https://core.trac.wordpress.org/ticket/21195 )
		 *
		 * @since 0.1
		 *
		 * @param string $email the email address of the user for which we're looking for the avatar
		 * @return string the url of the avatar
		 */

		public static function get_avatar_url( $email )
		{

			$avatar_html = get_avatar( $email, 24, 'blank' );
			// strip the avatar url from the get_avatar img tag.
			preg_match('/src=["|\'](.+)[\&|"|\']/U', $avatar_html, $matches);

			if( isset( $matches[1] ) && !empty( $matches[1] ) ){
				return esc_url_raw( $matches[1] );
			}

			return '';

		}/* json_get_avatar_url() */


		/**
		 * AJAX handler for when someone is logged in and trying to make a comment
		 *
		 * @since 0.1
		 *
		 * @param string $param description
		 * @return string|int returnDescription
		 */

		public static function wp_ajax_add_side_comment__AJAXHandler()
		{

			if( !wp_verify_nonce( $_REQUEST['nonce'], 'side_comments_nonce' ) ) {
				exit( __( 'Nonce check failed', 'wp-side-comments' ) );
			}

			// Collect data sent to us via the AJAX request
			$postID = absint( $_REQUEST['postID'] );
			$sectionID = absint( $_REQUEST['sectionID'] );
			$commentText = strip_tags( $_REQUEST['comment'], '<p><a><br>' );
			$authorName = sanitize_text_field( $_REQUEST['authorName'] );
			$authorID = absint( $_REQUEST['authorId'] );
			$loggedInToComment = get_option('comment_registration');
			$requireNameEmail = get_option('require_name_email');
			$commentModeration = get_option('comment_moderation');
			$commentWhitelist = get_option('comment_whitelist');

			$user = get_user_by( 'id', $authorID );

			if( !empty( $_SERVER['HTTP_CLIENT_IP'] ) ){
				$ip = $_SERVER['HTTP_CLIENT_IP'];
			}elseif( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ){
				$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
			}else{
				$ip = $_SERVER['REMOTE_ADDR'];
			}

			//$commentApproval = apply_filters( 'wp_side_comments_default_comment_approved_status', 1 );

			//if comments need to be approved manually or whitelist required and anonymous user
			if( $commentModeration || ($commentWhitelist && !is_user_logged_in()) ){
				$commentApproval = 0;
			} else{
				$commentApproval = 1;
			}

			$commentError = false;


			//if users must be logged in and they are not
			if( ($loggedInToComment || $requireNameEmail) && !is_user_logged_in() )
			{
				$commentError = true;

				$result = array(
					'type' => 'error',
					'message' => __( 'You must be signed in to comment', 'wp-side-comments' ),
					'loginURL' => wp_login_url(get_permalink($postID)),
				);
			}
			//don't allow empty comments
			elseif( empty($commentText) )
			{
				$commentError = true;

				$result = array(
					'type' => 'error',
					'message' => __( 'Comment cannot be blank', 'wp-side-comments' ),
				);
			}
			//comments must be at least two words
			elseif( str_word_count($commentText) <= 1 )
			{
				$commentError = true;

				$result = array(
					'type' => 'error',
					'message' => __( 'Comment too short', 'wp-side-comments' ),
				);
			}
			else
			{
				// The data we need for wp_insert_comment
				$wpInsertCommentArgs = array(
					'comment_post_ID' => $postID,
					'comment_author' => $authorName,
					'comment_author_email' => $user->user_email,
					'comment_author_url' => null,
					'comment_content' => $commentText,
					'comment_type' => '',
					'comment_parent' => 0,
					'user_id' => $authorID,
					'comment_author_IP' => $ip,
					'comment_agent' => $_SERVER['HTTP_USER_AGENT'],
					'comment_date' => null,
					'comment_approved' => $commentApproval
				);

				$newCommentID = wp_insert_comment( $wpInsertCommentArgs );
			}

			if( $newCommentID && !$commentError )
			{

				// Now we have a new comment ID, we need to add the meta for the section, stored as 'side-comment-section'
				update_comment_meta( $newCommentID, 'side-comment-section', $sectionID );

				// Setup our data which we're echoing
				$result = array(
					'type' => 'success',
					'newCommentID' => $newCommentID,
					'commentApproval' => $commentApproval,
					'message' => __( 'Comment submitted for approval', 'wp-side-comments' ),
				);

			}
			elseif( !$commentError )
			{

				// wp_insert_comment failed
				$result = array(
					'type' => 'failure',
					'reason' => __( 'wp_insert_comment failed', 'wp-side-comments' )
				);

			}

			if( !empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) == 'xmlhttprequest' )
			{

				$result = json_encode( $result );
				echo $result;

			}
			else
			{

				header( 'Location: ' . $_SERVER['HTTP_REFERER'] );

			}

			die();

		}/* wp_ajax_add_side_comment__AJAXHandler() */


		/**
		 * AJAX handler for when someone is NOT logged in and trying to make a comment.
		 * Probably should never get to here because of comments_open() being used, but
		 * better safe than sorry.
		 *
		 * @since 0.1
		 *
		 * @param string $param description
		 * @return string|int returnDescription
		 */

		public static function wp_ajax_nopriv_add_side_comment__redirectToLogin()
		{

			$redirect = apply_filters( 'wp_side_comments_redirect_on_not_logged_in_comment_submission', $_SERVER['HTTP_REFERER'] );

			if( $redirect ){

				wp_redirect(
					add_query_arg(
						array( 'redirect_to' => $redirect ),
						home_url()
					)
				);

			}

			die();

		}/* wp_ajax_nopriv_add_side_comment__redirectToLogin() */


		/**
		 * AJAX handler for when a comment is deleted
		 *
		 * @since 0.1
		 *
		 * @param null
		 * @return null
		 */

		public static function wp_ajax_delete_side_comment__AJAXHandler()
		{

			if( !wp_verify_nonce( $_REQUEST['nonce'], 'side_comments_nonce' ) ) {
				exit( __( 'Nonce check failed', 'wp-side-comments' ) );
			}

			// Collect data sent to us via the AJAX request
			$postID = absint( $_REQUEST['postID'] );
			$commentID = absint( $_REQUEST['commentID'] );

			// Force delete the comment?
			$forceDelete = apply_filters( 'wp_side_comments_force_delete_comment', false );

			$hasDeleted = wp_delete_comment( $commentID, $forceDelete );

			if( $hasDeleted )
			{

				// Setup our data which we're echoing
				$result = array(
					'type' => 'success',
					'forceDelete' => $forceDelete
				);

			}
			else
			{

				$result = array(
					'type' => 'failure',
					'message' => __( 'The comment was not deleted', 'wp-side-comments' )
				);

			}

			if( !empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) == 'xmlhttprequest' )
			{

				$result = json_encode( $result );
				echo $result;

			}
			else
			{

				header( 'Location: ' . $_SERVER['HTTP_REFERER'] );

			}

			die();

		}/* wp_ajax_delete_side_comment__AJAXHandler() */


		/**
		 * AJAX handler for when a comment is deleted and the user isn't logged in. Good luck with that.
		 *
		 *
		 * @since 0.1
		 *
		 * @param null
		 * @return null
		 */

		public static function wp_ajax_nopriv_delete_side_comment__redirectToLogin()
		{



		}/* wp_ajax_nopriv_delete_side_comment__redirectToLogin() */




		/**
		 * Method to determine if we're on the right place to load our scripts/styles and do our bits and pieces
		 * basically, not admin, on a singular post/page and comments are open
		 *
		 * @since 0.1
		 *
		 * @param null
		 * @return null
		 */

		private function weAreOnAValidScreen()
		{

			// We don't have anything for the admin at the moment and comments are only on a single
			if( is_admin() || !is_singular() ){
				return false;
			}

			// Ensure comments are open on the post we're on
			global $post;

			if( ! comments_open( $post->ID ) ){
				return false;
			}

			return true;

		}/* weAreOnAValidScreen() */

	}/* class CTLT_WP_Side_Comments */

	global $CTLT_WP_Side_Comments;
	$CTLT_WP_Side_Comments = new CTLT_WP_Side_Comments();
