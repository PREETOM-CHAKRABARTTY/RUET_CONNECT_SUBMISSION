
<?php
session_start();

require_once "config/database.php";

// Admin access only
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}

// CSRF protection
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$days = [
    "Sunday", "Monday", "Tuesday", "Wednesday",
    "Thursday", "Friday", "Saturday"
];

$error = "";
$success = $_SESSION["routine_success"] ?? "";
unset($_SESSION["routine_success"]);

$edit_routine = null;

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {
        die("Invalid request. Please refresh the page and try again.");
    }

    $action = $_POST["action"] ?? "";

    // DELETE ROUTINE
    if ($action === "delete") {

        $routine_id = filter_input(INPUT_POST, "routine_id", FILTER_VALIDATE_INT);

        if (!$routine_id) {
            $error = "Invalid routine selected.";
        } else {
            $stmt = $conn->prepare(
                "DELETE FROM class_routines WHERE id = ?"
            );

            $stmt->bind_param("i", $routine_id);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $_SESSION["routine_success"] = "Routine deleted successfully.";
                header("Location: admin_routine.php");
                exit;
            } else {
                $error = "Unable to delete the selected routine.";
            }

            $stmt->close();
        }
    }

    // ADD OR UPDATE ROUTINE
    elseif ($action === "save") {

        $routine_id = filter_input(
            INPUT_POST,
            "routine_id",
            FILTER_VALIDATE_INT
        );

        $offering_id = filter_input(
            INPUT_POST,
            "offering_id",
            FILTER_VALIDATE_INT
        );

        $day = $_POST["day_of_week"] ?? "";
        $start_time = $_POST["start_time"] ?? "";
        $end_time = $_POST["end_time"] ?? "";
        $room_number = trim($_POST["room_number"] ?? "");

        // Validate input
        if (!$offering_id) {
            $error = "Please select a valid course offering.";
        } elseif (!in_array($day, $days, true)) {
            $error = "Please select a valid day.";
        } elseif (
            !preg_match('/^\d{2}:\d{2}$/', $start_time) ||
            !preg_match('/^\d{2}:\d{2}$/', $end_time)
        ) {
            $error = "Please enter valid class times.";
        } elseif ($start_time >= $end_time) {
            $error = "The ending time must be later than the starting time.";
        } elseif ($room_number === "" || strlen($room_number) > 50) {
            $error = "Please enter a valid classroom number (maximum 50 characters).";
        } else {

            // Verify that the course offering exists
            $check = $conn->prepare(
                "SELECT id FROM course_offerings WHERE id = ?"
            );

            $check->bind_param("i", $offering_id);
            $check->execute();
            $offering_result = $check->get_result();
            $offering_exists = $offering_result->num_rows > 0;
            $check->close();

            if (!$offering_exists) {
                $error = "The selected course offering does not exist.";
            } else {

                // Prevent overlapping schedules for the same teacher or room
                $overlap_sql = "
                    SELECT cr.id
                    FROM class_routines cr
                    JOIN course_offerings existing_co
                        ON cr.offering_id = existing_co.id
                    JOIN course_offerings new_co
                        ON new_co.id = ?
                    WHERE cr.day_of_week = ?
                      AND cr.start_time < ?
                      AND cr.end_time > ?
                      AND (
                          existing_co.teacher_id = new_co.teacher_id
                          OR cr.room_number = ?
                      )
                ";

                if ($action === "save" && $routine_id) {
                    $overlap_sql .= " AND cr.id != ?";
                }

                $overlap_sql .= " LIMIT 1";

                $overlap = $conn->prepare($overlap_sql);

                if ($routine_id) {
                    $overlap->bind_param(
                        "issssi",
                        $offering_id,
                        $day,
                        $end_time,
                        $start_time,
                        $room_number,
                        $routine_id
                    );
                } else {
                    $overlap->bind_param(
                        "issss",
                        $offering_id,
                        $day,
                        $end_time,
                        $start_time,
                        $room_number
                    );
                }

                $overlap->execute();
                $overlap_result = $overlap->get_result();
                $has_overlap = $overlap_result->num_rows > 0;
                $overlap->close();

                if ($has_overlap) {
                    $error = "Schedule conflict! The teacher or classroom is already booked during this time.";
                } else {

                    if ($routine_id) {

                        // UPDATE ROUTINE
                        $stmt = $conn->prepare(
                            "UPDATE class_routines
                             SET offering_id = ?,
                                 day_of_week = ?,
                                 start_time = ?,
                                 end_time = ?,
                                 room_number = ?
                             WHERE id = ?"
                        );

                        $stmt->bind_param(
                            "issssi",
                            $offering_id,
                            $day,
                            $start_time,
                            $end_time,
                            $room_number,
                            $routine_id
                        );

                        $message = "Routine updated successfully.";

                    } else {

                        // INSERT ROUTINE
                        $stmt = $conn->prepare(
                            "INSERT INTO class_routines
                             (offering_id, day_of_week, start_time, end_time, room_number)
                             VALUES (?, ?, ?, ?, ?)"
                        );

                        $stmt->bind_param(
                            "issss",
                            $offering_id,
                            $day,
                            $start_time,
                            $end_time,
                            $room_number
                        );

                        $message = "Routine created successfully.";
                    }

                    if ($stmt->execute()) {
                        $_SESSION["routine_success"] = $message;
                        header("Location: admin_routine.php");
                        exit;
                    } else {
                        $error = "Database error: " . $stmt->error;
                    }

                    $stmt->close();
                }
            }
        }

        // Preserve entered values after validation errors
        if ($error !== "") {
            $edit_routine = [
                "id" => $routine_id ?: "",
                "offering_id" => $offering_id ?: "",
                "day_of_week" => $day,
                "start_time" => $start_time,
                "end_time" => $end_time,
                "room_number" => $room_number
            ];
        }
    }
}

