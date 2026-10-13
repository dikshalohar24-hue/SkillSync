<?php
session_start();
require_once "config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$message = "";
$message_type = "";

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

/* Process reschedule form */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $session_id = filter_input(
        INPUT_POST,
        "session_id",
        FILTER_VALIDATE_INT
    );

    $new_date = trim($_POST["session_date"] ?? "");
    $new_start = trim($_POST["start_time"] ?? "");
    $new_end = trim($_POST["end_time"] ?? "");

    /* Validate date format */
    $date_object = DateTime::createFromFormat("!Y-m-d", $new_date);
    $valid_date = $date_object &&
        $date_object->format("Y-m-d") === $new_date;

    /* Validate time format */
    $valid_start = preg_match(
        '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
        $new_start
    );

    $valid_end = preg_match(
        '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
        $new_end
    );

    if (
        !$session_id ||
        $session_id <= 0 ||
        !$valid_date ||
        !$valid_start ||
        !$valid_end
    ) {

        $message = "Please enter valid session details.";
        $message_type = "error";

    } elseif ($new_date < date("Y-m-d")) {

        $message = "Please select today or a future date.";
        $message_type = "error";

    } elseif ($new_start >= $new_end) {

        $message = "End time must be later than start time.";
        $message_type = "error";

    } elseif (
        $new_date === date("Y-m-d") &&
        $new_start <= date("H:i")
    ) {

        $message = "For today's session, please select a future start time.";
        $message_type = "error";

    } else {

        /* Verify that this is a scheduled session belonging to the user */
        $check = $conn->prepare(
            "SELECT teacher_id, learner_id, skill_id
             FROM `session`
             WHERE session_id = ?
             AND (teacher_id = ? OR learner_id = ?)
             AND status = 'Scheduled'"
        );

        $check->bind_param(
            "iii",
            $session_id,
            $user_id,
            $user_id
        );

        $check->execute();
        $check_result = $check->get_result();

        if ($check_result->num_rows !== 1) {

            $message = "Session not found or cannot be rescheduled.";
            $message_type = "error";
            $check->close();

        } else {

            $session = $check_result->fetch_assoc();

            $teacher_id = (int) $session["teacher_id"];
            $learner_id = (int) $session["learner_id"];
            $skill_id = (int) $session["skill_id"];

            $check->close();

            $other_user_id = ($user_id === $teacher_id)
                ? $learner_id
                : $teacher_id;

            /* Update only a session that is still scheduled */
            $update = $conn->prepare(
                "UPDATE `session`
                 SET session_date = ?,
                     start_time = ?,
                     end_time = ?
                 WHERE session_id = ?
                 AND (teacher_id = ? OR learner_id = ?)
                 AND status = 'Scheduled'"
            );

            $update->bind_param(
                "sssiii",
                $new_date,
                $new_start,
                $new_end,
                $session_id,
                $user_id,
                $user_id
            );

            if ($update->execute()) {

                /*
                 * MySQL may report zero changed rows if the submitted
                 * date and time are identical to the existing values.
                 */
                $update->close();

                /* Get skill name for notification */
                $skill_name = "Learning";

                $skill_stmt = $conn->prepare(
                    "SELECT skill_name
                     FROM skill
                     WHERE skill_id = ?"
                );

                $skill_stmt->bind_param("i", $skill_id);
                $skill_stmt->execute();

                $skill_result = $skill_stmt->get_result();

                if ($skill_result->num_rows === 1) {
                    $skill_row = $skill_result->fetch_assoc();
                    $skill_name = $skill_row["skill_name"];
                }

                $skill_stmt->close();

                /* Save notification for the other participant */
                $title = "Session Rescheduled";

                $notification_message =
                    "The session for " . $skill_name .
                    " is now scheduled for " . $new_date .
                    " from " . $new_start .
                    " to " . $new_end . ".";

                $reference_type = "session";

                $notification_stmt = $conn->prepare(
                    "INSERT INTO notification
                    (user_id, title, message, reference_type, reference_id)
                    VALUES (?, ?, ?, ?, ?)"
                );

                $notification_stmt->bind_param(
                    "isssi",
                    $other_user_id,
                    $title,
                    $notification_message,
                    $reference_type,
                    $session_id
                );

                if ($notification_stmt->execute()) {

                    $message = "Session details saved and the other participant has been notified.";
                    $message_type = "success";

                } else {

                    $message = "Session details were saved, but the notification could not be saved.";
                    $message_type = "error";

                }

                $notification_stmt->close();

            } else {

                $message = "Unable to update the session. Please try again.";
                $message_type = "error";
                $update->close();
            }
        }
    }
}

/* Fetch scheduled sessions for the current user */
$stmt = $conn->prepare(
    "SELECT
        s.session_id,
        s.session_date,
        s.start_time,
        s.end_time,
        s.mode,
        sk.skill_name,
        teacher.name AS teacher_name,
        learner.name AS learner_name
     FROM `session` s
     INNER JOIN skill sk
        ON s.skill_id = sk.skill_id
     INNER JOIN `user` teacher
        ON s.teacher_id = teacher.user_id
     INNER JOIN `user` learner
        ON s.learner_id = learner.user_id
     WHERE (s.teacher_id = ? OR s.learner_id = ?)
     AND s.status = 'Scheduled'
     ORDER BY s.session_date ASC, s.start_time ASC"
);

