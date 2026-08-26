<?php

return [
    'meta' => [
        'title' => 'Workspace invitation',
        'description' => 'Join a Recruiter Labs workspace you were invited to.',
    ],

    'details' => [
        'workspace' => 'Workspace',
        'invited_by' => 'Invited by',
        'invited_email' => 'Invited email',
        'expires_at' => 'Valid until',
        'signed_in_as' => 'Signed in as',
        'unknown_inviter' => 'A workspace administrator',
    ],

    'states' => [
        'invalid' => [
            'title' => 'This invitation link is not valid',
            'description' => 'The link may have been mistyped or is no longer in use. Ask the person who invited you to send a new invitation.',
        ],
        'expired' => [
            'title' => 'This invitation has expired',
            'description' => 'Invitations to :workspace are valid for a limited time and this one is past its date. Ask the person who invited you to send it again.',
        ],
        'revoked' => [
            'title' => 'This invitation was cancelled',
            'description' => 'The invitation to :workspace was withdrawn by the workspace, so it can no longer be used. Ask them to invite you again if you still need access.',
        ],
        'accepted' => [
            'title' => 'This invitation has already been used',
            'description' => 'The invitation to :workspace was already accepted and cannot be used again. Ask the workspace for a new invitation if you need access.',
        ],
        'already_member' => [
            'title' => 'You already have access',
            'description' => 'You are already part of :workspace, so there is nothing left to accept. Go straight to the workspace.',
        ],
        'guest' => [
            'title' => 'You have been invited to :workspace',
            'description' => 'Sign in with the invited email address to accept, or create your account if you do not have one yet.',
        ],
        'email_mismatch' => [
            'title' => 'This invitation is for a different account',
            'description' => 'You are signed in as :email, which is not the account :workspace invited. Sign out and sign back in with the invited address to accept.',
        ],
        'email_unverified' => [
            'title' => 'Confirm your email address first',
            'description' => 'Before joining :workspace you need to confirm :email. Once it is confirmed, come back to this page to accept.',
        ],
        'acceptable' => [
            'title' => 'Join :workspace',
            'description' => 'Accepting adds you to the workspace as a Member. You keep access to any other workspace you already belong to.',
        ],
    ],

    'actions' => [
        'accept' => 'Accept invitation',
        'login' => 'Sign in',
        'register' => 'Create account',
        'verify' => 'Confirm email address',
        'workspace' => 'Go to workspace',
    ],

    'flash' => [
        'accepted' => 'You are now a member of :workspace.',
    ],

    'register' => [
        'email_locked' => 'This invitation is tied to this email address, so your account is created with it.',
    ],
];
