<?php
session_start();
require_once "config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$message = "";

/* Accept or Reject a request */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $request_id = isset($_POST["request_id"])
        ? (int) $_POST["request_id"]
        : 0;

    $action = $_POST["action"] ?? "";

    if (
        $request_id <= 0 ||
        !in_array($action, ["Accept", "Reject"], true)
    ) {
        $message = "Invalid request or action.";

    } else {

        $transaction_started = false;

        try {
            $conn->begin_transaction();
            $transaction_started = true;

            /* Lock and fetch the pending request */
            $check = $conn->prepare(
                "SELECT sender_id, skill_id
                 FROM session_request
                 WHERE request_id = ?
                   AND receiver_id = ?
                   AND status = 'Pending'
                 FOR UPDATE"
            );

            $check->bind_param("ii", $request_id, $user_id);
            $check->execute();

            $request = $check->get_result()->fetch_assoc();
            $check->close();

            if (!$request) {
                throw new Exception(
                    "Request not found or already processed."
                );
            }

            $sender_id = (int) $request["sender_id"];
            $skill_id = (int) $request["skill_id"];

            /* Update request status */
            $update = $conn->prepare(
                "UPDATE session_request
                 SET status = ?
                 WHERE request_id = ?
                   AND receiver_id = ?
                   AND status = 'Pending'"
            );

            $update->bind_param(
                "sii",
                $action,
                $request_id,
                $user_id
            );

            if (!$update->execute() || $update->affected_rows !== 1) {
                $update->close();

                throw new Exception(
                    "Request could not be updated. Please try again."
                );
            }

            $update->close();

            /* Get skill name */
            $skill_name = "your requested skill";

            $skill_stmt = $conn->prepare(
                "SELECT skill_name
                 FROM skill
                 WHERE skill_id = ?"
            );

            $skill_stmt->bind_param("i", $skill_id);
            $skill_stmt->execute();

            $skill_row = $skill_stmt->get_result()->fetch_assoc();

            if ($skill_row) {
                $skill_name = $skill_row["skill_name"];
            }

            $skill_stmt->close();

            /* Prepare notification */
            if ($action === "Accept") {
                $title = "Session Request Accepted";
                $notification_message =
                    "Your session request for " .
                    $skill_name . " has been accepted.";
            } else {
                $title = "Session Request Rejected";
                $notification_message =
                    "Your session request for " .
                    $skill_name . " has been rejected.";
            }

            $reference_type = "session_request";
            $reference_id = $request_id;

            $notification_stmt = $conn->prepare(
                "INSERT INTO notification
                (user_id, title, message, reference_type, reference_id)
                VALUES (?, ?, ?, ?, ?)"
            );

            $notification_stmt->bind_param(
                "isssi",
                $sender_id,
                $title,
                $notification_message,
                $reference_type,
                $reference_id
            );

            if (!$notification_stmt->execute()) {
                $notification_stmt->close();

                throw new Exception(
                    "Could not save the notification."
                );
            }

            $notification_stmt->close();

            /* Commit status and notification together */
            $conn->commit();
            $transaction_started = false;

            $message = $action === "Accept"
                ? "Request accepted successfully! Notification sent."
                : "Request rejected successfully! Notification sent.";

        } catch (Throwable $e) {

            if ($transaction_started) {
                $conn->rollback();
            }

            $message = $e->getMessage();
        }
    }
}

/* Fetch requests received by the logged-in user */
$stmt = $conn->prepare(
    "SELECT
        sr.request_id,
        sr.sender_id,
        sr.skill_id,
        sr.message,
        sr.preferred_date,
        sr.start_time,
        sr.end_time,
        sr.mode,
        sr.status,
        u.name AS sender_name,
        s.skill_name
     FROM session_request sr
     INNER JOIN `user` u
        ON sr.sender_id = u.user_id
     INNER JOIN skill s
        ON sr.skill_id = s.skill_id
     WHERE sr.receiver_id = ?
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

    <title>Received Requests - SkillSync</title>

    <link rel="stylesheet" href="assets/style.css">
</head>

<body>

<nav>
    <h2>SkillSync</h2>

    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="matches.php">Matches</a>
        <a href="sessions.php">Sessions</a>
        <a href="notifications.php">Notifications</a>
        <a href="logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <div class="hero">
        <h1>📩 Received Session Requests</h1>
        <p>View and manage requests sent by other students.</p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="message">
            <?php echo htmlspecialchars(
                $message,
                ENT_QUOTES,
                "UTF-8"
            ); ?>
        </div>
    <?php endif; ?>

    <div class="cards">

        <?php if ($result->num_rows > 0): ?>

            <?php while ($row = $result->fetch_assoc()): ?>

                <div class="card">

                    <h3>
                        <?php echo htmlspecialchars(
                            $row["sender_name"],
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </h3>

                    <p>
                        <strong>Skill:</strong>
                        <?php echo htmlspecialchars(
                            $row["skill_name"],
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </p>

                    <p>
                        <strong>Message:</strong>
                        <?php echo htmlspecialchars(
                            $row["message"] ?? "No message",
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

                    <p>
                        <strong>Status:</strong>
                        <?php echo htmlspecialchars(
                            $row["status"],
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </p>

                    <?php if ($row["status"] === "Pending"): ?>

                        <form method="POST">

                            <input
                                type="hidden"
                                name="request_id"
                                value="<?php echo (int) $row["request_id"]; ?>"
                            >

                            <button
                                type="submit"
                                name="action"
                                value="Accept"
                            >
                                Accept
                            </button>

                            <button
                                type="submit"
                                name="action"
                                value="Reject"
                            >
                                Reject
                            </button>

                        </form>

                    <?php elseif ($row["status"] === "Accept"): ?>

                        <p>✅ Request accepted.</p>

                    <?php elseif ($row["status"] === "Reject"): ?>

                        <p>Request rejected.</p>

                    <?php endif; ?>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="card">
                <h3>No Requests Yet</h3>
                <p>Received session requests will appear here.</p>
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