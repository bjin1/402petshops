<?php
// import mysql config
require_once "config.php";

// import mysql connector
require_once "database.php";

// parse param
$name = I("post.name");

$password = I("post.password");

if (!empty($name) && !empty($password)) {
    // choose table
    $model = M("user");

    $res = $model->where(["name"=>$name])->find();
    if($res){
        jsonResponse(1,"The account has been registered.");
    }
    // search data from database
    $result = $model->add(["name"=>$name,"password"=>md5($password),"header"=>"images/male-".rand(0,10).".png"]);

    if($result){
        jsonResponse(0,"Success",$result);
    }else{
        jsonResponse(1,"Incorrect nickname or password");
    }
} else {
    jsonResponse(1, "Please fill in your nickname and password");
}