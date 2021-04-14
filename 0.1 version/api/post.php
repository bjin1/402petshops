<?php
// import mysql config
require_once "config.php";

// import mysql connector
require_once "database.php";

// parse param
$data = I("post.");

// choose table
$model = M("pets");

// search data from database
$result = $model->add($data);

if ($result) {
    jsonResponse(0, "Success", $result);
} else {
    jsonResponse(1, "Incorrect nickname or password");
}