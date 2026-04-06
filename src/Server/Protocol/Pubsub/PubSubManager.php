<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Pubsub;

use Psr\Log\LoggerInterface;
use Swoole\Http\Request;
use Swoole\Table;
use Tabula17\Satelles\Nexus\Utilis\Exception\RuntimeException;
use Tabula17\Satelles\Nexus\Utilis\Server\Hamum\Filum;
use Tabula17\Satelles\Nexus\Utilis\Server\Hamum\HamumServerInterface;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Data\Stats;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\ProtocolManagerInterface;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Pubsub\Request\Publish;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Pubsub\Request\Subscribe;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Pubsub\Request\Unsubscribe;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Pubsub\Subscription\ChannelDescriptor;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Pubsub\Subscription\SubscriberDescriptor;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Pubsub\Subscription\SubscriptionDescriptor;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Response\Status as ResponseStatus;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\ServiceProtocol;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Status;

class PubSubManager implements ProtocolManagerInterface
{

    const ServiceProtocol protocol = ServiceProtocol::PUBSUB;

    public Definition $request {
        get {
            return $this->request;
        }
    }
    private Table $channels;
    private Table $subscriptions;
    private Table $subscribers;

    /**
     * @param Definition $request
     */
    public function __construct(
        ?Definition                       $request,
        private readonly ?LoggerInterface $logger = null
    )
    {
        $this->request = $request ?? new Definition([
            'publish' => 'publish',
            'subscribe' => 'subscribe',
            'unsubscribe' => 'unsubscribe'
        ]);
        $this->initializeTables();
    }


    /**
     * @inheritDoc
     */
    public function initializeOnStart(HamumServerInterface $server): void
    {
        if (!$server::TYPE->isWebsocket()) {
            throw new RuntimeException("PubSubManager only works with websockets");
        }
        if (!$this->request->hasActionResolver('publish')) {
            $this->request->addActionResolver('publish', Publish::class);
        }
        if (!$this->request->hasActionResolver('subscribe')) {
            $this->request->addActionResolver('subscribe', Subscribe::class);
        }
        if (!$this->request->hasActionResolver('unsubscribe')) {
            $this->request->addActionResolver('unsubscribe', Unsubscribe::class);
        }
        if (!$this->request->hasResponseType('publish')) {
            $this->request->addResponseType('publish', ResponseStatus::class);
        }
        if (!$this->request->hasResponseType('subscribe')) {
            $this->request->addResponseType('subscribe', ResponseStatus::class);
        }
        if (!$this->request->hasResponseType('unsubscribe')) {
            $this->request->addResponseType('unsubscribe', ResponseStatus::class);
        }
        $this->autoSubscribeToChannels($server);
    }

    private function initializeTables(): void
    {
        $this->subscribers = SubscriberDescriptor::asTable(4096);
        $this->subscriptions = SubscriptionDescriptor::asTable(4096);
        $this->channels = ChannelDescriptor::asTable(1024);
    }

    private function initializeSubscriber(int $fd): void
    {
        if (!$this->subscribers->exists($fd)) {
            $subscriber = new SubscriberDescriptor($fd);
            $this->subscribers->set($fd, $subscriber->toArray());
            $this->autoSubscribeFdToChannels($fd);
        }

    }

    private function unloadSubscriber(int $fd): void
    {
        if ($this->subscribers->exists($fd)) {
            foreach ($this->channels as $channel) {
                $this->doUnsubscribe(['topic' => $channel], $fd);
            }
            $this->subscribers->del($fd);
        }
    }

    /**
     * @inheritDoc
     */
    public function initializeOnWorkers(HamumServerInterface $server, int $workerId): void
    {
        // TODO: Implement initializeOnWorkers() method.
    }

    /**
     * @inheritDoc
     */
    public function runOnOpenConnection(...$args): void
    {
        /** @var HamumServerInterface $server */
        /** @var Request $request */
        [$server, $request] = $args;
        $this->initializeSubscriber($request->fd);
        $this->logger?->debug("Connection open for FD {$request->fd}");
    }

    /**
     * @inheritDoc
     */
    public function runOnCloseConnection(HamumServerInterface $server, int $fd, int $reactorId): void
    {
        $this->unloadSubscriber($fd);
        $this->logger?->debug("Connection closed for FD {$fd}");
    }

    /**
     * @inheritDoc
     */
    public function cleanUpResources(HamumServerInterface $server, int $fd = 0): void
    {
        $workerId = $server->getWorkerId();
        $this->logger?->info("🛍️  #$workerId Cerrando conexiones de clientes...");
        foreach ($this->subscriptions as $key => $subscriber) {
            $this->subscriptions->del($key);
        }

        if (isset($this->subscribers)) {
            $closedCount = 0;
            foreach ($this->subscribers as $key => $subscriber) {
                $fd = $subscriber['fd'];
                if ($server->isEstablished($fd)) {
                    try {
                        //$this->server->close($fd);
                        $closedCount++;
                        $this->subscribers->del($key);
                    } catch (\Exception $e) {
                        // Ignorar errores de clientes ya desconectados
                    }
                }
            }
            foreach ($this->channels as $channel) {
                $channel['subscriber_count'] = 0;
            }
            $this->logger?->info("🛍️ #$workerId -> $closedCount conexiones de clientes cerradas");
        }


        //$this->subscribers->destroy();
        //$this->channels->destroy();
        $this->logger?->debug("🛍️ #$workerId Tablas de canales y suscriptores limpias");
    }

