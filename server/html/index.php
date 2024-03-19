<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your reservations</title>
    <link rel="stylesheet" href="./css/style.css">
</head>

<?php

require_once "../common/definitions.php";

session_start();

if (!isset($_SESSION["email"]))
    header("Location: login.php");

function load_reservations() {
    require "../credentials.php";

    $con = pg_connect("host=$PG_HOST port=$PG_PORT dbname=$PG_DATABASE user=$PG_USERNAME password=$PG_PASSWORD");

    if ($con === false)
        return Result::ConnectionFailed;

    $column_names = ["From", "To", "Reserved", "Status", "Details"];
    
    $query =
        "SELECT MIN(reserved_from) \"from\", MAX(reserved_to) \"to\", res.reserved_at, res.status, res.id
        FROM reservation AS res
            JOIN reserved_equipment AS res_eq ON res.id = res_eq.reservation_id
        WHERE user_id = $1
        GROUP BY res.id
        ORDER BY MIN(reserved_from) DESC";
    $res = pg_query_params($con, $query, [$_SESSION["user_id"]]);
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
    while ($row = pg_fetch_assoc($res)) {
        $reserved_from = $row["from"];
        $reserved_to = $row["to"];
        $reserved_at = $row["reserved_at"];
        $status = $row["status"];
        $res_id = $row["id"];

        $timestamp_from = (new DateTime($reserved_from))->getTimestamp();
        $timestamp_to = (new DateTime($reserved_to))->getTimestamp();
        $class = "";
        $status_msg = "Confirmed";
        switch ($status) {
            case "1":
                $class = "pending";
                $status_msg = "Pending";
                break;
            
            case "2":
                $class = "rejected";
                $status_msg = "Rejected";
                break;
            
            default:
                break;
        }
        if ($timestamp_to < time())
            $class = "expired";
        else if ($timestamp_from < time() && $status == "0")
            $class = "ongoing";
        else if ($status == 2)
            $class = "rejected";
        echo "<tr class=\"$class\">";
        $reserved_from = preg_split("( )", $reserved_from)[0];
        echo "<td>$reserved_from</td>";
        $reserved_to = preg_split("( )", $reserved_to)[0];
        echo "<td>$reserved_to</td>";
        $reserved_at = preg_split("(\.)", $reserved_at)[0];
        echo "<td>$reserved_at</td>";
        echo "<td>$status_msg</td>";
        echo
            "<td>
                <a href='reservation_details.php?res_id=$res_id'>
                    <input type='button' value='Details'>
                </a>
            </td>";
        echo "</tr>";
    }
    echo "</tbody>";
    echo "</table>";

    return Result::OK;
}
?>

<body>
    <?php include "../common/nav.php"; ?>
    <main>

        <h1>Your reservations</h1>
        
        <a href="new_reservation.php"><button>New reservation</button></a>
        <input type="search" name="filter" id="input_filter" placeholder="e.g. Confirmed">

        <?php

        $result = load_reservations();

        if ($result !== Result::OK)
            echo "<div class='error'>" . get_message($result) . "</div>";

        ?>
    </main>
    <script src="js/filter.js"></script>
</body>

</html>