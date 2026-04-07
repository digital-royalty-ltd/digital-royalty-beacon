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
        add_action('wp_mail_succeeded', [$this, 'logSuccess']);
        add_action('wp_mail_failed', [$this, 'logFailure']);
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
        $forceFrom = !empty($settings['force_from']);

        if ($fromEmail !== '') {
            $isDefault = in_array($mailer->From, ['', 'root@localhost', 'wordpress@' . ($_SERVER['SERVER_NAME'] ?? 'localhost')], true);

            if ($forceFrom || $isDefault) {
                $mailer->setFrom($fromEmail, $fromName);
            }
        }
    }

    public function logSuccess(array $data): void
    {
        $this->appendLog([
            'to'      => is_array($data['to']) ? implode(', ', $data['to']) : (string) $data['to'],
            'subject' => (string) ($data['subject'] ?? ''),
            'sent_at' => current_time('mysql'),
            'ok'      => true,
        ]);
    }

    public function logFailure(\WP_Error $error): void
    {
        $data = $error->get_error_data();
        $this->appendLog([
            'to'      => is_array($data['to'] ?? '') ? implode(', ', $data['to']) : (string) ($data['to'] ?? ''),
            'subject' => (string) ($data['subject'] ?? ''),
            'sent_at' => current_time('mysql'),
            'ok'      => false,
            'error'   => $error->get_error_message(),
        ]);
    }

    private function appendLog(array $entry): void
    {
        $log = (array) get_option('dr_beacon_smtp_mail_log', []);
        array_unshift($log, $entry);
        $log = array_slice($log, 0, 50);
        update_option('dr_beacon_smtp_mail_log', $log, false);
    }
}
