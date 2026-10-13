<?php

session_start();

require_once "config/db.php";

// Check login
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$message = "";

// Allowed days
$allowed_days = [
    "Monday",
    "Tuesday",
    "Wednesday",
    "Thursday",
    "Friday",
    "Saturday",
    "Sunday"
];

// Add availability
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $day = $_POST["day_of_week"] ?? "";
    $start_time = $_POST["start_time"] ?? "";
    $end_time = $_POST["end_time"] ?? "";

    // Validate day
    if (!in_array($day, $allowed_days, true)) {

        $message = "Please select a valid day.";

    // Validate time format
    } elseif (
        !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start_time) ||
        !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end_time)
    ) {

        $message = "Please enter valid start and end times.";

    // End time must be later
    } elseif ($start_time >= $end_time) {

        $message = "End time must be later than start time.";

    } else {

        // Check for overlapping availability on the same day
        $check = $conn->prepare(
            "SELECT availability_id
             FROM availability
             WHERE user_id = ?
               AND day_of_week = ?
               AND start_time < ?
               AND end_time > ?
             LIMIT 1"
        );

        $check->bind_param(
            "isss",
            $user_id,
            $day,
            $end_time,
            $start_time
        );

        $check->execute();
        $check_result = $check->get_result();

        if ($check_result->num_rows > 0) {

            $message = "This time overlaps with an existing availability.";

        } else {

            // Insert availability
            $stmt = $conn->prepare(
                "INSERT INTO availability
                (user_id, day_of_week, start_time, end_time)
                VALUES (?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "isss",
                $user_id,
                $day,
                $start_time,
                $end_time
            );

            if ($stmt->execute()) {
                $message = "Availability added successfully!";
            } else {
                $message = "Unable to add availability. Please try again.";
            }

            $stmt->close();
        }

        $check->close();
    }
}

// Get user's availability
$stmt = $conn->prepare(
    "SELECT availability_id, day_of_week, start_time, end_time
     FROM availability
     WHERE user_id = ?
     ORDER BY FIELD(
        day_of_week,
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Sunday'
     ), start_time"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Availability - SkillSync</title>

    <link rel="stylesheet" href="assets/style.css">

</head>

<body>

<nav>

    <h2>SkillSync</h2>

    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="profile.php">Profile</a>
        <a href="availability.php">Availability</a>
        <a href="logout.php">Logout</a>
    </div>

</nav>

<div class="container">

    <div class="form-box">

        <h1>My Availability</h1>

        <p>
            Select the days and times when you are available
            for learning or teaching.
        </p>

        <?php if ($message !== ""): ?>

            <div class="message">
                <?= htmlspecialchars(
                    $message,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="availability.php">

            <label for="day_of_week">Day</label>

            <select
                name="day_of_week"
                id="day_of_week"
                required
            >

                <option value="">Select Day</option>
                <option value="Monday">Monday</option>
                <option value="Tuesday">Tuesday</option>
                <option value="Wednesday">Wednesday</option>
                <option value="Thursday">Thursday</option>
                <option value="Friday">Friday</option>
                <option value="Saturday">Saturday</option>
                <option value="Sunday">Sunday</option>

            </select>

            <label for="start_time">Start Time</label>

            <input
                type="time"
                name="start_time"
                id="start_time"
                required
            >

            <label for="end_time">End Time</label>

            <input
                type="time"
                name="end_time"
                id="end_time"
                required
            >

            <button type="submit">
                Add Availability
            </button>

        </form>

    </div>

    <div class="cards">

        <?php if ($result->num_rows > 0): ?>

            <?php while ($row = $result->fetch_assoc()): ?>

                <div class="card">

                    <h3>
                        <?= htmlspecialchars(
                            $row["day_of_week"],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </h3>

                    <p>
                        <strong>Available Time:</strong><br>

                        <?= date(
                            "h:i A",
                            strtotime($row["start_time"])
                        ) ?>

                        -

                        <?= date(
                            "h:i A",
                            strtotime($row["end_time"])
                        ) ?>
                    </p>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="card">

                <h3>No Availability Added</h3>

                <p>
                    Add your available days and times using the form above.
                </p>

            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>