<?php

class DuplicateKeyException extends Exception
{
    private ?string $duplicateKey = null;
    private ?string $duplicateValue = null;

    public function __construct($message, $code = 0, Exception $previous = null)
    {
        if (preg_match('/Duplicate entry \'(?<value>[^\']+)\' for key \'(?<key>[^\']+)\'/', (string) $message, $matches)) {
            $this->duplicateKey = $matches['key'];
            $this->duplicateValue = $matches['value'];
        }

        parent::__construct($message, $code, $previous);
    }

    public function getDuplicateKey(): ?string
    {
        return $this->duplicateKey;
    }

    public function getDuplicateValue(): ?string
    {
        return $this->duplicateValue;
    }
}
