<?php


namespace Drupal\SigningOracle\Messaging;


class TempMessageProvider implements MessageProviderInterface
{
    protected $messages;

    public function __construct()
    {
        $this->messages = [
            new SigningRequestMessage('literal', 'hello world'),
            new SigningRequestMessage('literal', 'message two')
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

    public function getBlockingTime(): int
    {
        return 3;
    }
}