    /**
     * @inheritDoc
     */
    public function registerProtocolHandlers(HamumServerInterface $server): void
    {
        if ($server::TYPE->isWebsocket()) {
            $server->registerMessageHandlers($this->request->subscribe, $this->subscribe(...));
            $server->registerMessageHandlers($this->request->publish, $this->publish(...));
            $server->registerMessageHandlers($this->request->unsubscribe, $this->unsubscribe(...));
        }
    }

    protected function subscribe(Filum $server, int $fd, array $data = []): void
    {
        $output = [
            'action' => $this->request->subscribe,
            '_metadata' => new Stats(
                worker_id: $server->worker_id,
                timestamp: time(),
                server_time: date('Y-m-d H:i:s'),
                client_fd: $fd,
                origin_server: $server->getServerId()
            )
        ];
        if ($this->request->hasActionResolver($this->request->subscribe)) {
            if ($this->request->validateMessage($data)) {
                if(!is_array($data['payload'])){
                    $data['payload'] = ['topic' => $data['payload']];
                }

                $resolver = $this->request->resolve($this->request->subscribe, $this->doSubscription(...), $data, $this->request)->handle($fd);
                if (!$resolver->status->isValid()) {
                    $output['error'] = "Subscription for topic '{$data['payload']['topic']}' failed";
                    $this->logger?->error($output['error']);
                }
                $message = $resolver->getResponse($output);
                $server->push($fd, json_encode($message?->response));
                return;
            }

            $output['error'] = "Message cannot be validated/decoded. Unable to process action '{$this->request->subscribe}'";
            $this->logger?->error($output['error']);
        } else {
            $output['error'] = "Action '{$this->request->subscribe}' not found. Unable to process action.";
            $this->logger?->error($output['error']);
        }
        $message = new ResponseStatus(
            status: Status::error,
            values: $output
        );
        $server->push($fd, json_encode($message));
    }

    private function doSubscription(array $data, int $fd): void
    {
        //format is validated by the resolver, so we can assume it has the correct structure and types
        $channel = $data['payload']['topic'];
        if (!$this->channels->exists($channel)) {
            $this->addChannel($channel, null);
        }
        $subscriber = $this->subscribers->get($fd);
        if (!$subscriber) {
            $this->initializeSubscriber($fd);
            $subscriber = $this->subscribers->get($fd);
        }
        $subscriber['channels']++;
        $this->subscribers->set($fd, $subscriber);
        $this->channels->incr($channel . ':' . $fd, 'subscriberCount');
    }

    protected function publish(Filum $server, int $fd, array $data = []): void
    {
        $output = [
            'action' => $this->request->publish,
            '_metadata' => new Stats(
                worker_id: $server->worker_id,
                timestamp: time(),
                server_time: date('Y-m-d H:i:s'),
                client_fd: $fd,
                origin_server: $server->getServerId()
            )
        ];
        if ($this->request->hasActionResolver($this->request->publish)) {
            if ($this->request->validateMessage($data)) {
                $this->addChannel($data['topic'], null);

                $resolver = $this->request->resolve($this->request->publish, $this->doPublish(...), $data, $this->request)?->handle($server, $fd);
                if (!$resolver->status->isValid()) {
                    $output['error'] = "Publishing to topic '{$data['topic']}' failed";
                    $this->logger?->error($output['error']);
                }
                $message = $resolver->getResponse($output);
                $server->push($fd, json_encode($message?->response));
                return;
            }

            $output['error'] = "Message cannot be validated/decoded. Unable to process action '{$this->request->publish}'";
            $this->logger?->error($data['error']);
        } else {
            $output['error'] = "Action '{$this->request->publish}' not found. Unable to process action.";
            $this->logger?->error($output['error']);
        }
        $message = new ResponseStatus(
            status: Status::error,
            values: $output
        );
        $server->push($fd, json_encode($message));
    }

    private function doPublish(array $data, Filum $server, int $fd): void
    {
        //format is validated by the resolver, so we can assume it has the correct structure and types
        //topic,message
        foreach ($this->getChannelSubscribers($data['payload']['topic'], $server) as $subscriber) {
            $server->push($subscriber, json_encode($data['payload']));
        }
        $this->updateChannel($data['payload']['topic'], ['lastMessageAt' => time(), 'lastMessageFd' => $fd]);
    }

