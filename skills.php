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

// Allowed skill levels
$allowed_levels = ["Easy", "Medium", "Hard"];

// Add skill
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $skill_id = $_POST["skill_id"] ?? "";
    $skill_level = $_POST["skill_level"] ?? "";
    $experience = trim($_POST["experience"] ?? "");

    // Validate skill ID
    if (
        !ctype_digit((string)$skill_id) ||
        (int)$skill_id < 1
    ) {
        $message = "Please select a valid skill.";

    } elseif (!in_array($skill_level, $allowed_levels, true)) {
        $message = "Please select a valid skill level.";

    } elseif (strlen($experience) > 100) {
        $message = "Experience must not exceed 100 characters.";

    } else {

        $skill_id = (int)$skill_id;

        // Check whether selected skill exists
        $skill_check = $conn->prepare(
            "SELECT skill_id FROM skill WHERE skill_id = ?"
        );

        $skill_check->bind_param("i", $skill_id);
        $skill_check->execute();

        $skill_result = $skill_check->get_result();

        if ($skill_result->num_rows !== 1) {

            $message = "Selected skill does not exist.";

        } else {

            // Check duplicate skill
            $check = $conn->prepare(
                "SELECT user_skill_id
                 FROM user_skill
                 WHERE user_id = ? AND skill_id = ?
                 LIMIT 1"
            );

            $check->bind_param("ii", $user_id, $skill_id);
            $check->execute();

            $result = $check->get_result();

            if ($result->num_rows > 0) {

                $message = "You have already added this skill.";

            } else {

                // Insert skill
                $stmt = $conn->prepare(
                    "INSERT INTO user_skill
                    (user_id, skill_id, skill_level, experience)
                    VALUES (?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "iiss",
                    $user_id,
                    $skill_id,
                    $skill_level,
                    $experience
                );

                if ($stmt->execute()) {
                    $message = "Skill added successfully.";
                } else {
                    $message = "Unable to add skill. Please try again.";
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

// Get user's skills
$stmt = $conn->prepare(
    "SELECT
        us.user_skill_id,
        s.skill_name,
        us.skill_level,
        us.experience
     FROM user_skill us
     INNER JOIN skill s ON us.skill_id = s.skill_id
     WHERE us.user_id = ?
     ORDER BY s.skill_name ASC"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$user_skills = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Skills - SkillSync</title>

    <link rel="stylesheet" href="assets/style.css">

</head>

<body>

<nav>

    <h2>SkillSync</h2>

    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="profile.php">Profile</a>
        <a href="skills.php">My Skills</a>
        <a href="logout.php">Logout</a>
    </div>

</nav>

<div class="container">

    <div class="form-box">

        <h1>Skills I Can Teach</h1>

        <p>Add the skills that you can teach to other students.</p>

        <?php if ($message !== ""): ?>

            <div class="message">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="skills.php">

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

            <label for="skill_level">Skill Level</label>

            <select name="skill_level" id="skill_level" required>

                <option value="">Select Level</option>
                <option value="Easy">Easy</option>
                <option value="Medium">Medium</option>
                <option value="Hard">Hard</option>

            </select>

            <label for="experience">Experience</label>

            <input
                type="text"
                name="experience"
                id="experience"
                maxlength="100"
                placeholder="Example: 1 year"
            >

            <button type="submit">Add Skill</button>

        </form>

    </div>

    <div class="cards">

        <?php if ($user_skills->num_rows > 0): ?>

            <?php while ($item = $user_skills->fetch_assoc()): ?>

                <div class="card">

                    <h3>
                        <?= htmlspecialchars(
                            $item["skill_name"],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </h3>

                    <p>
                        <strong>Level:</strong>
                        <?= htmlspecialchars(
                            $item["skill_level"],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </p>

                    <p>
                        <strong>Experience:</strong>
                        <?= htmlspecialchars(
                            $item["experience"] ?: "Not specified",
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </p>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="card">
                <h3>No Skills Added Yet</h3>
                <p>Add your first skill using the form above.</p>
            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>