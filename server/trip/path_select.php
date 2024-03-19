<form action="" method="post">
    <?php

    function load_paths() {
        $start_id = $_SESSION["trip_data"]["start_id"];
        $end_id = $_SESSION["trip_data"]["end_id"];

        global $con;
        $query =
            "WITH RECURSIVE Path(station1_id, station2_id) AS (
                SELECT station1_id, station2_id, distance, kayaking_time, ARRAY[station1_id::text, station2_id::text] AS path FROM connection
                UNION
                SELECT c.station1_id, p.station2_id, c.distance + p.distance, c.kayaking_time + p.kayaking_time, array_cat(ARRAY[c.station1_id::text], p.path)
                FROM connection c
                    JOIN Path p ON c.station2_id = p.station1_id
            ) SELECT * FROM Path WHERE station1_id = $1 AND station2_id = $2";
        $values = [$start_id, $end_id];
        $res = pg_query_params($con, $query, $values);
        if ($res === false) {
            return Result::QueryFailed;
        }

        echo "<table>";
        echo "<thead>";
        echo "<tr>";
        echo "<th>Start</th>";
        echo "<th>Via</th>";
        echo "<th>End</th>";
        echo "<th>Distance</th>";
        echo "<th>Total distance</th>";
        echo "<th>Swim time</th>";
        echo "<th>Total time</th>";
        echo "<th>Select</th>";
        echo "</tr>";
        echo "</thead>";

        echo "<tbody>";

        while ($row = pg_fetch_assoc($res)) {
            $path = $row["path"];
            $path = str_replace(["{", "}"], "", $path);
            $path_array = preg_split("(,)", $path);
            $n = count($path_array);

            $total_distance = 0;
            $total_time = 0;
            for ($i = 0; $i < $n - 1; $i++) { 
                $query =
                    "SELECT s1.name AS name1, s2.name AS name2, distance, kayaking_time
                    FROM connection
                        JOIN station AS s1 ON station1_id = s1.id
                        JOIN station AS s2 ON station2_id = s2.id
                    WHERE station1_id = $1 AND station2_id = $2";
                $res2 = pg_query_params($con, $query, [$path_array[$i], $path_array[$i + 1]]);
                if ($res2 === false)
                    return Result::QueryFailed;

                $row2 = pg_fetch_assoc($res2);
                $distance = intval($row2["distance"]);
                $kayaking_time = intval($row2["kayaking_time"]);
                $total_distance += $distance;
                $total_time += $kayaking_time;

                if ($i === 0) {
                    $start = $row2["name1"];

                    echo "<tr>";
                    echo "<td>$start</td>";
                    echo "<td></td>";
                    echo "<td></td>";
                    echo "<td></td>";
                    echo "<td></td>";
                    echo "<td></td>";
                    echo "<td></td>";
                    echo
                        "<td rowspan='$n'>
                            <input type='radio' name='path' value='$path'>
                        </td>";
                    echo "</tr>";
                }
                
                echo "<tr>";
                echo "<td></td>";
                if ($i !== $n - 2) {
                    $through = $row2["name2"];
                    echo "<td>$through</td>";
                    echo "<td></td>";
                } else {
                    $end = $row2["name2"];
                    echo "<td></td>";
                    echo "<td>$end</td>";
                }

                echo "<td>" . $distance / 1000 . " km</td>";
                echo "<td>" . $total_distance / 1000 . " km</td>";
                echo "<td>" . floor($kayaking_time / 60) . ":" . $kayaking_time % 60 . "</td>";
                echo "<td>" . floor($total_time / 60) . ":" . $total_time % 60 . "</td>";
                echo "</tr>";
            }
        }

        echo "</tbody>";

        echo "</table>";

        return Result::OK;
    }
    
    $result = load_paths();

    ?>

    <button type="submit">Next</button>

    <?php
    if ($result !== Result::OK)
        echo "<div class='error'>" . get_message($result) . "</div>";
    ?>
</form>