<?php

namespace DigitalRoyalty\Beacon\Systems\Workshop;

use DigitalRoyalty\Beacon\Support\Enums\Admin\SmtpEnum;
use PHPMailer\PHPMailer\PHPMailer;

final class SmtpHandler
{
    public function register(): void
    {
        $settings = (array) get_option(SmtpEnum::OPTION_SETTINGS, []);

        if (empty($settings['host'])) {
            return;
        }

        add_action('phpmailer_init', [$this, 'configure']);
    }

    public function configure(PHPMailer $mailer): void
    {
        $settings = (array) get_option(SmtpEnum::OPTION_SETTINGS, []);

        $mailer->isSMTP();
        $mailer->Host       = (string) ($settings['host'] ?? '');
        $mailer->Port       = (int)    ($settings['port'] ?? 587);
        $mailer->SMTPSecure = (string) ($settings['encryption'] ?? 'tls');

        $username = (string) ($settings['username'] ?? '');
        $password = (string) ($settings['password'] ?? '');

        if ($username !== '') {
            $mailer->SMTPAuth = true;
            $mailer->Username = $username;
            $mailer->Password = $password;
        }

        $fromEmail = (string) ($settings['from_email'] ?? '');
        $fromName  = (string) ($settings['from_name'] ?? get_bloginfo('name'));

        if ($fromEmail !== '') {
            $mailer->setFrom($fromEmail, $fromName);
        }
    }
}
