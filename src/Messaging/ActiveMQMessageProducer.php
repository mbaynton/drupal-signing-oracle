<?php


namespace Drupal\SigningOracle\Messaging;


use Monolog\Logger;
use Stomp\StatefulStomp;
use Stomp\Transport\Message;

class ActiveMQMessageProducer implements MessageProducerInterface
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
     * ActiveMQMessageProducer constructor.
     *
     * @param StatefulStomp $stomp
     *   A preconfigured, connected StatefulStomp.
     */
    public function __construct(StatefulStomp $stomp, Logger $app_logger)
    {
        $this->stomp = $stomp;
        $this->app_logger = $app_logger;
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
            $this->app_logger->error('Failed to send signed message to {recipient}, correlation id {correlation-id}',
                ['recipient' => $message->recipient, 'correlation-id' => $message->correlation_id]
            );
            throw new MessagingException("Failed to send signed message to '{$message->recipient}', correlation id '{$message->correlation_id}'");
        }
        $this->app_logger->debug('Sent signed message to {recipient}, correlation id {correlation-id}',
            ['recipient' => $message->recipient, 'correlation-id' => $message->correlation_id]
        );
    }
}
