# Satelles Nexus Utilis

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D%208.4-8892bf.svg)](https://php.net)
[![Swoole Version](https://img.shields.io/badge/swoole-%3E%3D%206.1-red.svg)](https://github.com/swoole/swoole-src)

A high-performance, asynchronous networking utility library for PHP built on top of [Swoole](https://www.swoole.com/). It provides advanced connection pooling, and specialized server implementations for TCP, HTTP, and WebSockets.

## 🚀 Overview

`satelles-nexus-utilis` is part of the Satelles ecosystem, designed to simplify the development of concurrent networking applications. It offers:

- **Connection Pooling**: Robust management of PDO and general connection pools with auto-retry and health checks.
- **Hamum Servers**: Abstract, feature-rich server implementations:
  - `Basis`: For TCP/UDP servers.
  - `Graphema`: For HTTP servers.
  - `Filum`: For WebSocket servers.
- **Event-Driven Architecture**: Easily register handlers for various network events (receive, connect, packet, request, message, etc.) based on protocol actions.
- **Coroutine-Ready**: Optimized for Swoole's coroutine environment.

## 📋 Requirements

- **PHP**: `^8.4`
- **Swoole Extension**: `>= 6.1`
- **POSIX Extension**
- **PDO Extension**
- **Sockets Extension**

## 🔧 Installation

Install via Composer:

```bash
composer require xvii/satelles-nexus-utilis
```

> **Note**: This package currently uses a custom repository. Ensure your `composer.json` includes:
> ```json
> "repositories": [
>   {
>     "type": "composer",
>     "url": "https://tabula17.repo.repman.io"
>   }
> ]
> ```

## 💡 Usage

### Connection Pooling

Use `PoolConnectorManager` to manage your database or service connections:

```php
use Tabula17\Satelles\Nexus\Utilis\Connector\Pool\PoolConnectorManager;
use Tabula17\Satelles\Utilis\Config\ConnectionConfig;

$manager = new PoolConnectorManager();

$config = new ConnectionConfig(
    name: 'main_db',
    host: '127.0.0.1',
    port: 3306,
    user: 'user',
    password: 'pass',
    database: 'mydb'
);

$manager->loadConnection($config, poolSize: 10);

// Get a connection from the pool
$pdo = $manager->getConnection('main_db');

// Use the connection...

// Release it back to the pool
$manager->releaseConnection($pdo);
```

### TCP Server (Basis)

```php
use Tabula17\Satelles\Nexus\Utilis\Server\Hamum\Basis;
use Tabula17\Satelles\Utilis\Config\TCPServerConfig;

class MyTcpServer extends Basis {
    protected function init(): void {
        // Initialization logic
    }
}

$config = new TCPServerConfig(host: '0.0.0.0', port: 9501);
$server = new MyTcpServer($config);

$server->registerReceiveHandlers('ping', function($server, $fd, $reactorId, $data) {
    $server->send($fd, json_encode(['action' => 'pong']));
});

$server->start();
```

## 📂 Project Structure

- `src/Connector/Pool`: Connection pooling logic (`PoolConnectorManager`, `PDOPoolExtended`).
- `src/Server/Hamum`: Core server implementations (`Basis`, `Filum`, `Graphema`).
- `src/Server/Protocol`: Protocol management and data structures.
- `src/Server/Trait`: Reusable traits for server functionality (e.g., `HamumTrait`, `CoroutineHelper`).
- `src/Exception`: Custom exception definitions.

## 📜 Scripts

Currently, no custom scripts are defined in `composer.json`.

- **TODO**: Add automation scripts for common tasks (e.g., server startup, cleanup).

## 🧪 Tests

- **TODO**: Implement automated tests. The project structure is ready for a `tests/` directory.

## 🔐 Environment Variables

- **TODO**: Document any environment variables used for configuration.

## 📄 License

This project is licensed under the **MIT License**. See the [LICENSE](LICENSE) file for details (if available) or `composer.json`.

---
Created by [Martín Panizzo](mailto:code.tabula17@gmail.com.com)

