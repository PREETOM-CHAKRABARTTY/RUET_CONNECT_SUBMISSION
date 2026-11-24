
<?php

session_start();

require_once "config/database.php";

// =====================================================
// Check login
// =====================================================

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// =====================================================
// Check teacher role
// =====================================================

if ($_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit;
}

$teacher_id = (int) $_SESSION["user_id"];

// =====================================================
// Get course ID
// =====================================================

$course_id = $_GET["course_id"] ?? $_POST["course_id"] ?? null;

if (!$course_id || !is_numeric($course_id)) {
    die("Invalid course selected.");
}

$course_id = (int) $course_id;

$message = "";
$message_type = "";

// =====================================================
// Verify that the course is assigned to this teacher
// =====================================================

$course_query = "
    SELECT
        c.id,
        c.course_code,
        c.course_name,
        c.course_description,
        c.credit
    FROM courses c
    INNER JOIN course_offerings co
        ON c.id = co.course_id
    WHERE c.id = ?
      AND co.teacher_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($course_query);
$stmt->bind_param("ii", $course_id, $teacher_id);
$stmt->execute();

$course_result = $stmt->get_result();
$course = $course_result->fetch_assoc();

$stmt->close();

if (!$course) {
    die("You are not assigned to this course.");
}

// =====================================================
// Handle material deletion
// =====================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["delete_material"])
) {
    $material_id = $_POST["material_id"] ?? null;

    if (!$material_id || !is_numeric($material_id)) {
        $message = "Invalid material selected.";
        $message_type = "error";
    } else {
        $material_id = (int) $material_id;

        // Find the material uploaded by this teacher
        $find_material_query = "
            SELECT file_path
            FROM course_materials
            WHERE id = ?
              AND course_id = ?
              AND uploaded_by = ?
            LIMIT 1
        ";

        $find_stmt = $conn->prepare($find_material_query);

        $find_stmt->bind_param(
            "iii",
            $material_id,
            $course_id,
            $teacher_id
        );

        $find_stmt->execute();

        $find_result = $find_stmt->get_result();
        $material_to_delete = $find_result->fetch_assoc();

        $find_stmt->close();

        if (!$material_to_delete) {
            $message = "You are not allowed to delete this material.";
            $message_type = "error";
        } else {
            // Delete database record
            $delete_query = "
                DELETE FROM course_materials
                WHERE id = ?
                  AND course_id = ?
                  AND uploaded_by = ?
            ";

            $delete_stmt = $conn->prepare($delete_query);

            $delete_stmt->bind_param(
                "iii",
                $material_id,
                $course_id,
                $teacher_id
            );

            if (
                $delete_stmt->execute() &&
                $delete_stmt->affected_rows > 0
            ) {
                // Delete physical file
                $file_to_delete = __DIR__ . "/" .
                    $material_to_delete["file_path"];

                if (is_file($file_to_delete)) {
                    unlink($file_to_delete);
                }

                $message = "Material deleted successfully.";
                $message_type = "success";
            } else {
                $message = "Failed to delete the material.";
                $message_type = "error";
            }

            $delete_stmt->close();
        }
    }
}

// =====================================================
// Handle file upload
// =====================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["upload_material"])
) {
    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $material_type = trim($_POST["material_type"] ?? "");

    if ($title === "" || $material_type === "") {
        $message = "Please fill in all required fields.";
        $message_type = "error";
    } elseif (
        !isset($_FILES["material_file"]) ||
        $_FILES["material_file"]["error"] !== UPLOAD_ERR_OK
    ) {
        $message = "Please select a valid file.";
        $message_type = "error";
    } else {
        $file = $_FILES["material_file"];

        $max_size = 10 * 1024 * 1024; // 10 MB

        $allowed_extensions = [
            "pdf",
            "doc",
            "docx",
            "ppt",
            "pptx",
            "jpg",
            "jpeg",
            "png"
        ];

        $allowed_mime_types = [
            "application/pdf",
            "application/msword",
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            "application/vnd.ms-powerpoint",
            "application/vnd.openxmlformats-officedocument.presentationml.presentation",
            "image/jpeg",
            "image/png"
        ];

        $original_name = $file["name"];
        $file_size = $file["size"];
        $temporary_path = $file["tmp_name"];

        $extension = strtolower(
            pathinfo($original_name, PATHINFO_EXTENSION)
        );

        if ($file_size > $max_size) {
            $message = "File size must not exceed 10 MB.";
            $message_type = "error";
        } elseif (
            !in_array($extension, $allowed_extensions, true)
        ) {
            $message = "This file type is not allowed.";
            $message_type = "error";
        } else {
            // Validate MIME type
            $file_info = finfo_open(FILEINFO_MIME_TYPE);

            $mime_type = finfo_file(
                $file_info,
                $temporary_path
            );

            finfo_close($file_info);

            if (!in_array($mime_type, $allowed_mime_types, true)) {
                $message = "The uploaded file type is invalid.";
                $message_type = "error";
            } else {
                $upload_directory = __DIR__ . "/uploads/materials/";

                if (!is_dir($upload_directory)) {
                    mkdir($upload_directory, 0755, true);
                }

                try {
                    $random_name = bin2hex(random_bytes(16));
                } catch (Exception $e) {
                    $random_name = uniqid();
                }

                $new_file_name = $random_name . "." . $extension;

                $destination = $upload_directory . $new_file_name;

                $database_file_path =
                    "uploads/materials/" . $new_file_name;

                if (
                    move_uploaded_file(
                        $temporary_path,
                        $destination
                    )
                ) {
                    $insert_query = "
                        INSERT INTO course_materials
                        (
                            course_id,
                            title,
                            description,
                            file_path,
                            material_type,
                            uploaded_by
                        )
                        VALUES (?, ?, ?, ?, ?, ?)
                    ";

                    $insert_stmt = $conn->prepare($insert_query);

                    $insert_stmt->bind_param(
                        "issssi",
                        $course_id,
                        $title,
                        $description,
                        $database_file_path,
                        $material_type,
                        $teacher_id
                    );

                    if ($insert_stmt->execute()) {
                        $message = "Material uploaded successfully.";
                        $message_type = "success";
                    } else {
                        // Delete file if database insertion fails
                        if (is_file($destination)) {
                            unlink($destination);
                        }

                        $message = "File uploaded, but database saving failed.";
                        $message_type = "error";
                    }

                    $insert_stmt->close();
                } else {
                    $message = "Failed to upload the file.";
                    $message_type = "error";
                }
            }
        }
    }
}

