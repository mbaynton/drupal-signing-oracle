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

    /**
     * @var string
     * The queue that the signed message should be sent to.
     */
    public $reply_to;

    /**
     * @var string
     * A unique identifier for this payload, used by consumers of our outbound queue.
     */
    public $correlation_id;

    /**
     * @var mixed
     * An opaque value to pass to the message provider's ack() or nack() method.
     */
    public $ack_handle;

    /**
     * @var array
     * An associative array of metadata about the request for logging/auditing purposes.
     */
    public $metadata_bag;

    public function __construct(string $signable_payload_type, string $signable_payload, string $reply_to, string $correlation_id, $ack_handle, array $metadata_bag = [])
    {
        $this->signable_payload_type = $signable_payload_type;
        $this->signable_payload = $signable_payload;
        $this->reply_to = $reply_to;
        $this->correlation_id = $correlation_id;
        $this->ack_handle = $ack_handle;
        $this->metadata_bag = $metadata_bag;
    }

    /**
     * Produces an array of strings containing message metadata information, but not its entire contents.
     */
    public function summarize() : array
    {
        $facts = [];
        if (method_exists($this->ack_handle, 'getMessageId')) {
            $facts['message-id'] = $this->ack_handle->getMessageId();
        }
        $facts['signable-payload-type'] = $this->signable_payload_type;
        $facts['reply-to'] = $this->reply_to;
        $facts['correlation-id'] = $this->correlation_id;

        return array_filter($facts + $this->metadata_bag);
    }
}