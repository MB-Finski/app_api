<?php

declare(strict_types=1);

namespace OCA\AppAPI\Service;

use OCA\AppAPI\AppInfo\Application;
use OCA\AppAPI\Db\ExAppApiScope;
use OCA\AppAPI\Db\ExAppApiScopeMapper;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\ICache;
use OCP\ICacheFactory;
use Psr\Log\LoggerInterface;

class ExAppApiScopeService {
	public const BASIC_API_SCOPE = 1;
	public const ALL_API_SCOPE = 9999;
	private ICache $cache;

	public function __construct(
		private readonly LoggerInterface     $logger,
		private readonly ExAppApiScopeMapper $mapper,
		ICacheFactory                        $cacheFactory,
	) {
		$this->cache = $cacheFactory->createDistributed(Application::APP_ID . '/ex_apps_api_scopes');
	}

	public function getExAppApiScopes(): array {
		try {
			$cacheKey = '/all_api_scopes';
			$cached = $this->cache->get($cacheKey);
			if ($cached !== null) {
				return array_map(function ($cachedEntry) {
					return $cachedEntry instanceof ExAppApiScope ? $cachedEntry : new ExAppApiScope($cachedEntry);
				}, $cached);
			}

			$apiScopes = $this->mapper->findAll();
			$this->cache->set($cacheKey, $apiScopes);
			return $apiScopes;
		} catch (Exception $e) {
			$this->logger->error(sprintf('Failed to get all ApiScopes. Error: %s', $e->getMessage()), ['exception' => $e]);
			return [];
		}
	}

	public function getApiScopeByRoute(string $apiRoute): ?ExAppApiScope {
		try {
			$cacheKey = '/api_scope_' . $apiRoute;
			$cached = $this->cache->get($cacheKey);
			if ($cached !== null) {
				return $cached instanceof ExAppApiScope ? $cached : new ExAppApiScope($cached);
			}

			$apiScopes = $this->getExAppApiScopes();
			foreach ($apiScopes as $apiScope) {
				if (str_starts_with($this->sanitizeOcsRoute($apiRoute), $apiScope->getApiRoute())) {
					$this->cache->set($cacheKey, $apiScope);
					return $apiScope;
				}
			}
			return null;
		} catch (Exception) {
			return null;
		}
	}

	/**
	 * Check if the given route has ocs prefix and cut it off
	 */
	private function sanitizeOcsRoute(string $route): string {
		if (preg_match("/\/ocs\/v(1|2)\.php/", $route, $matches)) {
			return str_replace($matches[0], '', $route);
		}
		return $route;
	}