    protected function unsubscribe(Filum $server, int $fd, array $data = []): void
    {
        $output = [
            'action' => $this->request->unsubscribe,
            '_metadata' => new Stats(
                worker_id: $server->worker_id,
                timestamp: time(),
                server_time: date('Y-m-d H:i:s'),
                client_fd: $fd,
                origin_server: $server->getServerId()
            )
        ];
        if ($this->request->hasActionResolver($this->request->unsubscribe)) {
            if ($this->request->validateMessage($data)) {
                $resolver = $this->request->resolve($this->request->unsubscribe, $this->doUnsubscribe(...), $data, $this->request)?->handle($fd);
                if (!$resolver->status->isValid()) {
                    $output['error'] = "Unsubscription from topic '{$data['payload']['name']}' failed";
                    $this->logger?->error($output['error']);
                }
                $message = $resolver->getResponse($output);
                $server->push($fd, json_encode($message?->response));
                return;
            }

            $output['error'] = "Message cannot be validated/decoded. Unable to process action '{$this->request->unsubscribe}'";
            $this->logger?->error($data['error']);
        } else {
            $output['error'] = "Action '{$this->request->unsubscribe}' not found. Unable to process action.";
            $this->logger?->error($output['error']);
        }
        $message = new ResponseStatus(
            status: Status::error,
            values: $output
        );
        $server->push($fd, json_encode($message));
    }

    private function doUnsubscribe(array $data, int $fd): void
    {
        //format is validated by the resolver, so we can assume it has the correct structure and types
        $channel = $data['payload']['topic'];
        $idSubscription = $fd . ':' . $channel;
        if ($this->subscriptions->exists($idSubscription)) {
            $this->subscriptions->del($idSubscription);
        }
        if ($this->subscribers->exists($fd)) {
            if ($this->subscribers->get($fd, 'channels') > 0) {
                $this->subscribers->decr($fd, 'channels');
            } else {
                $this->subscribers->del($fd);
            }
        }
        if ($this->channels->get($channel, 'subscriberCount') > 0) {
            $this->channels->decr($channel, 'subscriberCount');
        }
        if ($this->channels->get($channel, 'subscriberCount') === 0 && !$this->channels->get($channel, 'autoSubscribe')) {
            $this->removeChannel($channel);
        }
    }

    //Channels
    private function updateChannel(string $topic, array $data): void
    {
        if (!$this->channels->exists($topic)) {
            return;
        }
        $channel = ChannelDescriptor::fromTable($this->channels->get($topic));
        $channel->loadProperties($data);
        $this->channels->set($topic, $channel->toArray());
    }

    private function addChannel(ChannelDescriptor|string $channelDescriptor, ?HamumServerInterface $server): array
    {
        if (is_string($channelDescriptor)) {
            $channelDescriptor = new ChannelDescriptor($channelDescriptor);
        }
        if ($this->channels->exists($channelDescriptor->name)) {
            return $this->channels->get($channelDescriptor->name);
        }
        $this->channels->set($channelDescriptor->name, $channelDescriptor->toArray());
        if ($channelDescriptor->autoSubscribe && $server) {
            foreach ($server->connections as $fd) {
                $this->doSubscription(['topic' => $channelDescriptor->name], $fd);
            }
        }
        return $channelDescriptor->toArray();
    }


    public function getChannelSubscribers(string $channel, HamumServerInterface $server): array
    {
        $subscribers = [];
        foreach ($this->subscriptions as $subscription) {
            if (!$server->isEstablished($subscription['subscriberFd'])) {
                $this->unloadSubscriber($subscription['subscriberFd']);
                continue;
            }
            if ($subscription['channel'] === $channel) {
                $subscribers[] = $subscription['subscriberFd'];
            }
        }
        return $subscribers;
    }
/*
    public function getChannelInfo(string $channel): array
    {
        if ($this->channels->exists($channel)) {
            return $this->channels->get($channel);
        }
        return [];
    }*/

    public function autoSubscribeToChannels(HamumServerInterface $server): void
    {
        foreach ($this->channels as $channel) {
            if (!$channel['autoSubscribe']) {
                continue;
            }
            foreach ($server->connections as $fd) {
                $this->doSubscription(['topic' => $channel], $fd);
            }
        }
    }

    public function autoSubscribeFdToChannels(int $fd): void
    {
        foreach ($this->channels as $channel) {
            if (!$channel['autoSubscribe']) {
                continue;
            }
            $this->doSubscription(['topic' => $channel['name']], $fd);
        }
    }


    public function removeChannel(string $channel): void
    {
        $this->channels->del($channel);
    }
/*
    public function getChannels(int $fd, array $data, ?HamumServerInterface $server): Status
    {
        //js ej: ws.send(JSON.stringify({action: 'channels'})) // "action" must match one of those defined in "$this->protocol (listChannels in this case)"
        $channels = [];
        foreach ($this->channels as $channel) {
            $channels[] = $channel;
        }
        //  $this->sendResponseToClient($fd, $this->responses->channels, $server, ['channels' => $channels]);
        return Status::ok;
    }*/
}