<?php

session_start();

if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: login.php");
    exit;
}

require_once "config/database.php";

$admin_id = (int) $_SESSION['user_id'];

$message = "";
$error = "";

/*
|--------------------------------------------------------------------------
| Delete Announcement
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["delete_announcement"])
) {

    $announcement_id = (int) $_POST["announcement_id"];

    $delete_sql = "DELETE FROM announcements
                   WHERE id = ? AND created_by = ?";

    $delete_stmt = $conn->prepare($delete_sql);

    if ($delete_stmt) {

        $delete_stmt->bind_param(
            "ii",
            $announcement_id,
            $admin_id
        );

        if ($delete_stmt->execute()) {
            $message = "Announcement deleted successfully.";
        } else {
            $error = "Unable to delete the announcement.";
        }

        $delete_stmt->close();

    } else {
        $error = "Database error.";
    }
}

/*
|--------------------------------------------------------------------------
| Create Announcement
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["create_announcement"])
) {

    $title = trim($_POST["title"] ?? "");
    $announcement_message = trim($_POST["message"] ?? "");
    $audience = $_POST["audience"] ?? "";

    $allowed_audiences = [
        "all",
        "students",
        "teachers"
    ];

    if ($title === "") {

        $error = "Please enter an announcement title.";

    } elseif ($announcement_message === "") {

        $error = "Please enter the announcement message.";

    } elseif (!in_array($audience, $allowed_audiences, true)) {

        $error = "Please select a valid audience.";

    } else {

        $insert_sql = "INSERT INTO announcements
                       (
                           title,
                           message,
                           audience,
                           created_by
                       )
                       VALUES (?, ?, ?, ?)";

        $insert_stmt = $conn->prepare($insert_sql);

        if ($insert_stmt) {

            $insert_stmt->bind_param(
                "sssi",
                $title,
                $announcement_message,
                $audience,
                $admin_id
            );

            if ($insert_stmt->execute()) {

                $message = "Announcement published successfully.";

            } else {

                $error = "Unable to publish the announcement.";
            }

            $insert_stmt->close();

        } else {

            $error = "Database error while creating the announcement.";
        }
    }
}

/*
|--------------------------------------------------------------------------
| Fetch Announcements
|--------------------------------------------------------------------------
*/

$announcements_sql = "SELECT
                          id,
                          title,
                          message,
                          audience,
                          created_at,
                          updated_at
                      FROM announcements
                      ORDER BY created_at DESC";

$announcements_result = $conn->query($announcements_sql);

$announcements = [];

