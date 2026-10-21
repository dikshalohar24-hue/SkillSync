
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
$reported_user_id = 0;
$reason = "";
$description = "";

$allowed_reasons = [
    "Inappropriate Behaviour",
    "Harassment",
    "Fake Profile",
    "Spam",
    "Other"
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $reported_user_id = filter_input(
        INPUT_POST,
        "reported_user_id",
        FILTER_VALIDATE_INT
    );

    $reason = trim($_POST["reason"] ?? "");
    $description = trim($_POST["description"] ?? "");

    if (!$reported_user_id || $reported_user_id <= 0) {
        $message = "Please select a valid student.";
        $message_type = "error";

    } elseif ($reported_user_id === $user_id) {
        $message = "You cannot report your own account.";
        $message_type = "error";

    } elseif (!in_array($reason, $allowed_reasons, true)) {
        $message = "Please select a valid report reason.";
        $message_type = "error";

    } elseif ($description === "") {
        $message = "Please describe the issue.";
        $message_type = "error";

    } elseif (strlen($description) > 3000) {
        $message = "Description must not exceed 3000 characters.";
        $message_type = "error";

    } else {
        $check = $conn->prepare(
            "SELECT user_id FROM `user`
             WHERE user_id = ? AND status = 'Active'"
        );

        if (!$check) {
            die("Database query error: " . $conn->error);
        }

        $check->bind_param("i", $reported_user_id);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows !== 1) {
            $message = "Selected student was not found or is inactive.";
            $message_type = "error";
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO `report`
                (reporter_id, reported_user_id, reason, description)
                VALUES (?, ?, ?, ?)"
            );

            if (!$stmt) {
                die("Report query error: " . $conn->error);
            }

            $stmt->bind_param(
                "iiss",
                $user_id,
                $reported_user_id,
                $reason,
                $description
            );

            if ($stmt->execute()) {
                $message = "Report submitted successfully.";
                $message_type = "success";

                $reported_user_id = 0;
                $reason = "";
                $description = "";
            } else {
                $message = "Unable to submit report. Please try again.";
                $message_type = "error";
            }

            $stmt->close();
        }

        $check->close();
    }
}

$stmt = $conn->prepare(
    "SELECT user_id, name, department
     FROM `user`
     WHERE user_id != ? AND status = 'Active'
     ORDER BY name ASC"
);

if (!$stmt) {
    die("Student query error: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$students = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report a Student | SkillSync</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<nav>
    <h2>SkillSync</h2>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="matches.php">Matches</a>
        <a href="notifications.php">Notifications</a>
        <a href="logout.php">Logout</a>
    </div>
</nav>

<div class="container">
    <div class="form-box">
        <h1>Report a Student</h1>

        <p>
            Report inappropriate behaviour to help maintain a
            safe and respectful learning environment.
        </p>

        <?php if ($message !== ""): ?>
            <div class="message">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="report.php">
            <label for="reported_user_id">Select Student</label>

            <select name="reported_user_id" id="reported_user_id" required>
                <option value="">Choose a student</option>

                <?php while ($student = $students->fetch_assoc()): ?>
                    <option
                        value="<?php echo (int) $student["user_id"]; ?>"
                        <?php
                        echo ((int) $reported_user_id ===
                            (int) $student["user_id"]) ? "selected" : "";
                        ?>
                    >
                        <?php
                        echo htmlspecialchars(
                            $student["name"] . " - " .
                            ($student["department"] ?? "Department not specified")
                        );
                        ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="reason">Reason for Report</label>
            <select name="reason" id="reason" required>
                <option value="">Select a reason</option>

                <?php foreach ($allowed_reasons as $item): ?>
                    <option
                        value="<?php echo htmlspecialchars($item); ?>"
                        <?php echo ($reason === $item) ? "selected" : ""; ?>
                    >
                        <?php echo htmlspecialchars($item); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="description">Describe the Issue</label>
            <textarea
                name="description"
                id="description"
                rows="5"
                maxlength="3000"
                placeholder="Explain the issue clearly..."
                required
            ><?php echo htmlspecialchars($description); ?></textarea>

            <button type="submit">Submit Report</button>
        </form>
    </div>
</div>

</body>
</html>

<?php
$stmt->close();
?>
