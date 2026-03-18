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
namespace pocketmine\network\mcpe\handler;

use pocketmine\entity\InvalidSkinException;
use pocketmine\entity\Skin;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\lang\Translatable;
use pocketmine\network\mcpe\auth\ProcessLegacyLoginTask;
use pocketmine\network\mcpe\auth\ProcessOpenIdLoginTask;
use pocketmine\network\mcpe\JwtException;
use pocketmine\network\mcpe\JwtUtils;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\login\AuthenticationInfo;
use pocketmine\network\mcpe\protocol\types\login\AuthenticationType;
use pocketmine\network\mcpe\protocol\types\login\clientdata\ClientData;
use pocketmine\network\mcpe\protocol\types\login\clientdata\ClientDataToSkinDataHelper;
use pocketmine\network\mcpe\protocol\types\login\legacy\LegacyAuthChain;
use pocketmine\network\mcpe\protocol\types\login\legacy\LegacyAuthIdentityData;
use pocketmine\network\mcpe\protocol\types\login\openid\XboxAuthJwtBody;
use pocketmine\network\mcpe\protocol\types\login\openid\XboxAuthJwtHeader;
use pocketmine\network\PacketHandlingException;
use pocketmine\player\Player;
use pocketmine\player\PlayerInfo;
use pocketmine\player\XboxLivePlayerInfo;
use pocketmine\Server;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use function base64_decode;
use function chr;
use function count;
use function gettype;
use function is_array;
use function is_object;
use function json_decode;
use function md5;
use function ord;
use function var_export;
use const JSON_THROW_ON_ERROR;

/**
 * Handles the initial login phase of the session. This handler is used as the initial state.
 */
class LoginPacketHandler extends PacketHandler{
	/**
	 * @phpstan-param \Closure(PlayerInfo) : void $playerInfoConsumer
	 * @phpstan-param \Closure(bool $isAuthenticated, bool $authRequired, Translatable|string|null $error, ?string $clientPubKey) : void $authCallback
	 */
	public function __construct(
		private Server $server,
		private NetworkSession $session,
		private \Closure $playerInfoConsumer,
		private \Closure $authCallback
	){}

	private static function calculateUuidFromXuid(string $xuid) : UuidInterface{
		$hash = md5("pocket-auth-1-xuid:" . $xuid, binary: true);
		$hash[6] = chr((ord($hash[6]) & 0x0f) | 0x30); // set version to 3
		$hash[8] = chr((ord($hash[8]) & 0x3f) | 0x80); // set variant to RFC 4122

		return Uuid::fromBytes($hash);
	}

