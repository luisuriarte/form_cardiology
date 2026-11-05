<?php

/**
 * CardPrintAssets - Shared assets for cardiology printable reports
 *
 * Resolves the institutional logo used by every card print.php page and
 * returns it as a base64 data URI. PNG is preferred over SVG because some
 * Dompdf/php-svg-lib versions do not render SVG <text> or embedded fonts.
 *
 * Candidate resolution order:
 *   1. CLINIC_LOGO_PATH (when it points to a readable local file)
 *   2. <openemr>/public/images/logos/cardiology/logo_banner.png|svg
 *   3. <openemr>/public/images/logos/cardiology/logo.png|svg
 *   4. <module>/assets/logo_banner.png|svg
 *   5. <module>/assets/logo.png|svg
 *
 * Compatible with OpenEMR 8.2.0 - 8.5.0.
 *
 * @package   OpenEMR
 * @subpackage Cardiology
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Forms\Cardiology;

final class CardPrintAssets
{
    /** Logo file names, in preference order (PNG before SVG on purpose). */
    private const BANNER_FILES = ['logo_banner.png', 'logo_banner.svg'];
    private const SQUARE_FILES = ['logo.png', 'logo.svg'];

    /**
     * Returns the best available logo as a data URI, or an empty string.
     */
    public static function logoDataUri(): string
    {
        foreach (self::candidatePaths() as $path) {
            if (!is_string($path) || $path === '' || !is_file($path) || !is_readable($path)) {
                continue;
            }

            $contents = @file_get_contents($path);
            if ($contents === false || $contents === '') {
                continue;
            }

            $mime = self::mimeForPath($path);
            if ($mime === null) {
                continue;
            }

            return 'data:' . $mime . ';base64,' . base64_encode($contents);
        }

        return '';
    }

    /**
     * Builds the ordered list of filesystem paths to probe.
     *
     * @return list<string>
     */
    private static function candidatePaths(): array
    {
        $paths = [];

        if (defined('CLINIC_LOGO_PATH') && is_string(CLINIC_LOGO_PATH) && CLINIC_LOGO_PATH !== '') {
            $paths[] = CLINIC_LOGO_PATH;
        }

        $logoDirs = [];

        $webServerRoot = (string)($GLOBALS['webserver_root'] ?? '');
        if ($webServerRoot !== '') {
            $logoDirs[] = rtrim($webServerRoot, '/\\') . '/public/images/logos/cardiology';
        }

        $documentRoot = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
        if ($documentRoot !== '') {
            $logoDirs[] = rtrim($documentRoot, '/\\') . '/public/images/logos/cardiology';
        }

        // The module ships its own copy under assets/.
        $logoDirs[] = dirname(__DIR__) . '/assets';

        foreach (array_unique($logoDirs) as $dir) {
            foreach (self::BANNER_FILES as $file) {
                $paths[] = $dir . '/' . $file;
            }
            foreach (self::SQUARE_FILES as $file) {
                $paths[] = $dir . '/' . $file;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * Maps a file extension to a MIME type accepted by Dompdf.
     */
    private static function mimeForPath(string $path): ?string
    {
        $extension = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'png'  => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            default => null,
        };
    }
}
