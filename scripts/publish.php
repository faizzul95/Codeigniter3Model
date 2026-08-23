<?php

/**
 * Shared publisher for the composer install/update hooks.
 *
 * Behaviour changes vs the previous inline scripts:
 *
 *  - Never overwrites an existing application file without first writing a
 *    timestamped .bak next to it. The old scripts copy()'d straight over
 *    application/core/MY_Model.php on every install AND every update, so any
 *    customisation there was destroyed silently and repeatedly.
 *  - Skips the copy entirely when the destination is byte-identical, so a normal
 *    update is a no-op instead of a churn of identical backups.
 *  - No longer edits the host application's composer.json. The old scripts injected
 *    an unrelated PSR-4 mapping ("App\\" => "application/") and set
 *    config.process-timeout, neither of which is this package's business.
 *  - No longer shells out with exec('composer dump-autoload'). Since we no longer
 *    change the autoload config, there is nothing to regenerate.
 *  - Exits non-zero and reports on failure rather than continuing quietly.
 */

if (!function_exists('ci3model_publish')) {

    /**
     * @param string $context 'Installation' or 'Update', used in output only.
     * @return void
     */
    function ci3model_publish($context = 'Installation')
    {
        $packageRoot = dirname(__DIR__);

        $targets = [
            $packageRoot . '/src/MY_Model.php' => 'application/core/MY_Model.php',
            $packageRoot . '/src/language/malay/form_validation_lang.php' => 'application/language/malay/form_validation_lang.php',
        ];

        $copied = 0;
        $skipped = 0;
        $backedUp = 0;
        $failed = 0;

        foreach ($targets as $source => $destination) {
            if (!is_file($source)) {
                fwrite(STDERR, "  [fail] missing package file: {$source}\n");
                $failed++;
                continue;
            }

            $directory = dirname($destination);

            if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
                fwrite(STDERR, "  [fail] could not create directory: {$directory}\n");
                $failed++;
                continue;
            }

            if (is_file($destination)) {
                // Identical content - nothing to do, and nothing worth backing up.
                if (@md5_file($destination) === @md5_file($source)) {
                    echo "  [skip] {$destination} is already up to date\n";
                    $skipped++;
                    continue;
                }

                $backup = $destination . '.bak-' . date('Ymd-His');

                if (!@copy($destination, $backup)) {
                    fwrite(STDERR, "  [fail] could not back up {$destination}; refusing to overwrite it\n");
                    $failed++;
                    continue;
                }

                echo "  [back] existing file saved as {$backup}\n";
                $backedUp++;
            }

            if (!@copy($source, $destination)) {
                fwrite(STDERR, "  [fail] could not write {$destination}\n");
                $failed++;
                continue;
            }

            echo "  [copy] {$destination}\n";
            $copied++;
        }

        echo "{$context}: {$copied} copied, {$skipped} unchanged, {$backedUp} backed up, {$failed} failed.\n";

        if ($backedUp > 0) {
            echo "NOTE: a .bak file was created. If you had customised MY_Model.php, merge your\n"
                . "      changes back from it - the published version does not include them.\n";
        }

        if ($failed > 0) {
            exit(1);
        }
    }
}
