<?php
defined('ABSPATH') || exit;

/** Reuse Fluent Support's complete record merge, including its add-on hook. */
final class FSCM_Core_Merge extends \FluentSupport\App\Services\EmailClaimService {
    public static function absorb_records($source, $target) {
        return parent::absorb($source, $target);
    }
}
