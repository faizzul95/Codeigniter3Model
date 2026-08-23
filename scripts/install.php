<?php

/**
 * Composer post-install hook. Publishes MY_Model and the Malay validation language
 * file. See scripts/publish.php - it backs up before overwriting anything.
 */

require __DIR__ . '/publish.php';

ci3model_publish('Installation');
