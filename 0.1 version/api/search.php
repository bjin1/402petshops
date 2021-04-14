<?php
// import mysql config
require_once "config.php";

// import mysql connector
require_once "database.php";

// parse param
$type = I("get.type");

$breed = I("get.breed");

$keyword = I("get.keyword");

$page = I("get.page");

$size = I("get.size");

// init search condition
$map = [];

if (!empty($type)) $map["type"] = $type;

if (!empty($breed)) $map["breed"] = $breed;

if (!empty($keyword)) $map["title|breed|oname|area"] = ["LIKE","%".$keyword."%"];

// choose table
$model = M("pets");

// search data from database
$result = $model->where($map)->page($page,$size)->select();

if ($result) {
    jsonResponse(0, "Success", $result);
} else {
    jsonResponse(1, "Incorrect");
}