<?php

namespace App\Common\WebSocketServer;

/**
 * Framing and validation for the loopback-only internal push channel.
 */
final class InternalPushProtocol {
	public const MAX_PAYLOAD_BYTES = 32 * 1024 * 1024;

	/**
	 * @throws \JsonException
	 * @throws \LengthException
	 */
	public static function encodePayload(array $fds, array $message): string
	{
		$payload = json_encode([
			"fd" => self::normaliseFileDescriptors($fds),
			"data" => $message,
		], JSON_THROW_ON_ERROR);

		if(strlen($payload) > self::MAX_PAYLOAD_BYTES){
			throw new \LengthException("The internal WebSocket payload exceeds the maximum permitted size.");
		}

		return $payload;
	}

	/**
	 * @return array{fd: array<int>, data: array}
	 *
	 * @throws \JsonException
	 * @throws \InvalidArgumentException
	 */
	public static function decodePayload(string $payload): array
	{
		if(strlen($payload) > self::MAX_PAYLOAD_BYTES){
			throw new \InvalidArgumentException("The internal WebSocket payload exceeds the maximum permitted size.");
		}

		$decoded = json_decode(rtrim($payload, "\r\n"), true, 512, JSON_THROW_ON_ERROR);

		if(!is_array($decoded)){
			throw new \InvalidArgumentException("The internal WebSocket payload must be a JSON object.");
		}
		if(!array_key_exists("fd", $decoded) || !is_array($decoded["fd"])){
			throw new \InvalidArgumentException("The internal WebSocket payload must contain a recipient list.");
		}
		if(!array_key_exists("data", $decoded) || !is_array($decoded["data"])){
			throw new \InvalidArgumentException("The internal WebSocket payload must contain an array data field.");
		}

		return [
			"fd" => self::normaliseFileDescriptors($decoded["fd"]),
			"data" => $decoded["data"],
		];
	}

	/**
	 * @throws \JsonException
	 */
	public static function encodeAcknowledgement(int $delivered, int $unavailable): string
	{
		return json_encode([
			"ok" => true,
			"delivered" => $delivered,
			"unavailable" => $unavailable,
		], JSON_THROW_ON_ERROR);
	}

	public static function encodeFailureAcknowledgement(\Throwable $throwable): string
	{
		try {
			return json_encode([
				"ok" => false,
				"error" => $throwable->getMessage(),
			], JSON_THROW_ON_ERROR);
		}
		catch(\JsonException) {
			return '{"ok":false,"error":"Internal push failed."}';
		}
	}

	/**
	 * @throws \JsonException
	 * @throws \RuntimeException
	 */
	public static function assertAcknowledgement(string $acknowledgement): void
	{
		$decoded = json_decode(rtrim($acknowledgement, "\r\n"), true, 32, JSON_THROW_ON_ERROR);

		if(!is_array($decoded) || ($decoded["ok"] ?? false) !== true){
			$error = is_array($decoded) && is_string($decoded["error"] ?? NULL)
				? $decoded["error"]
				: "The internal push listener rejected the message.";
			throw new \RuntimeException($error);
		}
	}

	/**
	 * @return array<int>
	 */
	private static function normaliseFileDescriptors(array $fds): array
	{
		$normalised = [];

		foreach($fds as $fd){
			if(!(is_int($fd) || (is_string($fd) && ctype_digit($fd))) || (int)$fd <= 0){
				throw new \InvalidArgumentException("Internal WebSocket recipients must be positive integer file descriptors.");
			}

			$normalised[(int)$fd] = (int)$fd;
		}

		if(!$normalised){
			throw new \InvalidArgumentException("At least one internal WebSocket recipient is required.");
		}

		return array_values($normalised);
	}
}
