<?php

namespace App\Support;

class Avatar
{
    /** Offline SVG avatar with the person's initials (no external service needed). */
    public static function for(string $name, string $bg = '#f59e0b'): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $initials = mb_substr($parts[0] ?? '?', 0, 1).mb_substr($parts[1] ?? '', 0, 1);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 128 128">'
            .'<rect width="128" height="128" fill="'.$bg.'"/>'
            .'<text x="64" y="64" dy=".35em" text-anchor="middle" font-family="Tahoma, Arial, sans-serif" font-size="52" fill="#fff">'
            .htmlspecialchars($initials).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