$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();

$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Reschedule Session | SkillSync</title>

    <link rel="stylesheet" href="assets/style.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fc;
            color: #263247;
        }

        nav {
            background: #253b80;
            color: white;
            padding: 16px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        nav h2 {
            margin: 0;
        }

        nav a {
            color: white;
            text-decoration: none;
            margin-left: 14px;
            font-size: 14px;
        }

        nav a:hover {
            text-decoration: underline;
        }

        .container {
            max-width: 1000px;
            margin: 35px auto;
            padding: 0 18px;
        }

        .hero {
            background: white;
            padding: 25px;
            border-radius: 13px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px #00000008;
        }

        .hero h1 {
            color: #253b80;
            margin-top: 0;
        }

        .hero p {
            color: #64748b;
            line-height: 1.6;
        }

        .message {
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .message.success {
            background: #dcfce7;
            color: #166534;
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
        }

        .session-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
        }

        .session-box {
            background: white;
            padding: 23px;
            border-radius: 13px;
            box-shadow: 0 4px 15px #0000000c;
            overflow-wrap: anywhere;
        }

        .session-box h3 {
            color: #253b80;
            margin-top: 0;
            font-size: 21px;
        }

        .session-box p {
            line-height: 1.6;
        }

        label {
            display: block;
            font-weight: bold;
            margin-top: 17px;
            margin-bottom: 7px;
        }

        input {
            display: block;
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 15px;
        }

        input:focus {
            outline: 2px solid #c7d2fe;
            border-color: #253b80;
        }

        button {
            width: 100%;
            padding: 13px;
            margin-top: 22px;
            background: #253b80;
            color: white;
            border: none;
            border-radius: 7px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #192c65;
        }

        .empty {
            background: white;
            padding: 35px 20px;
            text-align: center;
            border-radius: 12px;
            grid-column: 1 / -1;
        }

        .empty h3 {
            color: #253b80;
        }

        @media (max-width: 700px) {
            nav {
                flex-direction: column;
                align-items: flex-start;
            }

            nav a {
                display: inline-block;
                margin: 10px 10px 0 0;
            }

            .session-grid {
                grid-template-columns: 1fr;
            }

            .hero,
            .session-box {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

<nav>
    <h2>SkillSync</h2>

    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="sessions.php">My Sessions</a>
        <a href="session_history.php">Session History</a>
        <a href="notifications.php">Notifications</a>
        <a href="logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <div class="hero">
        <h1>📅 Reschedule Session</h1>

        <p>
            Choose a scheduled session and update its date or time.
            The other participant will receive a notification.
        </p>
    </div>

    <?php if ($message !== "") { ?>
        <div
            class="message <?php echo h($message_type); ?>"
            role="status"
        >
            <?php echo h($message); ?>
        </div>
    <?php } ?>

    <div class="session-grid">

        <?php if ($result->num_rows > 0) { ?>

            <?php while ($row = $result->fetch_assoc()) { ?>

                <div class="session-box">

                    <h3><?php echo h($row["skill_name"]); ?></h3>

                    <p>
                        <strong>Teacher:</strong>
                        <?php echo h($row["teacher_name"]); ?>
                    </p>

                    <p>
                        <strong>Learner:</strong>
                        <?php echo h($row["learner_name"]); ?>
                    </p>

                    <p>
                        <strong>Current Date:</strong>
                        <?php echo h($row["session_date"]); ?>
                    </p>

                    <p>
                        <strong>Current Time:</strong>
                        <?php echo h(substr($row["start_time"], 0, 5)); ?>
                        –
                        <?php echo h(substr($row["end_time"], 0, 5)); ?>
                    </p>

                    <p>
                        <strong>Mode:</strong>
                        <?php echo h($row["mode"] ?? "Not specified"); ?>
                    </p>

                    <form method="POST" action="reschedule_session.php">

                        <input
                            type="hidden"
                            name="session_id"
                            value="<?php echo (int) $row["session_id"]; ?>"
                        >

                        <label>New Date</label>

                        <input
                            type="date"
                            name="session_date"
                            min="<?php echo date("Y-m-d"); ?>"
                            value="<?php echo h($row["session_date"]); ?>"
                            required
                        >

                        <label>New Start Time</label>

                        <input
                            type="time"
                            name="start_time"
                            value="<?php echo h(substr($row["start_time"], 0, 5)); ?>"
                            required
                        >

                        <label>New End Time</label>

                        <input
                            type="time"
                            name="end_time"
                            value="<?php echo h(substr($row["end_time"], 0, 5)); ?>"
                            required
                        >

                        <button type="submit">
                            Save New Date &amp; Time
                        </button>

                    </form>

                </div>

            <?php } ?>

        <?php } else { ?>

            <div class="empty">
                <h3>No Scheduled Sessions</h3>
                <p>You currently have no sessions available to reschedule.</p>
                <a href="sessions.php">Go to My Sessions</a>
            </div>

        <?php } ?>

    </div>

</div>

</body>
</html>

<?php
$stmt->close();
?>