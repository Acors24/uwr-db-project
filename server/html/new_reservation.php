<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New reservation</title>
    <link rel="stylesheet" href="./css/style.css">
</head>

<?php

require_once "../common/definitions.php";

session_start();

if (!isset($_SESSION["email"]))
    header("Location: login.php");

require "../credentials.php";
$con = pg_connect("host=$PG_HOST port=$PG_PORT dbname=$PG_DATABASE user=$PG_USERNAME password=$PG_PASSWORD");
if ($con === false)
    return Result::ConnectionFailed;

define("STATIONS", pg_fetch_all(pg_query($con, "SELECT * FROM station ORDER BY id")));
define("ITEMS", pg_fetch_all(pg_query($con, "SELECT * FROM item ORDER BY id")));
define("EQUIPMENT", pg_fetch_all(pg_query($con, "SELECT * FROM equipment")));

function verify_stations()
{
    if (!isset($_POST["stations"]) || empty($_POST["stations"]))
        return Result::NoStation;

    foreach ($_POST["stations"] as $key => $value)
        if (is_string($value))
            $_POST["stations"][$key] = htmlspecialchars(trim($value)); foreach ($_POST["stations"] as $i => $value) {
        $ok = false;
        foreach (STATIONS as $j => $row)
            if ($value === $row["id"]) {
                $ok = true;
                break;
            }

        if (!$ok)
            return Result::InvalidStation;
    }

    return Result::OK;
}

function verify_items()
{
    $i = $_SESSION["reservation_data"]["station_index"];
    $id = $_SESSION["reservation_data"]["stations"][$i];

    $none = true;
    foreach (EQUIPMENT as $i => $row) {
        if ($row["station_id"] == $id) {
            $amount = $_POST[$row["item_id"]];
            if ($amount < 0)
                return Result::InvalidAmount;
            if ($row["available"] < $amount)
                return Result::ReservedAmountExceeded;
            if ($amount > 0)
                $none = false;
        }
    }

    if ($none)
        return Result::NoneReserved;

    $from = new DateTime($_POST["reserved_from"]);
    $to = new DateTime($_POST["reserved_to"]);
    $today = new DateTime();

    if ($from <= $today)
        return Result::InvalidDate;

    if ($to < $from)
        return Result::InvalidInterval;

    return Result::OK;
}

function reserve($con)
{
    pg_query($con, "BEGIN");
    $user_id = $_SESSION["user_id"];
    pg_insert($con, "reservation", ["user_id" => $user_id]);
    $result = pg_query_params($con, "SELECT id FROM reservation WHERE user_id = $1 ORDER BY id DESC LIMIT 1", [$user_id]);
    if ($result === false) {
        pg_query($con, "ROLLBACK");
        return Result::QueryFailed;
    }

    $result = pg_fetch_assoc($result);
    if ($result === false) {
        pg_query($con, "ROLLBACK");
        return Result::QueryFailed;
    }

    $reservation_id = $result["id"];
    foreach ($_SESSION["reservation_data"]["stations"] as $i => $station_id) {
        $reserved_from = $_SESSION["reservation_data"]["item_data"][$i]["reserved_from"];
        $reserved_to = $_SESSION["reservation_data"]["item_data"][$i]["reserved_to"];
        
        foreach ($_SESSION["reservation_data"]["item_data"][$i] as $item_id => $amount) {
            if (intval($item_id) == 0 || intval($amount) == 0)
            continue;
            
            $price = $_SESSION["reservation_data"]["item_data"]["prices"][$station_id][$item_id];
            $values = [
                "reservation_id" => $reservation_id,
                "station_id" => $station_id,
                "item_id" => intval($item_id),
                "amount" => intval($amount),
                "reserved_from" => $reserved_from,
                "reserved_to" => $reserved_to,
                "price" => $price
            ];
            $res = pg_insert($con, "reserved_equipment", $values);
            if ($res === false) {
                pg_query($con, "ROLLBACK");
                pg_close($con);
                return Result::QueryFailed;
            }
        }
    }
    pg_query($con, "COMMIT");

    return Result::OK;
}

if (isset($_SESSION["reservation_data"]["phase"]) && $_SESSION["reservation_data"]["phase"] == ReservationPhase::Success)
    unset($_SESSION["reservation_data"]);

$result = Result::OK;
if (isset($_SESSION["reservation_data"]) && !isset($_GET["reset"])) {
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        switch ($_SESSION["reservation_data"]["phase"]) {
            case ReservationPhase::StationSelect:
                $result = verify_stations();
                if ($result === Result::OK) {
                    $_SESSION["reservation_data"]["stations"] = $_POST["stations"];
                    $_SESSION["reservation_data"]["station_index"] = 0;
                    $_SESSION["reservation_data"]["phase"] = ReservationPhase::ItemSelect;
                    $_SESSION["reservation_data"]["item_data"] = [];
                }
                break;

            case ReservationPhase::ItemSelect:
                $result = verify_items();
                if ($result === Result::OK) {
                    $index = $_SESSION["reservation_data"]["station_index"];
                    $_SESSION["reservation_data"]["item_data"][$index] = $_POST;
                    $index = $_SESSION["reservation_data"]["station_index"] = $index + 1;
                    if ($index >= count($_SESSION["reservation_data"]["stations"])) {
                        $_SESSION["reservation_data"]["phase"] = ReservationPhase::Confirm;
                    }
                }
                break;

            case ReservationPhase::Confirm:
                if ($_SERVER["REQUEST_METHOD"] === "GET" || !empty($_POST)) {
                    break;
                }
                $result = reserve($con);
                if ($result === Result::OK) {
                    unset($_SESSION["reservation_data"]);
                    $_SESSION["reservation_data"]["phase"] = ReservationPhase::Success;
                }
                break;

            default:
                # code...
                break;
        }
    }
} else {
    $_SESSION["reservation_data"] = array("phase" => ReservationPhase::StationSelect);
    header("Location: new_reservation.php");
}

?>

<body>
    <?php include "../common/nav.php"; ?>
    <main>
        <h1>New reservation</h1>

        <?php

        switch ($_SESSION["reservation_data"]["phase"]) {
            case ReservationPhase::StationSelect:
                include "../reservation/station_select.php";
                break;

            case ReservationPhase::ItemSelect:
                include "../reservation/item_select.php";
                break;

            case ReservationPhase::Confirm:
                include "../reservation/confirm.php";
                break;

            case ReservationPhase::Success:
                include "../reservation/success.php";
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