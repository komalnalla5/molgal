
  // Go back to previous page in browser history
  document.querySelector('.back').addEventListener('click', function() {
    if (document.referrer) {
      window.history.back();
    } else {
      // Fallback if no referrer
      window.location.href = 'products.php'; // or homepage
    }
  });


  