// Load routine for editing
if (isset($_GET["edit"])) {

    $edit_id = filter_input(INPUT_GET, "edit", FILTER_VALIDATE_INT);

    if ($edit_id) {
        $stmt = $conn->prepare(
            "SELECT * FROM class_routines WHERE id = ?"
        );

        $stmt->bind_param("i", $edit_id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $edit_routine = $result->fetch_assoc();
        }

        $stmt->close();
    }
}

// Load available course offerings
$offerings = $conn->query(
    "SELECT
        co.id,
        co.year,
        co.semester,
        co.academic_session,
        c.course_code,
        c.course_name,
        u.full_name AS teacher_name
     FROM course_offerings co
     JOIN courses c ON co.course_id = c.id
     JOIN users u ON co.teacher_id = u.id
     ORDER BY co.year, co.semester, c.course_code"
);

if (!$offerings) {
    die("Unable to load course offerings: " . $conn->error);
}

// Load all class routines
$routines = $conn->query(
    "SELECT
        cr.id,
        cr.day_of_week,
        cr.start_time,
        cr.end_time,
        cr.room_number,
        co.year,
        co.semester,
        co.academic_session,
        c.course_code,
        c.course_name,
        u.full_name AS teacher_name
     FROM class_routines cr
     JOIN course_offerings co ON cr.offering_id = co.id
     JOIN courses c ON co.course_id = c.id
     JOIN users u ON co.teacher_id = u.id
     ORDER BY
        FIELD(
            cr.day_of_week,
            'Sunday', 'Monday', 'Tuesday',
            'Wednesday', 'Thursday', 'Friday', 'Saturday'
        ),
        cr.start_time"
);

