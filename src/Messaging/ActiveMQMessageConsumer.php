<?php


namespace Drupal\SigningOracle\Messaging;


use Monolog\Logger;
use Stomp\StatefulStomp;

class ActiveMQMessageConsumer implements MessageConsumerInterface
{
    /**
     * @var StatefulStomp
     */
    protected $stomp;

    /**
     * @var Logger $app_logger
     */
    protected $app_logger;

    /**
     * ActiveMQMessageConsumer constructor.
     *
     * @param StatefulStomp $stomp
     *   A preconfigured, connected StatefulStomp subscribed to the correct
     *   queue with read timeout set.
     */
    public function __construct(StatefulStomp $stomp, Logger $app_logger)
    {
        $this->stomp = $stomp;
        $this->app_logger = $app_logger;
    }

    public function getNextMessage(): SigningRequestMessage
    {
        $frame = $this->stomp->read();
        if ($frame === false) {
            // Read timeout expired.
            return new NullSigningRequestMessage();
        }

        $headers = $frame->getHeaders();
        $metadata = [];
        if (!empty($headers['type']) && $headers['type'] === 'application/json') {
            // Wrap the signable payload in json for future extensibility.
            $messageParts = json_decode($frame->getBody(), true);
            if ($messageParts === null || ! $this->messageJsonRequiredAttrs($messageParts)) {
                // This message is not processable.
                // Nack it now and return to QueueLoop.
                $this->app_logger->error(
                    'Request in json format was invalid or lacked required attributes. Examine DLQ message id {message-id}.',
                    ['message-id' => $frame->getMessageId(), 'correlation-id' => $headers['correlation-id'] ?? '', 'reply-to' => $headers['reply-to'] ?? '']
                );
                $this->nack($frame);
                return new NullSigningRequestMessage();
            }
            $signable_payload = $messageParts['signable-payload'];
            $metadata = array_diff_key($messageParts, ['signable-payload' => 1]);
        } else {
            $signable_payload = $frame->getBody();
        }

        return new SigningRequestMessage(
            'inline',
            $signable_payload,
            $headers['reply-to'] ?? '',
            $headers['correlation-id'] ?? '',
            $frame,
            $metadata
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