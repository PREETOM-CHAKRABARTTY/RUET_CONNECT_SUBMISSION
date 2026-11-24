
<?php

session_start();

require_once "config/database.php";

// Check login
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Only teachers can edit materials
if ($_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit;
}

$teacher_id = (int) $_SESSION["user_id"];

$material_id = $_GET["id"] ?? $_POST["material_id"] ?? null;

if (!$material_id || !is_numeric($material_id)) {
    die("Invalid material selected.");
}

$material_id = (int) $material_id;

// Fetch material belonging to this teacher
$fetch_query = "
    SELECT
        cm.id,
        cm.course_id,
        cm.title,
        cm.description,
        cm.file_path,
        cm.material_type,
        c.course_code,
        c.course_name
    FROM course_materials cm
    INNER JOIN courses c
        ON cm.course_id = c.id
    WHERE cm.id = ?
      AND cm.uploaded_by = ?
    LIMIT 1
";

$fetch_stmt = $conn->prepare($fetch_query);
$fetch_stmt->bind_param("ii", $material_id, $teacher_id);
$fetch_stmt->execute();

$fetch_result = $fetch_stmt->get_result();
$material = $fetch_result->fetch_assoc();

$fetch_stmt->close();

if (!$material) {
    die("You are not allowed to edit this material.");
}

$message = "";
$message_type = "";

