<?php
// auth_icons.php - small inline SVG icons used by login.php and register.php (no external files needed)
function icon(string $name): string
{
    $open = '<svg class="ico" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
    $paths = [
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/>',
        'lock' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'eye'  => '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/><path class="slash" d="M3 3l18 18"/>',
    ];
    return $open . ($paths[$name] ?? '') . '</svg>';
}
