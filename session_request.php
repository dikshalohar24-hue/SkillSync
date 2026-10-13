<?php
session_start();
require_once "config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$sender_id = (int) $_SESSION["user_id"];

$receiver_id = isset($_GET["receiver_id"])
    ? (int) $_GET["receiver_id"] : 0;

$skill_id = isset($_GET["skill_id"])
    ? (int) $_GET["skill_id"] : 0;

$message = "";

/* Send session request */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $receiver_id = (int) ($_POST["receiver_id"] ?? 0);
    $skill_id = (int) ($_POST["skill_id"] ?? 0);

    $request_message = trim($_POST["message"] ?? "");
    $preferred_date = trim($_POST["preferred_date"] ?? "");
    $start_time = trim($_POST["start_time"] ?? "");
    $end_time = trim($_POST["end_time"] ?? "");
    $mode = $_POST["mode"] ?? "";

    /* Validate required fields */
    if (
        $receiver_id <= 0 ||
        $skill_id <= 0 ||
        $receiver_id === $sender_id ||
        $request_message === "" ||
        strlen($request_message) > 10000 ||
        $preferred_date === "" ||
        $start_time === "" ||
        $end_time === "" ||
        !in_array($mode, ["Online", "Offline"], true)
    ) {
        $message = "Please enter valid request details.";

    } elseif (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $preferred_date) ||
        !checkdate(
            (int) substr($preferred_date, 5, 2),
            (int) substr($preferred_date, 8, 2),
            (int) substr($preferred_date, 0, 4)
        )
    ) {
        $message = "Please select a valid date.";

    } elseif (
        !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start_time) ||
        !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end_time)
    ) {
        $message = "Please select valid start and end times.";

    } elseif ($preferred_date < date("Y-m-d")) {
        $message = "Please select today or a future date.";

    } elseif (
        $end_time <= $start_time
    ) {
        $message = "End time must be after start time.";

    } elseif (
        $preferred_date === date("Y-m-d") &&
        $start_time <= date("H:i")
    ) {
        $message = "For today's session, please select a future start time.";

    } else {

        /* Verify active receiver and selected skill */
        $check = $conn->prepare(
            "SELECT user_id
             FROM `user`
             WHERE user_id = ?
               AND status = 'Active'"
        );

        $check->bind_param("i", $receiver_id);
        $check->execute();

        $receiver_exists = $check->get_result()->num_rows === 1;
        $check->close();

        $check = $conn->prepare(
            "SELECT skill_id
             FROM skill
             WHERE skill_id = ?"
        );

        $check->bind_param("i", $skill_id);
        $check->execute();

        $skill_exists = $check->get_result()->num_rows === 1;
        $check->close();

        /*
         * Verify the receiver offers this skill.
         * A user should not be able to request an unrelated skill.
         */
        $check = $conn->prepare(
            "SELECT 1
             FROM user_skill
             WHERE user_id = ?
               AND skill_id = ?
             LIMIT 1"
        );

        $check->bind_param("ii", $receiver_id, $skill_id);
        $check->execute();

        $receiver_teaches_skill =
            $check->get_result()->num_rows === 1;

        $check->close();

        if (
            !$receiver_exists ||
            !$skill_exists ||
            !$receiver_teaches_skill
        ) {
            $message =
                "Selected student or teaching skill is invalid.";

        } else {

            $transaction_started = false;

            try {
                $conn->begin_transaction();
                $transaction_started = true;

                $status = "Pending";

                /* Save the request */
                $stmt = $conn->prepare(
                    "INSERT INTO session_request
                    (
                        sender_id,
                        receiver_id,
                        skill_id,
                        message,
                        preferred_date,
                        start_time,
                        end_time,
                        mode,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "iiissssss",
                    $sender_id,
                    $receiver_id,
                    $skill_id,
                    $request_message,
                    $preferred_date,
                    $start_time,
                    $end_time,
                    $mode,
                    $status
                );

                if (!$stmt->execute()) {
                    $stmt->close();

                    throw new Exception(
                        "Could not save the session request."
                    );
                }

                $request_id = (int) $conn->insert_id;
                $stmt->close();

                /* Create notification for receiver */
                $sender_name = $_SESSION["name"] ?? "A student";

                $title = "New Session Request";

                $notification_message =
                    $sender_name .
                    " sent you a session request for " .
                    $preferred_date . " from " .
                    $start_time . " to " . $end_time . ".";

                $reference_type = "session_request";
                $reference_id = $request_id;

                $notification_stmt = $conn->prepare(
                    "INSERT INTO notification
                    (
                        user_id,
                        title,
                        message,
                        reference_type,
                        reference_id
                    )
                    VALUES (?, ?, ?, ?, ?)"
                );

                $notification_stmt->bind_param(
                    "isssi",
                    $receiver_id,
                    $title,
                    $notification_message,
                    $reference_type,
                    $reference_id
                );

                if (!$notification_stmt->execute()) {
                    $notification_stmt->close();

                    throw new Exception(
                        "Could not save the request notification."
                    );
                }

                $notification_stmt->close();

                /* Save both changes together */
                $conn->commit();
                $transaction_started = false;

                $message =
                    "Session request sent successfully! " .
                    "The student has been notified.";

            } catch (Throwable $e) {

                if ($transaction_started) {
                    $conn->rollback();
                }

                $message = $e->getMessage();
            }
        }
    }
}

