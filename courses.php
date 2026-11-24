<?php

session_start();

require_once "config/database.php";

/*
|--------------------------------------------------------------------------
| Login Check
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_role = $_SESSION["role"];

if (!in_array($user_role, ["student", "teacher", "admin"])) {
    header("Location: login.php");
    exit;
}

$first_name = explode(" ", trim($_SESSION["full_name"]))[0];

$message = "";
$error = "";


/*
|--------------------------------------------------------------------------
| ADMIN: Add Course
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_course"])) {

    if ($user_role !== "admin") {

        $error = "You are not authorized to add courses.";

    } else {

        $course_code = trim($_POST["course_code"] ?? "");
        $course_name = trim($_POST["course_name"] ?? "");
        $course_description = trim($_POST["course_description"] ?? "");
        $department = trim($_POST["department"] ?? "");
        $credit = trim($_POST["credit"] ?? "");

        if (
            empty($course_code) ||
            empty($course_name) ||
            empty($department) ||
            empty($credit)
        ) {

            $error = "Please fill in all required fields.";

        } elseif (!is_numeric($credit) || $credit <= 0) {

            $error = "Please enter a valid credit value.";

        } else {

            $check_sql = "SELECT id FROM courses WHERE course_code = ?";

            $check_stmt = $conn->prepare($check_sql);

            if ($check_stmt) {

                $check_stmt->bind_param("s", $course_code);
                $check_stmt->execute();

                $check_result = $check_stmt->get_result();

                if ($check_result->num_rows > 0) {

                    $error = "A course with this course code already exists.";

                } else {

                    $insert_sql = "
                        INSERT INTO courses
                        (
                            course_code,
                            course_name,
                            course_description,
                            department,
                            credit
                        )
                        VALUES (?, ?, ?, ?, ?)
                    ";

                    $insert_stmt = $conn->prepare($insert_sql);

                    if ($insert_stmt) {

                        $credit_value = (float)$credit;

                        $insert_stmt->bind_param(
                            "ssssd",
                            $course_code,
                            $course_name,
                            $course_description,
                            $department,
                            $credit_value
                        );

                        if ($insert_stmt->execute()) {

                            $message = "Course added successfully.";

                        } else {

                            $error = "Unable to add the course. Please try again.";
                        }

                        $insert_stmt->close();

                    } else {

                        $error = "Something went wrong. Please try again.";
                    }
                }

                $check_stmt->close();

            } else {

                $error = "Something went wrong. Please try again.";
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| ADMIN: Edit Course
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["edit_course"])) {

    if ($user_role !== "admin") {

        $error = "You are not authorized to edit courses.";

    } else {

        $course_id = intval($_POST["course_id"] ?? 0);
        $course_code = trim($_POST["course_code"] ?? "");
        $course_name = trim($_POST["course_name"] ?? "");
        $course_description = trim($_POST["course_description"] ?? "");
        $department = trim($_POST["department"] ?? "");
        $credit = trim($_POST["credit"] ?? "");

        if (
            $course_id <= 0 ||
            empty($course_code) ||
            empty($course_name) ||
            empty($department) ||
            empty($credit)
        ) {

            $error = "Please fill in all required fields.";

        } elseif (!is_numeric($credit) || $credit <= 0) {

            $error = "Please enter a valid credit value.";

        } else {

            $check_sql = "
                SELECT id
                FROM courses
                WHERE course_code = ?
                AND id != ?
            ";

            $check_stmt = $conn->prepare($check_sql);

            if ($check_stmt) {

                $check_stmt->bind_param(
                    "si",
                    $course_code,
                    $course_id
                );

                $check_stmt->execute();

                $check_result = $check_stmt->get_result();

                if ($check_result->num_rows > 0) {

                    $error = "Another course already uses this course code.";

                } else {

                    $update_sql = "
                        UPDATE courses
                        SET
                            course_code = ?,
                            course_name = ?,
                            course_description = ?,
                            department = ?,
                            credit = ?
                        WHERE id = ?
                    ";

                    $update_stmt = $conn->prepare($update_sql);

                    if ($update_stmt) {

                        $credit_value = (float)$credit;

                        $update_stmt->bind_param(
                            "ssssdi",
                            $course_code,
                            $course_name,
                            $course_description,
                            $department,
                            $credit_value,
                            $course_id
                        );

                        if ($update_stmt->execute()) {

                            $message = "Course updated successfully.";

                        } else {

                            $error = "Unable to update the course. Please try again.";
                        }

                        $update_stmt->close();

                    } else {

                        $error = "Something went wrong. Please try again.";
                    }
                }

                $check_stmt->close();

            } else {

                $error = "Something went wrong. Please try again.";
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| ADMIN: Delete Course
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_course"])) {

    if ($user_role !== "admin") {

        $error = "You are not authorized to delete courses.";

    } else {

        $course_id = intval($_POST["course_id"] ?? 0);

        if ($course_id <= 0) {

            $error = "Invalid course.";

        } else {

            $delete_sql = "DELETE FROM courses WHERE id = ?";

            $delete_stmt = $conn->prepare($delete_sql);

            if ($delete_stmt) {

                $delete_stmt->bind_param("i", $course_id);

                if ($delete_stmt->execute()) {

                    if ($delete_stmt->affected_rows > 0) {

                        $message = "Course deleted successfully.";

                    } else {

                        $error = "Course not found.";
                    }

                } else {

                    $error = "Unable to delete the course. Please try again.";
                }

                $delete_stmt->close();

            } else {

                $error = "Something went wrong. Please try again.";
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| ADMIN: Assign Course to Teacher
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["assign_course"])
) {

    if ($user_role !== "admin") {

        $error = "You are not authorized to assign courses.";

    } else {

        $course_id = intval($_POST["assign_course_id"] ?? 0);
        $teacher_id = intval($_POST["teacher_id"] ?? 0);
        $year = intval($_POST["year"] ?? 0);
        $semester = intval($_POST["semester"] ?? 0);
        $academic_session = trim($_POST["academic_session"] ?? "");

        if (
            $course_id <= 0 ||
            $teacher_id <= 0 ||
            $year < 1 ||
            $year > 4 ||
            $semester < 1 ||
            $semester > 2 ||
            empty($academic_session)
        ) {

            $error = "Please fill in all assignment fields.";

        } else {

            /*
             * Make sure selected user is actually a teacher
             */
            $teacher_check_sql = "
                SELECT id
                FROM users
                WHERE id = ?
                AND role = 'teacher'
            ";

            $teacher_check_stmt = $conn->prepare($teacher_check_sql);

            if ($teacher_check_stmt) {

                $teacher_check_stmt->bind_param(
                    "i",
                    $teacher_id
                );

                $teacher_check_stmt->execute();

                $teacher_result = $teacher_check_stmt->get_result();

                if ($teacher_result->num_rows === 0) {

                    $error = "Invalid teacher selected.";

                } else {

                    /*
                     * Check whether the course exists
                     */
                    $course_check_sql = "
                        SELECT id
                        FROM courses
                        WHERE id = ?
                    ";

                    $course_check_stmt =
                        $conn->prepare($course_check_sql);

                    if ($course_check_stmt) {

                        $course_check_stmt->bind_param(
                            "i",
                            $course_id
                        );

                        $course_check_stmt->execute();

                        $course_result =
                            $course_check_stmt->get_result();

                        if ($course_result->num_rows === 0) {

                            $error = "Invalid course selected.";

                        } else {

                            /*
                             * Check duplicate assignment
                             */
                            $duplicate_sql = "
                                SELECT id
                                FROM course_offerings
                                WHERE course_id = ?
                                AND teacher_id = ?
                                AND year = ?
                                AND semester = ?
                                AND academic_session = ?
                            ";

                            $duplicate_stmt =
                                $conn->prepare($duplicate_sql);

                            if ($duplicate_stmt) {

                                $duplicate_stmt->bind_param(
                                    "iiiis",
                                    $course_id,
                                    $teacher_id,
                                    $year,
                                    $semester,
                                    $academic_session
                                );

                                $duplicate_stmt->execute();

                                $duplicate_result =
                                    $duplicate_stmt->get_result();

                                if ($duplicate_result->num_rows > 0) {

                                    $error =
                                        "This course is already assigned to this teacher for the selected year, semester and session.";

                                } else {

                                    /*
                                     * Insert assignment
                                     */
                                    $assign_sql = "
                                        INSERT INTO course_offerings
                                        (
                                            course_id,
                                            teacher_id,
                                            year,
                                            semester,
                                            academic_session
                                        )
                                        VALUES (?, ?, ?, ?, ?)
                                    ";

                                    $assign_stmt =
                                        $conn->prepare($assign_sql);

                                    if ($assign_stmt) {

                                        $assign_stmt->bind_param(
                                            "iiiis",
                                            $course_id,
                                            $teacher_id,
                                            $year,
                                            $semester,
                                            $academic_session
                                        );

                                        if ($assign_stmt->execute()) {

                                            $message =
                                                "Course assigned successfully.";

                                        } else {

                                            $error =
                                                "Unable to assign the course. Please try again.";
                                        }

                                        $assign_stmt->close();

                                    } else {

                                        $error =
                                            "Something went wrong. Please try again.";
                                    }
                                }

                                $duplicate_stmt->close();

                            } else {

                                $error =
                                    "Something went wrong. Please try again.";
                            }
                        }

                        $course_check_stmt->close();

                    } else {

                        $error =
                            "Something went wrong. Please try again.";
                    }
                }

                $teacher_check_stmt->close();

            } else {

                $error =
                    "Something went wrong. Please try again.";
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| ADMIN: Remove Course Assignment
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["remove_assignment"])
) {

    if ($user_role !== "admin") {

        $error = "You are not authorized to remove assignments.";

    } else {

        $assignment_id =
            intval($_POST["assignment_id"] ?? 0);

        if ($assignment_id <= 0) {

            $error = "Invalid assignment.";

        } else {

            $remove_sql = "
                DELETE FROM course_offerings
                WHERE id = ?
            ";

            $remove_stmt = $conn->prepare($remove_sql);

            if ($remove_stmt) {

                $remove_stmt->bind_param(
                    "i",
                    $assignment_id
                );

                if ($remove_stmt->execute()) {

                    if ($remove_stmt->affected_rows > 0) {

                        $message =
                            "Course assignment removed successfully.";

                    } else {

                        $error = "Assignment not found.";
                    }

                } else {

                    $error =
                        "Unable to remove the assignment. Please try again.";
                }

                $remove_stmt->close();

            } else {

                $error =
                    "Something went wrong. Please try again.";
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Get All Courses
|--------------------------------------------------------------------------
*/

$courses = [];

$sql = "
    SELECT
        id,
        course_code,
        course_name,
        course_description,
        department,
        credit,
        created_at
    FROM courses
    ORDER BY course_code ASC
";

$result = $conn->query($sql);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
}


/*
|--------------------------------------------------------------------------
| Get Teachers
|--------------------------------------------------------------------------
*/

$teachers = [];

$teacher_sql = "
    SELECT
        id,
        full_name,
        email,
        department
    FROM users
    WHERE role = 'teacher'
    ORDER BY full_name ASC
";

$teacher_result = $conn->query($teacher_sql);

if ($teacher_result) {

    while ($row = $teacher_result->fetch_assoc()) {
        $teachers[] = $row;
    }
}


/*
|--------------------------------------------------------------------------
| Get Course Assignments
|--------------------------------------------------------------------------
*/

$assignments = [];

$assignment_sql = "
    SELECT
        course_offerings.id,
        course_offerings.year,
        course_offerings.semester,
        course_offerings.academic_session,

        courses.course_code,
        courses.course_name,

        users.full_name AS teacher_name

    FROM course_offerings

    INNER JOIN courses
        ON course_offerings.course_id = courses.id

    INNER JOIN users
        ON course_offerings.teacher_id = users.id

    ORDER BY
        course_offerings.year ASC,
        course_offerings.semester ASC,
        courses.course_code ASC,
        users.full_name ASC
";

$assignment_result = $conn->query($assignment_sql);

if ($assignment_result) {

    while ($row = $assignment_result->fetch_assoc()) {
        $assignments[] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Courses - RUET Connect</title>

    <link rel="stylesheet" href="css/style.css?v=3">

    <style>

        .course-page {
            width: 100%;
        }


        /* =========================================================
           Messages
        ========================================================= */

        .course-message,
        .course-error {
            margin-bottom: 20px;
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.5;
        }

        .course-message {
            background-color: #f5f8ed;
            color: #536b22;
            border: 1px solid #dce7c1;
        }

        .course-error {
            background-color: #f9eeee;
            color: #8a2635;
            border: 1px solid #eccdd2;
        }


        /* =========================================================
           Add Course
        ========================================================= */

        .course-form-section {
            margin-bottom: 35px;
        }

        .course-form-card {
            background-color: #ffffff;
            border: 1px solid #e5dfdc;
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 3px 12px rgba(61, 28, 36, 0.05);
        }

        .course-form-card h3 {
            margin-bottom: 22px;
            color: #3D1C24;
            font-size: 20px;
            font-weight: 600;
        }

        .course-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px 20px;
        }

        .course-form-group {
            display: flex;
            flex-direction: column;
        }

        .course-form-group.full-width {
            grid-column: 1 / -1;
        }

        .course-form-group label {
            margin-bottom: 7px;
            color: #3D1C24;
            font-size: 14px;
            font-weight: 600;
        }

        .course-form-group input,
        .course-form-group textarea,
        .course-form-group select {
            width: 100%;
            padding: 11px 13px;
            background-color: #ffffff;
            color: #222222;
            border: 1px solid #d9d3d1;
            border-radius: 8px;
            outline: none;
            font-size: 14px;
            font-family: inherit;
            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;
        }

        .course-form-group input,
        .course-form-group select {
            height: 44px;
        }

        .course-form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .course-form-group input:focus,
        .course-form-group textarea:focus,
        .course-form-group select:focus {
            border-color: #DAA520;
            box-shadow: 0 0 0 3px rgba(218, 165, 32, 0.13);
        }

        .course-form-actions {
            margin-top: 22px;
        }

        .course-submit-btn {
            height: 44px;
            padding: 0 22px;
            background-color: #3D1C24;
            color: #ffffff;
            border: 1px solid #3D1C24;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition:
                background-color 0.2s ease,
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }

        .course-submit-btn:hover {
            background-color: #512632;
            border-color: #512632;
            transform: translateY(-1px);
            box-shadow: 0 5px 12px rgba(61, 28, 36, 0.18);
        }


        /* =========================================================
           Course List
        ========================================================= */

        .course-list-section {
            margin-bottom: 40px;
        }

        .course-list-section h3,
        .assignment-section h3 {
            margin-bottom: 18px;
            color: #3D1C24;
            font-size: 20px;
            font-weight: 600;
        }

        .course-table-wrapper {
            width: 100%;
            overflow-x: auto;
            background-color: #ffffff;
            border: 1px solid #e5dfdc;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(61, 28, 36, 0.05);
        }

        .course-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 950px;
        }

        .course-table th {
            padding: 15px 16px;
            background-color: #3D1C24;
            color: #ffffff;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
        }

        .course-table td {
            padding: 15px 16px;
            border-bottom: 1px solid #eee9e7;
            color: #4d4745;
            font-size: 14px;
            line-height: 1.5;
            vertical-align: middle;
        }

        .course-table tbody tr:last-child td {
            border-bottom: none;
        }

        .course-table tbody tr:hover {
            background-color: #fcfaf8;
        }

        .course-code {
            color: #3D1C24;
            font-weight: 700;
            white-space: nowrap;
        }

        .course-credit {
            color: #3D1C24;
            font-weight: 600;
            white-space: nowrap;
        }


        /* =========================================================
           Admin Action Buttons
        ========================================================= */

        .course-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .edit-btn,
        .delete-btn,
        .remove-assignment-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 34px;
            padding: 0 13px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition:
                background-color 0.2s ease,
                border-color 0.2s ease,
                color 0.2s ease,
                transform 0.2s ease;
        }

        .edit-btn {
            background-color: #f8f3e4;
            color: #80610d;
            border: 1px solid #dfcf9d;
        }

        .edit-btn:hover {
            background-color: #f1e7c8;
            border-color: #d0ba76;
            transform: translateY(-1px);
        }

        .delete-btn,
        .remove-assignment-btn {
            background-color: #f9eeee;
            color: #8a2635;
            border: 1px solid #eccdd2;
        }

        .delete-btn:hover,
        .remove-assignment-btn:hover {
            background-color: #f3dddd;
            border-color: #dfafb7;
            transform: translateY(-1px);
        }


        /* =========================================================
           Assignment Section
        ========================================================= */

        .assignment-section {
            margin-top: 10px;
            margin-bottom: 40px;
        }

        .assignment-card {
            background-color: #ffffff;
            border: 1px solid #e5dfdc;
            border-radius: 12px;
            padding: 28px;
            margin-bottom: 25px;
            box-shadow: 0 3px 12px rgba(61, 28, 36, 0.05);
        }

        .assignment-card h4 {
            margin-bottom: 20px;
            color: #3D1C24;
            font-size: 17px;
            font-weight: 600;
        }

        .assignment-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px 20px;
        }

        .assignment-group {
            display: flex;
            flex-direction: column;
        }

        .assignment-group label {
            margin-bottom: 7px;
            color: #3D1C24;
            font-size: 14px;
            font-weight: 600;
        }

        .assignment-group select,
        .assignment-group input {
            width: 100%;
            height: 44px;
            padding: 0 13px;
            background-color: #ffffff;
            color: #222222;
            border: 1px solid #d9d3d1;
            border-radius: 8px;
            outline: none;
            font-size: 14px;
            font-family: inherit;
        }

        .assignment-group select:focus,
        .assignment-group input:focus {
            border-color: #DAA520;
            box-shadow: 0 0 0 3px rgba(218, 165, 32, 0.13);
        }

        .assignment-actions {
            margin-top: 22px;
        }

        .assignment-submit-btn {
            height: 44px;
            padding: 0 22px;
            background-color: #DAA520;
            color: #3D1C24;
            border: 1px solid #DAA520;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition:
                background-color 0.2s ease,
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }

        .assignment-submit-btn:hover {
            background-color: #c99518;
            border-color: #c99518;
            transform: translateY(-1px);
            box-shadow: 0 5px 12px rgba(218, 165, 32, 0.22);
        }

        .assignment-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        .assignment-table th {
            padding: 15px 16px;
            background-color: #3D1C24;
            color: #ffffff;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
        }

        .assignment-table td {
            padding: 15px 16px;
            border-bottom: 1px solid #eee9e7;
            color: #4d4745;
            font-size: 14px;
            vertical-align: middle;
        }

        .assignment-table tbody tr:last-child td {
            border-bottom: none;
        }

        .assignment-table tbody tr:hover {
            background-color: #fcfaf8;
        }

        .year-badge,
        .semester-badge,
        .session-badge {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .year-badge,
        .semester-badge {
            background-color: #f8f3e4;
            color: #80610d;
            border: 1px solid #dfcf9d;
        }

        .session-badge {
            background-color: #f5f2f1;
            color: #3D1C24;
            border: 1px solid #ded8d5;
        }

        .no-assignments {
            padding: 30px;
            text-align: center;
            color: #766d6a;
            font-size: 14px;
        }


        /* =========================================================
           Edit Modal
        ========================================================= */

        .edit-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            inset: 0;
            background-color: rgba(61, 28, 36, 0.55);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .edit-modal.active {
            display: flex;
        }

        .edit-modal-content {
            width: 100%;
            max-width: 650px;
            max-height: 90vh;
            overflow-y: auto;
            background-color: #ffffff;
            border-radius: 14px;
            padding: 30px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.20);
        }

        .edit-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .edit-modal-header h3 {
            color: #3D1C24;
            font-size: 21px;
            font-weight: 600;
        }

        .close-modal {
            width: 34px;
            height: 34px;
            border: none;
            border-radius: 50%;
            background-color: #f4f1ef;
            color: #3D1C24;
            font-size: 20px;
            cursor: pointer;
        }

        .close-modal:hover {
            background-color: #eee8e5;
        }

        .edit-form-group {
            margin-bottom: 17px;
        }

        .edit-form-group label {
            display: block;
            margin-bottom: 7px;
            color: #3D1C24;
            font-size: 14px;
            font-weight: 600;
        }

        .edit-form-group input,
        .edit-form-group textarea {
            width: 100%;
            padding: 11px 13px;
            background-color: #ffffff;
            color: #222222;
            border: 1px solid #d9d3d1;
            border-radius: 8px;
            outline: none;
            font-size: 14px;
            font-family: inherit;
        }

        .edit-form-group input {
            height: 44px;
        }

        .edit-form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .edit-form-group input:focus,
        .edit-form-group textarea:focus {
            border-color: #DAA520;
            box-shadow: 0 0 0 3px rgba(218, 165, 32, 0.13);
        }

        .edit-form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 24px;
        }

        .cancel-edit-btn,
        .save-edit-btn {
            height: 42px;
            padding: 0 19px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .cancel-edit-btn {
            background-color: #f4f1ef;
            color: #3D1C24;
            border: 1px solid #ddd5d2;
        }

        .cancel-edit-btn:hover {
            background-color: #ebe6e3;
        }

        .save-edit-btn {
            background-color: #3D1C24;
            color: #ffffff;
            border: 1px solid #3D1C24;
        }

        .save-edit-btn:hover {
            background-color: #512632;
        }


        /* =========================================================
           Responsive
        ========================================================= */

        @media (max-width: 700px) {

            .course-form-grid,
            .assignment-grid {
                grid-template-columns: 1fr;
            }

            .course-form-group.full-width {
                grid-column: auto;
            }

            .course-form-card,
            .assignment-card {
                padding: 20px;
            }

            .edit-modal-content {
                padding: 22px;
            }
        }

    </style>

</head>


<body class="dashboard-page">


    <!-- =========================================================
         Header
    ========================================================= -->

    <header class="dashboard-header">

        <div>

            <h1>RUET Connect</h1>

            <p>Courses</p>

        </div>


        <div class="dashboard-user">

            <div class="user-name">
                <?php echo htmlspecialchars($first_name); ?>
            </div>

            <a
                href="logout.php"
                class="dashboard-logout"
            >
                Logout
            </a>

        </div>

    </header>


    <!-- =========================================================
         Navigation
    ========================================================= -->

    <nav>

        <?php if ($user_role === "student"): ?>

            <a href="student.php">Dashboard</a>

        <?php elseif ($user_role === "teacher"): ?>

            <a href="teacher.php">Dashboard</a>

        <?php elseif ($user_role === "admin"): ?>

            <a href="admin.php">Dashboard</a>

        <?php endif; ?>

        <a href="courses.php">Courses</a>

    </nav>


    <!-- =========================================================
         Main
    ========================================================= -->

    <main class="dashboard-main course-page">


        <section class="welcome-section">

            <h2>Courses</h2>

            <p>
                View and manage the courses available in RUET Connect.
            </p>

        </section>


        <!-- Messages -->

        <?php if (!empty($message)): ?>

            <div class="course-message">
                <?php echo htmlspecialchars($message); ?>
            </div>

        <?php endif; ?>


        <?php if (!empty($error)): ?>

            <div class="course-error">
                <?php echo htmlspecialchars($error); ?>
            </div>

        <?php endif; ?>


        <!-- =====================================================
             ADMIN: Add Course
        ====================================================== -->

        <?php if ($user_role === "admin"): ?>

            <section class="course-form-section">

                <div class="course-form-card">

                    <h3>Add New Course</h3>


                    <form
                        action="courses.php"
                        method="post"
                    >

                        <div class="course-form-grid">


                            <div class="course-form-group">

                                <label for="course_code">
                                    Course Code *
                                </label>

                                <input
                                    type="text"
                                    id="course_code"
                                    name="course_code"
                                    placeholder="Example: ECE 3101"
                                    maxlength="20"
                                    required
                                >

                            </div>


                            <div class="course-form-group">

                                <label for="course_name">
                                    Course Name *
                                </label>

                                <input
                                    type="text"
                                    id="course_name"
                                    name="course_name"
                                    placeholder="Example: Digital Electronics"
                                    maxlength="150"
                                    required
                                >

                            </div>


                            <div class="course-form-group">

                                <label for="department">
                                    Department *
                                </label>

                                <input
                                    type="text"
                                    id="department"
                                    name="department"
                                    placeholder="Example: ECE"
                                    maxlength="100"
                                    required
                                >

                            </div>


                            <div class="course-form-group">

                                <label for="credit">
                                    Credit *
                                </label>

                                <input
                                    type="number"
                                    id="credit"
                                    name="credit"
                                    placeholder="Example: 3.0"
                                    min="0.5"
                                    max="9.0"
                                    step="0.5"
                                    required
                                >

                            </div>


                            <div class="course-form-group full-width">

                                <label for="course_description">
                                    Course Description
                                </label>

                                <textarea
                                    id="course_description"
                                    name="course_description"
                                    placeholder="Enter a short description of the course..."
                                ></textarea>

                            </div>

                        </div>


                        <div class="course-form-actions">

                            <button
                                type="submit"
                                name="add_course"
                                class="course-submit-btn"
                            >
                                Add Course
                            </button>

                        </div>

                    </form>

                </div>

            </section>


            <!-- =================================================
                 ADMIN: Assign Course
            ================================================== -->

            <section class="assignment-section">

                <h3>Assign Course to Teacher</h3>


                <div class="assignment-card">

                    <h4>New Course Assignment</h4>


                    <form
                        action="courses.php"
                        method="post"
                    >

                        <div class="assignment-grid">


                            <div class="assignment-group">

                                <label for="assign_course_id">
                                    Course *
                                </label>

                                <select
                                    id="assign_course_id"
                                    name="assign_course_id"
                                    required
                                >

                                    <option value="">
                                        Select Course
                                    </option>

                                    <?php foreach ($courses as $course): ?>

                                        <option
                                            value="<?php echo (int)$course["id"]; ?>"
                                        >
                                            <?php
                                            echo htmlspecialchars(
                                                $course["course_code"]
                                            );
                                            ?>
                                            -
                                            <?php
                                            echo htmlspecialchars(
                                                $course["course_name"]
                                            );
                                            ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <div class="assignment-group">

                                <label for="teacher_id">
                                    Teacher *
                                </label>

                                <select
                                    id="teacher_id"
                                    name="teacher_id"
                                    required
                                >

                                    <option value="">
                                        Select Teacher
                                    </option>

                                    <?php foreach ($teachers as $teacher): ?>

                                        <option
                                            value="<?php echo (int)$teacher["id"]; ?>"
                                        >
                                            <?php
                                            echo htmlspecialchars(
                                                $teacher["full_name"]
                                            );
                                            ?>
                                            -
                                            <?php
                                            echo htmlspecialchars(
                                                $teacher["department"]
                                            );
                                            ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <div class="assignment-group">

                                <label for="year">
                                    Academic Year *
                                </label>

                                <select
                                    id="year"
                                    name="year"
                                    required
                                >

                                    <option value="">
                                        Select Year
                                    </option>

                                    <option value="1">
                                        1st Year
                                    </option>

                                    <option value="2">
                                        2nd Year
                                    </option>

                                    <option value="3">
                                        3rd Year
                                    </option>

                                    <option value="4">
                                        4th Year
                                    </option>

                                </select>

                            </div>


                            <div class="assignment-group">

                                <label for="semester">
                                    Semester *
                                </label>

                                <select
                                    id="semester"
                                    name="semester"
                                    required
                                >

                                    <option value="">
                                        Select Semester
                                    </option>

                                    <option value="1">
                                        1st Semester
                                    </option>

                                    <option value="2">
                                        2nd Semester
                                    </option>

                                </select>

                            </div>


                            <div class="assignment-group">

                                <label for="academic_session">
                                    Academic Session *
                                </label>

                                <input
                                    type="text"
                                    id="academic_session"
                                    name="academic_session"
                                    placeholder="Example: 2026-27"
                                    maxlength="20"
                                    required
                                >

                            </div>

                        </div>


                        <div class="assignment-actions">

                            <button
                                type="submit"
                                name="assign_course"
                                class="assignment-submit-btn"
                            >
                                Assign Course
                            </button>

                        </div>

                    </form>

                </div>


                <!-- Existing Assignments -->

                <div class="course-table-wrapper">

                    <?php if (count($assignments) > 0): ?>

                        <table class="assignment-table">

                            <thead>

                                <tr>

                                    <th>Course</th>

                                    <th>Teacher</th>

                                    <th>Year</th>

                                    <th>Semester</th>

                                    <th>Session</th>

                                    <th>Action</th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($assignments as $assignment): ?>

                                    <tr>

                                        <td>

                                            <strong class="course-code">
                                                <?php
                                                echo htmlspecialchars(
                                                    $assignment["course_code"]
                                                );
                                                ?>
                                            </strong>

                                            <br>

                                            <?php
                                            echo htmlspecialchars(
                                                $assignment["course_name"]
                                            );
                                            ?>

                                        </td>


                                        <td>

                                            <?php
                                            echo htmlspecialchars(
                                                $assignment["teacher_name"]
                                            );
                                            ?>

                                        </td>


                                        <td>

                                            <span class="year-badge">

                                                <?php

                                                $year_text = [
                                                    1 => "1st Year",
                                                    2 => "2nd Year",
                                                    3 => "3rd Year",
                                                    4 => "4th Year"
                                                ];

                                                echo $year_text[
                                                    (int)$assignment["year"]
                                                ];

                                                ?>

                                            </span>

                                        </td>


                                        <td>

                                            <span class="semester-badge">

                                                <?php
                                                echo (int)$assignment["semester"] === 1
                                                    ? "1st Semester"
                                                    : "2nd Semester";
                                                ?>

                                            </span>

                                        </td>


                                        <td>

                                            <span class="session-badge">

                                                <?php
                                                echo htmlspecialchars(
                                                    $assignment["academic_session"]
                                                );
                                                ?>

                                            </span>

                                        </td>


                                        <td>

                                            <form
                                                action="courses.php"
                                                method="post"
                                                onsubmit="return confirm(
                                                    'Are you sure you want to remove this course assignment?'
                                                );"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="assignment_id"
                                                    value="<?php echo (int)$assignment["id"]; ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    name="remove_assignment"
                                                    class="remove-assignment-btn"
                                                >
                                                    Remove
                                                </button>

                                            </form>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    <?php else: ?>

                        <div class="no-assignments">

                            No course assignments have been created yet.

                        </div>

                    <?php endif; ?>

                </div>

            </section>

        <?php endif; ?>


        <!-- =====================================================
             Course List
        ====================================================== -->

        <section class="course-list-section">

            <h3>Available Courses</h3>


            <?php if (count($courses) > 0): ?>

                <div class="course-table-wrapper">

                    <table class="course-table">

                        <thead>

                            <tr>

                                <th>Course Code</th>

                                <th>Course Name</th>

                                <th>Department</th>

                                <th>Credit</th>

                                <th>Description</th>

                                <?php if ($user_role === "admin"): ?>

                                    <th>Actions</th>

                                <?php endif; ?>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($courses as $course): ?>

                                <tr>

                                    <td class="course-code">

                                        <?php
                                        echo htmlspecialchars(
                                            $course["course_code"]
                                        );
                                        ?>

                                    </td>


                                    <td>

                                        <?php
                                        echo htmlspecialchars(
                                            $course["course_name"]
                                        );
                                        ?>

                                    </td>


                                    <td>

                                        <?php
                                        echo htmlspecialchars(
                                            $course["department"]
                                        );
                                        ?>

                                    </td>


                                    <td class="course-credit">

                                        <?php
                                        echo htmlspecialchars(
                                            $course["credit"]
                                        );
                                        ?>

                                    </td>


                                    <td>

                                        <?php

                                        echo !empty(
                                            $course["course_description"]
                                        )
                                            ? htmlspecialchars(
                                                $course["course_description"]
                                            )
                                            : "—";

                                        ?>

                                    </td>


                                    <?php if ($user_role === "admin"): ?>

                                        <td>

                                            <div class="course-actions">


                                                <button
                                                    type="button"
                                                    class="edit-btn"
                                                    onclick='openEditModal(
                                                        <?php
                                                        echo json_encode(
                                                            $course,
                                                            JSON_HEX_TAG |
                                                            JSON_HEX_APOS |
                                                            JSON_HEX_AMP |
                                                            JSON_HEX_QUOT
                                                        );
                                                        ?>
                                                    )'
                                                >
                                                    Edit
                                                </button>


                                                <form
                                                    action="courses.php"
                                                    method="post"
                                                    onsubmit="return confirm(
                                                        'Are you sure you want to delete this course?'
                                                    );"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="course_id"
                                                        value="<?php echo (int)$course["id"]; ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        name="delete_course"
                                                        class="delete-btn"
                                                    >
                                                        Delete
                                                    </button>

                                                </form>

                                            </div>

                                        </td>

                                    <?php endif; ?>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="course-table-wrapper">

                    <div class="no-courses">

                        No courses have been added yet.

                    </div>

                </div>

            <?php endif; ?>

        </section>

    </main>


    <!-- =========================================================
         Edit Course Modal
    ========================================================== -->

    <?php if ($user_role === "admin"): ?>

        <div
            id="editModal"
            class="edit-modal"
        >

            <div class="edit-modal-content">


                <div class="edit-modal-header">

                    <h3>Edit Course</h3>

                    <button
                        type="button"
                        class="close-modal"
                        onclick="closeEditModal()"
                    >
                        &times;
                    </button>

                </div>


                <form
                    action="courses.php"
                    method="post"
                >

                    <input
                        type="hidden"
                        id="edit_course_id"
                        name="course_id"
                    >


                    <div class="edit-form-group">

                        <label for="edit_course_code">
                            Course Code *
                        </label>

                        <input
                            type="text"
                            id="edit_course_code"
                            name="course_code"
                            maxlength="20"
                            required
                        >

                    </div>


                    <div class="edit-form-group">

                        <label for="edit_course_name">
                            Course Name *
                        </label>

                        <input
                            type="text"
                            id="edit_course_name"
                            name="course_name"
                            maxlength="150"
                            required
                        >

                    </div>


                    <div class="edit-form-group">

                        <label for="edit_department">
                            Department *
                        </label>

                        <input
                            type="text"
                            id="edit_department"
                            name="department"
                            maxlength="100"
                            required
                        >

                    </div>


                    <div class="edit-form-group">

                        <label for="edit_credit">
                            Credit *
                        </label>

                        <input
                            type="number"
                            id="edit_credit"
                            name="credit"
                            min="0.5"
                            max="9.0"
                            step="0.5"
                            required
                        >

                    </div>


                    <div class="edit-form-group">

                        <label for="edit_course_description">
                            Course Description
                        </label>

                        <textarea
                            id="edit_course_description"
                            name="course_description"
                        ></textarea>

                    </div>


                    <div class="edit-form-actions">

                        <button
                            type="button"
                            class="cancel-edit-btn"
                            onclick="closeEditModal()"
                        >
                            Cancel
                        </button>


                        <button
                            type="submit"
                            name="edit_course"
                            class="save-edit-btn"
                        >
                            Save Changes
                        </button>

                    </div>

                </form>

            </div>

        </div>

    <?php endif; ?>


    <script>

        /*
        |--------------------------------------------------------------------------
        | Edit Course Modal
        |--------------------------------------------------------------------------
        */

        function openEditModal(course) {

            document.getElementById("edit_course_id").value =
                course.id;

            document.getElementById("edit_course_code").value =
                course.course_code;

            document.getElementById("edit_course_name").value =
                course.course_name;

            document.getElementById("edit_department").value =
                course.department;

            document.getElementById("edit_credit").value =
                course.credit;

            document.getElementById("edit_course_description").value =
                course.course_description || "";

            document
                .getElementById("editModal")
                .classList
                .add("active");
        }


        function closeEditModal() {

            document
                .getElementById("editModal")
                .classList
                .remove("active");
        }


        window.addEventListener("click", function(event) {

            const modal =
                document.getElementById("editModal");

            if (modal && event.target === modal) {

                closeEditModal();
            }

        });

    </script>

</body>

</html>
