<form action="" method="post">
    <div>
        <label for="select_user">Customer</label>
        <select name="user_id" id="select_user" required>
            <?php

            $res = pg_query($con, "SELECT id, first_name, last_name, email FROM \"user\"");

            while ($row = pg_fetch_assoc($res)) {
                $user_id = $row["id"];
                $fname = $row["first_name"];
                $lname = $row["last_name"];
                $email = $row["email"];
                echo "<option value='$user_id'>$fname $lname ($email)</option>";
            }

            ?>
        </select>
    </div>

    <div>
        <label for="select_end_station">End station</label>
        <select name="end_station" id="select_end_station" required>
            <?php

            $res = pg_query($con, "SELECT id, name, place FROM station");

            while ($row = pg_fetch_assoc($res)) {
                $id = $row["id"];
                $name = $row["name"];
                $place = $row["place"];
                $body = $row["body"];

                $bodies = [];
                $res2 = pg_query($con, "SELECT body FROM connection WHERE $id IN (station1_id, station2_id)");
                while ($row2 = pg_fetch_assoc($res2)) {
                    array_push($bodies, $row2["body"]);
                }
                $bodies = join(", ", $bodies);

                echo "<option value=\"$id\">$name, $place; $bodies</option>";
            }

            ?>
        </select>
    </div>

    <button type="submit">Next</button>
</form>