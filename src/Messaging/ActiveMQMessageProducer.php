<?php


namespace Drupal\SigningOracle\Messaging;


use Stomp\StatefulStomp;
use Stomp\Transport\Message;

class ActiveMQMessageProducer implements MessageProducerInterface
{
    /**
     * @var StatefulStomp
     */
    protected $stomp;

    /**
     * ActiveMQMessageProducer constructor.
     *
     * @param StatefulStomp $stomp
     *   A preconfigured, connected StatefulStomp.
     */
    public function __construct(StatefulStomp $stomp)
    {
        $this->stomp = $stomp;
    }

    public function sendMessage(SigningResponseMessage $message): void
    {
        $stomplibMessage = new Message(
            $message->signed_payload,
            [
                'persistent' => 'true',
                'correlation-id' => $message->correlation_id,
                'type' => 'text/plain',
            ]
        );
        if (!$this->stomp->send($message->recipient, $stomplibMessage)) {
            throw new MessagingException("Failed to send signed message to '{$message->recipient}', correlation id '{$message->correlation_id}'");
        }
    }
}
