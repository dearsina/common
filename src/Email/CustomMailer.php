<?php

namespace App\Common\Email;

use App\Common\Email\Email;
use App\Common\Process;

class CustomMailer extends Email
{
    
    public function notifyAdminsWorker(array $a): bool
    {
        extract($a);

        $email = new \App\Common\Email\Email();

        # Notify the generic email address
        // TODO Replace with all admin emails at some point
        $email->template("message", $vars['variables'])
            ->to($_ENV['email_username'])
            ->send();

        return true;
    }

    /**
     * Sends an async email to the admins:
     *
     * <code>
     * Email::notifyAdmins([
     *    "subject" => "",
     *    "body" => "",
     *    "backtrace" => str::backtrace(true)
     * ]);
     * </code>
     *
     *
     * @param array $variables
     */
    public static function notifyAdmins(array $variables): void
    {
        Process::request([
            "rel_table" => "email",
            "action" => "notifyAdminsWorker",
            "vars" => [
                "variables" => $variables
            ]
        ]);
    }
}