// =====================================================
// Handle material update
// =====================================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["update_material"])
) {
    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $material_type = trim($_POST["material_type"] ?? "");

    if ($title === "" || $material_type === "") {
        $message = "Please fill in all required fields.";
        $message_type = "error";
    } else {
        $new_file_path = $material["file_path"];
        $new_uploaded_file = false;
        $new_file_destination = "";

        // Check whether a replacement file was selected
        if (
            isset($_FILES["material_file"]) &&
            $_FILES["material_file"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {
            $file = $_FILES["material_file"];

            if ($file["error"] !== UPLOAD_ERR_OK) {
                $message = "There was a problem uploading the replacement file.";
                $message_type = "error";
            } else {
                $max_size = 10 * 1024 * 1024;

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
                    $file_info = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_file(
                        $file_info,
                        $temporary_path
                    );
                    finfo_close($file_info);

                    if (
                        !in_array(
                            $mime_type,
                            $allowed_mime_types,
                            true
                        )
                    ) {
                        $message = "The replacement file type is invalid.";
                        $message_type = "error";
                    } else {
                        $upload_directory =
                            __DIR__ . "/uploads/materials/";

                        if (!is_dir($upload_directory)) {
                            mkdir($upload_directory, 0755, true);
                        }

                        try {
                            $random_name = bin2hex(
                                random_bytes(16)
                            );
                        } catch (Exception $e) {
                            $random_name = uniqid();
                        }

                        $new_file_name =
                            $random_name . "." . $extension;

                        $new_file_destination =
                            $upload_directory . $new_file_name;

                        $new_file_path =
                            "uploads/materials/" . $new_file_name;

                        if (
                            move_uploaded_file(
                                $temporary_path,
                                $new_file_destination
                            )
                        ) {
                            $new_uploaded_file = true;
                        } else {
                            $message = "Failed to upload the replacement file.";
                            $message_type = "error";
                        }
                    }
                }
            }
        }

        // Continue only if there is no file-upload error
        if ($message_type !== "error") {
            $update_query = "
                UPDATE course_materials
                SET
                    title = ?,
                    description = ?,
                    material_type = ?,
                    file_path = ?
                WHERE id = ?
                  AND uploaded_by = ?
            ";

            $update_stmt = $conn->prepare($update_query);

            $update_stmt->bind_param(
                "ssssii",
                $title,
                $description,
                $material_type,
                $new_file_path,
                $material_id,
                $teacher_id
            );

            if ($update_stmt->execute()) {
                // Remove the old file only after successful update
                if ($new_uploaded_file) {
                    $old_file = __DIR__ . "/" . $material["file_path"];

                    if (
                        is_file($old_file) &&
                        realpath($old_file) !==
                        realpath($new_file_destination)
                    ) {
                        unlink($old_file);
                    }
                }

                $message = "Material updated successfully.";
                $message_type = "success";

                // Update displayed values
                $material["title"] = $title;
                $material["description"] = $description;
                $material["material_type"] = $material_type;
                $material["file_path"] = $new_file_path;
            } else {
                // Remove newly uploaded file if database update fails
                if (
                    $new_uploaded_file &&
                    is_file($new_file_destination)
                ) {
                    unlink($new_file_destination);
                }

                $message = "Failed to update the material.";
                $message_type = "error";
            }

            $update_stmt->close();
        }
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

    <title>Edit Material | RUET Connect</title>

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
            max-width: 900px;
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

        .card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(61, 28, 36, 0.08);
        }

        .course-info {
            color: #DAA520;
            font-weight: 700;
            margin-bottom: 8px;
        }

        h2 {
            color: #3D1C24;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 20px;
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
            min-height: 120px;
            resize: vertical;
        }

        .current-file {
            background: #f7f4f2;
            border-left: 4px solid #DAA520;
            padding: 12px;
            margin-bottom: 10px;
            overflow-wrap: anywhere;
        }

        .help-text {
            color: #777;
            font-size: 13px;
            margin-top: 7px;
        }

        .update-button {
            background: #DAA520;
            color: #3D1C24;
            border: none;
            padding: 13px 25px;
            border-radius: 9px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
        }

        .update-button:hover {
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

        @media (max-width: 600px) {
            .container {
                width: 94%;
                margin: 25px auto;
            }

            .card {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

    <header class="header">
        <h1>RUET Connect</h1>
        <p>Edit Course Material</p>
    </header>

    <main class="container">

        <a
            href="teacher_course_materials.php?course_id=<?php
                echo (int) $material["course_id"];
            ?>"
            class="back-link"
        >
            ← Back to Course Materials
        </a>

        <section class="card">

            <div class="course-info">
                <?php echo htmlspecialchars($material["course_code"]); ?>
            </div>

            <h2>
                Edit Material -
                <?php echo htmlspecialchars($material["course_name"]); ?>
            </h2>

            <?php if ($message !== ""): ?>
                <div
                    class="message <?php
                        echo htmlspecialchars($message_type);
                    ?>"
                >
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form
                method="POST"
                enctype="multipart/form-data"
            >

                <input
                    type="hidden"
                    name="material_id"
                    value="<?php echo $material_id; ?>"
                >

                <div class="form-group">
                    <label for="title">Material Title *</label>

                    <input
                        type="text"
                        id="title"
                        name="title"
                        maxlength="200"
                        value="<?php echo htmlspecialchars(
                            $material["title"]
                        ); ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="material_type">Material Type *</label>

                    <select
                        id="material_type"
                        name="material_type"
                        required
                    >
                        <option value="">Select material type</option>

                        <?php
                        $types = [
                            "Lecture",
                            "Notes",
                            "Assignment",
                            "Lab",
                            "Question",
                            "Other"
                        ];
                        ?>

                        <?php foreach ($types as $type): ?>
                            <option
                                value="<?php echo $type; ?>"
                                <?php
                                echo $material["material_type"] === $type
                                    ? "selected"
                                    : "";
                                ?>
                            >
                                <?php echo $type; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>

                    <textarea
                        id="description"
                        name="description"
                    ><?php echo htmlspecialchars(
                        $material["description"] ?? ""
                    ); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Current File</label>

                    <div class="current-file">
                        <?php echo htmlspecialchars(
                            basename($material["file_path"])
                        ); ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="material_file">
                        Replace File (Optional)
                    </label>

                    <input
                        type="file"
                        id="material_file"
                        name="material_file"
                        accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png"
                    >

                    <p class="help-text">
                        Leave empty to keep the current file.
                        Maximum size: 10 MB.
                    </p>
                </div>

                <button
                    type="submit"
                    name="update_material"
                    class="update-button"
                >
                    Update Material
                </button>

            </form>

        </section>

    </main>

</body>

</html>

<?php
$conn->close();
?>
