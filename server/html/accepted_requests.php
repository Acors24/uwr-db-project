<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accepted transport requests</title>
    <link rel="stylesheet" href="./css/style.css">
</head>

<?php

require_once "../common/definitions.php";

session_start();

if (!isset($_SESSION["email"]) || !$_SESSION["is_staff"])
    header("Location: login.php");

function load_accepted()
{
    require "../credentials.php";

    $con = pg_connect("host=$PG_HOST port=$PG_PORT dbname=$PG_DATABASE user=$PG_USERNAME password=$PG_PASSWORD");

    if ($con === false)
        return Result::ConnectionFailed;

    $column_names = ["Station", "Item", "Amount", "Time of arrival", "Status"];

    $query = "SELECT station.id FROM station JOIN \"user\" ON at_station = station.id WHERE \"user\".id = $1";
    $res = pg_query_params($con, $query, [$_SESSION["user_id"]]);
    if ($res === false) {
        pg_close($con);
        return Result::QueryFailed;
    }
    $row = pg_fetch_assoc($res);
    $to_station_id = $row["id"];

    $query =
        "SELECT acc.id, acc.request_id, station.name, item.name, acc.amount, acc.accepted_at, acc.status
        FROM transport_accept AS acc
        JOIN transport_request AS req ON acc.request_id = req.id
        JOIN station ON req.from_station_id = station.id
        JOIN item ON req.item_id = item.id
        WHERE acc.to_station_id = $1
        ORDER BY acc.accepted_at DESC";
    $res = pg_query_params($con, $query, [$to_station_id]);
    if ($res === false) {
        pg_close($con);
        return Result::QueryFailed;
    }

    echo "<table>";
    echo "<thead><tr>";
    foreach ($column_names as $col) {
        echo "<th>$col</th>";
    }
    echo "</thead>";

    echo "<tbody>";
    while ($row = pg_fetch_row($res)) {
        $id = $row[0];
        $req_id = $row[1];
        $station_name = $row[2];
        $item_name = $row[3];
        $amount = $row[4];
        $accepted_at = preg_split("(\.)", $row[5])[0];
        $status = $row[6];
        echo "<tr>";
        echo "<td>$station_name</td>";
        echo "<td>$item_name</td>";
        echo "<td>$amount</td>";
        echo "<td>$accepted_at</td>";
        $status_msg = null;
        switch ($status) {
            case "0":
                $status_msg = "Accepted";
                break;
            case "2":
                $status_msg = "Rejected";
                break;

            default:
                # code...
                break;
        }
        if ($status_msg)
            echo "<td>$status_msg</td>";
        else
            echo
                "<td>
                    <form action='' method='post'>
                        <div>
                            <input type='hidden' name='id' value='$id'>
                            <input type='submit' name='decision' value='Accept'>
                            <input type='submit' name='decision' value='Reject'>
                        </div>
                    </form>
                </td>";
        echo "</tr>";
    }
    echo "</table>";

    pg_close($con);
    return Result::OK;
}

function process_decision() {
    if ($_SERVER["REQUEST_METHOD"] !== "POST")
        return Result::OK;

    if (!isset($_POST["decision"]) || !isset($_POST["id"]))
        return Result::MissingValues;

    $decision = $_POST["decision"];
    $id = $_POST["id"];

    require "../credentials.php";

    $con = pg_connect("host=$PG_HOST port=$PG_PORT dbname=$PG_DATABASE user=$PG_USERNAME password=$PG_PASSWORD");

    if ($con === false)
        return Result::ConnectionFailed;

    $query = "UPDATE transport_accept SET status = $1 WHERE id = $2";
    $new_status = 1;
    switch ($decision) {
        case 'Accept':
            $new_status = 0;
            break;
        case 'Reject':
            $new_status = 2;
            break;
        
        default:
            # code...
            break;
    }
    $res = pg_query_params($con, $query, [$new_status, $id]);
    if ($res === false) {
        pg_close($con);
        return Result::QueryFailed;
    }

    pg_close($con);
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

        <h1>Accepted transport requests</h1>

        <input type="search" name="filter" id="input_filter" placeholder="e.g. Station A">

        <?php
        $result = process_decision();   
        $result2 = load_accepted();
        
        if ($result !== Result::OK)
            echo "<div class='error'>" . get_message($result) . "</div>";
        
        if ($result2 !== Result::OK)
            echo "<div class='error'>" . get_message($result2) . "</div>";
        ?>

    </main>
    <script src="js/filter.js"></script>
</body>

</html>