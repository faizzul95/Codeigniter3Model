<?php

/**
 * Composer post-update hook. See scripts/publish.php.
 */

require __DIR__ . '/publish.php';

ci3model_publish('Update');