	public function handleLogin(LoginPacket $packet) : bool{
		// Если сервер в оффлайн режиме, пропускаем всю JWT валидацию
		if(!$this->server->isOnlineMode()){
			// Создаем минимальную информацию для оффлайн входа
			$authInfo = new AuthenticationInfo();
			$authInfo->AuthenticationType = AuthenticationType::SELF_SIGNED->value;
			$authInfo->Certificate = $packet->authInfoJson;
			$authInfo->Token = "";
		}else{
			try{
				if($this->session->getProtocolId() >= ProtocolInfo::PROTOCOL_1_21_93){
					$authInfo = $this->parseAuthInfo($packet->authInfoJson);
				}elseif($this->session->getProtocolId() >= ProtocolInfo::PROTOCOL_1_21_90){
					$authInfo = $this->parseAuthInfo($packet->authInfoJson);
					$authInfo->AuthenticationType = AuthenticationType::SELF_SIGNED->value;
				}else{
					$authInfo = new AuthenticationInfo();
					$authInfo->AuthenticationType = AuthenticationType::SELF_SIGNED->value;
					$authInfo->Certificate = $packet->authInfoJson;
					$authInfo->Token = "";
				}
			}catch(\Throwable $e){
				// Любая ошибка парсинга - используем SELF_SIGNED
				$authInfo = new AuthenticationInfo();
				$authInfo->AuthenticationType = AuthenticationType::SELF_SIGNED->value;
				$authInfo->Certificate = $packet->authInfoJson;
				$authInfo->Token = "";
			}
		}

		if($authInfo->AuthenticationType === AuthenticationType::FULL->value){
			try{
				[$headerArray, $claimsArray,] = JwtUtils::parse($authInfo->Token);
			}catch(JwtException $e){
				// В оффлайн режиме игнорируем ошибки JWT
				if(!$this->server->isOnlineMode()){
					$authInfo->AuthenticationType = AuthenticationType::SELF_SIGNED->value;
					$authInfo->Certificate = $packet->authInfoJson;
				}else{
					throw PacketHandlingException::wrap($e, "Error parsing authentication token");
				}
			}
			
			// Проверяем что тип все еще FULL после try-catch
			if($authInfo->AuthenticationType === AuthenticationType::FULL->value){
				$header = $this->mapXboxTokenHeader($headerArray);
				$claims = $this->mapXboxTokenBody($claimsArray);

				$legacyUuid = self::calculateUuidFromXuid($claims->xid);
				$username = $claims->xname;
				$xuid = $claims->xid;

				$authRequired = $this->processLoginCommon($packet, $username, $legacyUuid, $xuid);
				if($authRequired === null){
					//plugin cancelled
					return true;
				}
				$this->processOpenIdLogin($authInfo->Token, $header->kid, $packet->clientDataJwt, $authRequired);
				return true;
			}
		}
		
		if($authInfo->AuthenticationType === AuthenticationType::SELF_SIGNED->value){
			$legacyUuid = null;
			$username = null;
			$xuid = "";
			
			try{
				$chainData = json_decode($authInfo->Certificate, flags: JSON_THROW_ON_ERROR);
			}catch(\JsonException $e){
				if($this->server->isOnlineMode()){
					throw PacketHandlingException::wrap($e, "Error parsing self-signed certificate chain");
				}
				// В оффлайн режиме логируем ошибку и создаем дефолтные данные
				$this->session->getLogger()->warning("Failed to parse certificate chain (offline mode): " . $e->getMessage());
				$chainData = null;
			}
			
			if($chainData !== null && !is_object($chainData)){
				if($this->server->isOnlineMode()){
					throw new PacketHandlingException("Unexpected type for self-signed certificate chain: " . gettype($chainData) . ", expected object");
				}
				$this->session->getLogger()->warning("Invalid certificate chain type (offline mode)");
				$chainData = null;
			}
			
			if($chainData !== null){
				try{
					$chain = $this->defaultJsonMapper("Self-signed auth chain JSON")->map($chainData, new LegacyAuthChain());
				}catch(\JsonMapper_Exception $e){
					if($this->server->isOnlineMode()){
						throw PacketHandlingException::wrap($e, "Error mapping self-signed certificate chain");
					}
					$this->session->getLogger()->warning("Failed to map certificate chain (offline mode): " . $e->getMessage());
					$chain = null;
				}
			}else{
				$chain = null;
			}
			
			if($chain !== null){
				// Проверяем что chain->chain инициализирован
				if(!isset($chain->chain) || !is_array($chain->chain)){
					if($this->server->isOnlineMode()){
						throw new PacketHandlingException("Certificate chain is not properly initialized");
					}
					$this->session->getLogger()->warning("Certificate chain not initialized (offline mode)");
					$chain = null;
					$claimsArray = null;
				}elseif($this->session->getProtocolId() >= ProtocolInfo::PROTOCOL_1_21_93){
					if(count($chain->chain) > 1 || !isset($chain->chain[0])){
						if($this->server->isOnlineMode()){
							throw new PacketHandlingException("Expected exactly one certificate in self-signed certificate chain, got " . count($chain->chain));
						}
						$this->session->getLogger()->warning("Invalid certificate chain count (offline mode)");
						$claimsArray = null;
					}else{
						try{
							[, $claimsArray, ] = JwtUtils::parse($chain->chain[0]);
						}catch(JwtException $e){
							if($this->server->isOnlineMode()){
								throw PacketHandlingException::wrap($e, "Error parsing self-signed certificate");
							}
							$this->session->getLogger()->warning("Failed to parse certificate JWT (offline mode): " . $e->getMessage());
							$claimsArray = null;
						}
						if($claimsArray !== null && (!isset($claimsArray["extraData"]) || !is_array($claimsArray["extraData"]))){
							if($this->server->isOnlineMode()){
								throw new PacketHandlingException("Expected \"extraData\" to be present in self-signed certificate");
							}
							$this->session->getLogger()->warning("extraData not found in certificate (offline mode)");
							$claimsArray = null;
						}
					}
				}else{
					$claimsArray = null;

					foreach($chain->chain as $jwt){
						try{
							[, $claims, ] = JwtUtils::parse($jwt);
						}catch(JwtException $e){
							if($this->server->isOnlineMode()){
								throw PacketHandlingException::wrap($e, "Error parsing legacy certificate");
							}
							$this->session->getLogger()->warning("Failed to parse legacy certificate JWT (offline mode): " . $e->getMessage());
							continue;
						}
						if(isset($claims["extraData"])){
							if($claimsArray !== null){
								if($this->server->isOnlineMode()){
									throw new PacketHandlingException("Multiple certificates in self-signed certificate chain contain \"extraData\" field");
								}
								$this->session->getLogger()->warning("Multiple extraData found (offline mode)");
								continue;
							}

							if(!is_array($claims["extraData"])){
								if($this->server->isOnlineMode()){
									throw new PacketHandlingException("'extraData' key should be an array");
								}
								$this->session->getLogger()->warning("extraData is not array (offline mode)");
								continue;
							}

							$claimsArray = $claims;
						}
					}

					if($claimsArray === null && $this->server->isOnlineMode()){
						throw new PacketHandlingException("'extraData' not found in legacy chain data");
					}
				}
			}else{
				$claimsArray = null;
			}

			if($claimsArray !== null && isset($claimsArray["extraData"])){
				try{
					$claims = $this->defaultJsonMapper("Self-signed auth JWT 'extraData'")->map($claimsArray["extraData"], new LegacyAuthIdentityData());
				}catch(\JsonMapper_Exception $e){
					if($this->server->isOnlineMode()){
						throw PacketHandlingException::wrap($e, "Error mapping self-signed certificate extraData");
					}
					$this->session->getLogger()->warning("Failed to map extraData (offline mode): " . $e->getMessage());
					$claims = null;
				}

				if($claims !== null){
					if(!Uuid::isValid($claims->identity)){
						if($this->server->isOnlineMode()){
							throw new PacketHandlingException("Invalid UUID string in self-signed certificate: " . $claims->identity);
						}
						$this->session->getLogger()->warning("Invalid UUID in certificate (offline mode): " . $claims->identity);
					}else{
						$legacyUuid = Uuid::fromString($claims->identity);
					}
					$username = $claims->displayName;
					$xuid = $this->session->getProtocolId() >= ProtocolInfo::PROTOCOL_1_21_93 ? "" : $claims->XUID;
					
					$this->session->getLogger()->info("Successfully parsed player data: username=$username, uuid=$legacyUuid, xuid=$xuid");
				}
			}
			
			// Если не удалось получить данные, создаем дефолтные
			if($legacyUuid === null){
				$legacyUuid = Uuid::uuid4();
				$this->session->getLogger()->warning("Generated random UUID: $legacyUuid");
			}
			if($username === null){
				// В офлайн режиме пробуем взять ник из clientData (Xbox/клиентский ник)
				$username = $this->tryGetUsernameFromClientData($packet->clientDataJwt);
				if($username === null){
					$username = "Player_" . bin2hex(random_bytes(4));
					$this->session->getLogger()->warning("Generated random username: $username");
				}
			}

			$authRequired = $this->processLoginCommon($packet, $username, $legacyUuid, $xuid);
			if($authRequired === null){
				//plugin cancelled
				return true;
			}
			$this->processSelfSignedLogin($chain !== null ? $chain->chain : [], $packet->clientDataJwt, $authRequired);
		}else{
			throw new PacketHandlingException("Unsupported authentication type: $authInfo->AuthenticationType");
		}

		return true;
	}

