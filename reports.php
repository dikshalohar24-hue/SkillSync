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

/* Handle report submission */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $reported_user_id = filter_input(
        INPUT_POST,
        "reported_user_id",
        FILTER_VALIDATE_INT
    );

    $reason = trim($_POST["reason"] ?? "");
    $description = trim($_POST["description"] ?? "");

    $allowed_reasons = [
        "Inappropriate Behaviour",
        "Harassment",
        "Fake Profile",
        "Spam",
        "Other"
    ];

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

    } elseif (strlen($reason) > 150) {

        $message = "The report reason is too long.";
        $message_type = "error";

    } else {

        /* Check that the selected account exists and is active */
        $check = $conn->prepare(
            "SELECT user_id
             FROM `user`
             WHERE user_id = ?
             AND status = 'Active'"
        );

        $check->bind_param("i", $reported_user_id);
        $check->execute();

        $student_result = $check->get_result();

        if ($student_result->num_rows !== 1) {

            $message = "The selected student was not found.";
            $message_type = "error";

        } else {

            /* Save the report */
            $stmt = $conn->prepare(
                "INSERT INTO `report`
                (reporter_id, reported_user_id, reason, description)
                VALUES (?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "iiss",
                $user_id,
                $reported_user_id,
                $reason,
                $description
            );

            if ($stmt->execute()) {

                $message = "Your report has been submitted successfully. The admin can review it.";
                $message_type = "success";

                /* Clear form values after successful submission */
                $reported_user_id = 0;
                $reason = "";
                $description = "";

            } else {

                $message = "Unable to submit your report. Please try again.";
                $message_type = "error";

            }

            $stmt->close();
        }

        $check->close();
    }
}

/* Fetch active users except the logged-in user */
$stmt = $conn->prepare(
    "SELECT user_id, name, department
     FROM `user`
     WHERE user_id != ?
     AND status = 'Active'
     ORDER BY name ASC"
);

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

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            color: #243047;
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
            max-width: 720px;
            margin: 40px auto;
            padding: 0 18px;
        }

        .form-box {
            background: white;
            padding: 30px;
            border-radius: 14px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.07);
        }

        h1 {
            margin-top: 0;
            color: #253b80;
            font-size: 27px;
        }

        .intro {
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-top: 18px;
            margin-bottom: 8px;
            font-weight: bold;
        }

        select,
        textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
            background: white;
        }

        textarea {
            resize: vertical;
            min-height: 120px;
        }

        select:focus,
        textarea:focus {
            outline: 2px solid #c7d2fe;
            border-color: #253b80;
        }

        button {
            margin-top: 24px;
            padding: 13px 20px;
            background: #253b80;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
        }

        button:hover {
            background: #192c65;
        }

        .message {
            padding: 13px;
            margin: 18px 0;
            border-radius: 8px;
            line-height: 1.5;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .notice {
            margin-top: 20px;
            padding: 14px;
            background: #eff6ff;
            color: #1e40af;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.6;
        }

        @media (max-width: 600px) {
            nav {
                flex-direction: column;
                align-items: flex-start;
            }

            nav a {
                display: inline-block;
                margin: 8px 10px 0 0;
            }

            .form-box {
                padding: 22px 18px;
            }

            h1 {
                font-size: 23px;
            }
        }
    </style>
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

        <h1>🚩 Report a Student</h1>

        <p class="intro">
            Help us maintain a safe and respectful learning environment.
            Submit a report if you experience inappropriate behaviour
            on SkillSync.
        </p>

        <?php if ($message !== "") { ?>
            <div
                class="message <?php echo htmlspecialchars($message_type); ?>"
                role="status"
            >
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php } ?>

        <form method="POST" action="report.php">

            <label for="reported_user_id">Select Student</label>

            <select
                name="reported_user_id"
                id="reported_user_id"
                required
            >
                <option value="">Choose a student</option>

                <?php while ($student = $students->fetch_assoc()) { ?>

                    <option
                        value="<?php echo (int) $student["user_id"]; ?>"
                        <?php
                        echo ((int) $reported_user_id === (int) $student["user_id"])
                            ? "selected"
                            : "";
                        ?>
                    >
                        <?php
                        echo htmlspecialchars(
                            $student["name"] .
                            " - " .
                            ($student["department"] ?? "Department not specified")
                        );
                        ?>
                    </option>

                <?php } ?>

            </select>

            <label for="reason">Reason for Report</label>

            <select name="reason" id="reason" required>

                <option value="">Select a reason</option>

                <?php
                $reasons = [
                    "Inappropriate Behaviour",
                    "Harassment",
                    "Fake Profile",
                    "Spam",
                    "Other"
                ];

                foreach ($reasons as $item) {
                ?>
                    <option
                        value="<?php echo htmlspecialchars($item); ?>"
                        <?php echo ($reason === $item) ? "selected" : ""; ?>
                    >
                        <?php echo htmlspecialchars($item); ?>
                    </option>
                <?php } ?>

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

        <div class="notice">
            <strong>Note:</strong> Your report will be sent to the
            SkillSync administration team for review. Please provide
            accurate information.
        </div>

    </div>

</div>

</body>
</html>

<?php
$stmt->close();
?>