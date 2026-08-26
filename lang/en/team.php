<?php

return [
    'navigation_label' => 'Team',
    'title' => 'Team',
    'subtitle' => 'Who has access to this workspace, and who has been invited.',
    'fields' => [
        'name' => 'Name',
        'email' => 'Email',
        'role' => 'Role',
        'invited_email' => 'Invited email',
        'status' => 'Status',
        'invited_at' => 'Invited on',
        'expires_at' => 'Expires on',
    ],
    'members' => [
        'heading' => 'Active members',
        'description' => 'People with access to this workspace right now.',
    ],
    'invitations' => [
        'heading' => 'Pending invitations',
        'description' => 'Invitations that have not been accepted yet. An invitation is not access — it becomes membership only once accepted.',
    ],
    'actions' => [
        'invite' => 'Invite member',
        'remove' => 'Remove',
        'resend' => 'Resend',
        'revoke' => 'Revoke',
    ],
    'invite' => [
        'heading' => 'Invite a member',
        'description' => 'They will receive an email with a link to join this workspace.',
        'confirm' => 'Send invitation',
        'use_resend' => 'Use Resend on their existing invitation instead.',
    ],
    'remove' => [
        'heading' => 'Remove :name from this workspace?',
        'description' => 'This person will immediately lose access to this workspace. They keep their account and any other workspace they belong to.',
        'confirm' => 'Remove access',
    ],
    'revoke' => [
        'heading' => 'Revoke the invitation to :email?',
        'description' => 'The invitation link will stop working. You can invite this address again at any time.',
        'confirm' => 'Revoke invitation',
    ],
    'notifications' => [
        'invitation_sent' => 'Invitation sent to :email',
        'invitation_resent' => 'Invitation resent to :email',
        'invitation_revoked' => 'Invitation to :email revoked',
        'invitation_email_failed' => 'The invitation for :email was created, but the email could not be sent. Use Resend to try again.',
        'member_removed' => ':name was removed from this workspace',
    ],
    'invitation_email' => [
        'subject' => 'You have been invited to join :workspace on Recruiter Labs',
        'greeting' => 'Hello!',
        'line_1' => ':inviter invited you to join the :workspace workspace on Recruiter Labs.',
        'action' => 'Accept invitation',
        'expires' => 'This invitation expires on :date.',
        'unknown_inviter' => 'A workspace owner',
        'line_2' => 'If you were not expecting this invitation, you can safely ignore this email.',
    ],
    'errors' => [
        'already_member' => ':email already has access to this workspace as :role.',
        'invitation_already_pending' => ':email already has a pending invitation to this workspace. Resend that invitation instead of creating a new one.',
        'invitation_revoked_cannot_resend' => 'This invitation was revoked. Invite this person again to give them access.',
        'invitation_already_accepted' => ':email already accepted this invitation and is an active member of this workspace.',
        'invitation_expired_cannot_accept' => 'This invitation has expired. Ask a workspace owner to send you a new one.',
        'invitation_revoked_cannot_accept' => 'This invitation is no longer valid. Ask a workspace owner to invite you again.',
        'invitation_already_used' => 'This invitation has already been used and cannot be accepted again.',
        'invitation_email_mismatch' => 'This invitation was sent to a different email address. You are signed in as :email. Sign in with the invited address to accept it.',
        'invitation_email_not_verified' => 'Verify :email before joining this workspace. Once your email is confirmed, open this invitation again.',
        'owner_cannot_be_removed' => 'The workspace owner cannot be removed. Every workspace must keep an owner.',
    ],
];
