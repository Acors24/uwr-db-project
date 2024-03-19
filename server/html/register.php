<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="stylesheet" href="./css/style.css">
</head>

<?php

require_once "../common/definitions.php";

session_start();

function verify()
{
    foreach ($_POST as $key => $value)
        $_POST[$key] = htmlspecialchars(trim($value));

    if ($_POST["first_name"] == "")
        return Result::MissingFirstName;
    if ($_POST["last_name"] == "")
        return Result::MissingLastName;
    if ($_POST["phone_number"] == "")
        return Result::MissingPhoneNumber;
    if ($_POST["email"] == "")
        return Result::MissingEmail;
    if ($_POST["password"] == "")
        return Result::MissingPassword;
    if ($_POST["password_re"] == "")
        return Result::MissingPasswordRe;

    if ($_POST["password"] !== $_POST["password_re"])
        return Result::WrongPasswordRe;

    require "../credentials.php";

    $con = pg_connect("host=$PG_HOST port=$PG_PORT dbname=$PG_DATABASE user=$PG_USERNAME password=$PG_PASSWORD");

    if (!$con)
        return Result::ConnectionFailed;

    $res = pg_select($con, "user", ["email" => $_POST["email"]]);
    if ($res === false) {
        pg_close($con);
        return Result::QueryFailed;
    }

    if (count($res) !== 0) {
        pg_close($con);
        return Result::UnavailableEmail;
    }

    $_POST["password"] = password_hash($_POST["password"], PASSWORD_ARGON2ID);
    unset($_POST["password_re"]);
    $res = pg_insert($con, "user", $_POST);
    if ($res === false) {
        pg_close($con);
        return Result::QueryFailed;
    }

    pg_close($con);
    return Result::OK;
}


$result = Result::OK;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $result = verify();
    if ($result === Result::OK)
        header("Location: login.php");
}

?>

<body>
    <main>
        <form action="" method="post" class="login-form">
            <h1>Register</h1>
            <label for="input_first_name">First name</label>
            <input type="text" name="first_name" id="input_first_name" required>

            <label for="input_last_name">Last name</label>
            <input type="text" name="last_name" id="input_last_name" required>

            <label for="input_phone_number">Phone number</label>
            <input type="tel" name="phone_number" id="input_phone_number" required>

            <label for="input_email">Email address</label>
            <input type="email" name="email" id="input_email" required>

            <label for="input_password">Password</label>
            <input type="password" name="password" id="input_password" required>

            <label for="input_password_re">Repeat password</label>
            <input type="password" name="password_re" id="input_password_re" required>

            <button type="submit">Register</button>

            <?php
            if ($result !== Result::OK)
                echo "<div class='error'>" . get_message($result) . "</div>";
            ?>

            <a href="login.php">Return to login</a>
        </form>
    </main>
</body>

</html>