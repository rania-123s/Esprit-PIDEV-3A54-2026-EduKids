<?php

namespace App\Command;

use App\Service\ChatWebSocketTokenService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:chat:websocket-server',
    description: 'Run the chat WebSocket server used by the sidebar realtime updates.'
)]
class ChatWebSocketServerCommand extends Command
{
    /**
     * @var array<int, array{
     *     socket: resource,
     *     buffer: string,
     *     isWebSocket: bool,
     *     userId: int|null
     * }>
     */
    private array $clients = [];

    public function __construct(
        private readonly ChatWebSocketTokenService $tokenService,
        #[Autowire('%env(CHAT_WS_BIND_HOST)%')]
        private readonly string $bindHost,
        #[Autowire('%env(CHAT_WS_BIND_PORT)%')]
        private readonly string $bindPort,
        #[Autowire('%env(CHAT_WS_BRIDGE_SECRET)%')]
        private readonly string $bridgeSecret,
        #[Autowire('%kernel.secret%')]
        private readonly string $kernelSecret
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $address = sprintf('tcp://%s:%d', trim($this->bindHost), max(1, (int) $this->bindPort));
        $server = @stream_socket_server($address, $errorCode, $errorMessage);
        if (!is_resource($server)) {
            $output->writeln(sprintf('<error>Unable to start WebSocket server on %s: %s (%d)</error>', $address, $errorMessage, $errorCode));

            return Command::FAILURE;
        }

        stream_set_blocking($server, false);
        $output->writeln(sprintf('<info>Chat WebSocket server listening on %s</info>', $address));
        $output->writeln('<info>WebSocket endpoint: /ws?token=...</info>');
        $output->writeln('<info>Bridge endpoint: POST /publish</info>');

        while (true) {
            $readSockets = [$server];
            foreach ($this->clients as $client) {
                $readSockets[] = $client['socket'];
            }

            $writeSockets = [];
            $exceptSockets = [];
            $selected = @stream_select($readSockets, $writeSockets, $exceptSockets, 1);
            if ($selected === false || $selected === 0) {
                continue;
            }

            foreach ($readSockets as $socket) {
                if ($socket === $server) {
                    $connection = @stream_socket_accept($server, 0);
                    if (!is_resource($connection)) {
                        continue;
                    }

                    stream_set_blocking($connection, false);
                    $this->clients[(int) $connection] = [
                        'socket' => $connection,
                        'buffer' => '',
                        'isWebSocket' => false,
                        'userId' => null,
                    ];

                    continue;
                }

                $this->handleClientSocket($socket, $output);
            }
        }
    }

    /**
     * @param resource $socket
     */
    private function handleClientSocket($socket, OutputInterface $output): void
    {
        $clientId = (int) $socket;
        if (!isset($this->clients[$clientId])) {
            return;
        }

        $data = @fread($socket, 8192);
        if ($data === false || ($data === '' && feof($socket))) {
            $this->disconnectClient($clientId);

            return;
        }

        if ($data === '') {
            return;
        }

        $client = &$this->clients[$clientId];
        if ($client['isWebSocket']) {
            // Only close frames are handled from browser clients.
            if ((ord($data[0]) & 0x0F) === 0x08) {
                $this->disconnectClient($clientId);
            }

            return;
        }

        $client['buffer'] .= $data;
        $request = $this->tryParseHttpRequest($client['buffer']);
        if ($request === null) {
            return;
        }

        $client['buffer'] = $request['remaining'];
        if ($this->isWebSocketUpgrade($request)) {
            $this->handleWebSocketUpgrade($clientId, $request, $output);

            return;
        }

        if ($request['method'] === 'POST' && $request['path'] === '/publish') {
            $this->handlePublishRequest($clientId, $request);

            return;
        }

        $this->writeHttpResponse($socket, 404, ['error' => 'Not found']);
        $this->disconnectClient($clientId);
    }

    /**
     * @param array{method: string, target: string, path: string, query: string, headers: array<string, string>, body: string, remaining: string} $request
     */
    private function isWebSocketUpgrade(array $request): bool
    {
        return $request['method'] === 'GET'
            && $request['path'] === '/ws'
            && strtolower($request['headers']['upgrade'] ?? '') === 'websocket';
    }

