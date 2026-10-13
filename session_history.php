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

/* Update session status */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $session_id = (int) ($_POST["session_id"] ?? 0);
    $new_status = $_POST["status"] ?? "";

    if (
        $session_id <= 0 ||
        !in_array($new_status, ["Conducted", "Cancelled"], true)
    ) {
        $message = "Invalid session information.";
        $message_type = "error";

    } else {

        /* Verify that the current user is a participant */
        $check = $conn->prepare(
            "SELECT teacher_id, learner_id, skill_id
             FROM `session`
             WHERE session_id = ?
             AND (teacher_id = ? OR learner_id = ?)
             AND status = 'Scheduled'"
        );

        $check->bind_param("iii", $session_id, $user_id, $user_id);
        $check->execute();

        $session_result = $check->get_result();

        if ($session_result->num_rows !== 1) {

            $message = "Session not found, already updated, or you are not a participant.";
            $message_type = "error";
            $check->close();

        } else {

            $session_data = $session_result->fetch_assoc();
            $teacher_id = (int) $session_data["teacher_id"];
            $learner_id = (int) $session_data["learner_id"];
            $skill_id = (int) $session_data["skill_id"];

            $check->close();

            /* Update only a scheduled session */
            $update = $conn->prepare(
                "UPDATE `session`
                 SET status = ?
                 WHERE session_id = ?
                 AND (teacher_id = ? OR learner_id = ?)
                 AND status = 'Scheduled'"
            );

            $update->bind_param(
                "siii",
                $new_status,
                $session_id,
                $user_id,
                $user_id
            );

            if ($update->execute() && $update->affected_rows === 1) {

                $other_user_id = ($user_id === $teacher_id)
                    ? $learner_id
                    : $teacher_id;

                $update->close();

                /* Get skill name */
                $skill_name = "Learning";

                $skill_stmt = $conn->prepare(
                    "SELECT skill_name FROM skill WHERE skill_id = ?"
                );

                $skill_stmt->bind_param("i", $skill_id);
                $skill_stmt->execute();

                $skill_result = $skill_stmt->get_result();

                if ($skill_result->num_rows === 1) {
                    $skill_data = $skill_result->fetch_assoc();
                    $skill_name = $skill_data["skill_name"];
                }

                $skill_stmt->close();

                /* Prepare notification */
                if ($new_status === "Conducted") {
                    $title = "Session Completed";
                    $notification_message =
                        "Your session for " . $skill_name .
                        " has been marked as conducted.";
                } else {
                    $title = "Session Cancelled";
                    $notification_message =
                        "Your session for " . $skill_name .
                        " has been cancelled.";
                }

                $reference_type = "session";

                /* Notify the other participant */
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
                    $message = "Session status updated successfully! The other participant has been notified.";
                    $message_type = "success";
                } else {
                    $message = "Session updated, but the notification could not be saved.";
                    $message_type = "error";
                }

                $notification_stmt->close();

            } else {

                $message = "Session could not be updated. Please try again.";
                $message_type = "error";
                $update->close();
            }
        }
    }
}

/* Fetch all sessions for the logged-in participant */
$stmt = $conn->prepare(
    "SELECT
        se.session_id,
        se.session_date,
        se.start_time,
        se.end_time,
        se.mode,
        se.status,
        se.teacher_id,
        se.learner_id,
        sk.skill_name,
        teacher.name AS teacher_name,
        learner.name AS learner_name
     FROM `session` se
     INNER JOIN skill sk
        ON se.skill_id = sk.skill_id
     INNER JOIN `user` teacher
        ON se.teacher_id = teacher.user_id
     INNER JOIN `user` learner
        ON se.learner_id = learner.user_id
     WHERE se.teacher_id = ? OR se.learner_id = ?
     ORDER BY se.session_date DESC, se.start_time DESC"
);

$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();

$result = $stmt->get_result();