if ($announcements_result) {

    while ($announcement = $announcements_result->fetch_assoc()) {
        $announcements[] = $announcement;
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

    <title>Announcements | RUET Connect</title>

    <link rel="stylesheet" href="css/style.css">

    <style>

        body {
            margin: 0;
            background: #f8f5f2;
            color: #292929;
            font-family: Arial, sans-serif;
        }

        .announcement-page {
            min-height: 100vh;
        }

        .announcement-header {
            background: #3D1C24;
            color: white;
            padding: 24px 5%;
        }

        .announcement-header-content {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 24px;
        }

        .announcement-header h1 {
            margin: 0;
            font-size: 30px;
        }

        .announcement-header p {
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

        .announcement-container {
            width: 90%;
            max-width: 1200px;

            margin: 35px auto;
        }

        .announcement-card {
            background: white;

            border-radius: 16px;

            padding: 28px;

            margin-bottom: 28px;

            box-shadow:
                0 8px 25px rgba(61, 28, 36, 0.08);
        }

        .announcement-card h2 {
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
        .form-group textarea,
        .form-group select {

            width: 100%;

            box-sizing: border-box;

            padding: 13px;

            border: 1px solid #ddd2d5;

            border-radius: 8px;

            font-size: 15px;

            font-family: inherit;
        }

        .form-group textarea {

            min-height: 140px;

            resize: vertical;
        }

        .publish-button {

            background: #DAA520;

            color: #3D1C24;

            border: none;

            padding: 13px 22px;

            border-radius: 8px;

            font-weight: 700;

            cursor: pointer;
        }

        .publish-button:hover {
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

        .announcement-list {

            display: flex;

            flex-direction: column;

            gap: 18px;
        }

        .announcement-item {

            border: 1px solid #eee;

            border-radius: 12px;

            padding: 20px;

            background: #fff;
        }

        .announcement-item-header {

            display: flex;

            align-items: flex-start;

            justify-content: space-between;

            gap: 20px;

            margin-bottom: 12px;
        }

        .announcement-item h3 {

            margin: 0;

            color: #3D1C24;

            font-size: 19px;
        }

        .audience-badge {

            display: inline-block;

            background: #f4e7bd;

            color: #3D1C24;

            padding: 6px 10px;

            border-radius: 6px;

            font-size: 12px;

            font-weight: 700;

            white-space: nowrap;
        }

        .announcement-message {

            margin: 0 0 14px;

            line-height: 1.6;

            color: #444;

            white-space: pre-wrap;
        }

        .announcement-date {

            font-size: 13px;

            color: #777;
        }

        .announcement-actions {

            margin-top: 15px;

            padding-top: 15px;

            border-top: 1px solid #eee;
        }

        .delete-button {

            background: #b83232;

            color: white;

            border: none;

            padding: 8px 13px;

            border-radius: 6px;

            cursor: pointer;
        }

        .delete-button:hover {

            background: #922626;
        }

        .empty-state {

            padding: 30px;

            text-align: center;

            color: #777;

            background: #fafafa;

            border-radius: 10px;
        }

        @media (max-width: 700px) {

            .announcement-header-content {

                align-items: flex-start;

                flex-direction: column;
            }

            .announcement-header h1 {
                font-size: 24px;
            }

            .announcement-container {
                width: 94%;
            }

            .announcement-card {
                padding: 20px;
            }

            .announcement-item-header {
                flex-direction: column;
            }

        }

    </style>

</head>

<body>

<div class="announcement-page">

    <header class="announcement-header">

        <div class="announcement-header-content">

            <div>

                <h1>
                    Announcement Management
                </h1>

                <p>
                    Publish important announcements for RUET-Connect users.
                </p>

            </div>

            <a
                href="admin.php"
                class="dashboard-button"
            >
                ← Back to Dashboard
            </a>

        </div>

    </header>


    <main class="announcement-container">

        <?php if ($message !== ""): ?>

            <div class="alert success">
                <?php
                echo htmlspecialchars($message);
                ?>
            </div>

        <?php endif; ?>


        <?php if ($error !== ""): ?>

            <div class="alert danger">
                <?php
                echo htmlspecialchars($error);
                ?>
            </div>

        <?php endif; ?>


        <!-- Create Announcement -->

        <section class="announcement-card">

            <h2>
                Create Announcement
            </h2>

            <form method="POST">

                <div class="form-group">

                    <label for="title">
                        Announcement Title
                    </label>

                    <input
                        type="text"
                        name="title"
                        id="title"
                        maxlength="200"
                        placeholder="Example: Midterm Examination Schedule"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="message">
                        Announcement Message
                    </label>

                    <textarea
                        name="message"
                        id="message"
                        placeholder="Write the announcement here..."
                        required
                    ></textarea>

                </div>


                <div class="form-group">

                    <label for="audience">
                        Audience
                    </label>

                    <select
                        name="audience"
                        id="audience"
                        required
                    >

                        <option value="">
                            Select Audience
                        </option>

                        <option value="all">
                            Everyone
                        </option>

                        <option value="students">
                            Students Only
                        </option>

                        <option value="teachers">
                            Teachers Only
                        </option>

                    </select>

                </div>


                <button
                    type="submit"
                    name="create_announcement"
                    class="publish-button"
                >
                    Publish Announcement
                </button>

            </form>

        </section>


        <!-- Existing Announcements -->

        <section class="announcement-card">

            <h2>
                Published Announcements
            </h2>


            <?php if (empty($announcements)): ?>

                <div class="empty-state">

                    No announcements have been published yet.

                </div>

            <?php else: ?>

                <div class="announcement-list">

                    <?php foreach ($announcements as $announcement): ?>

                        <article class="announcement-item">

                            <div class="announcement-item-header">

                                <h3>
                                    <?php
                                    echo htmlspecialchars(
                                        $announcement["title"]
                                    );
                                    ?>
                                </h3>


                                <span class="audience-badge">

                                    <?php

                                    if (
                                        $announcement["audience"]
                                        === "all"
                                    ) {
                                        echo "Everyone";

                                    } elseif (
                                        $announcement["audience"]
                                        === "students"
                                    ) {
                                        echo "Students Only";

                                    } else {
                                        echo "Teachers Only";
                                    }

                                    ?>

                                </span>

                            </div>


                            <p class="announcement-message">
                                <?php
                                echo htmlspecialchars(
                                    $announcement["message"]
                                );
                                ?>
                            </p>


                            <div class="announcement-date">

                                Published:
                                <?php
                                echo htmlspecialchars(
                                    $announcement["created_at"]
                                );
                                ?>

                            </div>


                            <div class="announcement-actions">

                                <form
                                    method="POST"
                                    onsubmit="return confirm(
                                        'Delete this announcement?'
                                    );"
                                >

                                    <input
                                        type="hidden"
                                        name="announcement_id"
                                        value="<?php
                                        echo (int)
                                            $announcement["id"];
                                        ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="delete_announcement"
                                        class="delete-button"
                                    >
                                        Delete
                                    </button>

                                </form>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>

</body>

</html>
