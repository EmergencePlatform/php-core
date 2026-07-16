<?php

namespace Emergence\Storage;

use League\Flysystem\Config;
use Superbalist\Flysystem\GoogleStorage\GoogleStorageAdapter;

/**
 * GoogleStorageAdapter variant compatible with uniform bucket-level access
 * (UBLA) buckets.
 *
 * The upstream adapter attaches a legacy per-object ACL (predefinedAcl) to
 * every write, which UBLA buckets reject with HTTP 400 ("Cannot insert
 * legacy ACL for an object when uniform bucket-level access is enabled").
 * On UBLA buckets — the modern default — access is governed entirely by
 * IAM, so this subclass omits the ACL unless a write explicitly requests a
 * visibility. Writes without visibility also behave correctly on legacy
 * fine-grained buckets (objects inherit the bucket's default object ACL).
 *
 * Note: setVisibility() and copy() still use per-object ACLs upstream and
 * remain unsupported against UBLA buckets.
 */
class GoogleCloudStorageAdapter extends GoogleStorageAdapter
{
    /**
     * @return array<string,mixed>
     */
    protected function getOptionsFromConfig(Config $config)
    {
        $options = parent::getOptionsFromConfig($config);

        // only carry an ACL when a visibility was explicitly requested
        if (!$config->has('visibility')) {
            unset($options['predefinedAcl']);
        }

        return $options;
    }
}