/* Fetch receiver details */
$receiver_name = "";
$receiver_valid = false;

if ($receiver_id > 0 && $receiver_id !== $sender_id) {

    $stmt = $conn->prepare(
        "SELECT name
         FROM `user`
         WHERE user_id = ?
           AND status = 'Active'"
    );

    $stmt->bind_param("i", $receiver_id);
    $stmt->execute();

    $receiver = $stmt->get_result()->fetch_assoc();

    if ($receiver) {
        $receiver_name = $receiver["name"];
        $receiver_valid = true;
    }

    $stmt->close();
}

/* Fetch selected skill */
$skill_name = "";
$skill_valid = false;

if ($skill_id > 0) {

    $stmt = $conn->prepare(
        "SELECT skill_name
         FROM skill
         WHERE skill_id = ?"
    );

    $stmt->bind_param("i", $skill_id);
    $stmt->execute();

    $skill = $stmt->get_result()->fetch_assoc();

    if ($skill) {
        $skill_name = $skill["skill_name"];
        $skill_valid = true;
    }

    $stmt->close();
}

/* Confirm receiver offers this skill */
if ($receiver_valid && $skill_valid) {

    $stmt = $conn->prepare(
        "SELECT 1
         FROM user_skill
         WHERE user_id = ?
           AND skill_id = ?
         LIMIT 1"
    );

    $stmt->bind_param("ii", $receiver_id, $skill_id);
    $stmt->execute();

    $receiver_teaches_skill =
        $stmt->get_result()->num_rows === 1;

    $stmt->close();

    if (!$receiver_teaches_skill) {
        $receiver_valid = false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Session Request - SkillSync</title>

    <link rel="stylesheet" href="assets/style.css">
</head>

<body>

<nav>
    <h2>SkillSync</h2>

    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="matches.php">Matches</a>
        <a href="requests.php">Requests</a>
        <a href="notifications.php">Notifications</a>
        <a href="logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <div class="form-box">

        <h1>📩 Session Request</h1>

        <?php if ($message !== ""): ?>
            <div class="message">
                <?php echo htmlspecialchars(
                    $message,
                    ENT_QUOTES,
                    "UTF-8"
                ); ?>
            </div>
        <?php endif; ?>

        <?php if ($receiver_valid && $skill_valid): ?>

            <form method="POST">

                <input
                    type="hidden"
                    name="receiver_id"
                    value="<?php echo $receiver_id; ?>"
                >

                <input
                    type="hidden"
                    name="skill_id"
                    value="<?php echo $skill_id; ?>"
                >

                <label>Student</label>

                <input
                    type="text"
                    value="<?php echo htmlspecialchars(
                        $receiver_name,
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>"
                    readonly
                >

                <label>Skill</label>

                <input
                    type="text"
                    value="<?php echo htmlspecialchars(
                        $skill_name,
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>"
                    readonly
                >

                <label>Message</label>

                <textarea
                    name="message"
                    rows="4"
                    maxlength="10000"
                    placeholder="Write your message..."
                    required
                ></textarea>

                <label>Preferred Date</label>

                <input
                    type="date"
                    name="preferred_date"
                    min="<?php echo date("Y-m-d"); ?>"
                    required
                >

                <label>Start Time</label>

                <input
                    type="time"
                    name="start_time"
                    required
                >

                <label>End Time</label>

                <input
                    type="time"
                    name="end_time"
                    required
                >

                <label>Mode</label>

                <select name="mode" required>
                    <option value="Online">Online</option>
                    <option value="Offline">Offline</option>
                </select>

                <button type="submit">Send Request</button>

            </form>

        <?php else: ?>

            <p>
                Please open this page through a valid match.
                The selected student or skill is unavailable.
            </p>

            <a class="button" href="matches.php">
                Back to Matches
            </a>

        <?php endif; ?>

    </div>

</div>

</body>
</html>

<?php
$conn->close();
?>