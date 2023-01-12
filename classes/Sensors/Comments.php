<?php
/**
 * Sensor: Comments
 *
 * Comments sensor class file.
 *
 * @since     1.0.0
 * @package   wsal
 * @subpachae sensors
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress Comments.
 *
 * 2090 User approved a comment
 * 2091 User unapproved a comment
 * 2092 User replied to a comment
 * 2093 User edited a comment
 * 2094 User marked a comment as Spam
 * 2095 User marked a comment as Not Spam
 * 2096 User moved a comment to trash
 * 2097 User restored a comment from the trash
 * 2098 User permanently deleted a comment
 * 2099 User posted a comment
 *
 * @package    wsal
 * @subpackage sensors
 */
class WSAL_Sensors_Comments extends WSAL_AbstractSensor {

	/**
	 * {@inheritDoc}
	 */
	public function hook_events() {
		add_action( 'edit_comment', array( $this, 'event_comment_edit' ), 10, 1 );
		add_action( 'transition_comment_status', array( $this, 'event_comment_approve' ), 10, 3 );
		add_action( 'spammed_comment', array( $this, 'event_comment_spam' ), 10, 1 );
		add_action( 'unspammed_comment', array( $this, 'event_comment_unspam' ), 10, 1 );
		add_action( 'trashed_comment', array( $this, 'event_comment_trash' ), 10, 1 );
		add_action( 'untrashed_comment', array( $this, 'event_comment_untrash' ), 10, 1 );
		add_action( 'deleted_comment', array( $this, 'event_comment_deleted' ), 10, 1 );
		add_action( 'comment_post', array( $this, 'event_comment' ), 10, 3 );
	}

	/**
	 * Trigger comment edit.
	 *
	 * @param integer $comment_id - Comment ID.
	 */
	public function event_comment_edit( $comment_id ) {
		$this->EventGeneric( $comment_id, 2093 );
	}

	/**
	 * Trigger comment status.
	 *
	 * @param string   $new_status - New status.
	 * @param string   $old_status - Old status.
	 * @param stdClass $comment - Comment.
	 */
	public function event_comment_approve( $new_status, $old_status, $comment ) {
		if ( ! empty( $comment ) && $old_status !== $new_status ) {
			$post         = get_post( $comment->comment_post_ID );
			$comment_link = get_permalink( $post->ID ) . '#comment-' . $comment->comment_ID;
			$fields       = array(
				'PostTitle'   => $post->post_title,
				'PostID'      => $post->ID,
				'PostType'    => $post->post_type,
				'PostStatus'  => $post->post_status,
				'CommentID'   => $comment->comment_ID,
				'Author'      => $comment->comment_author,
				'Date'        => $comment->comment_date,
				'CommentLink' => '<a target="_blank" href="' . $comment_link . '">' . $comment->comment_date . '</a>',
			);

			if ( 'approved' === $new_status ) {
				$this->plugin->alerts->trigger_event( 2090, $fields );
			}
			if ( 'unapproved' === $new_status ) {
				$this->plugin->alerts->trigger_event( 2091, $fields );
			}
		}
	}

	/**
	 * Trigger comment spam.
	 *
	 * @param integer $comment_id - Comment ID.
	 */
	public function event_comment_spam( $comment_id ) {
		$this->EventGeneric( $comment_id, 2094 );
	}

	/**
	 * Trigger comment unspam.
	 *
	 * @param integer $comment_id - Comment ID.
	 */
	public function event_comment_unspam( $comment_id ) {
		$this->EventGeneric( $comment_id, 2095 );
	}

	/**
	 * Trigger comment trash.
	 *
	 * @param integer $comment_id - Comment ID.
	 */
	public function event_comment_trash( $comment_id ) {
		$this->EventGeneric( $comment_id, 2096 );
	}

	/**
	 * Trigger comment untrash.
	 *
	 * @param integer $comment_id comment ID.
	 */
	public function event_comment_untrash( $comment_id ) {
		$this->EventGeneric( $comment_id, 2097 );
	}

	/**
	 * Trigger comment deleted.
	 *
	 * @param integer $comment_id comment ID.
	 */
	public function event_comment_deleted( $comment_id ) {
		$this->EventGeneric( $comment_id, 2098 );
	}

	/**
	 * Fires immediately after a comment is inserted into the database.
	 *
	 * @param int        $comment_id       The comment ID.
	 * @param int|string $comment_approved 1 if the comment is approved, 0 if not, 'spam' if spam.
	 * @param array      $comment_data     Comment data.
	 */
	public function event_comment( $comment_id, $comment_approved, $comment_data ) {
		// Check if the comment is response to another comment.
		if ( isset( $comment_data['comment_parent'] ) && $comment_data['comment_parent'] ) {
			$this->EventGeneric( $comment_id, 2092 );
			return;
		}

		$comment = get_comment( $comment_id );
		if ( $comment ) {
			if ( 'spam' !== $comment->comment_approved ) {
				$post         = get_post( $comment->comment_post_ID );
				$comment_link = get_permalink( $post->ID ) . '#comment-' . $comment_id;
				$fields       = array(
					'PostTitle'   => $post->post_title,
					'PostID'      => $post->ID,
					'PostType'    => $post->post_type,
					'PostStatus'  => $post->post_status,
					'CommentID'   => $comment->comment_ID,
					'Date'        => $comment->comment_date,
					'CommentLink' => $comment_link,
				);

				// Get user data.
				$user_data = get_user_by( 'email', $comment->comment_author_email );

				if ( $user_data && $user_data instanceof WP_User ) {
					// Get user roles.
					$user_roles = $user_data->roles;

					// Check if superadmin.
					if ( function_exists( 'is_super_admin' ) && is_super_admin() ) {
						$user_roles[] = 'superadmin';
					}

					// Set the fields.
					$fields['Username']         = $user_data->user_login;
					$fields['CurrentUserRoles'] = $user_roles;
					$this->plugin->alerts->trigger_event( 2099, $fields );
				}
			}
		}
	}

	/**
	 * Trigger generic event.
	 *
	 * @param integer $comment_id - Comment ID.
	 * @param integer $alert_code - Event code.
	 */
	private function EventGeneric( $comment_id, $alert_code ) {
		$comment = get_comment( $comment_id );
		if ( $comment ) {
			$post         = get_post( $comment->comment_post_ID );
			$comment_link = get_permalink( $post->ID ) . '#comment-' . $comment_id;
			$fields       = array(
				'PostTitle'   => $post->post_title,
				'PostTitle'   => $post->post_title,
				'PostID'      => $post->ID,
				'PostType'    => $post->post_type,
				'PostStatus'  => $post->post_status,
				'CommentID'   => $comment->comment_ID,
				'Author'      => $comment->comment_author,
				'Date'        => $comment->comment_date,
				'CommentLink' => '<a target="_blank" href="' . $comment_link . '">' . $comment->comment_date . '</a>',
			);

			if ( 'shop_order' !== $post->post_type ) {
				$this->plugin->alerts->trigger_event( $alert_code, $fields );
			}
		}
	}
}
