<?php
// import mysql config
require_once "config.php";

// import mysql connector
require_once "database.php";

// parse param
$name = I("post.name");

// init search condition
$map = [];

if (!empty($name)) $map["receiver"] = $name;

// choose table
$model = M("messages");

// search data from database
$result = $model->where($map)->select();

if ($result) {
    jsonResponse(0, "Success", $result);
} else {
    jsonResponse(1, "Incorrect");
}