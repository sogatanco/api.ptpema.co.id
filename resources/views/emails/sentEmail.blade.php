<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification Sent</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            text-align: center;
        }

        .container {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 100%;
        }

        h1 {
            color: #3498db;
        }

        p {
            color: #777;
            margin-bottom: 20px;
        }

        a {
            color: #2ecc71;
            text-decoration: none;
            font-weight: bold;
        }

        .logo {
            max-width: 200px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="container">
    <img class="logo" src="https://ptpema.co.id/uploads/settings/16347907124310.PNG" alt="Your Logo">
    <h1>Email Verification Sent</h1>
    <p>We have sent a verification email to <?php echo $email; ?>. Please check your email and follow the instructions to verify your account.</p>
    <a href="#">Resend Verification Email</a>
</div>

</body>
</html>


