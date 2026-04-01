<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Trait;

use Tabula17\Satelles\Nexus\Utilis\Process\AbstractSubscriberProcess;

trait ProcessSubscriberTrait
{
    final public const bool PROCESS_SUBSCRIBER_ENABLED = true;
    private array $processSubscribers = [];


    public function addProcessSubscriber(AbstractSubscriberProcess $subscriber): void
    {
        $subscriber->init();
        $this->processSubscribers[$subscriber->origin] = $subscriber;
    }

    /**
     * Registers a channel with the specified handler for one or more subscriber processes.
     *
     * @param string $channel The name of the channel to be registered.
     * @param callable $handler The handler to process messages for the specified channel.
     * @param string|null $subscriberProcess The specific subscriber process to associate with the channel.
     *                                       If null, the channel will be registered across all subscriber processes.
     * @return void
     */
    public function registerChannel(string $channel, callable $handler, ?string $subscriberProcess = null): void
    {
        if ($subscriberProcess) {
            $this->processSubscribers[$subscriberProcess]->addChannel($channel, $handler, $subscriberProcess);
        } else {

            foreach ($this->processSubscribers as $subscriber) {
                $subscriber->addChannel($channel, $handler);
            }
        }
    }
}