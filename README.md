# Common
A set of commonly used PHP classes

## Internal WebSocket push transport

The application can send authenticated HTTPS requests directly to the primary
Swoole HTTPS/WSS listener. This avoids launching a shell and PHP CLI process for
every push while retaining the legacy WebSocket sender as a fallback.

```dotenv
websocket_internal_ip="127.0.0.1"
websocket_internal_port="8088"
websocket_external_port="8080"
websocket_internal_push_secret="use-at-least-32-random-characters"
websocket_internal_tcp_enabled="false"
websocket_open_file_limit="65536"
websocket_max_connections="32768"
```

Generate the shared secret with `openssl rand -hex 32`. Roll out with the fast
sender disabled, restart the Swoole WebSocket server, and verify the direct
sender from the application directory:

```sh
php -r 'require "/var/www/html/app/settings.php"; \App\Common\PA::getInstance()->tcpPush([999999], ["success" => true]); echo "OK\n";'
```

The expected output is `OK`. Then set `websocket_internal_tcp_enabled=true` and
reload the application PHP-FPM pool. Port 8089 is no longer used; port 8088 is
retained exclusively by the unchanged legacy fallback.

The cron supervisor raises the WebSocket process's soft open-file limit before
PHP starts. `websocket_max_connections` is deliberately lower than that limit
to reserve descriptors for Swoole's listeners, pipes, logs, and internal
requests. The startup log records both effective values so production rollout
can be verified without guessing.

The in-process sender posts to the primary listener on port 8080 and waits for
an acknowledgement. Connection, write, acknowledgement, or protocol failures
automatically use `legacySwoolePush()` and report the first fallback once per
application execution. The legacy listener and method remain unchanged for
rollback.
