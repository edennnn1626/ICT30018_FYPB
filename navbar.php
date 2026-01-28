<!-- Centralized Navigation Bar -->
<div class="topnav">
    <div class="nav-left">
        <a href="dashboard.php" class="text-white text-decoration-none">Swinburne Alumni Survey</a>
    </div>
    <div class="nav-center">
        <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
        <a href="survey_management.php" class="<?= basename($_SERVER['PHP_SELF']) == 'survey_management.php' ? 'active' : '' ?>">Manage Surveys</a>
        <a href="create_survey.php" class="<?= basename($_SERVER['PHP_SELF']) == 'create_survey.php' ? 'active' : '' ?>">Create Survey</a>
        
        <!-- Alumni Dropdown -->
        <div class="dropdown">
            <a href="#" class="dropdown-toggle <?= (basename($_SERVER['PHP_SELF']) == 'alumni.php' || basename($_SERVER['PHP_SELF']) == 'create_alumni.php') ? 'active' : '' ?>">
                Alumni
            </a>
            <div class="dropdown-menu">
                <a href="alumni.php" class="dropdown-item <?= basename($_SERVER['PHP_SELF']) == 'alumni.php' ? 'active' : '' ?>">Alumni Directory</a>
                <a href="create_alumni.php" class="dropdown-item <?= basename($_SERVER['PHP_SELF']) == 'create_alumni.php' ? 'active' : '' ?>">Add Alumni</a>
            </div>
        </div>
        
        <a href="check_response.php" class="<?= basename($_SERVER['PHP_SELF']) == 'check_nonresponders.php' ? 'active' : '' ?>">Check Survey Responses</a>
        <a href="approval.php" class="<?= basename($_SERVER['PHP_SELF']) == 'approval.php' ? 'active' : '' ?>">New User Approvals</a>
        <a href="logs.php" class="<?= basename($_SERVER['PHP_SELF'])=='logs.php'?'active':'' ?>">Logs</a>
    </div>
    <div class="nav-right">
        <a href="#" onclick="logout()">Logout</a>
    </div>
</div>
<style>
/* Dropdown container */
.dropdown {
    position: relative;
    display: inline-block;
}

/* Dropdown toggle button */
.dropdown-toggle {
    padding: 14px 16px;
    text-decoration: none;
    color: white;
    display: inline-flex;
    align-items: center;
    cursor: pointer;
}

.dropdown-toggle:hover, .dropdown-toggle.active {
    background-color: #111;
}

/* Dropdown menu (hidden by default) - CHANGED BACKGROUND TO DARK */
.dropdown-menu {
    display: none;
    position: absolute;
    background-color: #222;
    min-width: 180px;
    box-shadow: 0 8px 16px rgba(0,0,0,0.4); 
    z-index: 1000;
    border-radius: 4px;
    top: 100%;
    left: 0;
    overflow: hidden;
    border: 1px solid #444;
}

/* Dropdown items */
.dropdown-item {
    color: #eee;
    padding: 12px 16px;
    text-decoration: none;
    display: block;
    transition: background-color 0.2s;
}

.dropdown-item:hover {
    background-color: #333;
    color: #fff; 
}

.dropdown-item.active {
    background-color: #e2001a;
    color: white;
}

/* Show dropdown on hover */
.dropdown:hover .dropdown-menu {
    display: block;
}

/* Add a subtle arrow to the dropdown */
.dropdown-menu::before {
    content: '';
    position: absolute;
    top: -6px;
    left: 20px;
    width: 0;
    height: 0;
    border-left: 6px solid transparent;
    border-right: 6px solid transparent;
    border-bottom: 6px solid #222;
}

.dropdown-item:not(:last-child) {
    border-bottom: 1px solid #444;
}
</style>

<!-- JavaScript for better mobile support -->
<script>
// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdowns = document.querySelectorAll('.dropdown');
    dropdowns.forEach(dropdown => {
        if (!dropdown.contains(event.target)) {
            const menu = dropdown.querySelector('.dropdown-menu');
            if (menu) {
                menu.style.display = 'none';
            }
        }
    });
});

// Toggle dropdown on click for mobile
document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
    toggle.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) { // Mobile only
            e.preventDefault();
            const menu = this.nextElementSibling;
            if (menu.style.display === 'block') {
                menu.style.display = 'none';
            } else {
                menu.style.display = 'block';
            }
        }
    });
});
</script>