<?php
// import mysql config
require_once "config.php";

// import mysql connector
require_once "database.php";

// parse param
$uid = I("post.uid");

$pid = I("post.pid");

$price = I("post.price");

$address = I("post.address");

$phone = I("post.phone");

// choose table
$model = M("orders");

// search data from database
$result = $model->add([
    "uid" => $uid,
    "pid" => $pid,
    "price"=>$price,
    "address"=>$address,
    "phone"=>$phone
]);

if ($result) {
    jsonResponse(0, "Success", $result);
} else {
    jsonResponse(1, "Incorrect");
}