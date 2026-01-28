function logout() {
  if (confirm('Are you sure you want to logout?')) {
    // Get the base path dynamically
    const pathArray = window.location.pathname.split('/');
    const basePath = pathArray.slice(0, pathArray.indexOf('js')).join('/');
    window.location.href = basePath + '/logout.php';
  }
}