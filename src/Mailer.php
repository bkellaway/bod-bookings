<?php
declare(strict_types=1);

final class Mailer
{
    public static function send(string $to, string $subject, string $body, ?string $replyTo = null): bool
    {
        $driver = Env::get('MAIL_DRIVER', 'log');
        $from   = Env::get('MAIL_FROM', 'no-reply@localhost');
        $name   = Env::get('MAIL_FROM_NAME', Env::get('APP_NAME', 'Bookings'));
        if ($driver === 'log') {
            error_log("[mail] to=$to subject=\"$subject\"\n$body");
            return true;
        }
        $headers = ["From: $name <$from>", 'Content-Type: text/plain; charset=UTF-8', 'X-Mailer: PHP/' . PHP_VERSION];
        if ($replyTo) $headers[] = "Reply-To: $replyTo";
        try {
            return @mail($to, $subject, $body, implode("\r\n", $headers));
        } catch (Throwable) {
            return false;
        }
    }
}