	public function registerInitScopes(): bool {
		// Note: "user_check" should be zero for APIs that does not need user context.
		$aeApiV1Prefix = '/apps/' . Application::APP_ID . '/api/v1';

		$initApiScopes = [
			// AppAPI scopes
			['api_route' => $aeApiV1Prefix . '/ui/files-actions-menu', 'scope_group' => 1, 'name' => 'BASIC', 'user_check' => 0],
			['api_route' => $aeApiV1Prefix . '/ui/top-menu', 'scope_group' => 1, 'name' => 'BASIC', 'user_check' => 0],
			['api_route' => $aeApiV1Prefix . '/ui/initial-state', 'scope_group' => 1, 'name' => 'BASIC', 'user_check' => 0],
			['api_route' => $aeApiV1Prefix . '/ui/script', 'scope_group' => 1, 'name' => 'BASIC', 'user_check' => 0],
			['api_route' => $aeApiV1Prefix . '/ui/style', 'scope_group' => 1, 'name' => 'BASIC', 'user_check' => 0],
			['api_route' => $aeApiV1Prefix . '/ui/settings', 'scope_group' => 1, 'name' => 'BASIC', 'user_check' => 0],
			['api_route' => $aeApiV1Prefix . '/log', 'scope_group' => 1, 'name' => 'BASIC', 'user_check' => 0],
			['api_route' => $aeApiV1Prefix . '/ex-app/config', 'scope_group' => 1, 'name' => 'BASIC', 'user_check' => 0],
			['api_route' => $aeApiV1Prefix . '/ex-app/preference', 'scope_group' => 1, 'name' => 'BASIC', 'user_check' => 0],
			['api_route' => $aeApiV1Prefix . '/users', 'scope_group' => 2, 'name' => 'SYSTEM', 'user_check' => 1],
			['api_route' => $aeApiV1Prefix . '/ex-app/all', 'scope_group' => 2, 'name' => 'SYSTEM', 'user_check' => 1],
			['api_route' => $aeApiV1Prefix . '/ex-app/enabled', 'scope_group' => 2, 'name' => 'SYSTEM', 'user_check' => 1],
			['api_route' => $aeApiV1Prefix . '/notification', 'scope_group' => 32, 'name' => 'NOTIFICATIONS', 'user_check' => 1],
			['api_route' => $aeApiV1Prefix . '/talk_bot', 'scope_group' => 60, 'name' => 'TALK_BOT', 'user_check' => 0],
			['api_route' => $aeApiV1Prefix . '/ai_provider/', 'scope_group' => 61, 'name' => 'AI_PROVIDERS', 'user_check' => 0],

			// AppAPI internal scopes
			['api_route' => '/apps/app_api/apps/status', 'scope_group' => 1, 'name' => 'BASIC', 'user_check' => 0],

			// Cloud scopes
			['api_route' => '/cloud/capabilities', 'scope_group' => 1, 'name' => 'BASIC', 'user_check' => 0],
			['api_route' => '/cloud/apps', 'scope_group' => 2, 'name' => 'SYSTEM', 'user_check' => 1],
			['api_route' => '/apps/provisioning_api/api/', 'scope_group' => 2, 'name' => 'SYSTEM', 'user_check' => 1],
			['api_route' => '/settings/admin/log/', 'scope_group' => 2, 'name' => 'SYSTEM', 'user_check' => 0],
			['api_route' => '/dav/', 'scope_group' => 10, 'name' => 'FILES', 'user_check' => 1],
			['api_route' => '/apps/files/ajax/', 'scope_group' => 10, 'name' => 'FILES', 'user_check' => 1],
			['api_route' => '/apps/files_sharing/api/', 'scope_group' => 11, 'name' => 'FILES_SHARING', 'user_check' => 1],
			['api_route' => '/cloud/user', 'scope_group' => 30, 'name' => 'USER_INFO', 'user_check' => 1],
			['api_route' => '/cloud/groups', 'scope_group' => 30, 'name' => 'USER_INFO', 'user_check' => 1],
			['api_route' => '/apps/user_status/api/', 'scope_group' => 31, 'name' => 'USER_STATUS', 'user_check' => 1],
			['api_route' => '/apps/notifications/api/', 'scope_group' => 32, 'name' => 'NOTIFICATIONS', 'user_check' => 1],
			['api_route' => '/apps/weather_status/api/', 'scope_group' => 33, 'name' => 'WEATHER_STATUS', 'user_check' => 1],
			['api_route' => '/apps/spreed/api/', 'scope_group' => 50, 'name' => 'TALK', 'user_check' => 1],
			['api_route' => '/apps/activity/api/', 'scope_group' => 110, 'name' => 'ACTIVITIES', 'user_check' => 1],
			['api_route' => '/apps/notes/api/', 'scope_group' => 120, 'name' => 'NOTES', 'user_check' => 1],

			//ALL Scope
			['api_route' => 'non-exist-all-api-route', 'scope_group' => self::ALL_API_SCOPE, 'name' => 'ALL', 'user_check' => 1],
		];

		$this->cache->clear('/all_api_scopes');
		$registeredApiScopes = $this->getExAppApiScopes();
		$registeredApiScopesRoutes = [];
		foreach ($registeredApiScopes as $registeredApiScope) {
			$registeredApiScopesRoutes[$registeredApiScope->getApiRoute()] = $registeredApiScope->getId();
		}
		try {
			foreach ($initApiScopes as $apiScope) {
				if (in_array($apiScope['api_route'], array_keys($registeredApiScopesRoutes))) {
					$apiScope['id'] = $registeredApiScopesRoutes[$apiScope['api_route']];
				}
				$registeredApiScope = $this->mapper->insertOrUpdate(new ExAppApiScope($apiScope));
				$cacheKey = '/api_scope_' . $apiScope['api_route'];
				$this->cache->set($cacheKey, $registeredApiScope);
			}
			return true;
		} catch (Exception $e) {
			$this->logger->error('Failed to fill init ApiScopes: ' . $e->getMessage(), ['exception' => $e]);
			return false;
		}
	}

	public function registerApiScope(string $apiRoute, int $scopeGroup, string $name): ?ExAppApiScope {
		try {
			$apiScope = new ExAppApiScope([
				'api_route' => $apiRoute,
				'scope_group' => $scopeGroup,
				'name' => $name,
			]);
			try {
				$exAppApiScope = $this->mapper->findByApiRoute($apiRoute);
				if ($exAppApiScope !== null) {
					$apiScope->setId($exAppApiScope->getId());
				}
			} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception) {
				$exAppApiScope = null;
			}
			$this->mapper->insertOrUpdate($apiScope);
			$this->cache->remove('/api_scope_' . $apiRoute);
			$this->cache->remove('/all_api_scopes');
			return $apiScope;
		} catch (Exception $e) {
			$this->logger->error('Failed to register API scope: ' . $e->getMessage(), ['exception' => $e]);
			return null;
		}
	}

	/**
	 * @param int[] $scopeGroups
	 *
	 * @return string[]
	 */
	public function mapScopeGroupsToNames(array $scopeGroups): array {
		$apiScopes = array_values(array_filter($this->getExAppApiScopes(), function (ExAppApiScope $apiScope) use ($scopeGroups) {
			return in_array($apiScope->getScopeGroup(), $scopeGroups);
		}));
		return array_unique(array_map(function (ExAppApiScope $apiScope) {
			return $apiScope->getName();
		}, $apiScopes));
	}

	public function mapScopeNamesToNumbers(array $scopeNames): array {
		$apiScopes = array_values(array_filter($this->getExAppApiScopes(), function (ExAppApiScope $apiScope) use ($scopeNames) {
			return in_array($apiScope->getName(), $scopeNames);
		}));
		return array_unique(array_map(function (ExAppApiScope $apiScope) {
			return $apiScope->getScopeGroup();
		}, $apiScopes));
	}
}