	private function processLoginCommon(LoginPacket $packet, string $username, UuidInterface $legacyUuid, string $xuid) : ?bool{
		if(!Player::isValidUserName($username)){
			$this->session->disconnectWithError(KnownTranslationFactory::disconnectionScreen_invalidName());

			return null;
		}

		try{
			$clientData = $this->parseClientData($packet->clientDataJwt);
		}catch(\Throwable $e){
			// В оффлайн режиме игнорируем ошибки парсинга clientData
			if(!$this->server->isOnlineMode()){
				$this->session->getLogger()->debug("ClientData parse error (ignored in offline mode): " . $e->getMessage());
				// Создаем минимальный ClientData
				$clientData = new ClientData();
				$clientData->LanguageCode = "en_US";
			}else{
				throw $e;
			}
		}

		try{
			$skin = $this->session->getTypeConverter()->getSkinAdapter()->fromSkinData(ClientDataToSkinDataHelper::fromClientData($clientData));
		}catch(\Throwable $e){
			// ПОЛНОСТЬЮ ИГНОРИРУЕМ ВСЕ ОШИБКИ СКИНОВ
			// Используем дефолтный скин, но сохраняем плащ из clientData если есть
			$this->session->getLogger()->debug("Skin error (ignored): " . $e->getMessage());
			try{
				$skin = $this->getDefaultSkin();
				$capeData = "";
				if(isset($clientData->CapeData) && is_string($clientData->CapeData) && $clientData->CapeData !== ""){
					$decoded = base64_decode($clientData->CapeData, true);
					if($decoded !== false){
						$capeData = $decoded;
					}
				}
				if($capeData !== ""){
					$skin = new Skin(
						$skin->getSkinId(),
						$skin->getSkinData(),
						$capeData,
						$skin->getGeometryName(),
						$skin->getGeometryData()
					);
				}
			}catch(\Throwable $e2){
				// Даже если дефолтный скин не создался - продолжаем без скина
				$skin = null;
			}
		}
		
		// Если скин null, создаем минимальный скин
		if($skin === null){
			$skin = new Skin("Default", str_repeat("\x00", 8192), "", "", "");
		}

		if($xuid !== ""){
			$playerInfo = new XboxLivePlayerInfo(
				$xuid,
				$username,
				$legacyUuid,
				$skin,
				$clientData->LanguageCode,
				(array) $clientData
			);
		}else{
			$playerInfo = new PlayerInfo(
				$username,
				$legacyUuid,
				$skin,
				$clientData->LanguageCode,
				(array) $clientData
			);
		}
		($this->playerInfoConsumer)($playerInfo);

		$ev = new PlayerPreLoginEvent(
			$playerInfo,
			$this->session,
			$this->server->requiresAuthentication()
		);
		if($this->server->getNetwork()->getValidConnectionCount() > $this->server->getMaxPlayers()){
			$ev->setKickFlag(PlayerPreLoginEvent::KICK_FLAG_SERVER_FULL, KnownTranslationFactory::disconnectionScreen_serverFull());
		}
		if(!$this->server->isWhitelisted($playerInfo->getUsername())){
			$ev->setKickFlag(PlayerPreLoginEvent::KICK_FLAG_SERVER_WHITELISTED, KnownTranslationFactory::pocketmine_disconnect_whitelisted());
		}

		$banMessage = null;
		if(($banEntry = $this->server->getNameBans()->getEntry($playerInfo->getUsername())) !== null){
			$banReason = $banEntry->getReason();
			$banMessage = $banReason === "" ? KnownTranslationFactory::pocketmine_disconnect_ban_noReason() : KnownTranslationFactory::pocketmine_disconnect_ban($banReason);
		}elseif(($banEntry = $this->server->getIPBans()->getEntry($this->session->getIp())) !== null){
			$banReason = $banEntry->getReason();
			$banMessage = KnownTranslationFactory::pocketmine_disconnect_ban($banReason !== "" ? $banReason : KnownTranslationFactory::pocketmine_disconnect_ban_ip());
		}
		if($banMessage !== null){
			$ev->setKickFlag(PlayerPreLoginEvent::KICK_FLAG_BANNED, $banMessage);
		}

		$ev->call();
		if(!$ev->isAllowed()){
			$this->session->disconnect($ev->getFinalDisconnectReason(), $ev->getFinalDisconnectScreenMessage());
			return null;
		}

		return $ev->isAuthRequired();
	}

