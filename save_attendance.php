
<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit();
}

require_once "config/database.php";

$teacher_id = (int)$_SESSION["user_id"];

// Validate CSRF token.
$submitted_token = $_POST["csrf_token"] ?? "";
$session_token = $_SESSION["attendance_csrf_token"] ?? "";

if (
    !is_string($submitted_token) ||
    !is_string($session_token) ||
    $session_token === "" ||
    !hash_equals($session_token, $submitted_token)
) {
    http_response_code(403);
    exit("Invalid request. Please reload the attendance page and try again.");
}

// Validate course offering ID.
$offering_id = filter_var(
    $_POST["offering_id"] ?? null,
    FILTER_VALIDATE_INT
);

if (!$offering_id || $offering_id <= 0) {
    exit("Invalid course offering.");
}

// Validate attendance date.
$attendance_date = $_POST["attendance_date"] ?? "";

$date_object = is_string($attendance_date)
    ? DateTime::createFromFormat("!Y-m-d", $attendance_date)
    : false;

$date_errors = DateTime::getLastErrors();

if (
    !$date_object ||
    ($date_errors !== false &&
        ($date_errors["warning_count"] > 0 || $date_errors["error_count"] > 0)) ||
    $date_object->format("Y-m-d") !== $attendance_date ||
    $attendance_date > date("Y-m-d")
) {
    exit("Invalid attendance date.");
}

// Ensure both submitted checkbox fields are arrays.
$absent_students = $_POST["absent"] ?? [];
$late_students = $_POST["late"] ?? [];

if (!is_array($absent_students) || !is_array($late_students)) {
    exit("Invalid attendance data.");
}

// Verify that the offering belongs to this teacher.
$sql = "SELECT
            co.id,
            co.year,
            co.semester,
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
    http_response_code(403);
    exit("You are not authorized to manage this course.");
}

// Fetch the actual eligible student roster from the database.
$sql = "SELECT id
        FROM users
        WHERE role = 'student'
          AND LOWER(TRIM(department)) = LOWER(TRIM(?))
          AND academic_year = ?
          AND semester = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "sii",
    $offering["department"],
    $offering["year"],
    $offering["semester"]
);

$stmt->execute();

$result = $stmt->get_result();

$roster = [];

while ($row = $result->fetch_assoc()) {
    $roster[] = (int)$row["id"];
}

$stmt->close();

if (count($roster) === 0) {
    exit("No eligible students were found for this course.");
}

// Convert submitted student IDs into validated integer arrays.
function validateStudentArray($submitted, $roster) {

    $validated = [];

    foreach ($submitted as $student_id) {

        $student_id = filter_var(
            $student_id,
            FILTER_VALIDATE_INT
        );

        if (!$student_id || $student_id <= 0) {
            throw new Exception("Invalid student information.");
        }

        if (!in_array($student_id, $roster, true)) {
            throw new Exception("An unauthorized student was submitted.");
        }

        $validated[] = (int)$student_id;
    }

    return array_unique($validated);
}

try {

    $absent_students = validateStudentArray(
        $absent_students,
        $roster
    );

    $late_students = validateStudentArray(
        $late_students,
        $roster
    );

    // A student cannot be both absent and late.
    $overlap = array_intersect(
        $absent_students,
        $late_students
    );

    if (count($overlap) > 0) {
        throw new Exception(
            "A student cannot be marked both absent and late."
        );
    }

    // Prepare the attendance status for every eligible student.
    $attendance_data = [];

    foreach ($roster as $student_id) {

        if (in_array($student_id, $absent_students, true)) {
            $status = "absent";
        } elseif (in_array($student_id, $late_students, true)) {
            $status = "late";
        } else {
            // Neither checkbox selected means Present.
            $status = "present";
        }

        $attendance_data[] = [
            "student_id" => $student_id,
            "status" => $status
        ];
    }

    // Save everything in one database transaction.
    $conn->begin_transaction();

    // Create a session or retrieve the existing session.
    // Existing sessions are reused, so editing does not create duplicates.
    $sql = "INSERT INTO attendance_sessions
                (offering_id, attendance_date, created_by)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                id = LAST_INSERT_ID(id)";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "isi",
        $offering_id,
        $attendance_date,
        $teacher_id
    );

    $stmt->execute();

    $session_id = (int)$conn->insert_id;

    $stmt->close();

    if ($session_id <= 0) {
        throw new Exception("Could not create attendance session.");
    }

    // Insert new records or update existing records.
    $sql = "INSERT INTO attendance_records
                (session_id, student_id, status)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                status = VALUES(status)";

    $stmt = $conn->prepare($sql);

    foreach ($attendance_data as $record) {

        $student_id = $record["student_id"];
        $status = $record["status"];

        $stmt->bind_param(
            "iis",
            $session_id,
            $student_id,
            $status
        );

        $stmt->execute();
    }

    $stmt->close();

    // Commit only after every student's record has been saved.
    $conn->commit();

    // Redirect back to the attendance page.
    header(
        "Location: manage_attendance.php?offering_id=" .
        urlencode((string)$offering_id) .
        "&attendance_date=" .
        urlencode($attendance_date) .
        "&saved=1"
    );

    exit();

} catch (Throwable $e) {

    $conn->rollback();

    error_log("Attendance save error: " . $e->getMessage());

    http_response_code(500);

    echo "<h2>Unable to save attendance.</h2>";
    echo "<p>Please try again. If the problem continues, check the PHP error log.</p>";
    echo "<p><a href='manage_attendance.php?offering_id=" .
        urlencode((string)$offering_id) .
        "&attendance_date=" .
        urlencode($attendance_date) .
        "'>Return to attendance page</a></p>";

    exit();
}
