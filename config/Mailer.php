<?php
/**
 * config/Mailer.php
 * Envoi d'emails sans bibliothèque externe.
 *
 * - Si un serveur SMTP est configuré dans config/mail.local.php (ex : Gmail avec un
 *   "mot de passe d'application"), l'email est vraiment envoyé.
 * - Sinon (XAMPP n'envoie pas d'emails), il est enregistré dans storage/mails/
 *   sous forme de fichier .eml que l'on ouvre avec Outlook ou le Bloc-notes.
 *   Ce dossier est interdit d'accès depuis le navigateur (storage/.htaccess).
 *
 * config/mail.local.php contient un mot de passe : il n'est JAMAIS publié (.gitignore).
 * Modèle : config/mail.example.php
 */
class Mailer
{
    private array $config;

    public function __construct()
    {
        $local = __DIR__ . '/mail.local.php';
        $this->config = array_merge([
            'host'       => '',          // vide = mode local (fichiers .eml)
            'port'       => 587,
            'encryption' => 'tls',       // 'tls' (port 587) ou 'ssl' (port 465)
            'username'   => '',
            'password'   => '',
            'from'       => 'no-reply@labtrack.tn',
            'from_name'  => 'LabTrack',
        ], is_file($local) ? (array) require $local : []);
    }

    /** Envoie un email texte (UTF-8). Retourne false en cas d'échec. */
    public function send(string $to, string $subject, string $body): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $message = $this->buildMessage($to, $subject, $body);
        try {
            return $this->config['host'] === ''
                ? $this->saveToOutbox($message)
                : $this->sendSmtp($to, $message);
        } catch (Throwable $e) {
            error_log('LabTrack Mailer: ' . $e->getMessage());
            return false;
        }
    }

    /** Message complet : en-têtes + corps encodé en base64 (accents et arabe) */
    private function buildMessage(string $to, string $subject, string $body): string
    {
        $from = $this->config['username'] !== '' ? $this->config['username'] : $this->config['from'];
        $headers = [
            'Date: ' . date('r'),
            'From: ' . $this->encodeHeader($this->config['from_name']) . ' <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . $this->encodeHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@labtrack.tn>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $body = str_replace("\n", "\r\n", $body);
        return implode("\r\n", $headers) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($body), 76, "\r\n"));
    }

    private function encodeHeader(string $text): string
    {
        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }

    /** Mode local : storage/mails/AAAAMMJJ-HHMMSS-xxxx.eml */
    private function saveToOutbox(string $message): bool
    {
        $dir = __DIR__ . '/../storage/mails';
        if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
            return false;
        }
        $file = $dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.eml';
        return file_put_contents($file, $message) !== false;
    }

    /** Client SMTP minimal : STARTTLS ou SSL, puis AUTH LOGIN */
    private function sendSmtp(string $to, string $message): bool
    {
        $c = $this->config;
        $remote = ($c['encryption'] === 'ssl' ? 'ssl://' : 'tcp://') . $c['host'] . ':' . (int) $c['port'];
        $smtp = stream_socket_client($remote, $errno, $errstr, 15);
        if (!$smtp) {
            throw new RuntimeException("connexion SMTP impossible ($errstr)");
        }
        stream_set_timeout($smtp, 15);
        try {
            $this->expect($smtp, 220);
            $this->command($smtp, 'EHLO labtrack.local', 250);
            if ($c['encryption'] === 'tls') {
                $this->command($smtp, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('STARTTLS refusé');
                }
                $this->command($smtp, 'EHLO labtrack.local', 250);
            }
            if ($c['username'] !== '') {
                $this->command($smtp, 'AUTH LOGIN', 334);
                $this->command($smtp, base64_encode($c['username']), 334);
                $this->command($smtp, base64_encode($c['password']), 235);
            }
            $from = $c['username'] !== '' ? $c['username'] : $c['from'];
            $this->command($smtp, 'MAIL FROM:<' . $from . '>', 250);
            $this->command($smtp, 'RCPT TO:<' . $to . '>', 250);
            $this->command($smtp, 'DATA', 354);
            // une ligne commençant par "." doit être doublée (règle SMTP)
            $data = preg_replace('/^\./m', '..', $message);
            $this->command($smtp, $data . "\r\n.", 250);
            $this->command($smtp, 'QUIT', 221);
            return true;
        } finally {
            fclose($smtp);
        }
    }

    private function command($smtp, string $line, int $expected): void
    {
        fwrite($smtp, $line . "\r\n");
        $this->expect($smtp, $expected);
    }

    /** Lit la réponse (éventuellement sur plusieurs lignes "250-...") et vérifie son code */
    private function expect($smtp, int $expected): void
    {
        $response = '';
        while (($line = fgets($smtp, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        if ((int) substr($response, 0, 3) !== $expected) {
            throw new RuntimeException('réponse SMTP inattendue : ' . trim($response));
        }
    }
}
