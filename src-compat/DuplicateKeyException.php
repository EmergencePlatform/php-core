<?php

class DuplicateKeyException extends Exception
{
    private ?string $duplicateKey = null;
    private ?string $duplicateTable = null;
    private ?string $duplicateValue = null;

    public function __construct($message, $code = 0, Exception $previous = null)
    {
        if (preg_match('/Duplicate entry \'(?<value>[^\']+)\' for key \'(?<key>[^\']+)\'/', (string) $message, $matches)) {
            $key = $matches['key'];

            // MySQL 8.0.19 and later name the key as table.index; callers
            // match the name against the index they declared, so keep the
            // table apart from it (an index name cannot contain a dot)
            if (($dot = strrpos($key, '.')) !== false) {
                $this->duplicateTable = substr($key, 0, $dot);
                $key = substr($key, $dot + 1);
            }

            $this->duplicateKey = $key;
            $this->duplicateValue = $matches['value'];
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * The name of the index that was violated, without the table prefix
     * MySQL 8 adds, so it compares equal to the name the model declares
     * (or PRIMARY)
     */
    public function getDuplicateKey(): ?string
    {
        return $this->duplicateKey;
    }

    /**
     * The table MySQL named with the key, when it did; null on older servers
     */
    public function getDuplicateTable(): ?string
    {
        return $this->duplicateTable;
    }

    public function getDuplicateValue(): ?string
    {
        return $this->duplicateValue;
    }
}
