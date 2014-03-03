<?php
include_once 'DBisso_GoogleAnalyticsInternal_Event.php';

/**
 * Triggers Google Analytics measurement when internal WordPress events
 * occur.
 */
class DBisso_GoogleAnalyticsInternal {
	const OPTION = 'dbisso_gai_options';
	/**
	 * Set up and bind hooks.
	 */
	static public function bootstrap() {
		add_action( 'publish_post', array( __CLASS__, 'action_publish_post' ), 10, 1 );
		add_action( 'transition_post_status', array( __CLASS__, 'action_transition_post_status' ), 10, 3 );
		add_action( 'comment_post', array( __CLASS__, 'action_comment_post' ), 10, 2 );
		add_action( 'plugins_loaded', array( __CLASS__, 'plugins_loaded' ) );
	}

	/**
	 * Load our text domain
	 */
	static public function plugins_loaded() {
		load_plugin_textdomain( 'dbisso-google-analytics-internal', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Return the correct event action to send.
	 *
	 * @param  string       $new_status The newly set status.
	 * @param  string       $old_status The old status.
	 * @return string|false The event action to send.
	 */
	static public function get_post_event_action( $new_status, $old_status  ) {
		$separate_update_events = self::get_option( 'separate_update_events' );
		$is_post_published      = $old_status === 'publish';
		$action                 = false;

		if ( $new_status === 'publish' ) {
			if ( $separate_update_events && $is_post_published ) {
				$action = self::get_event_action( 'update_post' );
			} else {
				$action = self::get_event_action( 'publish_post' );
			}
		}

		return $action;
	}

	/**
	 * Trigger the GA event when a post is published
	 * @param  int $post_id The post ID.
	 */
	static public function action_transition_post_status( $new_status, $old_status, $post ) {
		$action = self::get_post_event_action( $new_status, $old_status );

		self::maybe_send_post_event( $action, $post->ID );
	}

	/**
	 * Trigger GA event when a comment is submitted / approved.
	 *
	 * @param  int        $comment_id The comment ID.
	 * @param  string|int $status     The comment status.
	 */
	static public function action_comment_post( $comment_id, $status ) {
		$is_spam        = ('spam' === $status);
		$is_approved    = (1 === $status);
		$is_disapproved = (0 === $status);
		$action         = null;

		$submitted_action = self::get_event_action( 'comment_submitted' );
		$approved_action  = self::get_event_action( 'comment_approved' );

		// If the comment isn't spam start with the submitted action.
		if ( ! $is_spam && $submitted_action ) {
			$action = $submitted_action;
		}

		// If the comment had been auto approved then override with.
		// the approved action.
		if ( $is_approved && $approved_action ) {
			$action = $approved_action;
		}

		$comment = get_comment( $comment_id );
		self::maybe_send_post_event( $action, $comment->comment_post_ID );
	}

	/**
	 * Create and dispatch the event only if we have a value action.
	 *
	 * @param  string $action  The action stirng
	 * @param  int    $post_id The post ID the action relates to
	 */
	static private function maybe_send_post_event( $action, $post_id ) {
		if ( is_string( $action ) ) {
			$event = new DBisso_GoogleAnalyticsInternal_Event(
				$action,
				get_the_title( (int) $post_id )
			);

			$event->send();
		}
	}

	/**
	 * Get plugins options
	 */
	static private function get_options() {
		$data = get_option( self::OPTION, self::get_options_defaults() );

		return $data;
	}

	static private function get_option( $name ) {
		$options = self::get_options();

		return $options[$name];
	}

	static private function get_options_defaults() {
		return array(
			'separate_update_events' => true,
		);
	}

	/**
	 * Get the action string for a given WP event
	 * @param  string $hook The name of the hook.
	 * @return string       The action for the hook.
	 */
	static private function get_event_action( $hook ) {
		$actions = self::get_event_actions();

		return $actions[$hook];
	}

	/**
	 * Returns the filtered string to use as the action in the GA event.
	 * @return array Array with WP event => action text.
	 */
	static private function get_event_actions() {
		$actions = array(
			'publish_post' => __( 'Publish Post', 'dbisso-google-analytics-internal' ),
			'update_post' => __( 'Update Post', 'dbisso-google-analytics-internal' ),
			'comment_submitted' => __( 'Comment Submitted', 'dbisso-google-analytics-internal' ),
			'comment_approved' => __( 'Comment Approved', 'dbisso-google-analytics-internal' )
		);

		return apply_filters( 'dbisso_gai_event_actions', $actions );
	}

	static private function is_post_published( $post_id ) {
		return get_post_status( $post_id ) === 'publish';
	}
}