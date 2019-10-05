<?php


namespace Drupal\SigningOracle\Messaging;

/**
 * Class SigningResponseMessage
 *
 * Models the elements of a signing response message, immediately before it is serialized onto the queue.
 */
class SigningResponseMessage
{
    /**
     * @var string
     *
     * Indicates whether the signable payload is provided literally or is a reference.
     */
    public $payload_type;

    /**
     * @var string
     * The csig-signed message, or a reference to it.
     */
    public $signed_payload;

    /**
     * @var string
     */
    public $recipient;

    /**
     * @var string
     * A unique identifier echoed from the causal SigningRequestMessage.
     */
    public $correlation_id;

    public function __construct($payload_type, $signed_payload, $recipient, $correlation_id)
    {
        $this->payload_type = $payload_type;
        $this->signed_payload = $signed_payload;
        $this->recipient = $recipient;
        $this->correlation_id = $correlation_id;
    }
}