<?php
require_once 'auth.php';
require_once 'db_config.php';
require_once 'log_action.php';

require_login();

// Handle AJAX approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);
    $action = $data['action'] ?? 'approve';

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'invalid id']);
        exit;
    }

    // Get user info
    $getUser = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
    $getUser->bind_param("i", $id);
    $getUser->execute();
    $emailRes = $getUser->get_result();

    if ($emailRes->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'user not found']);
        exit;
    }

    $userInfo = $emailRes->fetch_assoc();
    $targetEmail = $userInfo['email'];

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE users SET approved = 1 WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            // Send email ONLY for approve action
            $subject = "Your Alumni Survey Account Has Been Approved";
            $body = "Hello,\n\nYour account has been approved by the admin. You can now log in using your credentials.\n\nLogin here: " . BASE_URL . "login.html\n\nThank you.";
            
            // Include mailer.php only for approve action
            require_once 'mailer.php';
            $emailResult = send_email($targetEmail, $subject, $body);
            
            // Log email result
            if ($emailResult !== true) {
                error_log("Email sending failed: " . $emailResult);
            }
            
            log_action("Approved new user $targetEmail");
            echo json_encode(['success' => true]);
            $stmt->close();
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
            $stmt->close();
            exit;
        }
        
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            // NO EMAIL for reject action
            log_action("Rejected (deleted) user $targetEmail");
            echo json_encode(['success' => true]);
            $stmt->close();
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
            $stmt->close();
            exit;
        }
    }
}

// GET: render approval UI
$usersRes = $conn->query("
    SELECT id, email, created_at 
    FROM users 
    WHERE approved = 0 AND email_verified = 1
    ORDER BY created_at ASC
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>User Approvals - Swinburne Alumni Survey</title>
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
        <h3 class="card-title mb-3">Approve New Users</h3>
        <p class="text-muted">Approve newly registered users before they can login.</p>

        <?php if ($usersRes && $usersRes->num_rows > 0): ?>
          <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
              <thead>
                <tr>
                  <th>Email</th>
                  <th>Registered</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="usersTableBody">
                <?php while ($u = $usersRes->fetch_assoc()): ?>
                  <tr id="userRow<?= htmlspecialchars($u['id']) ?>">
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['created_at']) ?></td>
                    <td class="text-end">
                      <button class="btn btn-sm btn-success me-1 approve-btn" 
                              data-id="<?= intval($u['id']) ?>"
                              onclick="handleUser(<?= intval($u['id']) ?>,'approve', this)">
                        <span class="btn-text">Approve</span>
                      </button>
                      <button class="btn btn-sm btn-danger reject-btn" 
                              data-id="<?= intval($u['id']) ?>"
                              onclick="handleUser(<?= intval($u['id']) ?>,'reject', this)">
                        <span class="btn-text">Reject</span>
                      </button>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="alert alert-secondary">No pending users.</div>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <footer class="site-footer fixed-bottom py-3 mt-auto">
    <div class="container text-center">
      <p class="mb-0">ICT30017 ICT PROJECT A | TEAM Deadline Dominators</p>
    </div>
  </footer>

  <script>
    // Track ongoing requests to prevent multiple clicks
    const pendingRequests = new Set();
    
    function handleUser(id, action, buttonElement) {
      // Check if request is already pending for this user
      const requestKey = `${id}-${action}`;
      if (pendingRequests.has(requestKey)) {
        return;
      }
      
      const confirmMsg = action === 'approve' 
        ? 'Approve this user?' 
        : 'Reject (delete) this user?';
      
      if (!confirm(confirmMsg)) return;
      
      // Add to pending requests
      pendingRequests.add(requestKey);
      
      // Disable all buttons on this row
      const row = document.getElementById('userRow' + id);
      if (row) {
        row.classList.add('processing');
      }
      
      // Show loading state on clicked button
      if (buttonElement) {
        buttonElement.classList.add('btn-loading');
        buttonElement.disabled = true;
      }
      
      // Show loading on the other button in same row too
      const otherAction = action === 'approve' ? 'reject' : 'approve';
      const otherButton = row.querySelector(`.${otherAction}-btn[data-id="${id}"]`);
      if (otherButton) {
        otherButton.disabled = true;
        otherButton.style.opacity = '0.5';
      }
      
      fetch('approval.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id, action: action})
      })
      .then(r => {
        if (!r.ok) {
          throw new Error('Network response was not ok');
        }
        return r.json();
      })
      .then(res => {
        if (res.success) {
          // Remove the row after a short delay for visual feedback
          setTimeout(() => {
            const row = document.getElementById('userRow' + id);
            if (row) {
              row.style.transition = 'opacity 0.3s';
              row.style.opacity = '0';
              setTimeout(() => {
                row.remove();
                // Check if table is now empty
                const tableBody = document.getElementById('usersTableBody');
                if (tableBody && tableBody.children.length === 0) {
                  // Add "no users" message
                  const cardBody = document.querySelector('.card-body');
                  const existingAlert = cardBody.querySelector('.alert');
                  if (!existingAlert) {
                    const alert = document.createElement('div');
                    alert.className = 'alert alert-secondary';
                    alert.textContent = 'No pending users.';
                    cardBody.appendChild(alert);
                  }
                }
              }, 300);
            }
          }, 500);
        } else {
          alert('Error: ' + (res.message || 'Failed to process request'));
          // Re-enable buttons
          if (row) row.classList.remove('processing');
          if (buttonElement) {
            buttonElement.classList.remove('btn-loading');
            buttonElement.disabled = false;
          }
          if (otherButton) {
            otherButton.disabled = false;
            otherButton.style.opacity = '1';
          }
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Network error. Please try again.');
        // Re-enable buttons
        if (row) row.classList.remove('processing');
        if (buttonElement) {
          buttonElement.classList.remove('btn-loading');
          buttonElement.disabled = false;
        }
        if (otherButton) {
          otherButton.disabled = false;
          otherButton.style.opacity = '1';
        }
      })
      .finally(() => {
        // Remove from pending requests
        pendingRequests.delete(requestKey);
      });
    }
    
    // Prevent double-clicks
    document.addEventListener('DOMContentLoaded', () => {
      const buttons = document.querySelectorAll('.approve-btn, .reject-btn');
      buttons.forEach(btn => {
        btn.addEventListener('click', function(e) {
          // If already processing, prevent another click
          if (this.classList.contains('btn-loading')) {
            e.preventDefault();
            e.stopPropagation();
            return false;
          }
        });
      });
    });
  </script>
</body>
</html>