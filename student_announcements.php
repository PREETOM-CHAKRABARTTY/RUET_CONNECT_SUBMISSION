
<?php
session_start();

require_once "config/database.php";

// Allow only logged-in students
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// Fetch announcements for students
$sql = "SELECT 
            a.id,
            a.title,
            a.message,
            a.created_at,
            u.full_name AS admin_name
        FROM announcements a
        JOIN users u ON a.created_by = u.id
        WHERE a.audience IN ('all', 'students')
        ORDER BY a.created_at DESC";

$result = $conn->query($sql);

if (!$result) {
    die("Error fetching announcements: " . $conn->error);
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements | RUET Connect</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        body {
            margin: 0;
            min-height: 100vh;
            background: #f7f5f2;
            font-family: Arial, sans-serif;
            color: #292526;
        }

        .announcement-container {
            width: min(1000px, 92%);
            margin: 40px auto;
        }

        .page-heading {
            margin-bottom: 28px;
        }

        .page-heading h1 {
            margin: 0 0 8px;
            color: #3D1C24;
            font-size: 30px;
        }

        .page-heading p {
            margin: 0;
            color: #777;
            font-size: 15px;
        }

        .announcement-card {
            background: #fff;
            border: 1px solid #e9e3df;
            border-left: 5px solid #DAA520;
            border-radius: 10px;
            padding: 24px;
            margin-bottom: 18px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.04);
        }

        .announcement-card h2 {
            color: #3D1C24;
            font-size: 21px;
            margin: 0 0 12px;
        }

        .announcement-message {
            color: #494345;
            font-size: 15px;
            line-height: 1.7;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .announcement-meta {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 20px;
            padding-top: 13px;
            border-top: 1px solid #eee9e5;
            color: #888;
            font-size: 13px;
        }

        .empty-state {
            background: #fff;
            border: 1px solid #e9e3df;
            border-radius: 10px;
            padding: 45px 20px;
            text-align: center;
            color: #777;
        }

        .empty-state h2 {
            color: #3D1C24;
            margin-top: 0;
        }

        .back-button {
            display: inline-block;
            margin-bottom: 25px;
            padding: 10px 18px;
            background: #3D1C24;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            transition: 0.2s;
        }

        .back-button:hover {
            background: #542a35;
        }

        @media (max-width: 600px) {
            .announcement-container {
                margin: 25px auto;
            }

            .announcement-card {
                padding: 18px;
            }

            .page-heading h1 {
                font-size: 25px;
            }
        }
    </style>
</head>

<body>

<div class="announcement-container">

    <a href="student.php" class="back-button">
        &larr; Back to Dashboard
    </a>

    <div class="page-heading">
        <h1>Announcements</h1>
        <p>Stay updated with the latest announcements from RUET Connect.</p>
    </div>

    <?php if ($result->num_rows > 0): ?>

        <?php while ($announcement = $result->fetch_assoc()): ?>

            <div class="announcement-card">

                <h2><?php echo e($announcement['title']); ?></h2>

                <div class="announcement-message"><?php
                    echo e($announcement['message']);
                ?></div>

                <div class="announcement-meta">
                    <span>
                        Posted by: <?php echo e($announcement['admin_name']); ?>
                    </span>

                    <span>
                        <?php
                        echo date(
                            "d M Y, h:i A",
                            strtotime($announcement['created_at'])
                        );
                        ?>
                    </span>
                </div>

            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="empty-state">
            <h2>No Announcements Yet</h2>
            <p>There are currently no announcements available for students.</p>
        </div>

    <?php endif; ?>

</div>

</body>
</html>

<?php
$conn->close();
?>
