<?php

namespace Drupal\SigningOracle\Signing;


/**
 * Interface SignerServiceInterface
 */
interface SignerServiceInterface
{
    /**
     * Given an arbitrary string, returns the csig for it.
     *
     * @param string $message
     * @return string
     * @throws SigningException
     */
    public function signString(string $message): string;
}