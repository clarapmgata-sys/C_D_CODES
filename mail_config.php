<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';


function sendResetEmail($email, $resetLink)
{
    $mail = new PHPMailer(true);

    try {

        // SMTP settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;

        // Your Gmail address
        $mail->Username = 'clarapmgata@gmail.com';

        // IMPORTANT:
        // Replace this with your NEW Google App Password.
        // Do not use an old or exposed password.
        $mail->Password = 'jbme oybg xbbw rhpu';

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Sender and recipient
        $mail->setFrom(
            'clarapmgata@gmail.com',
            'Clare Designs'
        );

        $mail->addAddress($email);

        // Email format
        $mail->isHTML(true);
        $mail->Subject = 'Clare Designs - Password Reset';

        // Escape the reset link before placing it in HTML
        $safeLink = htmlspecialchars(
            $resetLink,
            ENT_QUOTES,
            'UTF-8'
        );

        // Email message
        $mail->Body = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Password Reset</title>
            </head>

            <body style="
                margin:0;
                padding:20px;
                background-color:#f5f0e8;
                font-family:Arial,sans-serif;
                color:#333333;
            ">

                <div style="
                    max-width:600px;
                    margin:20px auto;
                    background-color:#ffffff;
                    border-radius:10px;
                    overflow:hidden;
                    border:1px solid #d2b48c;
                ">

                    <div style="
                        background-color:#8B6B4A;
                        color:#ffffff;
                        padding:25px;
                        text-align:center;
                    ">
                        <h1 style="margin:0;">
                            CLARE DESIGNS
                        </h1>

                        <p style="margin-bottom:0;">
                            Turning an idea to reality
                        </p>
                    </div>

                    <div style="padding:30px;">

                        <h2 style="color:#8B6B4A;">
                            Password Reset
                        </h2>

                        <p>Hello,</p>

                        <p>
                            We received a request to reset your
                            Clare Designs administrator password.
                        </p>

                        <p>
                            Click the button below to create a
                            new password.
                        </p>

                        <p style="text-align:center; margin:30px 0;">

                            <a href="' . $safeLink . '"
                               style="
                                   background-color:#8B6B4A;
                                   color:#ffffff;
                                   padding:14px 24px;
                                   text-decoration:none;
                                   border-radius:6px;
                                   display:inline-block;
                                   font-weight:bold;
                               ">
                                Reset My Password
                            </a>

                        </p>

                        <p>
                            This link expires one hour after it
                            is created. If it expires, request a
                            new password-reset email.
                        </p>

                        <p>
                            If you did not request a password
                            reset, you can ignore this email.
                        </p>

                        <p>
                            Regards,<br>
                            <strong>Clare Designs Team</strong>
                        </p>

                    </div>

                    <div style="
                        background-color:#f5f0e8;
                        padding:15px;
                        text-align:center;
                        font-size:12px;
                        color:#53545F;
                    ">
                        &copy; ' . date('Y') . ' Clare Designs.
                        All rights reserved.
                    </div>

                </div>

            </body>
            </html>
        ';

        // Plain-text alternative
        $mail->AltBody =
            "Clare Designs Password Reset\n\n" .
            "Open this link to reset your password:\n" .
            $resetLink . "\n\n" .
            "This link expires one hour after it is created.";

        // Send email
        $mail->send();

        return true;

    } catch (Exception $e) {

        error_log(
            'Password reset email error: ' . $mail->ErrorInfo
        );

        return false;
    }
}


