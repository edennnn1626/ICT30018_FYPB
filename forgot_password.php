<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Swinburne Admin - Forgot Password</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link rel="stylesheet" href="styles/signups.css">
  <link rel="stylesheet" href="styles/main.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
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
    <h3 class="text-center mb-3">Forgot Password</h3>
    <form id="forgotForm">
      <div class="mb-3">
        <label for="email" class="form-label">Enter your email</label>
        <input type="email" id="email" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
      <a href="login.html" class="btn btn-warning w-100">Back</a>
    </form>
    <div id="forgotAlert" class="mt-3"></div>
  </div>
</div>

<script>
document.getElementById("forgotForm").addEventListener("submit", async function(e){
  e.preventDefault();
  const email = document.getElementById("email").value;

  const formData = new FormData();
  formData.append("email", email);

  const res = await fetch("forgot_password_process.php", { method: "POST", body: formData });
  const text = await res.text();

  document.getElementById("forgotAlert").innerHTML = `<div class="alert alert-info">${text}</div>`;
});
</script>
</body>
</html>