	/**
	 * @throws PacketHandlingException
	 */
	protected function parseAuthInfo(string $authInfo) : AuthenticationInfo{
		try{
			$authInfoJson = json_decode($authInfo, associative: false, flags: JSON_THROW_ON_ERROR);
		}catch(\JsonException $e){
			// В оффлайн режиме возвращаем дефолтный AuthInfo
			if(!$this->server->isOnlineMode()){
				$defaultAuth = new AuthenticationInfo();
				$defaultAuth->AuthenticationType = AuthenticationType::SELF_SIGNED->value;
				$defaultAuth->Certificate = $authInfo;
				$defaultAuth->Token = "";
				return $defaultAuth;
			}
			throw PacketHandlingException::wrap($e);
		}
		if(!is_object($authInfoJson)){
			if(!$this->server->isOnlineMode()){
				$defaultAuth = new AuthenticationInfo();
				$defaultAuth->AuthenticationType = AuthenticationType::SELF_SIGNED->value;
				$defaultAuth->Certificate = $authInfo;
				$defaultAuth->Token = "";
				return $defaultAuth;
			}
			throw new PacketHandlingException("Unexpected type for auth info data: " . gettype($authInfoJson) . ", expected object");
		}

		$mapper = $this->defaultJsonMapper("Root authentication info JSON");
		try{
			$clientData = $mapper->map($authInfoJson, new AuthenticationInfo());
		}catch(\JsonMapper_Exception $e){
			if(!$this->server->isOnlineMode()){
				$defaultAuth = new AuthenticationInfo();
				$defaultAuth->AuthenticationType = AuthenticationType::SELF_SIGNED->value;
				$defaultAuth->Certificate = $authInfo;
				$defaultAuth->Token = "";
				return $defaultAuth;
			}
			throw PacketHandlingException::wrap($e);
		}
		return $clientData;
	}