    /**
     * @param array{method: string, target: string, path: string, query: string, headers: array<string, string>, body: string, remaining: string} $request
     */
    private function handleWebSocketUpgrade(int $clientId, array $request, OutputInterface $output): void
    {
        if (!isset($this->clients[$clientId])) {
            return;
        }

        $socket = $this->clients[$clientId]['socket'];
        $key = trim((string) ($request['headers']['sec-websocket-key'] ?? ''));
        if ($key === '') {
            $this->writeHttpResponse($socket, 400, ['error' => 'Missing Sec-WebSocket-Key']);
            $this->disconnectClient($clientId);

            return;
        }

        parse_str($request['query'], $queryParams);
        $token = (string) ($queryParams['token'] ?? '');
        $userId = $this->tokenService->resolveUserId($token);
        if ($userId === null) {
            $this->writeHttpResponse($socket, 401, ['error' => 'Invalid token']);
            $this->disconnectClient($clientId);

            return;
        }

        $accept = base64_encode(hash('sha1', $key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        $response = "HTTP/1.1 101 Switching Protocols\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Accept: {$accept}\r\n\r\n";
        @fwrite($socket, $response);

        $this->clients[$clientId]['isWebSocket'] = true;
        $this->clients[$clientId]['userId'] = $userId;
        $output->writeln(sprintf('<comment>WebSocket client connected (user #%d)</comment>', $userId));
    }

    /**
     * @param array{method: string, target: string, path: string, query: string, headers: array<string, string>, body: string, remaining: string} $request
     */
    private function handlePublishRequest(int $clientId, array $request): void
    {
        if (!isset($this->clients[$clientId])) {
            return;
        }

        $socket = $this->clients[$clientId]['socket'];
        $incomingSecret = trim((string) ($request['headers']['x-chat-bridge-secret'] ?? ''));
        $expectedSecret = trim($this->bridgeSecret) !== '' ? trim($this->bridgeSecret) : $this->kernelSecret;
        if (!hash_equals($expectedSecret, $incomingSecret)) {
            $this->writeHttpResponse($socket, 403, ['error' => 'Forbidden']);
            $this->disconnectClient($clientId);

            return;
        }

        $payload = json_decode($request['body'], true);
        if (!is_array($payload)) {
            $this->writeHttpResponse($socket, 400, ['error' => 'Invalid JSON payload']);
            $this->disconnectClient($clientId);

            return;
        }

        $recipientUserIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, (array) ($payload['recipientUserIds'] ?? [])),
            static fn (int $id): bool => $id > 0
        )));

        unset($payload['recipientUserIds']);

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (!is_string($jsonPayload)) {
            $this->writeHttpResponse($socket, 400, ['error' => 'Invalid payload']);
            $this->disconnectClient($clientId);

            return;
        }

        if ($recipientUserIds !== []) {
            $this->broadcastToUsers($recipientUserIds, $jsonPayload);
        }

        $this->writeHttpResponse($socket, 200, ['ok' => true]);
        $this->disconnectClient($clientId);
    }

    /**
     * @param int[] $userIds
     */
    private function broadcastToUsers(array $userIds, string $payload): void
    {
        $frame = $this->encodeWebSocketFrame($payload);
        $allowed = array_fill_keys($userIds, true);

        foreach ($this->clients as $clientId => $client) {
            if (!$client['isWebSocket']) {
                continue;
            }

            $userId = $client['userId'];
            if ($userId === null || !isset($allowed[$userId])) {
                continue;
            }

            @fwrite($client['socket'], $frame);
        }
    }

    /**
     * @return array{method: string, target: string, path: string, query: string, headers: array<string, string>, body: string, remaining: string}|null
     */
    private function tryParseHttpRequest(string $buffer): ?array
    {
        $headerEnd = strpos($buffer, "\r\n\r\n");
        if ($headerEnd === false) {
            return null;
        }

        $headerBlock = substr($buffer, 0, $headerEnd);
        $lines = explode("\r\n", $headerBlock);
        $requestLine = array_shift($lines);
        if (!is_string($requestLine) || $requestLine === '') {
            return null;
        }

        $requestParts = explode(' ', $requestLine, 3);
        if (count($requestParts) < 2) {
            return null;
        }

        $headers = [];
        foreach ($lines as $line) {
            $separatorPos = strpos($line, ':');
            if ($separatorPos === false) {
                continue;
            }

            $name = strtolower(trim(substr($line, 0, $separatorPos)));
            $value = trim(substr($line, $separatorPos + 1));
            $headers[$name] = $value;
        }

        $contentLength = max(0, (int) ($headers['content-length'] ?? 0));
        $totalLength = $headerEnd + 4 + $contentLength;
        if (strlen($buffer) < $totalLength) {
            return null;
        }

        $target = (string) $requestParts[1];
        $body = $contentLength > 0 ? substr($buffer, $headerEnd + 4, $contentLength) : '';
        $remaining = (string) substr($buffer, $totalLength);
        $urlParts = parse_url($target);

        return [
            'method' => strtoupper((string) $requestParts[0]),
            'target' => $target,
            'path' => (string) ($urlParts['path'] ?? '/'),
            'query' => (string) ($urlParts['query'] ?? ''),
            'headers' => $headers,
            'body' => $body,
            'remaining' => $remaining,
        ];
    }

    private function encodeWebSocketFrame(string $payload): string
    {
        $length = strlen($payload);
        if ($length <= 125) {
            return chr(0x81) . chr($length) . $payload;
        }

        if ($length <= 65535) {
            return chr(0x81) . chr(126) . pack('n', $length) . $payload;
        }

        return chr(0x81) . chr(127) . pack('NN', 0, $length) . $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @param resource $socket
     */
    private function writeHttpResponse($socket, int $statusCode, array $payload): void
    {
        $statusText = match ($statusCode) {
            200 => 'OK',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            default => 'OK',
        };

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (!is_string($body)) {
            $body = '{}';
        }

        $response = sprintf(
            "HTTP/1.1 %d %s\r\nContent-Type: application/json\r\nContent-Length: %d\r\nConnection: close\r\n\r\n%s",
            $statusCode,
            $statusText,
            strlen($body),
            $body
        );

        @fwrite($socket, $response);
    }

    private function disconnectClient(int $clientId): void
    {
        if (!isset($this->clients[$clientId])) {
            return;
        }

        @fclose($this->clients[$clientId]['socket']);
        unset($this->clients[$clientId]);
    }
}
