<?php
/*
 * db.config.example.php - Copy to db.config.php and set your MySQL credentials.
 *
 * Local XAMPP: user root, empty password, dbname earth_odyssey (the defaults below).
 * cPanel live: NEVER use root. In cPanel → MySQL Databases, create a DB + user,
 * grant All Privileges, then use the prefixed names shown there, e.g.:
 *   dbname => cpaneluser_dbname
 *   user   => cpaneluser_dbuser
 *   pass   => (the password you set in cPanel)
 * Create db.config.php on the server only — do not upload your local XAMPP file.
 * Never put a real password in this example file; it is committed to git.
 */
return [
    'host'    => 'localhost',
    'dbname'  => 'earth_odyssey',
    'user'    => 'root',
    'pass'    => '',
    'charset' => 'utf8mb4',
];
