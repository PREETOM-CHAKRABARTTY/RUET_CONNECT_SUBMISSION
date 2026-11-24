
<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit();
}

require_once "config/database.php";

$teacher_id = (int) $_SESSION["user_id"];

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function showError($message) {
    http_response_code(400);
    echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'>";
    echo "<title>Attendance Error</title></head><body>";
    echo "<h2>" . e($message) . "</h2>";
    echo "<p><a href='teacher_attendance.php'>Back to Attendance Management</a></p>";
    echo "</body></html>";
    exit();
}

// Validate incoming parameters.
$offering_id = filter_input(INPUT_GET, "offering_id", FILTER_VALIDATE_INT);
$attendance_date = $_GET["attendance_date"] ?? "";

$date_object = DateTime::createFromFormat("!Y-m-d", $attendance_date);
$date_errors = DateTime::getLastErrors();

if (
    !$offering_id ||
    !$date_object ||
    ($date_errors !== false &&
        ($date_errors["warning_count"] > 0 || $date_errors["error_count"] > 0)) ||
    $date_object->format("Y-m-d") !== $attendance_date
) {
    showError("Invalid course or attendance date.");
}

if ($attendance_date > date("Y-m-d")) {
    showError("Attendance cannot be taken for a future date.");
}

// Verify that this course offering belongs to the logged-in teacher.
$sql = "SELECT
            co.id AS offering_id,
            co.year,
            co.semester,
            co.academic_session,
            c.course_code,
            c.course_name,
            c.department
        FROM course_offerings co
        INNER JOIN courses c ON co.course_id = c.id
        WHERE co.id = ? AND co.teacher_id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $offering_id, $teacher_id);
$stmt->execute();

$offering = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$offering) {
    showError("You are not authorized to manage this course offering.");
}

// Fetch students belonging to this course's department, year and semester.
$sql = "SELECT
            id,
            full_name,
            student_id
        FROM users
        WHERE role = 'student'
          AND LOWER(TRIM(department)) = LOWER(TRIM(?))
          AND academic_year = ?
          AND semester = ?
        ORDER BY student_id ASC, full_name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "sii",
    $offering["department"],
    $offering["year"],
    $offering["semester"]
);
$stmt->execute();

$result = $stmt->get_result();
$students = [];

while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

$stmt->close();

// Check whether attendance has already been recorded for this date.
$sql = "SELECT id
        FROM attendance_sessions
        WHERE offering_id = ? AND attendance_date = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $offering_id, $attendance_date);
$stmt->execute();

$existing_session = $stmt->get_result()->fetch_assoc();
$stmt->close();

$session_id = $existing_session ? (int)$existing_session["id"] : null;

// Load previously saved attendance statuses.
$attendance_statuses = [];

if ($session_id !== null) {
    $sql = "SELECT student_id, status
            FROM attendance_records
            WHERE session_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $session_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $attendance_statuses[(int)$row["student_id"]] = $row["status"];
    }

    $stmt->close();
}

