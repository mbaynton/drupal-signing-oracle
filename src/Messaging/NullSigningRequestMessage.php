<?php


namespace Drupal\SigningOracle\Messaging;

/**
 * Class NullSigningRequestMessage
 *
 * A special message type returned when the blocking time has elapsed and no message has arrived.
 */
class NullSigningRequestMessage extends SigningRequestMessage
{
    public function __construct()
    {
        parent::__construct('inline', '', '', '', null);
    }
}