<?php
// import mysql config
require_once "config.php";

// import mysql connector
require_once "database.php";

// choose table
$model = M("pets");

// search data from database
$result = $model->group('breed')->select();

if ($result) {
    jsonResponse(0, "Success", $result);
} else {
    jsonResponse(1, "Incorrect");
}