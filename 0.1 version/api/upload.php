<?php
// import mysql config
require_once "config.php";

// import mysql connector
require_once "database.php";

if ($_FILES) {
    $ext = explode(".",$_FILES["file"]["name"])[1];
    $filename = md5(time().$_FILES["file"]["name"]).".".$ext;
    $path = "../dist/images/".$filename;
    $result = move_uploaded_file($_FILES['file']['tmp_name'], $path);
    if($result){
        jsonResponse(0,"Success","/dist/images/".$filename);
    }else{
        jsonResponse(1,"Upload Fail");
    }
}else{
    jsonResponse(1,"File empty!");
}