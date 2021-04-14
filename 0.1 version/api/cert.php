<?php
// import mysql config
require_once "config.php";

// import mysql connector
require_once "database.php";

$uid = I("post.id");

$cafile = I("post.cafile");

// init search condition
$map = [];

if (!empty($uid)) $map["id"] = $uid;

// choose table
$model = M("user");

// search data from database
$result = $model->where($map)->save(["certfile"=>$cafile]);

if ($result) {
    jsonResponse(0, "Upload Success", $result);
} else {
    jsonResponse(1, "Upload Incorrect");
}