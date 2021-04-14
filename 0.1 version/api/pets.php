<?php
// import mysql config
require_once "config.php";

// import mysql connector
require_once "database.php";

// parse param
$id = I("get.id");

// init search condition
$map = [];

if (!empty($id)) $map["id"] = $id;

// choose table
$model = M("pets");

// search data from database
$result = $model->where($map)->find();

if ($result) {
    $model->where($map)->setInc("views",1);
    jsonResponse(0, "Success", $result);
} else {
    jsonResponse(1, "Incorrect nickname or password");
}