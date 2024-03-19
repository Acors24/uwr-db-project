<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New trip</title>
    <link rel="stylesheet" href="./css/style.css">
</head>

<?php

require_once "../common/definitions.php";

session_start();

if (!isset($_SESSION["email"]) || !$_SESSION["is_staff"])
    header("Location: login.php");

require "../credentials.php";
$con = pg_connect("host=$PG_HOST port=$PG_PORT dbname=$PG_DATABASE user=$PG_USERNAME password=$PG_PASSWORD");
if ($con === false)
    return Result::ConnectionFailed;

function verify_stations() {
    if (!isset($_POST["end_station"]))
        return Result::NotBothEndStations;

    global $con;
    $query = "SELECT at_station FROM \"user\" WHERE id = $1";
    $res = pg_query_params($con, $query, [$_SESSION["user_id"]]);
    if ($res === false)
        return Result::QueryFailed;

    $id1 = pg_fetch_assoc($res)["at_station"];
    $id2 = $_POST["end_station"];

    if ($id1 === $id2)
        return Result::SameStations;
    
    $query = "SELECT COUNT(*) FROM station WHERE id IN ($1, $2)";
    $res = pg_query_params($con, $query, [$id1, $id2]);
    if ($res === false)
        return Result::QueryFailed;

    if (pg_fetch_row($res)[0] != 2)
        return Result::InvalidStation;

    $query =
        "WITH RECURSIVE Path(station1_id, station2_id) AS (
            SELECT station1_id, station2_id, distance, kayaking_time, ARRAY[station1_id::text, station2_id::text] AS path FROM connection
            UNION
            SELECT c.station1_id, p.station2_id, c.distance + p.distance, c.kayaking_time + p.kayaking_time, array_cat(ARRAY[c.station1_id::text], p.path)
            FROM connection c
                JOIN Path p ON c.station2_id = p.station1_id
        ) SELECT * FROM Path WHERE station1_id = $1 AND station2_id = $2";
    $res = pg_query_params($con, $query, [$id1, $id2]);
    if ($res === false)
        return Result::QueryFailed;

    if (pg_num_rows($res) == 0)
        return Result::NoStationConnection;

    $_SESSION["trip_data"]["start_id"] = $id1;
    $_SESSION["trip_data"]["end_id"] = $id2;
    return Result::OK;
}

function add_trip() {
    $path = $_POST["path"];
    
    global $con;
    $query =
        "WITH RECURSIVE Path(station1_id, station2_id) AS (
            SELECT station1_id, station2_id, distance, kayaking_time, ARRAY[station1_id, station2_id] AS path FROM connection
            UNION
            SELECT c.station1_id, p.station2_id, c.distance + p.distance, c.kayaking_time + p.kayaking_time, array_cat(ARRAY[c.station1_id], p.path)
            FROM connection c
                JOIN Path p ON c.station2_id = p.station1_id
        ) SELECT * FROM Path WHERE path = ARRAY[$path]";
    $res = pg_query($con, $query);
    if ($res === false)
        return Result::QueryFailed;
    $row = pg_fetch_assoc($res);
    $distance = $row["distance"];
    $kayaking_time = $row["kayaking_time"];

    $user_id = $_SESSION["trip_data"]["user_id"];
    $query =
        "INSERT INTO trip VALUES(DEFAULT, $user_id, ARRAY[$path], $distance, DEFAULT, now() + interval '$kayaking_time minutes', NULL, DEFAULT)";
    $res = pg_query($con, $query);

    if ($res === false) 
        return Result::QueryFailed;

    return Result::OK;
}

if (!isset($_SESSION["trip_data"]["phase"]) || $_SESSION["trip_data"]["phase"] === TripCreationPhase::Success || isset($_GET["reset"])) {
    $_SESSION["trip_data"]["phase"] = TripCreationPhase::StationSelect;
    header("Location: new_trip.php");
}

$result = Result::OK;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    switch ($_SESSION["trip_data"]["phase"]) {
        case TripCreationPhase::StationSelect:
            $result = verify_stations();
            if ($result === Result::OK) {
                $_SESSION["trip_data"]["user_id"] = $_POST["user_id"];
                $_SESSION["trip_data"]["phase"] = TripCreationPhase::PathSelect;
            }
            break;
        
        case TripCreationPhase::PathSelect:
            $result = add_trip();
            if ($result === Result::OK) {
                $_SESSION["trip_data"] = [];
                $_SESSION["trip_data"]["phase"] = TripCreationPhase::Success;
            }
            break;
        
        default:
            # code...
            break;
    }
}

?>

<body>
    <?php include "../common/nav.php"; ?>
    <main>
        <div class="bread">
            <a href="staff_dashboard.php">Staff panel</a>
            <a href="trips.php">Customers' trips</a>
        </div>

        <h1>New trip</h1>

        <?php

        switch ($_SESSION["trip_data"]["phase"]) {
            case TripCreationPhase::StationSelect:
                require "../trip/station_select.php";
                break;
            
            case TripCreationPhase::PathSelect:
                require "../trip/path_select.php";
                break;
            
            case TripCreationPhase::Success:
                require "../trip/success.php";
                break;
            
            default:
                # code...
                break;
        }
        ?>

        <a href="?reset">Start over</a>

        <?php
        if ($result !== Result::OK)
            echo "<div class='error'>" . get_message($result) . "</div>";
        ?>

    </main>
</body>

</html>