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

// Allowed values
$allowed_levels = ["Easy", "Medium", "Hard"];
$allowed_priorities = ["Low", "Medium", "High"];

// Add learning goal
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $skill_id = $_POST["skill_id"] ?? "";
    $skill_level = $_POST["skill_level"] ?? "";
    $priority = $_POST["priority"] ?? "";

    // Validate skill ID
    if (
        !ctype_digit((string)$skill_id) ||
        (int)$skill_id < 1
    ) {
        $message = "Please select a valid skill.";

    } elseif (!in_array($skill_level, $allowed_levels, true)) {
        $message = "Please select a valid learning level.";

    } elseif (!in_array($priority, $allowed_priorities, true)) {
        $message = "Please select a valid priority.";

    } else {

        $skill_id = (int)$skill_id;

        // Check whether skill exists
        $skill_check = $conn->prepare(
            "SELECT skill_id FROM skill WHERE skill_id = ?"
        );

        $skill_check->bind_param("i", $skill_id);
        $skill_check->execute();

        $skill_result = $skill_check->get_result();

        if ($skill_result->num_rows !== 1) {

            $message = "Selected skill does not exist.";

        } else {

            // Check duplicate learning goal
            $check = $conn->prepare(
                "SELECT goal_id
                 FROM learning_goal
                 WHERE user_id = ? AND skill_id = ?
                 LIMIT 1"
            );

            $check->bind_param("ii", $user_id, $skill_id);
            $check->execute();

            $result = $check->get_result();

            if ($result->num_rows > 0) {

                $message = "This learning goal is already added.";

            } else {

                // Insert learning goal
                $stmt = $conn->prepare(
                    "INSERT INTO learning_goal
                    (user_id, skill_id, priority, skill_level)
                    VALUES (?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "iiss",
                    $user_id,
                    $skill_id,
                    $priority,
                    $skill_level
                );

                if ($stmt->execute()) {
                    $message = "Learning goal added successfully.";
                } else {
                    $message = "Unable to add learning goal. Please try again.";
                }

                $stmt->close();
            }

            $check->close();
        }

        $skill_check->close();
    }
}

// Get all available skills
$skills = $conn->query(
    "SELECT skill_id, skill_name
     FROM skill
     ORDER BY skill_name ASC"
);

// Get user's learning goals
$stmt = $conn->prepare(
    "SELECT
        lg.goal_id,
        s.skill_name,
        lg.priority,
        lg.skill_level
     FROM learning_goal lg
     INNER JOIN skill s ON lg.skill_id = s.skill_id
     WHERE lg.user_id = ?
     ORDER BY s.skill_name ASC"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$goals = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Learning Goals - SkillSync</title>

    <link rel="stylesheet" href="assets/style.css">

</head>

<body>

<nav>

    <h2>SkillSync</h2>

    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="profile.php">Profile</a>
        <a href="skills.php">My Skills</a>
        <a href="learning_goals.php">Learning Goals</a>
        <a href="logout.php">Logout</a>
    </div>

</nav>

<div class="container">

    <div class="form-box">

        <h1>Skills I Want to Learn</h1>

        <p>
            Select the skills you want to learn from other students.
        </p>

        <?php if ($message !== ""): ?>

            <div class="message">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="learning_goals.php">

            <label for="skill_id">Select Skill</label>

            <select name="skill_id" id="skill_id" required>

                <option value="">Select a skill</option>

                <?php while ($skill = $skills->fetch_assoc()): ?>

                    <option value="<?= (int)$skill["skill_id"] ?>">
                        <?= htmlspecialchars(
                            $skill["skill_name"],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </option>

                <?php endwhile; ?>

            </select>

            <label for="skill_level">Learning Level</label>

            <select name="skill_level" id="skill_level" required>

                <option value="">Select Level</option>
                <option value="Easy">Easy</option>
                <option value="Medium">Medium</option>
                <option value="Hard">Hard</option>

            </select>

            <label for="priority">Priority</label>

            <select name="priority" id="priority" required>

                <option value="">Select Priority</option>
                <option value="Low">Low</option>
                <option value="Medium">Medium</option>
                <option value="High">High</option>

            </select>

            <button type="submit">Add Learning Goal</button>

        </form>

    </div>

    <div class="cards">

        <?php if ($goals->num_rows > 0): ?>

            <?php while ($goal = $goals->fetch_assoc()): ?>

                <div class="card">

                    <h3>
                        <?= htmlspecialchars(
                            $goal["skill_name"],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </h3>

                    <p>
                        <strong>Level:</strong>
                        <?= htmlspecialchars(
                            $goal["skill_level"],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </p>

                    <p>
                        <strong>Priority:</strong>
                        <?= htmlspecialchars(
                            $goal["priority"],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </p>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="card">
                <h3>No Learning Goals Added Yet</h3>
                <p>Add a skill that you want to learn.</p>
            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>