<?php

return [

    'backup' => [
        /*
         * The name of this application. You can use this name to monitor
         * the backups.
         */
        'name' => 'quarma360-staging', // CHANGE to 'quarma360-production' for production

        'source' => [
            'files' => [
                /*
                 * The list of directories and files that will be included in the backup.
                 */
                'include' => [
                    base_path('public/uploads/profile_pics'), // ✅ CHANGED: Only profile pictures
                ],

                /*
                 * These directories and files will be excluded from the backup.
                 *
                 * Directories used by the backup process will automatically be excluded.
                 */
                'exclude' => [
                    // ✅ CHANGED: Not needed anymore
                ],

                /*
                 * Determines if symlinks should be followed.
                 */
                'follow_links' => false,

                /*
                 * Determines if it should avoid unreadable folders.
                 */
                'ignore_unreadable_directories' => false,

                /*
                 * This path is used to make directories in resulting zip-file relative
                 * Set to `null` to include complete absolute path
                 * Example: base_path()
                 */
                'relative_path' => null,
            ],

            /*
             * The names of the connections to the databases that should be backed up
             * MySQL, PostgreSQL, SQLite and Mongo databases are supported.
             */
            'databases' => [
                env('DB_CONNECTION', 'mysql'),
            ],
        ],

        /*
         * The database dump can be compressed to decrease disk space usage.
         */
        'database_dump_compressor' => null,

        /*
         * If specified, the database dumped file name will contain a timestamp (e.g.: 'Y-m-d-H-i-s').
         */
        'database_dump_file_timestamp_format' => null,

        /*
         * The base of the dump filename, either 'database' or 'connection'
         */
        'database_dump_filename_base' => 'database',

        /*
         * The file extension used for the database dump files.
         */
        'database_dump_file_extension' => '',

        'destination' => [
            /*
             * The compression algorithm to be used for creating the zip archive.
             */
            'compression_method' => ZipArchive::CM_DEFAULT,

            /*
             * The compression level corresponding to the used algorithm; an integer between 0 and 9.
             */
            'compression_level' => 9,

            /*
             * The filename prefix used for the backup zip file.
             */
            'filename_prefix' => '',

            /*
             * The disk names on which the backups will be stored.
             */
            'disks' => [
                's3', // ✅ CHANGED: Upload to S3 instead of local
            ],
        ],

        /*
         * The directory where the temporary files will be stored.
         */
        'temporary_directory' => storage_path('app/backup-temp'),

        /*
         * The password to be used for archive encryption.
         * Set to `null` to disable encryption.
         */
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),

        /*
         * The encryption algorithm to be used for archive encryption.
         * You can set it to `null` or `false` to disable encryption.
         */
        'encryption' => 'default',

        /*
         * The number of attempts, in case the backup command encounters an exception
         */
        'tries' => 1,

        /*
         * The number of seconds to wait before attempting a new backup if the previous try failed
         * Set to `0` for none
         */
        'retry_delay' => 0,
    ],

    /*
     * You can get notified when specific events occur.
     */
    'notifications' => [
        'notifications' => [
            \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification::class => ['mail'],
        ],

        /*
         * Here you can specify the notifiable to which the notifications should be sent.
         */
        'notifiable' => \Spatie\Backup\Notifications\Notifiable::class,

        'mail' => [
            'to' => env('BACKUP_NOTIFICATION_EMAIL', 'your-email@quarma360.com'), // ✅ CHANGED: Use env variable

            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Example'),
            ],
        ],

        'slack' => [
            'webhook_url' => '',
            'channel' => null,
            'username' => null,
            'icon' => null,
        ],

        'discord' => [
            'webhook_url' => '',
            'username' => '',
            'avatar_url' => '',
        ],
    ],

    /*
     * Here you can specify which backups should be monitored.
     */
    'monitor_backups' => [
        [
            'name' => 'quarma360-staging', // ✅ CHANGED: Specific name (change for production)
            'disks' => ['s3'], // ✅ CHANGED: Monitor S3 instead of local
            'health_checks' => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],
    ],

    'cleanup' => [
        /*
         * The strategy that will be used to cleanup old backups.
         */
        'strategy' => \Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy::class,

        'default_strategy' => [
            /*
             * The number of days for which backups must be kept.
             */
            'keep_all_backups_for_days' => 15, // ✅ CHANGED: Keep all for 15 days

            /*
             * After the "keep_all_backups_for_days" period is over, delete all.
             */
            'keep_daily_backups_for_days' => 0, // ✅ CHANGED: Don't keep daily after 15 days
            'keep_weekly_backups_for_weeks' => 0, // ✅ CHANGED: Don't keep weekly
            'keep_monthly_backups_for_months' => 0, // ✅ CHANGED: Don't keep monthly
            'keep_yearly_backups_for_years' => 0, // ✅ CHANGED: Don't keep yearly

            /*
             * After cleaning up the backups remove the oldest backup until
             * this amount of megabytes has been reached.
             */
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],

        /*
         * The number of attempts, in case the cleanup command encounters an exception
         */
        'tries' => 1,

        /*
         * The number of seconds to wait before attempting a new cleanup if the previous try failed
         */
        'retry_delay' => 0,
    ],

];