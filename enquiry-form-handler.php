<!DOCTYPE html>
<html lang="en">

<head>

    <?php include('includes/header-2.php');
         ?>

    <?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect form data
    $name = htmlspecialchars(trim($_POST['name']));
    $email = htmlspecialchars(trim($_POST['email']));
    $product = htmlspecialchars(trim($_POST['product']));

    // Email details
    $to = "ask@mubychem.com";
    $subject = "New Enquiry from Website";
    $headers = "From: Website Enquiry <ask@mubychem.com>\r\n"; // ✅ Use your domain email
    $headers .= "Reply-To: $email\r\n"; 
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    // Email body
    $message = "
        <h2>New Product Enquiry</h2>
        <p><strong>Enquiry From:</strong> MOLCOP</p>
        <p><strong>Name:</strong> $name</p>
        <p><strong>Email:</strong> $email</p>
        <p><strong>Product Interested In:</strong> $product</p>
    ";

// Send email
    if (mail($to, $subject, $message, $headers)) {
        // Display styled success message
        echo '
<div style="
    border: 1px solid #4CAF50;
    background-color: #eafaf1;
    color: #256029;
    padding: 40px 20px;
    margin: 20px auto;
    border-radius: 6px;
    font-family: Segoe UI, sans-serif;
    box-shadow: 0 0 10px rgba(0, 128, 0, 0.1);
    max-width: 600px;
    line-height: 1.5;
    text-align: center;
">
    <img src="https://cdn-icons-png.flaticon.com/512/845/845646.webp" alt="Success" style="width: 60px; height: 60px; margin-bottom: 15px;">
    
    <h3 style="margin-top: 0; color: #1b5e20; font-size: 1.2em;">
        Thank you for your enquiry to Muby Chem Private Limited!
    </h3>
    <p>
        Our team has received your request and will get back to you shortly with the details.
    </p>
</div>';
    } else {
        // Display error message
        echo '
        <div style="
            border: 1px solid #f44336;
            background-color: #fdecea;
            color: #c62828;
            padding: 20px;
            margin: 20px auto;
            border-radius: 6px;
            font-family: Segoe UI, sans-serif;
            max-width: 600px;
            line-height: 1.5;
        ">
            <strong>Something went wrong.</strong> Please try again later or contact us directly at <a href="mailto:ask@mubychem.com">ask@mubychem.com</a>.
        </div>';
    }
} else {
    // Block direct access
    echo "<p>Invalid request.</p>";
}
?>
    <?php include('includes/footer.php');
    ?>

</html>