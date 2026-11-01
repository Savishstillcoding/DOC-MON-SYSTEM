<?php

/**
 * asset() plus "?v=<last modified time>", e.g. /css/docmon-dashboard.css?v=1761890000.
 * Browsers keep CSS and JS files for a while; the changing number makes them
 * download the new file after every update instead of showing an old design.
 */
function asset_v(string $path): string
{
    $file = public_path($path);

    return asset($path).(is_file($file) ? '?v='.filemtime($file) : '');
}
