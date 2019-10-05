<?php


namespace Drupal\SigningOracle\Messaging;


use Stomp\StatefulStomp;

class ActiveMQMessageConsumer implements MessageConsumerInterface
{
    /**
     * @var StatefulStomp
     */
    protected $stomp;

    /**
     * ActiveMQMessageConsumer constructor.
     *
     * @param StatefulStomp $stomp
     *   A preconfigured, connected StatefulStomp subscribed to the correct
     *   queue with read timeout set.
     */
    public function __construct(StatefulStomp $stomp)
    {
        $this->stomp = $stomp;
    }

    public function getNextMessage(): SigningRequestMessage
    {
        $frame = $this->stomp->read();
        if ($frame === false) {
            // Read timeout expired.
            return new NullSigningRequestMessage();
        }

        $headers = $frame->getHeaders();
        if (!empty($headers['type']) && $headers['type'] === 'application/json') {
            // Wrap the signable payload in json for future extensibility.
            $messageParts = json_decode($frame->getBody(), true);
            if ($messageParts === null || ! $this->messageJsonRequiredAttrs($messageParts)) {
                // This message is not processable.
                // Nack it now and return to QueueLoop.
                $this->nack($frame);
                return new NullSigningRequestMessage();
            }
            $signable_payload = $messageParts['signable-payload'];
        } else {
            $signable_payload = $frame->getBody();
        }

        return new SigningRequestMessage(
            'inline',
            $signable_payload,
            $headers['reply-to'] ?? '',
            $headers['correlation-id'] ?? '',
            $frame
        );
    }

    /**
     * Tests whether an incoming message has all required attributes.
     *
     * @return bool
     */
    protected function messageJsonRequiredAttrs(array $messageParts) : bool {
        $required_parts = ['signable-payload'];
        foreach($required_parts as $part) {
            if (! array_key_exists($part, $messageParts)) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function ack($ack_handle): void
    {
        $this->stomp->ack($ack_handle);
    }

    /**
     * {@inheritDoc}
     */
    public function nack($ack_handle): void
    {
        $this->stomp->nack($ack_handle);
    }
}