// Generate CSRF token.
if (empty($_SESSION["attendance_csrf_token"])) {
    $_SESSION["attendance_csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["attendance_csrf_token"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Take Attendance | RUET Connect</title>

    <link rel="stylesheet" href="css/style.css?v=3">

    <style>
        body {
            background: #f5f3f1;
            color: #292526;
        }

        .attendance-container {
            width: 100%;
            max-width: 1150px;
            margin: 0 auto;
            padding: 35px 30px;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 22px;
            color: #3D1C24;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .page-heading {
            margin-bottom: 25px;
        }

        .page-heading h1 {
            color: #3D1C24;
            font-size: 28px;
            margin-bottom: 8px;
        }

        .page-heading p {
            color: #777;
            font-size: 14px;
        }

        .info-card,
        .roster-card {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
        }

        .course-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .info-item small {
            display: block;
            color: #888;
            font-size: 12px;
            margin-bottom: 7px;
        }

        .info-item strong {
            color: #3D1C24;
            font-size: 15px;
        }

        .roster-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .roster-heading h2 {
            color: #3D1C24;
            font-size: 19px;
        }

        .student-count {
            background: #f5e9c9;
            color: #6c5010;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .instruction {
            background: #faf7ef;
            border-left: 4px solid #DAA520;
            padding: 13px 15px;
            margin-bottom: 20px;
            color: #66552c;
            font-size: 13px;
            line-height: 1.6;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        .attendance-table th {
            background: #3D1C24;
            color: white;
            padding: 14px;
            text-align: left;
            font-size: 13px;
        }

        .attendance-table td {
            padding: 14px;
            border-bottom: 1px solid #eee9e9;
            font-size: 13px;
        }

        .attendance-table tbody tr:hover {
            background: #faf8f7;
        }

        .student-name {
            color: #3D1C24;
            font-weight: 600;
        }

        .status-option {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            cursor: pointer;
            font-weight: 500;
        }

        .status-option input {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #3D1C24;
        }

        .present-label {
            color: #398354;
            font-weight: 600;
        }

        .absent-label {
            color: #b23b3b;
        }

        .late-label {
            color: #9a7416;
        }

        .submit-section {
            display: flex;
            justify-content: flex-end;
            margin-top: 22px;
            gap: 12px;
        }

        .submit-btn {
            background: #3D1C24;
            color: #fff;
            border: none;
            padding: 13px 25px;
            border-radius: 7px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
        }

        .submit-btn:hover {
            background: #542833;
        }

        .empty-state {
            text-align: center;
            padding: 35px 15px;
            color: #888;
            font-size: 14px;
        }

        @media(max-width: 700px) {
            .attendance-container {
                padding: 25px 15px;
            }

            .course-info {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .roster-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .info-card,
            .roster-card {
                padding: 18px 14px;
            }
        }
    </style>
</head>

<body>

<div class="attendance-container">

    <a href="teacher_attendance.php" class="back-link">
        &larr; Back to Attendance Management
    </a>

    <div class="page-heading">
    <h1>Take Attendance</h1>
    <p>Record or update attendance for your students.</p>
</div>

<?php if (isset($_GET["saved"]) && $_GET["saved"] == "1"): ?>
    <div style="
        background: #e9f5ec;
        color: #28643b;
        padding: 14px 18px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
        font-weight: 600;
    ">
        Attendance saved successfully!
    </div>
<?php endif; ?>

    <div class="info-card">

        <div class="course-info">

            <div class="info-item">
                <small>Course</small>
                <strong>
                    <?= e($offering["course_code"]) ?> -
                    <?= e($offering["course_name"]) ?>
                </strong>
            </div>

            <div class="info-item">
                <small>Academic Session</small>
                <strong><?= e($offering["academic_session"]) ?></strong>
            </div>

            <div class="info-item">
                <small>Attendance Date</small>
                <strong><?= date("d F Y", strtotime($attendance_date)) ?></strong>
            </div>

            <div class="info-item">
                <small>Department</small>
                <strong><?= e($offering["department"]) ?></strong>
            </div>

            <div class="info-item">
                <small>Academic Year</small>
                <strong>Year <?= e($offering["year"]) ?></strong>
            </div>

            <div class="info-item">
                <small>Semester</small>
                <strong>Semester <?= e($offering["semester"]) ?></strong>
            </div>

        </div>

    </div>

    <div class="roster-card">

        <div class="roster-heading">
            <h2>Student Attendance List</h2>

            <span class="student-count">
                <?= count($students) ?> Students
            </span>
        </div>

        <div class="instruction">
            <strong>Instructions:</strong>
            Select Absent or Late for a student when applicable.
            If neither option is selected, the student's status will be
            recorded as Present. Absent and Late cannot be selected together.
        </div>

        <?php if (count($students) > 0): ?>

            <form action="save_attendance.php" method="POST" id="attendanceForm">

                <input type="hidden" name="csrf_token"
                       value="<?= e($csrf_token) ?>">

                <input type="hidden" name="offering_id"
                       value="<?= e($offering_id) ?>">

                <input type="hidden" name="attendance_date"
                       value="<?= e($attendance_date) ?>">

                <div class="table-wrapper">

                    <table class="attendance-table">

                        <thead>
                            <tr>
                                <th>SL</th>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Attendance Status</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($students as $index => $student): ?>

                                <?php
                                    $student_id = (int)$student["id"];
                                    $status = $attendance_statuses[$student_id] ?? "present";

                                    $is_absent = ($status === "absent");
                                    $is_late = ($status === "late");
                                ?>

                                <tr>

                                    <td><?= $index + 1 ?></td>

                                    <td>
                                        <?= e($student["student_id"] ?: "N/A") ?>
                                    </td>

                                    <td class="student-name">
                                        <?= e($student["full_name"]) ?>
                                    </td>

                                    <td>

                                        <label class="status-option absent-label">
                                            <input
                                                type="checkbox"
                                                name="absent[]"
                                                value="<?= $student_id ?>"
                                                data-student="<?= $student_id ?>"
                                                data-status="absent"
                                                <?= $is_absent ? "checked" : "" ?>
                                            >
                                            Absent
                                        </label>

                                        &nbsp;&nbsp;

                                        <label class="status-option late-label">
                                            <input
                                                type="checkbox"
                                                name="late[]"
                                                value="<?= $student_id ?>"
                                                data-student="<?= $student_id ?>"
                                                data-status="late"
                                                <?= $is_late ? "checked" : "" ?>
                                            >
                                            Late
                                        </label>

                                        &nbsp;&nbsp;

                                        <span class="present-label"
                                              id="present-<?= $student_id ?>"
                                              <?= ($is_absent || $is_late) ? "style='display:none'" : "" ?>>
                                            Present
                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <div class="submit-section">

                    <button type="submit" class="submit-btn">
                        <?= $session_id !== null ? "Update Attendance" : "Save Attendance" ?>
                    </button>

                </div>

            </form>

        <?php else: ?>

            <div class="empty-state">
                No students were found for this course's department,
                academic year and semester.
            </div>

        <?php endif; ?>

    </div>

</div>

<script>
document.querySelectorAll(
    'input[data-status="absent"], input[data-status="late"]'
).forEach(function(checkbox) {

    checkbox.addEventListener("change", function() {

        const studentId = this.dataset.student;
        const status = this.dataset.status;

        const otherStatus = status === "absent" ? "late" : "absent";

        const otherCheckbox = document.querySelector(
            'input[data-student="' + studentId +
            '"][data-status="' + otherStatus + '"]'
        );

        // Selecting one status automatically unchecks the other.
        if (this.checked) {
            otherCheckbox.checked = false;
        }

        const absentCheckbox = document.querySelector(
            'input[data-student="' + studentId +
            '"][data-status="absent"]'
        );

        const lateCheckbox = document.querySelector(
            'input[data-student="' + studentId +
            '"][data-status="late"]'
        );

        const presentLabel = document.getElementById(
            "present-" + studentId
        );

        presentLabel.style.display =
            (!absentCheckbox.checked && !lateCheckbox.checked)
            ? "inline"
            : "none";
    });

});
</script>

</body>
</html>
