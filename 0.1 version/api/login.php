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

    // search data from database
    $result = $model->where(["name"=>$name,"password"=>md5($password)])->find();

    if($result){
        $token = md5(time().$result["id"]);
        $model->where(["id"=>$result["id"]])->save(["token"=>$token]);
        $result["token"] = $token;
        jsonResponse(0,"Success",$result);
    }else{
        jsonResponse(1,"Incorrect nickname or password");
    }
} else {
    jsonResponse(1, "Please fill in your nickname and password");
}