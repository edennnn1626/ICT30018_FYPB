<?php
require_once 'db_config.php';
$token = $_GET['token'] ?? '';
if (!$token) die("Invalid request");

// Check token validity
$stmt = $conn->prepare("SELECT id, reset_expires FROM users WHERE reset_token = ? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) die("Invalid or expired token");

$user = $result->fetch_assoc();
if (strtotime($user['reset_expires']) < time()) die("Token has expired");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reset Password</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <!-- Navigation Bar -->
  <div class="topnav">
    <div class="nav-left">
      <a href="dashboard.php" class="text-white text-decoration-none">Swinburne Alumni Survey</a>
    </div>
    <div class="nav-center"></div>
    <div class="nav-right"></div>
  </div>

  <div class="container d-flex justify-content-center align-items-center vh-100">
    <div class="card p-4 shadow" style="width: 400px;">
      <h3 class="text-center mb-3">Reset Password</h3>
      <form id="resetForm">
        <div class="mb-3">
          <label for="password" class="form-label">New Password</label>
          <input type="password" id="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Update Password</button>
      </form>
      <div id="resetAlert" class="mt-3"></div>
    </div>
  </div>

<script>
document.getElementById("resetForm").addEventListener("submit", async function(e){
  e.preventDefault();
  const password = document.getElementById("password").value;

  const formData = new FormData();
  formData.append("password", password);
  formData.append("token", "<?php echo htmlspecialchars($token); ?>");

  const res = await fetch("reset_password_process.php", { method: "POST", body: formData });
  const text = await res.text();
  document.getElementById("resetAlert").innerHTML = `<div class="alert alert-info">${text}</div>`;
});
</script>
</body>
</html>
