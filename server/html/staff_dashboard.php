<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff panel</title>
    <link rel="stylesheet" href="./css/style.css">
</head>

<?php

require_once "../common/definitions.php";

session_start();

if (!$_SESSION["is_staff"])
    header("Location: index.php");

if (!isset($_SESSION["email"]))
    header("Location: login.php");

?>

<body>
    <?php include "../common/nav.php"; ?>
    <main>

        <h1>Staff panel</h1>

        <div class="wide_list">
            <a href="customer_reservations.php"><button>Customers' reservations</button></a>
            <a href="trips.php"><button>Customers' trips</button></a>
            <a href="transport_requests.php"><button>Transport requests</button></a>
        </div>

        <?php

        $result = Result::OK;
        if ($result !== Result::OK)
            echo "<div class='error'>" . get_message($result) . "</div>";

        ?>
    </main>
</body>

</html>