if (!$routines) {
    die("Unable to load class routines: " . $conn->error);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Class Routine | RUET Connect</title>

    <link rel="stylesheet" href="css/style.css?v=3">

    <style>

        .routine-container {
            width: min(1200px, 94%);
            margin: 35px auto;
        }

        .routine-heading {
            margin-bottom: 25px;
        }

        .routine-heading h2 {
            color: #3D1C24;
            margin-bottom: 8px;
        }

        .routine-heading p {
            color: #777;
        }

        .routine-form,
        .routine-list {
            background: #fff;
            border: 1px solid #e9e3df;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.04);
        }

        .routine-form h3,
        .routine-list h3 {
            color: #3D1C24;
            margin-top: 0;
            margin-bottom: 22px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-weight: 600;
            color: #3D1C24;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            background: #fff;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #DAA520;
        }

        .form-actions {
            margin-top: 22px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .primary-button,
        .cancel-button,
        .edit-button,
        .delete-button {
            display: inline-block;
            padding: 10px 17px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .primary-button {
            background: #3D1C24;
            color: #fff;
        }

        .primary-button:hover {
            background: #542a35;
        }

        .cancel-button {
            background: #eee;
            color: #333;
        }

        .edit-button {
            background: #DAA520;
            color: #211b0c;
        }

        .delete-button {
            background: #a83232;
            color: #fff;
        }

        .alert {
            padding: 13px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: #e8f5e9;
            color: #23632a;
        }

        .alert-error {
            background: #fdeaea;
            color: #a12626;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .routine-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        .routine-table th {
            background: #3D1C24;
            color: #fff;
            padding: 13px;
            text-align: left;
            font-size: 13px;
        }

        .routine-table td {
            padding: 13px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        .routine-table tr:hover td {
            background: #faf8f6;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .action-buttons form {
            margin: 0;
        }

        .empty-message {
            padding: 30px;
            text-align: center;
            color: #777;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #3D1C24;
            text-decoration: none;
            font-weight: 600;
        }

        @media(max-width: 650px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .routine-form,
            .routine-list {
                padding: 18px;
            }
        }

    </style>

</head>

<body class="dashboard-page">

<header class="dashboard-header">

    <div>
        <h1>RUET Connect</h1>
        <p>Admin Routine Management</p>
    </div>

    <div class="dashboard-user">

        <div class="user-name">
            <?php echo e(explode(" ", trim($_SESSION["full_name"]))[0]); ?>
        </div>

        <a href="logout.php" class="dashboard-logout">
            Logout
        </a>

    </div>

</header>

<div class="routine-container">

    <a href="admin.php" class="back-link">
        &larr; Back to Dashboard
    </a>

    <div class="routine-heading">

        <h2>Class Routine Management</h2>

        <p>Create and manage weekly class schedules for RUET students.</p>

    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo e($success); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <?php echo e($error); ?>
        </div>
    <?php endif; ?>

    <!-- ADD / EDIT ROUTINE FORM -->

    <section class="routine-form">

        <h3>
            <?php echo $edit_routine ? "Edit Class Routine" : "Add New Class Routine"; ?>
        </h3>

        <form method="POST" action="admin_routine.php">

            <input type="hidden" name="csrf_token"
                   value="<?php echo e($_SESSION["csrf_token"]); ?>">

            <input type="hidden" name="action" value="save">

            <input type="hidden" name="routine_id"
                   value="<?php echo e($edit_routine["id"] ?? ""); ?>">

            <div class="form-grid">

                <div class="form-group">

                    <label for="offering_id">Course and Teacher</label>

                    <select name="offering_id" id="offering_id" required>

                        <option value="">Select a course offering</option>

                        <?php while ($offering = $offerings->fetch_assoc()): ?>

                            <option
                                value="<?php echo e($offering["id"]); ?>"
                                <?php
                                echo (
                                    (string)($edit_routine["offering_id"] ?? "") ===
                                    (string)$offering["id"]
                                ) ? "selected" : "";
                                ?>
                            >

                                <?php
                                echo e(
                                    $offering["course_code"] . " - " .
                                    $offering["course_name"] .
                                    " | " . $offering["teacher_name"] .
                                    " | Year " . $offering["year"] .
                                    ", Semester " . $offering["semester"] .
                                    " | " . $offering["academic_session"]
                                );
                                ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>

                <div class="form-group">

                    <label for="day_of_week">Day of the Week</label>

                    <select name="day_of_week" id="day_of_week" required>

                        <option value="">Select a day</option>

                        <?php foreach ($days as $day): ?>

                            <option
                                value="<?php echo e($day); ?>"
                                <?php
                                echo (
                                    ($edit_routine["day_of_week"] ?? "") === $day
                                ) ? "selected" : "";
                                ?>
                            >
                                <?php echo e($day); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-group">

                    <label for="start_time">Starting Time</label>

                    <input
                        type="time"
                        name="start_time"
                        id="start_time"
                        value="<?php echo e($edit_routine["start_time"] ?? ""); ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="end_time">Ending Time</label>

                    <input
                        type="time"
                        name="end_time"
                        id="end_time"
                        value="<?php echo e($edit_routine["end_time"] ?? ""); ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="room_number">Classroom</label>

                    <input
                        type="text"
                        name="room_number"
                        id="room_number"
                        placeholder="Example: ECE-301"
                        maxlength="50"
                        value="<?php echo e($edit_routine["room_number"] ?? ""); ?>"
                        required
                    >

                </div>

            </div>

            <div class="form-actions">

                <button type="submit" class="primary-button">

                    <?php echo $edit_routine ? "Update Routine" : "Save Routine"; ?>

                </button>

                <?php if ($edit_routine): ?>

                    <a href="admin_routine.php" class="cancel-button">
                        Cancel Editing
                    </a>

                <?php endif; ?>

            </div>

        </form>

    </section>

    <!-- EXISTING ROUTINES -->

    <section class="routine-list">

        <h3>Existing Class Routines</h3>

        <?php if ($routines->num_rows > 0): ?>

            <div class="table-wrapper">

                <table class="routine-table">

                    <thead>

                        <tr>
                            <th>Course</th>
                            <th>Teacher</th>
                            <th>Year / Semester</th>
                            <th>Day</th>
                            <th>Time</th>
                            <th>Room</th>
                            <th>Actions</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php while ($routine = $routines->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <strong><?php echo e($routine["course_code"]); ?></strong><br>
                                    <?php echo e($routine["course_name"]); ?>
                                </td>

                                <td>
                                    <?php echo e($routine["teacher_name"]); ?>
                                </td>

                                <td>
                                    Year <?php echo e($routine["year"]); ?><br>
                                    Semester <?php echo e($routine["semester"]); ?>
                                </td>

                                <td>
                                    <?php echo e($routine["day_of_week"]); ?>
                                </td>

                                <td>
                                    <?php
                                    echo date("h:i A", strtotime($routine["start_time"]));
                                    echo " - ";
                                    echo date("h:i A", strtotime($routine["end_time"]));
                                    ?>
                                </td>

                                <td>
                                    <?php echo e($routine["room_number"]); ?>
                                </td>

                                <td>

                                    <div class="action-buttons">

                                        <a
                                            href="admin_routine.php?edit=<?php echo e($routine["id"]); ?>"
                                            class="edit-button"
                                        >
                                            Edit
                                        </a>

                                        <form method="POST"
                                              action="admin_routine.php"
                                              onsubmit="return confirm('Are you sure you want to delete this routine?');">

                                            <input type="hidden"
                                                   name="csrf_token"
                                                   value="<?php echo e($_SESSION["csrf_token"]); ?>">

                                            <input type="hidden"
                                                   name="action"
                                                   value="delete">

                                            <input type="hidden"
                                                   name="routine_id"
                                                   value="<?php echo e($routine["id"]); ?>">

                                            <button type="submit" class="delete-button">
                                                Delete
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty-message">
                No class routines have been created yet.
            </div>

        <?php endif; ?>

    </section>

</div>

</body>
</html>

<?php
$conn->close();
?>
