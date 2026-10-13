<?php
session_start();
require_once "config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$message = "";

/* Create session from accepted request */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $request_id = isset($_POST["request_id"])
        ? (int) $_POST["request_id"]
        : 0;

    if ($request_id <= 0) {
        $message = "Invalid request.";

    } else {

        $transaction_started = false;

        try {
            $conn->begin_transaction();
            $transaction_started = true;

            /*
             * Lock the accepted request to reduce
             * the chance of duplicate session creation.
             */
            $stmt = $conn->prepare(
                "SELECT request_id, sender_id, receiver_id, skill_id,
                        preferred_date, start_time, end_time, mode
                 FROM session_request
                 WHERE request_id = ?
                   AND receiver_id = ?
                   AND status = 'Accept'
                 FOR UPDATE"
            );

            $stmt->bind_param("ii", $request_id, $user_id);
            $stmt->execute();

            $result = $stmt->get_result();
            $request = $result->fetch_assoc();
            $stmt->close();

            if (!$request) {
                throw new Exception(
                    "No accepted request found for your account."
                );
            }

            /* Check for an existing session */
            $check = $conn->prepare(
                "SELECT session_id
                 FROM `session`
                 WHERE request_id = ?
                 LIMIT 1"
            );

            $check->bind_param("i", $request_id);
            $check->execute();

            $existing = $check->get_result()->fetch_assoc();
            $check->close();

            if ($existing) {
                throw new Exception(
                    "A session already exists for this request."
                );
            }

            $teacher_id = (int) $request["receiver_id"];
            $learner_id = (int) $request["sender_id"];
            $skill_id = (int) $request["skill_id"];

            $session_date = $request["preferred_date"];
            $start_time = $request["start_time"];
            $end_time = $request["end_time"];
            $mode = $request["mode"];

            /* Create the learning session */
            $insert = $conn->prepare(
                "INSERT INTO `session`
                (request_id, teacher_id, learner_id, skill_id,
                 session_date, start_time, end_time, mode, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled')"
            );

            $insert->bind_param(
                "iiiissss",
                $request_id,
                $teacher_id,
                $learner_id,
                $skill_id,
                $session_date,
                $start_time,
                $end_time,
                $mode
            );

            if (!$insert->execute()) {
                $insert->close();
                throw new Exception("Could not create the session.");
            }

            $session_id = (int) $conn->insert_id;
            $insert->close();

            /* Get skill name */
            $skill_stmt = $conn->prepare(
                "SELECT skill_name
                 FROM skill
                 WHERE skill_id = ?"
            );

            $skill_stmt->bind_param("i", $skill_id);
            $skill_stmt->execute();

            $skill_result = $skill_stmt->get_result();
            $skill_row = $skill_result->fetch_assoc();
            $skill_stmt->close();

            $skill_name = $skill_row
                ? $skill_row["skill_name"]
                : "Learning";

            /* Notification details */
            $title = "Learning Session Scheduled";

            $notification_message =
                "Your session for " . $skill_name .
                " has been scheduled for " . $session_date .
                " from " . $start_time .
                " to " . $end_time . ".";

            $reference_type = "session";
            $reference_id = $session_id;

            /* Notify both participants */
            $notification_stmt = $conn->prepare(
                "INSERT INTO notification
                (user_id, title, message, reference_type, reference_id)
                VALUES (?, ?, ?, ?, ?)"
            );

            foreach ([$teacher_id, $learner_id] as $recipient_id) {

                $notification_stmt->bind_param(
                    "isssi",
                    $recipient_id,
                    $title,
                    $notification_message,
                    $reference_type,
                    $reference_id
                );

                if (!$notification_stmt->execute()) {
                    $notification_stmt->close();

                    throw new Exception(
                        "Could not save session notifications."
                    );
                }
            }

            $notification_stmt->close();

            /* Save session and notifications together */
            $conn->commit();
            $transaction_started = false;

            $message =
                "Learning session created successfully! " .
                "Both participants have been notified.";

        } catch (Throwable $e) {

            if ($transaction_started) {
                $conn->rollback();
            }

            $message = $e->getMessage();
        }
    }
}

/* Show accepted requests without an existing session */
$stmt = $conn->prepare(
    "SELECT sr.request_id,
            u.name AS student_name,
            s.skill_name,
            sr.preferred_date,
            sr.start_time,
            sr.end_time,
            sr.mode
     FROM session_request sr
     INNER JOIN `user` u
        ON sr.sender_id = u.user_id
     INNER JOIN skill s
        ON sr.skill_id = s.skill_id
     LEFT JOIN `session` se
        ON se.request_id = sr.request_id
     WHERE sr.receiver_id = ?
       AND sr.status = 'Accept'
       AND se.session_id IS NULL
     ORDER BY sr.request_id DESC"
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
    <title>Sessions - SkillSync</title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>

<nav>
    <h2>SkillSync</h2>

    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="requests.php">Requests</a>
        <a href="notifications.php">Notifications</a>
        <a href="logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <div class="hero">
        <h1>📚 Session Management</h1>
        <p>Create learning sessions from accepted requests.</p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="message">
            <?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?>
        </div>
    <?php endif; ?>

    <div class="cards">

        <?php if ($result->num_rows > 0): ?>

            <?php while ($row = $result->fetch_assoc()): ?>

                <div class="card">

                    <h3>
                        <?php echo htmlspecialchars(
                            $row["skill_name"],
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </h3>

                    <p>
                        <strong>Student:</strong>
                        <?php echo htmlspecialchars(
                            $row["student_name"],
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </p>

                    <p>
                        <strong>Date:</strong>
                        <?php echo htmlspecialchars(
                            $row["preferred_date"] ?? "Not specified",
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </p>

                    <p>
                        <strong>Time:</strong>
                        <?php echo htmlspecialchars(
                            $row["start_time"] ?? "--",
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                        -
                        <?php echo htmlspecialchars(
                            $row["end_time"] ?? "--",
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </p>

                    <p>
                        <strong>Mode:</strong>
                        <?php echo htmlspecialchars(
                            $row["mode"] ?? "Online",
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </p>

                    <form method="POST">
                        <input
                            type="hidden"
                            name="request_id"
                            value="<?php echo (int) $row["request_id"]; ?>"
                        >

                        <button type="submit">Create Session</button>
                    </form>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="card">
                <h3>No Accepted Requests</h3>
                <p>Accepted session requests will appear here.</p>
            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>