<?php
require_once 'auth.php';
require_once 'db_config.php';
require_login();

// Fetch logs with user email
$logsRes = $conn->query("
    SELECT logs.id, logs.action, logs.created_at, users.email
    FROM logs
    LEFT JOIN users ON users.id = logs.user_id
    ORDER BY logs.id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Activity Logs - Swinburne Alumni Survey</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/main.css">
    <link rel="stylesheet" href="styles/createsurvey.css">
		<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet"> 
    <meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body>
<!-- Navigation Bar -->
<?php include 'navbar.php'; ?>

<main class="container py-4">
    <div class="card shadow-sm">
        <div class="card-body">
            <h3 class="card-title mb-3">Activity Logs</h3>
            <p class="text-muted">View all system actions performed by users.</p>

            <?php if ($logsRes && $logsRes->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-sm align-middle">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th width="180">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($log = $logsRes->fetch_assoc()): ?>
                                <tr>
                                    <?php
                                    // Sanitize and prepare action text
                                    $actionText = htmlspecialchars($log['action'] ?? '[Unknown Action]');

                                    // Extract last word as email (if exists)
                                    $parts = explode(' ', $actionText);
                                    $email = end($parts);

                                    // Color based on Approved/Rejected
                                    if ($email) {
                                        if (str_contains($actionText, 'Approved')) {
                                            $coloredEmail = "<span style='color:green;font-weight:bold;'>$email</span>";
                                            $actionText = str_replace($email, $coloredEmail, $actionText);
                                        } elseif (str_contains($actionText, 'Rejected')) {
                                            $coloredEmail = "<span style='color:red;font-weight:bold;'>$email</span>";
                                            $actionText = str_replace($email, $coloredEmail, $actionText);
                                        }
                                    }
                                    ?>

                                    <tr>
                                        <td><?= htmlspecialchars($log['email'] ?? 'Unknown') ?></td>
                                        <td><?= $actionText ?></td>
                                        <td><?= htmlspecialchars($log['created_at']) ?></td>
                                    </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-secondary">No logs yet.</div>
            <?php endif; ?>
        </div>
    </div>
</main>

<footer class="site-footer fixed-bottom py-3 mt-auto">
    <div class="container text-center">
        <p class="mb-0">ICT30017 ICT PROJECT A | TEAM Deadline Dominators</p>
    </div>
</footer>

<script src="js/main.js"></script>
<script>
function logout() {
    if (confirm("Logout?")) window.location.href = "logout.php";
}
</script>

</body>
</html>