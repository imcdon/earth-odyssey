<?php
/*
 * ratelimit.php - Fixed-window request counter stored in storage/ratelimit (one small file per bucket).
 */

/* True if this request is within $max per $windowSec for $bucket. Fails open if storage is unwritable. */
function rate_limit_allow(string $bucket, int $max, int $windowSec): bool
{
    $dir = dirname(__DIR__) . '/storage/ratelimit';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $fh = @fopen($dir . '/' . sha1($bucket) . '.cnt', 'c+');
    if (!$fh) {
        return true;
    }

    flock($fh, LOCK_EX);
    $slot = intdiv(time(), $windowSec);
    [$savedSlot, $count] = array_map('intval', explode(':', (string) stream_get_contents($fh)) + [-1, 0]);
    $count = ($savedSlot === $slot ? $count : 0) + 1;
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, $slot . ':' . $count);
    flock($fh, LOCK_UN);
    fclose($fh);

    return $count <= $max;
}
