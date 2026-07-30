<?php

namespace Modules\Reliability\Application\Services;

use RuntimeException;

final class BackupCipher
{
    private const MAGIC = 'AGCPBKP1';

    public function encrypt(string $source, string $destination): void
    {
        $key = $this->key();
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        $chunkSize = max(65536, (int) config('reliability.backup.chunk_bytes', 1048576));
        $input = fopen($source, 'rb');
        $output = fopen($destination, 'wb');

        if ($input === false || $output === false) {
            throw new RuntimeException('Unable to open backup encryption streams.');
        }

        try {
            fwrite($output, self::MAGIC.$header);
            $wrote = false;
            $finalWritten = false;

            while (! feof($input)) {
                $chunk = fread($input, $chunkSize);
                if ($chunk === false) {
                    throw new RuntimeException('Unable to read backup archive during encryption.');
                }
                if ($chunk === '' && feof($input)) {
                    break;
                }

                $wrote = true;
                $tag = feof($input)
                    ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                    : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
                fwrite($output, sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag));
                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    $finalWritten = true;
                }
            }

            if (! $finalWritten) {
                fwrite($output, sodium_crypto_secretstream_xchacha20poly1305_push(
                    $state,
                    '',
                    '',
                    SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL,
                ));
            }
        } finally {
            fclose($input);
            fclose($output);
            sodium_memzero($key);
        }
    }

    public function decrypt(string $source, string $destination): void
    {
        $key = $this->key();
        $input = fopen($source, 'rb');
        $output = fopen($destination, 'wb');

        if ($input === false || $output === false) {
            throw new RuntimeException('Unable to open backup decryption streams.');
        }

        try {
            $magic = $this->readExact($input, strlen(self::MAGIC));
            if ($magic !== self::MAGIC) {
                throw new RuntimeException('Backup encryption header is invalid.');
            }

            $header = $this->readExact($input, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
            $cipherChunkSize = max(65536, (int) config('reliability.backup.chunk_bytes', 1048576))
                + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;
            $finalSeen = false;

            while (! feof($input)) {
                $ciphertext = $this->readUpTo($input, $cipherChunkSize);
                if ($ciphertext === '') {
                    break;
                }

                $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $ciphertext);
                if ($result === false) {
                    throw new RuntimeException('Backup decryption authentication failed.');
                }

                [$plaintext, $tag] = $result;
                fwrite($output, $plaintext);
                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    $finalSeen = true;
                    if (! feof($input) && $this->readUpTo($input, 1) !== '') {
                        throw new RuntimeException('Backup contains data after the authenticated final frame.');
                    }
                    break;
                }
            }

            if (! $finalSeen) {
                throw new RuntimeException('Backup final authentication frame is missing.');
            }
        } finally {
            fclose($input);
            fclose($output);
            sodium_memzero($key);
        }
    }

    private function key(): string
    {
        $encoded = trim((string) config('reliability.backup.encryption_key'));
        $key = base64_decode($encoded, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('BACKUP_ENCRYPTION_KEY must be a base64-encoded 32-byte key.');
        }

        return $key;
    }

    /** @param resource $stream */
    private function readExact($stream, int $length): string
    {
        $data = $this->readUpTo($stream, $length);
        if (strlen($data) !== $length) {
            throw new RuntimeException('Backup file is truncated.');
        }
        return $data;
    }

    /** @param resource $stream */
    private function readUpTo($stream, int $length): string
    {
        $data = '';
        while (strlen($data) < $length && ! feof($stream)) {
            $part = fread($stream, $length - strlen($data));
            if ($part === false) {
                throw new RuntimeException('Unable to read backup stream.');
            }
            if ($part === '') {
                break;
            }
            $data .= $part;
        }
        return $data;
    }
}
