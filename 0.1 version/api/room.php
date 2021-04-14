<?php
// import mysql config
require_once "config.php";

// import mysql connector
require_once "database.php";

// parse param
$name = I("get.name");

// init search condition
$map = [];

if (!empty($name)) $map["receiver"] = ["LIKE","%".$name."%"];

// choose table
$model = M("messages");

// search data from database
$result = $model->where($map)->group("receiver")->select();

if ($result) {
    jsonResponse(0, "Success", $result);
} else {
    jsonResponse(0, "No Results");
}