/* Escape displayed text */
function escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Session History | SkillSync</title>

    <link rel="stylesheet" href="assets/style.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f3f6fc;
            font-family: Arial, sans-serif;
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
            margin-left: 15px;
            font-size: 14px;
        }

        nav a:hover {
            text-decoration: underline;
        }

        .container {
            max-width: 1100px;
            margin: 35px auto;
            padding: 0 18px;
        }

        .hero {
            background: white;
            padding: 25px;
            border-radius: 14px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px #00000008;
        }

        .hero h1 {
            margin: 0 0 10px;
            color: #253b80;
        }

        .hero p {
            margin-bottom: 0;
            color: #64748b;
            line-height: 1.6;
        }

        .message {
            padding: 14px;
            margin-bottom: 22px;
            border-radius: 8px;
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

        .cards {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
        }

        .card {
            background: white;
            padding: 23px;
            border-radius: 13px;
            box-shadow: 0 4px 15px #0000000c;
            overflow-wrap: anywhere;
        }

        .card h3 {
            color: #253b80;
            margin-top: 0;
            font-size: 21px;
        }

        .card p {
            line-height: 1.6;
            margin: 12px 0;
        }

        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 13px;
            font-weight: bold;
        }

        .status.Conducted {
            background: #dcfce7;
            color: #166534;
        }

        .status.Cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }

        button,
        .review-link {
            border: none;
            padding: 11px 15px;
            border-radius: 7px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .complete-btn {
            background: #15803d;
            color: white;
        }

        .cancel-btn {
            background: #dc2626;
            color: white;
        }

        .review-link {
            background: #253b80;
            color: white;
        }

        button:hover,
        .review-link:hover {
            opacity: 0.88;
        }

        .empty {
            background: white;
            padding: 35px 20px;
            border-radius: 12px;
            text-align: center;
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

            .cards {
                grid-template-columns: 1fr;
            }

            .hero,
            .card {
                padding: 20px;
            }

            .hero h1 {
                font-size: 25px;
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
        <a href="reschedule_session.php">Reschedule</a>
        <a href="reviews.php">Reviews</a>
        <a href="notifications.php">Notifications</a>
        <a href="logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <div class="hero">
        <h1>📚 Session History</h1>
        <p>
            View your learning sessions and manage their status.
            Completed sessions can be reviewed.
        </p>
    </div>

    <?php if ($message !== "") { ?>
        <div class="message <?php echo escape($message_type); ?>" role="status">
            <?php echo escape($message); ?>
        </div>
    <?php } ?>

    <div class="cards">

        <?php if ($result->num_rows > 0) { ?>

            <?php while ($row = $result->fetch_assoc()) { ?>

                <div class="card">

                    <h3><?php echo escape($row["skill_name"]); ?></h3>

                    <p>
                        <strong>Teacher:</strong>
                        <?php echo escape($row["teacher_name"]); ?>
                    </p>

                    <p>
                        <strong>Learner:</strong>
                        <?php echo escape($row["learner_name"]); ?>
                    </p>

                    <p>
                        <strong>Date:</strong>
                        <?php echo escape($row["session_date"] ?? "Not specified"); ?>
                    </p>

                    <p>
                        <strong>Time:</strong>
                        <?php echo escape($row["start_time"] ?? "--"); ?>
                        -
                        <?php echo escape($row["end_time"] ?? "--"); ?>
                    </p>

                    <p>
                        <strong>Mode:</strong>
                        <?php echo escape($row["mode"] ?? "Not specified"); ?>
                    </p>

                    <p>
                        <strong>Status:</strong>
                        <span class="status <?php echo escape($row["status"]); ?>">
                            <?php echo escape($row["status"]); ?>
                        </span>
                    </p>

                    <?php if ($row["status"] === "Scheduled") { ?>

                        <form method="POST" action="session_history.php">
                            <input
                                type="hidden"
                                name="session_id"
                                value="<?php echo (int) $row["session_id"]; ?>"
                            >

                            <div class="actions">
                                <button
                                    class="complete-btn"
                                    type="submit"
                                    name="status"
                                    value="Conducted"
                                >
                                    Mark Conducted
                                </button>

                                <button
                                    class="cancel-btn"
                                    type="submit"
                                    name="status"
                                    value="Cancelled"
                                >
                                    Cancel Session
                                </button>
                            </div>
                        </form>

                    <?php } elseif ($row["status"] === "Conducted") { ?>

                        <p>
                            <strong>Session completed.</strong>
                            You can leave a review for the other participant.
                        </p>

                        <a class="review-link" href="reviews.php">
                            Give a Review
                        </a>

                    <?php } elseif ($row["status"] === "Cancelled") { ?>

                        <p>This session has been cancelled.</p>

                    <?php } ?>

                </div>

            <?php } ?>

        <?php } else { ?>

            <div class="empty">
                <h3>No Sessions Yet</h3>
                <p>Your learning sessions will appear here once scheduled.</p>
                <a class="review-link" href="sessions.php">Go to My Sessions</a>
            </div>

        <?php } ?>

    </div>

</div>

</body>
</html>

<?php
$stmt->close();
?>