<?php


namespace Drupal\SigningOracle\Messaging;

/**
 * Class SigningRequestMessage
 *
 * Models the elements of signing request message, immediately after it has been successfully parsed off the queue.
 */
class SigningRequestMessage
{
    /**
     * @var string
     *
     * Indicates whether the signable payload is provided literally or is a reference.
     */
    public $signable_payload_type;

    /**
     * @var string
     *
     * The message needing to be signed, or a reference to it.
     */
    public $signable_payload;

    public function __construct(string $signable_payload_type, string $signable_payload)
    {
        $this->signable_payload_type = $signable_payload_type;
        $this->signable_payload = $signable_payload;
    }
}