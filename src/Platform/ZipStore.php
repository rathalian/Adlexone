<?php
declare(strict_types=1);

namespace Adlexone\Platform;

/**
 * Writes an uncompressed zip archive. PHP's zip extension is not required.
 */
final class ZipStore
{
    /**
     * @param array<string, string> $files archive path => absolute filesystem path
     */
    public static function write(string $destination, array $files): void
    {
        $handle = fopen($destination, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Cannot create the backup archive.');
        }
        $central = '';
        $offset = 0;
        $count = 0;
        foreach ($files as $name => $path) {
            $name = str_replace('\\', '/', $name);
            $data = (string) file_get_contents($path);
            $size = strlen($data);
            $crc = crc32($data);
            $nameLen = strlen($name);
            $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0, $crc, $size, $size, $nameLen, 0) . $name . $data;
            fwrite($handle, $local);
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 0, $crc, $size, $size, $nameLen, 0, 0, 0, 0, 0, $offset) . $name;
            $offset += strlen($local);
            $count++;
        }
        $centralLen = strlen($central);
        fwrite($handle, $central);
        fwrite($handle, pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, $centralLen, $offset, 0));
        fclose($handle);
    }
}
