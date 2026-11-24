
<?php

session_start();

if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] !== 'teacher'
) {
    header("Location: login.php");
    exit;
}

require_once "config/database.php";

$teacher_id = (int) $_SESSION['user_id'];

$message = "";
$error = "";

/*
|--------------------------------------------------------------------------
| Delete marks file
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_marks"])) {

    $marks_id = (int) $_POST["marks_id"];

    $sql = "SELECT file_path
            FROM marks_files
            WHERE id = ? AND teacher_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $marks_id, $teacher_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $file = $result->fetch_assoc();

    $stmt->close();

    if ($file) {

        $delete_sql = "DELETE FROM marks_files
                       WHERE id = ? AND teacher_id = ?";

        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("ii", $marks_id, $teacher_id);

        if ($delete_stmt->execute()) {

            $absolute_path = __DIR__ . "/" . $file["file_path"];

            if (file_exists($absolute_path)) {
                unlink($absolute_path);
            }

            $message = "Marks file deleted successfully.";

        } else {
            $error = "Unable to delete the marks file.";
        }

        $delete_stmt->close();

    } else {
        $error = "You are not authorized to delete this file.";
    }
}

/*
|--------------------------------------------------------------------------
| Upload marks file
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["upload_marks"])) {

    $course_id = (int) $_POST["course_id"];
    $title = trim($_POST["title"]);
    $description = trim($_POST["description"]);

    if ($course_id <= 0 || $title === "") {

        $error = "Please select a course and enter a title.";

    } elseif (
        !isset($_FILES["marks_file"]) ||
        $_FILES["marks_file"]["error"] !== UPLOAD_ERR_OK
    ) {

        $error = "Please select a valid marks file.";

    } else {

        /*
        | Verify that this course is assigned to the logged-in teacher
        */

        $course_sql = "SELECT c.id, c.course_code, c.course_name
                       FROM courses c
                       INNER JOIN course_offerings co
                           ON c.id = co.course_id
                       WHERE co.course_id = ?
                         AND co.teacher_id = ?";

        $course_stmt = $conn->prepare($course_sql);
        $course_stmt->bind_param("ii", $course_id, $teacher_id);
        $course_stmt->execute();

        $course_result = $course_stmt->get_result();
        $assigned_course = $course_result->fetch_assoc();

        $course_stmt->close();

        if (!$assigned_course) {

            $error = "You are not assigned to this course.";

        } else {

            $file = $_FILES["marks_file"];

            $original_name = $file["name"];
            $temporary_path = $file["tmp_name"];
            $file_size = $file["size"];

            $extension = strtolower(
                pathinfo($original_name, PATHINFO_EXTENSION)
            );

            $allowed_extensions = [
                "csv",
                "xls",
                "xlsx",
                "pdf"
            ];

            $max_file_size = 10 * 1024 * 1024;

            if (!in_array($extension, $allowed_extensions, true)) {

                $error = "Only CSV, XLS, XLSX, and PDF files are allowed.";

            } elseif ($file_size > $max_file_size) {

                $error = "The file size must not exceed 10 MB.";

            } else {

                $upload_directory = __DIR__ . "/uploads/marks/";

                if (!is_dir($upload_directory)) {
                    mkdir($upload_directory, 0755, true);
                }

                $server_filename =
                    bin2hex(random_bytes(16)) . "." . $extension;

                $destination = $upload_directory . $server_filename;

                $relative_path =
                    "uploads/marks/" . $server_filename;

                if (move_uploaded_file($temporary_path, $destination)) {

                    $insert_sql = "INSERT INTO marks_files
                                   (
                                       course_id,
                                       teacher_id,
                                       title,
                                       description,
                                       file_path,
                                       file_type
                                   )
                                   VALUES (?, ?, ?, ?, ?, ?)";

                    $insert_stmt = $conn->prepare($insert_sql);

                    $insert_stmt->bind_param(
                        "iissss",
                        $course_id,
                        $teacher_id,
                        $title,
                        $description,
                        $relative_path,
                        $extension
                    );

                    if ($insert_stmt->execute()) {

                        $message = "Marks file uploaded successfully.";

                    } else {

                        if (file_exists($destination)) {
                            unlink($destination);
                        }

                        $error = "Database error while saving the marks file.";
                    }

                    $insert_stmt->close();

                } else {
                    $error = "Unable to upload the file.";
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Fetch assigned courses
|--------------------------------------------------------------------------
*/

$courses_sql = "SELECT DISTINCT
                    c.id,
                    c.course_code,
                    c.course_name,
                    c.credit
                FROM courses c
                INNER JOIN course_offerings co
                    ON c.id = co.course_id
                WHERE co.teacher_id = ?
                ORDER BY c.course_code ASC";

$courses_stmt = $conn->prepare($courses_sql);
$courses_stmt->bind_param("i", $teacher_id);
$courses_stmt->execute();

$courses_result = $courses_stmt->get_result();

$courses = [];

while ($course = $courses_result->fetch_assoc()) {
    $courses[] = $course;
}

$courses_stmt->close();

/*
|--------------------------------------------------------------------------
| Fetch uploaded marks files
|--------------------------------------------------------------------------
*/

$files_sql = "SELECT
                  mf.id,
                  mf.title,
                  mf.description,
                  mf.file_path,
                  mf.file_type,
                  mf.uploaded_at,
                  c.course_code,
                  c.course_name
              FROM marks_files mf
              INNER JOIN courses c
                  ON mf.course_id = c.id
              WHERE mf.teacher_id = ?
              ORDER BY mf.uploaded_at DESC";

$files_stmt = $conn->prepare($files_sql);
$files_stmt->bind_param("i", $teacher_id);
$files_stmt->execute();

$files_result = $files_stmt->get_result();

$marks_files = [];

while ($marks_file = $files_result->fetch_assoc()) {
    $marks_files[] = $marks_file;
}

$files_stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Teacher Marks | RUET Connect</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        body {
            margin: 0;
            background: #f8f5f2;
            color: #292929;
            font-family: Arial, sans-serif;
        }

        .marks-page {
            min-height: 100vh;
        }

        .marks-header {
            background: #3D1C24;
            color: white;
            padding: 24px 5%;
        }

        .marks-header-content {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;

            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }

        .marks-header h1 {
            margin: 0;
            font-size: 30px;
        }

        .marks-header p {
            margin: 8px 0 0;
            color: #f0dca4;
        }

        .dashboard-button {
            display: inline-block;
            background: #DAA520;
            color: #3D1C24;
            text-decoration: none;
            padding: 12px 18px;
            border-radius: 8px;
            font-weight: 700;
            white-space: nowrap;
            transition: background 0.2s ease;
        }

        .dashboard-button:hover {
            background: #c49318;
        }

        .marks-container {
            width: 90%;
            max-width: 1200px;
            margin: 35px auto;
        }

        .marks-card {
            background: white;
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 28px;
            box-shadow: 0 8px 25px rgba(61, 28, 36, 0.08);
        }

        .marks-card h2 {
            margin-top: 0;
            color: #3D1C24;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 13px;
            border: 1px solid #ddd2d5;
            border-radius: 8px;
            font-size: 15px;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .submit-button {
            background: #DAA520;
            color: #3D1C24;
            border: none;
            padding: 13px 22px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
        }

        .submit-button:hover {
            background: #c49318;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .success {
            background: #e4f5e9;
            color: #216b39;
        }

        .danger {
            background: #fbe5e5;
            color: #9b2525;
        }

        .file-table-wrapper {
            overflow-x: auto;
        }

        .file-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        .file-table th,
        .file-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .file-table th {
            background: #3D1C24;
            color: white;
        }

        .file-table tr:hover {
            background: #fffaf0;
        }

        .view-link {
            color: #3D1C24;
            font-weight: 700;
            text-decoration: none;
            margin-right: 12px;
        }

        .view-link:hover {
            color: #DAA520;
        }

        .delete-button {
            background: #b83232;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
        }

        .delete-button:hover {
            background: #922626;
        }

        .empty-state {
            padding: 25px;
            text-align: center;
            color: #777;
            background: #fafafa;
            border-radius: 10px;
        }

        @media (max-width: 700px) {

            .marks-header-content {
                align-items: flex-start;
                flex-direction: column;
            }

            .marks-header h1 {
                font-size: 24px;
            }

            .dashboard-button {
                margin-top: 5px;
            }

            .marks-container {
                width: 94%;
            }

            .marks-card {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

<div class="marks-page">

    <header class="marks-header">

        <div class="marks-header-content">

            <div>
                <h1>Marks Management</h1>
                <p>Upload and manage marks files for your assigned courses.</p>
            </div>

            <a href="teacher.php" class="dashboard-button">
                ← Back to Dashboard
            </a>

        </div>

    </header>

    <main class="marks-container">

        <?php if ($message !== ""): ?>
            <div class="alert success">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="alert danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <section class="marks-card">

            <h2>Upload Marks File</h2>

            <?php if (empty($courses)): ?>

                <div class="empty-state">
                    No courses are currently assigned to you.
                </div>

            <?php else: ?>

                <form method="POST" enctype="multipart/form-data">

                    <div class="form-group">
                        <label for="course_id">Select Course</label>

                        <select name="course_id" id="course_id" required>
                            <option value="">Choose an assigned course</option>

                            <?php foreach ($courses as $course): ?>

                                <option value="<?php echo (int) $course["id"]; ?>">
                                    <?php
                                    echo htmlspecialchars(
                                        $course["course_code"] .
                                        " - " .
                                        $course["course_name"]
                                    );
                                    ?>
                                </option>

                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="title">Marks File Title</label>

                        <input
                            type="text"
                            name="title"
                            id="title"
                            placeholder="Example: ECE 3101 Final Marks"
                            maxlength="200"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>

                        <textarea
                            name="description"
                            id="description"
                            placeholder="Optional description"
                        ></textarea>
                    </div>

                    <div class="form-group">
                        <label for="marks_file">Select Marks File</label>

                        <input
                            type="file"
                            name="marks_file"
                            id="marks_file"
                            accept=".csv,.xls,.xlsx,.pdf"
                            required
                        >

                        <small>
                            Allowed formats: CSV, XLS, XLSX, PDF. Maximum size: 10 MB.
                        </small>
                    </div>

                    <button
                        type="submit"
                        name="upload_marks"
                        class="submit-button"
                    >
                        Upload Marks
                    </button>

                </form>

            <?php endif; ?>

        </section>

        <section class="marks-card">

            <h2>Uploaded Marks Files</h2>

            <?php if (empty($marks_files)): ?>

                <div class="empty-state">
                    You have not uploaded any marks files yet.
                </div>

            <?php else: ?>

                <div class="file-table-wrapper">

                    <table class="file-table">

                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Uploaded At</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($marks_files as $file): ?>

                                <tr>

                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $file["course_code"] .
                                            " - " .
                                            $file["course_name"]
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($file["title"]); ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo strtoupper(
                                            htmlspecialchars($file["file_type"])
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($file["uploaded_at"]); ?>
                                    </td>

                                    <td>

                                        <a
                                            class="view-link"
                                            href="<?php echo htmlspecialchars($file["file_path"]); ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            View
                                        </a>

                                        <form
                                            method="POST"
                                            style="display: inline;"
                                            onsubmit="return confirm('Delete this marks file?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="marks_id"
                                                value="<?php echo (int) $file["id"]; ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="delete_marks"
                                                class="delete-button"
                                            >
                                                Delete
                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>

</body>
</html>
