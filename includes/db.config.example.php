<?php
/*
 * db.config.example.php - Copy to db.config.php and set your MySQL credentials.
 *
 * Local XAMPP: user root, empty password, dbname earth_odyssey (see below).
 * cPanel live: NEVER use root. In cPanel → MySQL Databases, create a DB + user,
 * grant All Privileges, then use the prefixed names shown there, e.g.:
 *   dbname => cpaneluser_earth_odyssey
 *   user   => cpaneluser_site
 *   pass   => (the password you set in cPanel)
 * Create this file on the server only — do not upload your local db.config.php.
 */
return [
    'host'    => 'localhost',
    'dbname'  => 'earth_odyssey',
    'user'    => 'root',
    'pass'    => '',
    'charset' => 'utf8mb4',
];
