<?php

class WSAL_Sensors_Comments extends WSAL_AbstractSensor
{

    public function HookEvents()
    {
        add_action('edit_comment', array($this, 'EventCommentEdit'), 10, 1);
        add_action('transition_comment_status', array($this, 'EventCommentApprove'), 10, 3);
        add_action('spammed_comment', array($this, 'EventCommentSpam'), 10, 1);
        add_action('unspammed_comment', array($this, 'EventCommentUnspam'), 10, 1);
        add_action('trashed_comment', array($this, 'EventCommentTrash'), 10, 1);
        add_action('untrashed_comment', array($this, 'EventCommentUntrash'), 10, 1);
        add_action('deleted_comment', array($this, 'EventCommentDeleted'), 10, 1);
        add_action('comment_post', array($this, 'EventComment'), 10, 2);
    }

    public function EventCommentEdit($comment_ID)
    {
        $comment = get_comment($comment_ID);
        $this->EventGeneric($comment_ID, 2093);
    }

    public function EventCommentApprove($new_status, $old_status, $comment)
    {
        if (!empty($comment) && $old_status != $new_status) {
            if ($new_status == 'approved') {
                $this->plugin->alerts->Trigger(2090, array(
                    'Author' => $comment->comment_author,
                    'AuthorEmail' => $comment->comment_author_email,
                    'Date' => $comment->comment_date
                ));
            }
            if ($new_status == 'unapproved') {
                $this->plugin->alerts->Trigger(2091, array(
                    'Author' => $comment->comment_author,
                    'AuthorEmail' => $comment->comment_author_email,
                    'Date' => $comment->comment_date
                ));
            }
        }
    }

    public function EventCommentSpam($comment_ID)
    {
        $this->EventGeneric($comment_ID, 2094);
    }

    public function EventCommentUnspam($comment_ID)
    {
        $this->EventGeneric($comment_ID, 2095);
    }

    public function EventCommentTrash($comment_ID)
    {
        $this->EventGeneric($comment_ID, 2096);
    }

    public function EventCommentUntrash($comment_ID)
    {
        $this->EventGeneric($comment_ID, 2097);
    }

    public function EventCommentDeleted($comment_ID)
    {
        $this->EventGeneric($comment_ID, 2098);
    }

    /**
     * Fires immediately after a comment is inserted into the database.
     * @param int        $comment_ID       The comment ID.
     * @param int|string $comment_approved 1 if the comment is approved, 0 if not, 'spam' if spam.
     */
    public function EventComment($comment_ID, $comment_approved)
    {
        if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'replyto-comment') {
            $this->EventGeneric($comment_ID, 2092);
        }
        if (isset($_REQUEST['comment'])) {
            $this->EventGeneric($comment_ID, 2099);
        }
    }

    private function EventGeneric($comment_ID, $alert_code)
    {
        $comment = get_comment($comment_ID);
        if (!empty($comment)) {
            $this->plugin->alerts->Trigger($alert_code, array(
                'Author' => $comment->comment_author,
                'AuthorEmail' => $comment->comment_author_email,
                'Date' => $comment->comment_date
            ));
        }
    }
}