// =====================================================
// Fetch uploaded materials
// =====================================================

$materials_query = "
    SELECT
        id,
        title,
        description,
        file_path,
        material_type,
        created_at,
        uploaded_by
    FROM course_materials
    WHERE course_id = ?
    ORDER BY created_at DESC
";

$materials_stmt = $conn->prepare($materials_query);
$materials_stmt->bind_param("i", $course_id);
$materials_stmt->execute();

$materials_result = $materials_stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?php echo htmlspecialchars($course["course_code"]); ?> -
        Course Materials | RUET Connect
    </title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: #f7f4f2;
            color: #222;
            min-height: 100vh;
        }

        .header {
            background: #3D1C24;
            color: white;
            padding: 22px 6%;
        }

        .header h1 {
            font-size: 27px;
            font-weight: 600;
        }

        .header p {
            margin-top: 6px;
            color: #f2dca2;
            font-size: 14px;
        }

        .container {
            width: 92%;
            max-width: 1200px;
            margin: 35px auto;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 22px;
            color: #3D1C24;
            text-decoration: none;
            font-weight: 600;
        }

        .back-link:hover {
            color: #DAA520;
        }

        .course-card,
        .upload-card,
        .materials-card {
            background: white;
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 25px;
            box-shadow: 0 8px 25px rgba(61, 28, 36, 0.08);
        }

        .course-card h2 {
            color: #3D1C24;
            margin-bottom: 10px;
        }

        .course-card p {
            color: #666;
            line-height: 1.6;
        }

        .section-title {
            color: #3D1C24;
            margin-bottom: 20px;
            font-size: 22px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #3D1C24;
            font-weight: 600;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #ddd;
            border-radius: 9px;
            font-size: 15px;
            font-family: inherit;
            outline: none;
        }

        input:focus,
        textarea:focus,
        select:focus {
            border-color: #DAA520;
            box-shadow: 0 0 0 3px rgba(218, 165, 32, 0.15);
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        .upload-button {
            background: #DAA520;
            color: #3D1C24;
            border: none;
            padding: 13px 24px;
            border-radius: 9px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
        }

        .upload-button:hover {
            background: #c89518;
        }

        .message {
            padding: 14px 17px;
            border-radius: 9px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .message.success {
            background: #e5f6e9;
            color: #176b32;
        }

        .message.error {
            background: #fde8e8;
            color: #a32222;
        }

        .material-item {
            border: 1px solid #eee;
            border-left: 5px solid #DAA520;
            border-radius: 10px;
            padding: 18px;
            margin-bottom: 15px;
        }

        .material-item h3 {
            color: #3D1C24;
            margin-bottom: 8px;
        }

        .material-item p {
            color: #666;
            margin-bottom: 10px;
            line-height: 1.5;
        }

        .material-meta {
            color: #888;
            font-size: 13px;
            margin-bottom: 12px;
        }

        .material-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .view-button,
        .edit-button {
            display: inline-block;
            text-decoration: none;
            padding: 9px 16px;
            border-radius: 7px;
            font-size: 14px;
            font-family: inherit;
            font-weight: 600;
        }

        .view-button {
            background: #3D1C24;
            color: white;
        }

        .view-button:hover {
            background: #542832;
        }

        .edit-button {
            background: #DAA520;
            color: #3D1C24;
        }

        .edit-button:hover {
            background: #c89518;
        }

        .delete-button {
            background: #b3261e;
            color: white;
            border: none;
            padding: 9px 16px;
            border-radius: 7px;
            font-size: 14px;
            cursor: pointer;
            font-family: inherit;
        }

        .delete-button:hover {
            background: #8f1d18;
        }

        .empty-message {
            color: #777;
            text-align: center;
            padding: 25px 0;
        }

        @media (max-width: 600px) {
            .container {
                width: 94%;
                margin: 25px auto;
            }

            .course-card,
            .upload-card,
            .materials-card {
                padding: 20px;
            }

            .header {
                padding: 20px 5%;
            }
        }
    </style>
</head>

<body>

    <header class="header">
        <h1>RUET Connect</h1>
        <p>Teacher Course Materials</p>
    </header>

    <main class="container">

        <a
            href="teacher_materials.php"
            class="back-link"
        >
            ← Back to My Courses
        </a>

        <section class="course-card">

            <h2>
                <?php echo htmlspecialchars($course["course_code"]); ?>
                -
                <?php echo htmlspecialchars($course["course_name"]); ?>
            </h2>

            <p>
                <?php
                echo htmlspecialchars(
                    $course["course_description"] ??
                    "No course description available."
                );
                ?>
            </p>

        </section>

        <?php if ($message !== ""): ?>

            <div class="message <?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>

        <?php endif; ?>

        <section class="upload-card">

            <h2 class="section-title">
                Upload New Material
            </h2>

            <form
                method="POST"
                enctype="multipart/form-data"
            >

                <input
                    type="hidden"
                    name="course_id"
                    value="<?php echo $course_id; ?>"
                >

                <div class="form-group">

                    <label for="title">
                        Material Title *
                    </label>

                    <input
                        type="text"
                        id="title"
                        name="title"
                        placeholder="Enter material title"
                        maxlength="200"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="material_type">
                        Material Type *
                    </label>

                    <select
                        id="material_type"
                        name="material_type"
                        required
                    >
                        <option value="">
                            Select material type
                        </option>

                        <option value="Lecture">
                            Lecture
                        </option>

                        <option value="Notes">
                            Notes
                        </option>

                        <option value="Assignment">
                            Assignment
                        </option>

                        <option value="Lab">
                            Lab
                        </option>

                        <option value="Question">
                            Question
                        </option>

                        <option value="Other">
                            Other
                        </option>
                    </select>

                </div>

                <div class="form-group">

                    <label for="description">
                        Description
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        placeholder="Write a short description..."
                    ></textarea>

                </div>

                <div class="form-group">

                    <label for="material_file">
                        Select File *
                    </label>

                    <input
                        type="file"
                        id="material_file"
                        name="material_file"
                        accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png"
                        required
                    >

                    <small>
                        Allowed: PDF, DOC, DOCX, PPT, PPTX, JPG, JPEG, PNG.
                        Maximum size: 10 MB.
                    </small>

                </div>

                <button
                    type="submit"
                    name="upload_material"
                    class="upload-button"
                >
                    Upload Material
                </button>

            </form>

        </section>

        <section class="materials-card">

            <h2 class="section-title">
                Uploaded Materials
            </h2>

            <?php if ($materials_result->num_rows > 0): ?>

                <?php while ($material = $materials_result->fetch_assoc()): ?>

                    <div class="material-item">

                        <h3>
                            <?php
                            echo htmlspecialchars($material["title"]);
                            ?>
                        </h3>

                        <div class="material-meta">

                            Type:
                            <?php
                            echo htmlspecialchars(
                                $material["material_type"]
                            );
                            ?>

                            |

                            Uploaded:
                            <?php
                            echo htmlspecialchars(
                                $material["created_at"]
                            );
                            ?>

                        </div>

                        <?php if (!empty($material["description"])): ?>

                            <p>
                                <?php
                                echo nl2br(
                                    htmlspecialchars(
                                        $material["description"]
                                    )
                                );
                                ?>
                            </p>

                        <?php endif; ?>

                        <div class="material-actions">

                            <a
                                href="<?php
                                    echo htmlspecialchars(
                                        $material["file_path"]
                                    );
                                ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="view-button"
                            >
                                View Material
                            </a>

                            <?php if (
                                (int) $material["uploaded_by"] === $teacher_id
                            ): ?>

                                <a
                                    href="edit_material.php?id=<?php
                                        echo (int) $material["id"];
                                    ?>"
                                    class="edit-button"
                                >
                                    Edit
                                </a>

                                <form
                                    method="POST"
                                    onsubmit="return confirm(
                                        'Are you sure you want to delete this material?'
                                    );"
                                >

                                    <input
                                        type="hidden"
                                        name="course_id"
                                        value="<?php echo $course_id; ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="material_id"
                                        value="<?php
                                            echo (int) $material["id"];
                                        ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="delete_material"
                                        class="delete-button"
                                    >
                                        Delete
                                    </button>

                                </form>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endwhile; ?>

            <?php else: ?>

                <p class="empty-message">
                    No materials have been uploaded for this course yet.
                </p>

            <?php endif; ?>

        </section>

    </main>

</body>

</html>

<?php

$materials_stmt->close();
$conn->close();

?>
