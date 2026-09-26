<?php
/**
 * Adlexone FlowIQ License Agreement 1.0
 *
 * 1. Copying the Adlexone FlowIQ software and distributing as your own software
 *  without the written permission of Adlexone is forbidden under the terms of
 *  the Adlexone FlowIQ License.
 * 2. You may modify your copy of the Adlexone FlowIQ software, however where
 *  Adlexone FlowIQ files contain the Adlexone FlowIQ license in the header of the file, the
 *  Adlexone FlowIQ License header must remain.
 * 3. Adlexone, and the copyright holders of the Adlexone FlowIQ, provide no
 *  warranty for the data created or managed by your Adlexone FlowIQ installation.
 * 4. Adlexone, and the copyright holders of the Adlexone FlowIQ, provide no
 *  warranty for your Adlexone FlowIQ configuration or the hosting environment
 *  your Adlexone FlowIQ installation operates in.
 * 5. Adlexone, and the copyright holders of the Adlexone FlowIQ, provide no
 *  warranty for the Adlexone FlowIQ where the software has been modified by
 *  third parties (i.e. other than Adlexone), unless an agreement has been
 *  reached with Adlexone.
 * 6. By using the Adlexone FlowIQ, you are indicating your acceptance of the
 *  stated Adlexone FlowIQ License terms and conditions.
 *
 * Contact info@oneorzero.com if you have any further licensing questions.
 */

namespace Adlexone\support;

class Communications
{
    public static function SMTP($smtpHost, $port, $authentication, $tls, $username, $password, $fromName, $from, $to, $subject, $bodyText, $htmlBody = false, $secure = false)
    {
        $headers = [];
        $headers[] = 'From: ' . $fromName . ' <' . $from . '>';
        $headers[] = 'Reply-To: ' . $fromName . ' <' . $from . '>';
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: ' . ($htmlBody ? 'text/html' : 'text/plain') . '; charset=UTF-8';

        $toAddresses = array_map('trim', explode(',', $to));
        $toString = implode(',', $toAddresses);

        if (defined('SET_DEBUG_MODE') && SET_DEBUG_MODE === 'Yes') {
            echo '<pre>';
            print_r([
                'to' => $toString,
                'subject' => $subject,
                'body' => $bodyText,
                'headers' => $headers
            ]);
            echo '</pre>';
        }

        $success = mail($toString, $subject, $bodyText, implode("\r\n", $headers));
        if (!$success) {
            echo "Error: Email could not be sent.<br />";
        }
    }

    /**
         * Sends an email using a direct SMTP connection.
         *
         * This method establishes a connection to the specified SMTP server and sends an email
         * by issuing SMTP commands directly. It supports authentication and allows specifying
         * the sender, recipient, subject, and message body.
         *
         * @param string $host The SMTP server hostname (e.g., smtp.example.com).
         * @param int $port The port number to connect to (e.g., 25, 465, or 587).
         * @param string $username The username for SMTP authentication.
         * @param string $password The password for SMTP authentication.
         * @param string $to The recipient's email address.
         * @param string $subject The subject of the email.
         * @param string $message The body of the email.
         * @param string $from The sender's email address.
         * @return bool Returns true if the email was sent successfully, false otherwise.
         */
        public static function sendSMTPMail($host, $port, $username, $password, $to, $subject, $message, $from): bool
        {
            // Open a socket connection to the SMTP server
            $socket = fsockopen($host, $port, $errno, $errstr, 30);
            if (!$socket) {
                // Output error message if the connection fails
                echo "Error: $errstr ($errno)";
                return false;
            }

            // Define the sequence of SMTP commands to send the email
            $commands = [
                "EHLO localhost\r\n", // Identify the client to the server
                "AUTH LOGIN\r\n", // Request authentication
                base64_encode($username) . "\r\n", // Send the username (base64-encoded)
                base64_encode($password) . "\r\n", // Send the password (base64-encoded)
                "MAIL FROM:<$from>\r\n", // Specify the sender's email address
                "RCPT TO:<$to>\r\n", // Specify the recipient's email address
                "DATA\r\n", // Begin the email content
                "Subject: $subject\r\nFrom: $from\r\nTo: $to\r\n\r\n$message\r\n.\r\n", // Email headers and body
                "QUIT\r\n" // Terminate the SMTP session
            ];

            // Send each command to the SMTP server and read the server's response
            foreach ($commands as $cmd) {
                fwrite($socket, $cmd);
                fgets($socket, 512); // Read the server's response (up to 512 bytes)
            }

            // Close the socket connection
            fclose($socket);

            // Return true to indicate the email was sent successfully
            return true;
        }
}