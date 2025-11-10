<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email Language Lines - English
    |--------------------------------------------------------------------------
    |
    | The following language lines are used in email templates
    |
    */

    // Password Setup Email
    'password_setup' => [
        'subject' => 'Set Your Password – :org_name',
        'title' => 'Password Setup',
        'greeting' => 'Welcome to **:org_name**\'s 360 evaluation system!',
        'invitation' => 'You have been invited to the system with the email address **:email**.',
        'action_text' => 'To log in for the first time, click the button below and set your password:',
        'button' => 'Set Password',
        'expires' => 'This link is valid until **:expires_at**.',
        'ignore' => 'If you did not initiate this, please ignore this email.',
        'salutation' => 'Best regards,',
    ],

    // Password Reset Email
    'password_reset' => [
        'subject' => 'Password Reset – :org_name',
        'title' => 'Password Reset',
        'intro' => 'A password reset was requested for the **:email** account in the **:org_name** system.',
        'action_text' => 'Click the button below to set a new password:',
        'button' => 'Reset Password',
        'expires' => 'This link is valid until **:expires_at**.',
        'warning' => 'If you did not request this, please contact your company admin immediately.',
        'salutation' => 'Best regards,',
    ],

    // Email Verification Code
    'verification_code' => [
        'subject' => 'Login Verification Code',
        'greeting' => 'Dear :user_name!',
        'intro' => 'Your verification code for logging in:',
        'code_label' => 'Verification code:',
        'expires' => 'This code is valid for 10 minutes.',
        'warning' => 'If you did not attempt to log in, please ignore this email.',
        'salutation' => 'Best regards,',
        'team' => 'The Quarma360 Team',
    ],

    // Assessment Started Email
    'assessment_started' => [
        'subject' => 'Assessment Period Started – :org_name',
        'title' => 'Assessment Period Has Begun',
        'greeting' => 'Dear :name!',
        'intro' => 'A new assessment period has started in the **:org_name** organization.',
        'deadline_info' => 'Completion deadline: **:deadline**',
        'action_text' => 'Log in to complete your assessments:',
        'button' => 'Login',
        'reminder' => 'Please complete the assessments before the deadline.',
        'salutation' => 'Best regards,',
    ],

    // Assessment Closed Email
    'assessment_closed' => [
        'subject' => 'Assessment Period Closed – :org_name',
        'title' => 'Assessment Period Completed',
        'greeting' => 'Dear :name!',
        'intro' => 'The assessment period has been closed in the **:org_name** organization.',
        'results_ready' => 'Your results are now available in the system.',
        'action_text' => 'Log in to view your results:',
        'button' => 'View Results',
        'reminder' => 'You can find detailed breakdowns of your results in the system.',
        'salutation' => 'Best regards,',
    ],

    // Ticket Notification Email
    'ticket_notification' => [
        'subject_new' => 'New Support Ticket Created – #:ticket_id',
        'subject_update' => 'Support Ticket Updated – #:ticket_id',
        'title_new' => 'New Support Ticket',
        'title_update' => 'Support Ticket Updated',
        'greeting' => 'Dear :name!',
        'new_ticket_intro' => 'A new support ticket has been created. Ticket ID: **#:ticket_id**',
        'update_intro' => 'Your support ticket has been updated. Ticket ID: **#:ticket_id**',
        'ticket_title' => 'Subject',
        'status' => 'Status',
        'priority' => 'Priority',
        'conversation' => 'Conversation History',
        'action_text' => 'Log in to view the full conversation:',
        'button' => 'View Ticket',
        'salutation' => 'Best regards,',
    ],

    // Payment Pending Email
    'payment_pending' => [
        'subject' => 'Payment Pending – :org_name',
        'title' => 'Payment Required',
        'greeting' => 'Dear :name!',
        'intro' => 'There is an outstanding payment for the **:org_name** organization account.',
        'assessment_info' => 'Assessment period ID: **#:assessment_id**',
        'amount' => 'Amount Due',
        'created' => 'Created',
        'action_text' => 'Log in to process the payment:',
        'button' => 'Pay Now',
        'note' => 'Payment is required to close the assessment period.',
        'salutation' => 'Best regards,',
    ],

    // Payment Success Email
    'payment_success' => [
        'subject' => 'Payment Successful – :org_name',
        'title' => 'Payment Successfully Completed',
        'greeting' => 'Dear :name!',
        'intro' => 'Payment has been successfully completed for the **:org_name** organization.',
        'amount' => 'Amount Paid',
        'invoice_number' => 'Invoice Number',
        'paid_at' => 'Payment Date',
        'processing' => 'Processing',
        'invoice_ready' => 'You can download your invoice by clicking the button below:',
        'download_button' => 'Download Invoice',
        'thank_you' => 'Thank you for your payment!',
        'salutation' => 'Best regards,',
    ],

    // Assessment Progress Email (Daily Reminder)
    'assessment_progress' => [
        'subject' => 'Assessment Status – :org_name',
        'title' => 'Assessment Period Status',
        'greeting' => 'Dear :name!',
        'intro' => 'Daily reminder about the current assessment period in the **:org_name** organization.',
        'completion_status' => 'Completion Status',
        'assessments_completed' => 'Assessments Completed',
        'rankings_completed' => 'Rankings Completed',
        'deadline' => 'Deadline',
        'payment_warning' => 'Payment Warning',
        'payment_blocked' => 'An outstanding payment (:amount) is blocking the assessment period closure. Please process the payment.',
        'action_text' => 'Log in to view details:',
        'button' => 'Login',
        'salutation' => 'Best regards,',
    ],


    // Common elements
    'footer' => [
        'copyright' => '© :year :app_name. All rights reserved.',
    ],
];