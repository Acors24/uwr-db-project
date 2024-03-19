<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging out...</title>
    <link rel="stylesheet" href="./css/style.css">
</head>

<?php

session_start();
session_unset();
session_destroy();
session_write_close();
setcookie(session_name(),'',0,'/');
session_regenerate_id(true);
header("Location: login.php");

?>

<body>
    <main>
        <h1>Logging out...</h1>
    </main>
</body>

</html>