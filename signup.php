<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Swinburne Signup</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles/signups.css">
  <link rel="stylesheet" href="styles/main.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

</head>
<body>
  <div class="topnav">
    <div class="nav-left">Swinburne Alumni Survey</div>
    <div class="nav-center">
    </div>
    <div class="nav-right">
    </div>
  </div>

  <div class="container d-flex justify-content-center align-items-center vh-100">
    <div class="card p-4 shadow-lg" style="width: 400px;">
      <img src="images/swinburne_logo.png" alt="Swinburne Logo" class="img-fluid mb-3" style="width: 200px;">
      <h3 class="text-center">Sign Up</h3>
      <form id="signupForm">
      <div class="mb-3">
        <label for="email" class="form-label">Email Address</label>
        <input type="email" class="form-control" id="email" required>
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" required>
      </div>
      <a href="login.html" class="btn btn-warning w-100">Back</a>
      <button type="submit" class="btn btn-warning w-100">Sign Up</button>
    </form>
    </div>
  </div>

  <script>
    document.getElementById("signupForm").addEventListener("submit", function(event) {
      event.preventDefault();
      
      const email = document.getElementById("email").value;
      const password = document.getElementById("password").value;

      const formData = new FormData();
      formData.append("email", email);
      formData.append("password", password);

      fetch("signup_process.php", {
        method: "POST",
        body: formData
      })
      .then(response => response.text())
      .then(result => {
        result = result.trim();

        if (result.startsWith("invalid:")) {
            alert(result.replace("invalid:", ""));
        }
        else if (result === "exists") {
            alert("User already exists.");
        }
        else if (result === "verify_pending") {
            alert("Signup successful! Please check your email to verify your account for admin approval.");
            window.location.href = "login.html";
        }
        else {
            alert("An error occurred. Please try again.");
        }
    })

      .catch(error => {
        console.error("Error:", error);
        alert("Could not connect to server.");
      });
    });
  </script>

  <footer class="site-footer fixed-bottom py-3 mt-auto">
        <div class="container text-center">
            <p class="mb-0">ICT30017 ICT PROJECT A | <span class="team-name">TEAM Deadline Dominators</span></p>
        </div>
  </footer>

</body>
</html>
