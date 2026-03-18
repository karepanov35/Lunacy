<?php


/*
 *
 *
 *▒█░░░ ▒█░▒█ ▒█▄░▒█ ░█▀▀█ ▒█▀▀█ ▒█░░▒█
 *▒█░░░ ▒█░▒█ ▒█▒█▒█ ▒█▄▄█ ▒█░░░ ▒█▄▄▄█
 *▒█▄▄█ ░▀▄▄▀ ▒█░░▀█ ▒█░▒█ ▒█▄▄█ ░░▒█░░
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GPL-2.0 license as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author Karepanov
 * @link https://github.com/karepanov35/Lunacy
 *
 *
 */

declare(strict_types=1);
namespace pocketmine\network\mcpe\auth;

use pocketmine\network\mcpe\JwtException;
use pocketmine\network\mcpe\JwtUtils;
use pocketmine\network\mcpe\protocol\types\login\openid\api\AuthServiceKey;
use pocketmine\promise\Promise;
use pocketmine\promise\PromiseResolver;
use pocketmine\scheduler\AsyncPool;
use pocketmine\utils\AssumptionFailedError;
use function array_keys;
use function count;
use function implode;
use function time;

class AuthKeyProvider{
	private const ALLOWED_REFRESH_INTERVAL = 30 * 60; // 30 minutes

	private ?AuthKeyring $keyring = null;

	/** @phpstan-var PromiseResolver<AuthKeyring> */
	private ?PromiseResolver $resolver = null;

	private int $lastFetch = 0;

	public function __construct(
		private readonly \Logger $logger,
		private readonly AsyncPool $asyncPool,
		private readonly int $keyRefreshIntervalSeconds = self::ALLOWED_REFRESH_INTERVAL
	){}

	/**
	 * Fetches the key for the given key ID.
	 * The promise will be resolved with an array of [issuer, pemPublicKey].
	 *
	 * @phpstan-return Promise<array{string, string}>
	 */
	public function getKey(string $keyId) : Promise{
		/** @phpstan-var PromiseResolver<array{string, string}> $resolver */
		$resolver = new PromiseResolver();

		if(
			$this->keyring === null || //we haven't fetched keys yet
			($this->keyring->getKey($keyId) === null && $this->lastFetch < time() - $this->keyRefreshIntervalSeconds) //we don't recognise this one & keys might be outdated
		){
			//only refresh keys when we see one we don't recognise
			$this->fetchKeys()->onCompletion(
				onSuccess: fn(AuthKeyring $newKeyring) => $this->resolveKey($resolver, $newKeyring, $keyId),
				onFailure: $resolver->reject(...)
			);
		}else{
			$this->resolveKey($resolver, $this->keyring, $keyId);
		}

		return $resolver->getPromise();
	}

	/**
	 * @phpstan-param PromiseResolver<array{string, string}> $resolver
	 */
	private function resolveKey(PromiseResolver $resolver, AuthKeyring $keyring, string $keyId) : void{
		$key = $keyring->getKey($keyId);
		if($key === null){
			$this->logger->debug("Key $keyId not recognised!");
			$resolver->reject();
			return;
		}

		$this->logger->debug("Key $keyId found in keychain");
		$resolver->resolve([$keyring->getIssuer(), $key]);
	}

	/**
	 * @phpstan-param array<string, AuthServiceKey>|null $keys
	 * @phpstan-param string[]|null                      $errors
	 */
	private function onKeysFetched(?array $keys, string $issuer, ?array $errors) : void{
		$resolver = $this->resolver;
		if($resolver === null){
			throw new AssumptionFailedError("Not expecting this to be called without a resolver present");
		}
		try{
			if($errors !== null){
				// Убрано сообщение об ошибках получения ключей аутентификации
				//we might've still succeeded in fetching keys even if there were errors, so don't return
			}

			if($keys === null){
				// Убрано критическое сообщение о неудачном получении ключей
				$resolver->reject();
			}else{
				$pemKeys = [];
				foreach($keys as $keyModel){
					if($keyModel->use !== "sig" || $keyModel->kty !== "RSA"){
						$this->logger->error("Key ID $keyModel->kid doesn't have the expected properties: expected use=sig, kty=RSA, got use=$keyModel->use, kty=$keyModel->kty");
						continue;
					}
					$derKey = JwtUtils::rsaPublicKeyModExpToDer($keyModel->n, $keyModel->e);

					//make sure the key is valid
					try{
						JwtUtils::parseDerPublicKey($derKey);
					}catch(JwtException $e){
						$this->logger->error("Failed to parse RSA public key for key ID $keyModel->kid: " . $e->getMessage());
						$this->logger->logException($e);
						continue;
					}

					//retain PEM keys instead of OpenSSLAsymmetricKey since these are easier and cheaper to copy between threads
					$pemKeys[$keyModel->kid] = $derKey;
				}

				if(count($keys) === 0){
					// Убрано критическое сообщение о пустом списке ключей
					$resolver->reject();
				}else{
					// Убрано сообщение об успешном получении ключей аутентификации
					$this->keyring = new AuthKeyring($issuer, $pemKeys);
					$this->lastFetch = time();
					$resolver->resolve($this->keyring);
				}
			}
		}finally{
			$this->resolver = null;
		}
	}

	/**
	 * @phpstan-return Promise<AuthKeyring>
	 */
	private function fetchKeys() : Promise{
		if($this->resolver !== null){
			$this->logger->debug("Key refresh was requested, but it's already in progress");
			return $this->resolver->getPromise();
		}

		// Убрано сообщение "Fetching new authentication keys" для Lunacy
		// $this->logger->notice("Fetching new authentication keys");

		/** @phpstan-var PromiseResolver<AuthKeyring> $resolver */
		$resolver = new PromiseResolver();
		$this->resolver = $resolver;
		//TODO: extract this so it can be polyfilled for unit testing
		$this->asyncPool->submitTask(new FetchAuthKeysTask($this->onKeysFetched(...)));
		return $this->resolver->getPromise();
	}
}