	/**
	 * @param array<string, mixed> $headerArray
	 * @throws PacketHandlingException
	 */
	protected function mapXboxTokenHeader(array $headerArray) : XboxAuthJwtHeader{
		$mapper = $this->defaultJsonMapper("OpenID JWT header");
		try{
			$header = $mapper->map($headerArray, new XboxAuthJwtHeader());
		}catch(\JsonMapper_Exception $e){
			throw PacketHandlingException::wrap($e);
		}
		return $header;
	}

	/**
	 * @param array<string, mixed> $bodyArray
	 * @throws PacketHandlingException
	 */
	protected function mapXboxTokenBody(array $bodyArray) : XboxAuthJwtBody{
		$mapper = $this->defaultJsonMapper("OpenID JWT body");
		try{
			$header = $mapper->map($bodyArray, new XboxAuthJwtBody());
		}catch(\JsonMapper_Exception $e){
			throw PacketHandlingException::wrap($e);
		}
		return $header;
	}

	/**
	 * @throws PacketHandlingException
	 */
	/**
	 * Пытается извлечь ник игрока из clientData JWT (ThirdPartyName — Xbox/клиентский ник).
	 * Используется в офлайн режиме, когда сертификат не распарсился.
	 */
	private function tryGetUsernameFromClientData(string $clientDataJwt) : ?string{
		try{
			[, $clientDataClaims, ] = JwtUtils::parse($clientDataJwt);
		}catch(JwtException $e){
			return null;
		}
		if(!is_array($clientDataClaims)){
			return null;
		}
		$name = $clientDataClaims["ThirdPartyName"] ?? $clientDataClaims["thirdPartyName"] ?? null;
		if($name === null || $name === ""){
			return null;
		}
		$name = (string) $name;
		return Player::isValidUserName($name) ? $name : null;
	}

	protected function parseClientData(string $clientDataJwt) : ClientData{
		try{
			[, $clientDataClaims, ] = JwtUtils::parse($clientDataJwt);
		}catch(JwtException $e){
			throw PacketHandlingException::wrap($e);
		}

		$mapper = $this->defaultJsonMapper("ClientData JWT body");
		try{
			$clientData = $mapper->map($clientDataClaims, new ClientData());
		}catch(\JsonMapper_Exception $e){
			throw PacketHandlingException::wrap($e);
		}
		return $clientData;
	}

