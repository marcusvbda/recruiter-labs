<?php

return [
    'label' => 'Email template',
    'plural_label' => 'Email templates',
    'navigation_label' => 'Email templates',
    'list_subheading' => 'Reusable messages for candidates. Write one once, then use it when sending a message or when a candidate enters a stage.',
    'create_subheading' => 'Name the template, write the subject and message, and use the variables to personalise it.',
    'edit_subheading' => 'Changes apply the next time this template is used. Messages already sent are never changed.',
    'sections' => [
        'details' => 'Template details',
        'details_description' => 'How this template is identified when someone picks it.',
        'content' => 'Message',
        'content_description' => 'The subject and body candidates receive, with variables resolved at send time.',
        'preview' => 'Preview',
        'preview_description' => 'How the message reads with example values in place of the variables.',
    ],
    'fields' => [
        'name' => 'Name',
        'name_helper' => 'Only recruiters see this. Candidates never see the template name.',
        'is_available' => 'Available for use',
        'is_available_helper' => 'Turn this off to keep the template without offering it when sending messages.',
        'subject' => 'Subject',
        'subject_placeholder' => 'Your application for {{ job.title }}',
        'body' => 'Message',
        'body_helper' => 'Use the variables below to personalise the message.',
        'updated_at' => 'Last updated',
    ],
    'preview' => [
        'subject' => 'Subject',
        'body' => 'Message',
        'empty' => 'Nothing yet',
    ],
    'empty_state' => [
        'heading' => 'No email templates yet',
        'description' => 'Create one to reuse the same message across candidates instead of rewriting it each time.',
    ],
];
