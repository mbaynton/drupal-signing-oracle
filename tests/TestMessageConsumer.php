<?php


namespace Drupal\SigningOracle\Messaging;


class TestMessageConsumer implements MessageConsumerInterface
{
    protected $messages;

    public function __construct()
    {
        $this->messages = [
            new SigningRequestMessage(
                'literal',
                'hello world',
                'host2-queue',
                '1',
                null
            ),
            new SigningRequestMessage(
                'literal',
                'message two',
                'host1-queue',
                '2',
                null
            )
        ];
    }

    public function getNextMessage(): SigningRequestMessage
    {
        $msg = current($this->messages);
        next($this->messages);

        if ($msg === FALSE) {
            sleep($this->getBlockingTime());
            return new NullSigningRequestMessage();
        }

        return $msg;
    }

    public function ack($ack_handle): void
    {
        // TODO: Implement ack() method.
    }

    public function nack($ack_handle): void
    {
        // TODO: Implement nack() method.
    }
}
