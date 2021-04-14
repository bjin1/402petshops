<?php
// import mysql config
require_once "config.php";

// import mysql connector
require_once "database.php";

// parse param
$uid = I("post.id");

// init search condition
$map = [];

if (!empty($uid)) $map["id"] = $uid;

// choose table
$model = M("user");

// search data from database
$result = $model->where($map)->find();

if ($result) {
    jsonResponse(0, "Success", $result);
} else {
    jsonResponse(1, "Incorrect");
}