<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New transport request</title>
    <link rel="stylesheet" href="./css/style.css">
</head>

<?php

require_once "../common/definitions.php";

session_start();

if (!isset($_SESSION["email"]))
    header("Location: login.php");

require "../credentials.php";
$con = pg_connect("host=$PG_HOST port=$PG_PORT dbname=$PG_DATABASE user=$PG_USERNAME password=$PG_PASSWORD");

function load_items($station_id) {
    global $con;
    
    $query =
        "SELECT item.id, item.name, eq.available, eq.maximum
        FROM item
            JOIN equipment AS eq ON eq.item_id = item.id
        WHERE eq.station_id = $station_id";
    $res = pg_query($con, $query);
    if ($res === false)
        return Result::QueryFailed;

    while ($row = pg_fetch_assoc($res)) {
        $item_id = $row["id"];
        $item_name = $row["name"];
        $available = $row["available"];
        $maximum = $row["maximum"];
        echo "<option value='$item_id'>$item_name ($available out of $maximum)</option>";
    }

    return Result::OK;
}

function load_fields() {
    global $con;

    $user_id = $_SESSION["user_id"];
    $res = pg_query($con, "SELECT at_station FROM \"user\" WHERE id = $user_id");
    if ($res === false)
        return Result::QueryFailed;
    $station_id = pg_fetch_assoc($res)["at_station"];

    echo "<div>";
    echo "<label for='select_item'>Item: </label>";
    echo "<select name='item_id' id='select_item' required>";

    load_items($station_id);

    echo "</select>";

    echo "<input type='number' name='amount' id='input_amount'>";
    echo "<input type='hidden' name='from_station_id' value='$station_id'>";
    echo "</div>";

    return Result::OK;
}

function request() {
    if ($_SERVER["REQUEST_METHOD"] !== "POST")
        return Result::OK;

    if (!isset($_POST["item_id"], $_POST["amount"], $_POST["from_station_id"]))
        return Result::MissingValues;

    global $con;

    $from_station_id = $_POST["from_station_id"];
    $item_id = $_POST["item_id"];
    $amount = $_POST["amount"];
    $values = [
        "from_station_id" => $from_station_id,
        "item_id" => $item_id,
        "amount" => $amount
    ];

    pg_query($con, "BEGIN");
    $res = pg_insert($con, "transport_request", $values);
    if ($res === false) {
        pg_query($con, "ROLLBACK");
        return Result::QueryFailed;
    }

    $query =
        "UPDATE equipment
        SET available = available - $amount
        WHERE station_id = $from_station_id
            AND item_id = $item_id";
    $res = pg_query($con, $query);
    if ($res === false) {
        pg_query($con, "ROLLBACK");
        return Result::QueryFailed;
    }

    pg_query($con, "COMMIT");
    return Result::OK;
}

?>

<body>
    <?php include "../common/nav.php"; ?>
    <main>
        <div class="bread">
            <a href="staff_dashboard.php">Staff panel</a>
            <a href="transport_requests.php">Transport requests</a>
        </div>
        
        <h1>New transport request</h1>

        <form action="" method="post">
            <?php

            $result = request();
            $result2 = load_fields();

            ?>

            <button type="submit">OK</button>
        </form>

        <?php
        if ($result !== Result::OK)
            echo "<div class='error'>" . get_message($result) . "</div>";

        if ($result2 !== Result::OK)
            echo "<div class='error'>" . get_message($result2) . "</div>";
        ?>

    </main>
</body>

</html>