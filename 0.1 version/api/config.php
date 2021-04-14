<?php
$CONFIG = array(
    # default database
    0 => array(
        "hostname" => '127.0.0.1',
        "username" => 'root',
        'password' => 'root',
        'database' => 'petshop',
        'hostport' => '3306',
        'dbms' => 'mysql',
        'pconnect' => false,
        'charset' => 'utf8',
        'DB_DEBUG' => true,
    )
);
define('CONFIG', $CONFIG);