	/**
	 * TODO: This is separated for the purposes of allowing plugins (like Specter) to hack it and bypass authentication.
	 * In the future this won't be necessary.
	 *
	 * @throws \InvalidArgumentException
	 */
	protected function processOpenIdLogin(string $token, string $keyId, string $clientData, bool $authRequired) : void{
		$this->session->setHandler(null); //drop packets received during login verification

		$authKeyProvider = $this->server->getAuthKeyProvider();
		
		// Если AuthKeyProvider не доступен (оффлайн режим), разрешаем подключение
		if($authKeyProvider === null){
			($this->authCallback)(false, false, null, null);
			return;
		}

		$authKeyProvider->getKey($keyId)->onCompletion(
			function(array $issuerAndKey) use ($token, $clientData, $authRequired) : void{
				[$issuer, $mojangPublicKeyPem] = $issuerAndKey;
				$this->server->getAsyncPool()->submitTask(new ProcessOpenIdLoginTask($token, $issuer, $mojangPublicKeyPem, $clientData, $authRequired, $this->authCallback));
			},
			fn() => ($this->authCallback)(false, false, null, null) // Разрешаем подключение даже при неизвестном ключе
		);
	}

	/**
	 * @param string[] $legacyCertificate
	 */
	protected function processSelfSignedLogin(array $legacyCertificate, string $clientDataJwt, bool $authRequired) : void{
		$this->session->setHandler(null); //drop packets received during login verification

		// Если сервер в оффлайн режиме, пропускаем аутентификацию
		if(!$this->server->isOnlineMode()){
			($this->authCallback)(false, false, null, null);
			return;
		}

		$rootAuthKeyDer = $this->session->getProtocolId() >= ProtocolInfo::PROTOCOL_1_21_93 ? null : base64_decode(ProcessLegacyLoginTask::LEGACY_MOJANG_ROOT_PUBLIC_KEY, true);
		if($rootAuthKeyDer === false){ //should never happen unless the constant is messed up
			throw new \InvalidArgumentException("Failed to base64-decode hardcoded Mojang root public key");
		}
		$this->server->getAsyncPool()->submitTask(new ProcessLegacyLoginTask($legacyCertificate, $clientDataJwt, rootAuthKeyDer: $rootAuthKeyDer, authRequired: $authRequired, onCompletion: $this->authCallback));
	}

	private function defaultJsonMapper(string $logContext) : \JsonMapper{
		$mapper = new \JsonMapper();
		$mapper->bExceptionOnMissingData = false; // Отключаем исключения для недостающих данных
		$mapper->undefinedPropertyHandler = function(object $object, string $name, mixed $value) use ($logContext) : void{
			// В оффлайн режиме не логируем warning
			if($this->server->isOnlineMode()){
				$this->session->getLogger()->debug(
					"$logContext: Unexpected JSON property for " . (new \ReflectionClass($object))->getShortName() . ": " . $name
				);
			}
		};
		$mapper->bStrictObjectTypeChecking = false; // Отключаем строгую проверку типов
		$mapper->bEnforceMapType = false;
		return $mapper;
	}

	/**
	 * @phpstan-return \Closure(object, string, mixed) : void
	 */
	private function warnUndefinedJsonPropertyHandler(string $context) : \Closure{
		return fn(object $object, string $name, mixed $value) => $this->session->getLogger()->warning(
			"$context: Unexpected JSON property for " . (new \ReflectionClass($object))->getShortName() . ": " . $name . " = " . var_export($value, return: true)
		);
	}

	/**
	 * Создает дефолтный скин Steve для игроков с невалидными скинами
	 */
	private function getDefaultSkin() : Skin{
		// Создаем стандартный скин Steve (64x64, прозрачный)
		// Используем прозрачные пиксели (RGBA: 0,0,0,0) вместо синих
		$skinData = str_repeat("\x00\x00\x00\x00", 64 * 64);
		
		return new Skin(
			"Standard_Steve_" . bin2hex(random_bytes(4)), // Уникальный ID
			$skinData,
			"", // Нет плаща
			"geometry.humanoid.custom", // Стандартная геометрия
			""  // Нет данных геометрии
		);
	}
}
