<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="./css/style.css">
</head>

<?php

require_once "../common/definitions.php";

session_start();

if (isset($_SESSION["email"]))
    header("Location: index.php");

if (!empty($_SESSION))
    header("Location: index.php");

function verify(&$row)
{
    foreach ($_POST as $key => $value)
        $_POST[$key] = htmlspecialchars(trim($value));

    if ($_POST["email"] == "")
        return Result::MissingEmail;
    if ($_POST["password"] == "")
        return Result::MissingPassword;

    require "../credentials.php";

    $con = pg_connect("host=$PG_HOST port=$PG_PORT dbname=$PG_DATABASE user=$PG_USERNAME password=$PG_PASSWORD");

    if ($con === false)
        return Result::ConnectionFailed;

    $res = pg_query_params($con, "SELECT * FROM \"user\" WHERE email = $1", array($_POST["email"]));
    if ($res === false) {
        pg_close($con);
        return Result::QueryFailed;
    }

    if (pg_num_rows($res) === 0) {
        pg_close($con);
        return Result::UnknownEmail;
    }

    $row = pg_fetch_assoc($res);
    if (!password_verify($_POST["password"], $row["password"])) {
        pg_close($con);
        return Result::WrongPassword;
    }

    pg_close($con);
    return Result::OK;
}


$result = Result::OK;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $row = null;
    $result = verify($row);
    if ($result === Result::OK) {
        $_SESSION["user_id"] = $row["id"];
        $_SESSION["first_name"] = $row["first_name"];
        $_SESSION["last_name"] = $row["last_name"];
        $_SESSION["email"] = $row["email"];
        $_SESSION["is_staff"] = $row["is_staff"] == "t";
        header("Location: index.php");
    }
}

?>

<body>
    <main>
        <form action="" method="post" class="login-form">
            <h1>Log in</h1>
            <label for="input_email">Email</label>
            <input type="email" name="email" id="input_email" required>

            <label for="input_password">Password</label>
            <input type="password" name="password" id="input_password" required>

            <button type="submit">Log in</button>

            <?php 
            if ($result !== Result::OK)
                echo "<div class='error'>" . get_message($result) . "</div>";
            ?>

            <a href="register.php">Register</a>
        </form>
    </main>
</body>

</html>