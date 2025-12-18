<!DOCTYPE html>
<html>
<head>
    <title>SMTP Test Tool</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
        }
        .box {
            width: 500px;
            margin: 40px auto;
            background: #ffffff;
            padding: 20px;
            border-radius: 6px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        label {
            margin-top: 10px;
            display: block;
            font-weight: bold;
        }
        input, select, textarea, button {
            width: 100%;
            padding: 8px;
            margin-top: 6px;
        }
        button {
            background: #2c7be5;
            color: #fff;
            border: none;
            cursor: pointer;
            margin-top: 15px;
        }
        button:hover {
            background: #1a68d1;
        }
    </style>
</head>
<body>

<div class="box">
    <h2>📧 SMTP Test Tool (Localhost)</h2>

    <form method="post" action="test_smtp_send.php">
        <label>SMTP Host</label>
        <input type="text" name="smtp_host" required placeholder="smtp.yourdomain.com">

        <label>SMTP Port</label>
        <input type="number" name="smtp_port" required value="587">

        <label>Encryption</label>
        <select name="smtp_encryption">
            <option value="tls">TLS</option>
            <option value="ssl">SSL</option>
        </select>

        <label>SMTP Username (From Email)</label>
        <input type="email" name="smtp_user" required placeholder="test@yourdomain.com">

        <label>SMTP Password</label>
        <input type="password" name="smtp_pass" required>

        <label>To Email (Test Receiver)</label>
        <input type="email" name="to_email" required placeholder="yourgmail@gmail.com">

        <label>Message</label>
        <textarea name="message" rows="4">Hello, this is a test email from MailPilot SMTP Test.</textarea>

        <button type="submit">🚀 Send Test Email</button>
    </form>
</div>

</body>
</html>
